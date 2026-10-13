<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/music-library-scheduler.php';
function scheduler_no(callable $f):void{try{$f();}catch(Throwable){return;}throw new RuntimeException('Expected scheduler stop');}
$dir=sys_get_temp_dir().'/rr-scheduler-'.bin2hex(random_bytes(6));mkdir($dir,0700);mkdir($dir.'/studio-public');mkdir($dir.'/studio-private');mkdir($dir.'/studio-private/app');$sha=str_repeat('a',40);$state=['commit'=>$sha,'files'=>[]];
try{foreach(['music-library-scheduler.php','music-library-worker.php','music-library-online.php','music-library-producer.php','music-library-analysis.php','music-library-tags.php','music-library-preparation.php','music-library.php','music.php','producer.php','config.php']as$file){$p='studio-private/app/'.$file;file_put_contents($dir.'/'.$p,'synthetic '.$file);$state['files'][$p]=['live'=>hash_file('sha256',$dir.'/'.$p)];}
 file_put_contents($dir.'/studio-public/release.json',json_encode(['commit'=>$sha]));file_put_contents($dir.'/state.json',json_encode($state));music_scheduler_verify($sha,$dir,$dir.'/state.json');
 scheduler_no(fn()=>music_scheduler_verify('main',$dir,$dir.'/state.json'));scheduler_no(fn()=>music_scheduler_verify(str_repeat('b',40),$dir,$dir.'/state.json'));
 file_put_contents($dir.'/studio-private/app/music-library-worker.php','drift');scheduler_no(fn()=>music_scheduler_verify($sha,$dir,$dir.'/state.json'));
 $state['commit']=str_repeat('b',40);file_put_contents($dir.'/state.json',json_encode($state));scheduler_no(fn()=>music_scheduler_verify($sha,$dir,$dir.'/state.json'));
 echo "PASS scheduler: immutable commit, release/state alignment and changed worker blocked before config or network\n";
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($dir);}
