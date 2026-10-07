<?php
declare(strict_types=1);

// Only shipped code is managed. Runtime config/data never enters the manifest.
function studioManaged(string $p): bool {
    return !str_contains($p, '..') && !str_contains($p, '\\')
        && !preg_match('~(^|/)(config|cache|data|backups?|\.git)(/|$)~i', $p)
        && (bool)preg_match('~^(studio-public/(?:[a-zA-Z0-9_./-]+\.(?:php|css|js|mjs|png|svg|ico)|\.htaccess|release\.json)|studio-private/(?:app/[a-zA-Z0-9_./-]+\.php|vendor/[a-zA-Z0-9_./-]+|composer\.(?:json|lock)|\.htaccess))$~D', $p);
}
function studioPath(string $root, string $p): string {
    if (!studioManaged($p)) throw new RuntimeException('Unmanaged path: '.$p);
    $at=$root;
    foreach (explode('/', $p) as $part) {
        $at.='/'.$part;
        if (is_link($at)) throw new RuntimeException('Symlink rejected: '.$p);
    }
    return $at;
}
function studioHash(string $p): ?string {
    if (!file_exists($p)) return null;
    if (!is_file($p)) throw new RuntimeException('Expected a regular file');
    return hash_file('sha256', $p);
}
function studioWrite(string $path, string $content, int $mode): void {
    if (!is_dir(dirname($path))) {
        $mask=umask(0022);
        try { if (!mkdir(dirname($path),0755,true)) throw new RuntimeException('mkdir failed'); }
        finally { umask($mask); }
    }
    $tmp=tempnam(dirname($path), '.rr-deploy-');
    if ($tmp===false) throw new RuntimeException('temp file failed');
    try {
        if (file_put_contents($tmp,$content)!==strlen($content) || !chmod($tmp,$mode) || !rename($tmp,$path)) throw new RuntimeException('Atomic write failed');
    } finally { if (is_file($tmp)) unlink($tmp); }
}
function studioPublish(string $root, string $source, string $work, array $baseline, string $commit, string $run, callable $health): array {
    if (!preg_match('/^[a-f0-9]{40}$/D',$commit) || !preg_match('/^[a-zA-Z0-9-]+$/D',$run)) throw new RuntimeException('Invalid release identity');
    $lock=fopen($work.'/deploy.lock','c');
    if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Another deployment is running');
    try {
        $statePath=$work.'/selective-state.json';
        $state=is_file($statePath)?json_decode(file_get_contents($statePath),true,512,JSON_THROW_ON_ERROR):$baseline;
        $files=$state['files'];
        $desired=[];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS)) as $f) {
            $p=substr($f->getPathname(),strlen($source)+1);
            if (!$f->isFile() || !studioManaged($p)) continue;
            $desired[$p]=studioHash(studioPath($source,$p));
        }
        if (count($desired)<10) throw new RuntimeException('Incomplete package');
        $plan=[];$next=[];
        foreach (array_unique(array_merge(array_keys($files),array_keys($desired))) as $p) {
            $target=studioPath($root,$p);
            $before=$files[$p]['live']??null;
            if (studioHash($target)!==$before) throw new RuntimeException('Production drift: '.$p);
            $after=$desired[$p]??null;
            $changed=$after!==($files[$p]['source']??null);
            if ($changed && $after!==$before) $plan[$p]=['before'=>$before,'after'=>$after];
            if ($after!==null) $next[$p]=['source'=>$after,'live'=>$changed?$after:$before];
        }
        // Save an exact private plan/backup before touching any production file.
        $backup=$work.'/backups/selective-'.$run;
        if (!mkdir($backup,0700)) throw new RuntimeException('Backup already exists or cannot be created');
        $meta=['commit'=>$commit,'previous'=>$state['commit'],'files'=>$plan];
        foreach ($plan as $p=>&$entry) {
            $target=studioPath($root,$p);
            $entry['mode']=is_file($target)?(fileperms($target)&0777):0644;
            if ($entry['before']!==null) {
                studioWrite($backup.'/'.$p,file_get_contents($target),0600);
                if (studioHash($backup.'/'.$p)!==$entry['before']) throw new RuntimeException('Backup mismatch');
            }
        }
        unset($entry);
        $meta['files']=$plan;
        studioWrite($backup.'/manifest.json',json_encode($meta,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),0600);
        $written=[];
        try {
            foreach ($plan as $p=>$entry) {
                $target=studioPath($root,$p);
                if (studioHash($target)!==$entry['before']) throw new RuntimeException('File changed during deployment: '.$p);
                if ($entry['after']===null) {
                    if (!unlink($target)) throw new RuntimeException('Code removal failed');
                } else {
                    $content=file_get_contents(studioPath($source,$p));
                    if (hash('sha256',$content)!==$entry['after']) throw new RuntimeException('Staging changed');
                    studioWrite($target,$content,$entry['mode']);
                }
                $written[]=$p;
                if (studioHash($target)!==$entry['after']) throw new RuntimeException('Post-write mismatch');
            }
            $health();
            foreach ($next as $p=>$v) if (studioHash(studioPath($root,$p))!==$v['live']) throw new RuntimeException('Final hash mismatch: '.$p);
            studioWrite($statePath,json_encode(['commit'=>$commit,'run'=>$run,'files'=>$next],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),0600);
        } catch (Throwable $e) {
            foreach (array_reverse($written) as $p) {
                $entry=$plan[$p];$target=studioPath($root,$p);
                if (studioHash($target)!==$entry['after']) throw new RuntimeException('Rollback stopped: concurrent edit. Private backup: '.$backup,0,$e);
                if ($entry['before']===null) { if (!unlink($target)) throw new RuntimeException('Rollback removal failed',0,$e); }
                else studioWrite($target,file_get_contents($backup.'/'.$p),$entry['mode']);
                if (studioHash($target)!==$entry['before']) throw new RuntimeException('Rollback hash mismatch',0,$e);
            }
            throw $e;
        }
        return ['commit'=>$commit,'changed'=>count($plan),'backup'=>$backup];
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
if (realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    umask(0077);
    [$script,$run,$commit]=$argv;
    $root='/customers/9/3/1/cptk37ymg/webroots/r1417157';
    if(realpath('/run/webroots/r1417157')!==$root) throw new RuntimeException('Unexpected document root');
    foreach (['studio-public','studio-private'] as $dir) if(realpath($root.'/'.$dir)!==$root.'/'.$dir || is_link($root.'/'.$dir)) throw new RuntimeException('Unexpected Studio path');
    $work=getenv('HOME').'/.radiorubben-studio-deploy';
    if(!preg_match('/^[a-zA-Z0-9-]+$/D',$run)) throw new RuntimeException('Invalid run');
    $stage=$work.'/staging/'.$run;
    $release=json_decode(file_get_contents($stage.'/unpacked/studio-public/release.json'),true,512,JSON_THROW_ON_ERROR);
    if(($release['commit']??'')!==$commit) throw new RuntimeException('Package commit mismatch');
    $baseline=json_decode(file_get_contents($stage.'/baseline.json'),true,512,JSON_THROW_ON_ERROR);
    $health=static function():void {
        $ch=curl_init('https://studio.radiorubben.no/');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>25]);
        $response=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        if($response===false || $status!==303 || !preg_match('/^location: \/login\.php\s*$/mi',$response)) throw new RuntimeException('HTTPS/login health check failed');
    };
    $health();
    $releaseHealth=static function() use ($health,$release):void {
        $health();
        $ch=curl_init('https://studio.radiorubben.no/release.json?commit='.$release['commit']);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>25]);
        $response=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $published=$response===false?null:json_decode($response,true);
        if($status!==200 || !is_array($published) || $published!==$release) throw new RuntimeException('HTTPS release manifest mismatch');
    };
    echo json_encode(studioPublish($root,$stage.'/unpacked',$work,$baseline,$commit,$run,$releaseHealth),JSON_UNESCAPED_SLASHES)."\n";
}
