<?php
declare(strict_types=1);
require __DIR__.'/../app/jingles.php';
require __DIR__.'/../app/music-liquidsoap.php';
function jcheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS jingles: $label\n";}
function jreject(callable $f,string $label):void{try{$f();}catch(Throwable){jcheck(true,$label);return;}throw new RuntimeException($label);}
$tmp=sys_get_temp_dir().'/rr-jingles-'.bin2hex(random_bytes(6));mkdir($tmp,0700);mkdir($tmp.'/config');$lib=$tmp.'/media/music';music_library_directory($lib,true);$path=$tmp.'/config/queue.json';$actor=['id'=>'test','role'=>'producer'];
try{
    $tracks=[];
    foreach(['music','jingle'] as $kind){$bytes='fixture-'.$kind;$id=md5($bytes);$sha=hash('sha256',$bytes);file_put_contents($lib.'/originals/'.$id.'.mp3',$bytes);$tracks[]=['id'=>$id,'kind'=>$kind,'sha256'=>$sha,'bytes'=>strlen($bytes),'originalName'=>$kind.'.mp3','metadata'=>['source'=>'file','sourceSha256'=>$sha,'durationSeconds'=>$kind==='music'?180:5,'fields'=>['title'=>$kind,'artist'=>'Same artist']],'revision'=>1];}
    file_put_contents($lib.'/library.json',json_encode(['schemaVersion'=>1,'tracks'=>$tracks]));file_put_contents($lib.'/library.lock','');
    jcheck(count(music_library_read($lib))===1&&music_library_read($lib)[0]['kind']==='music','music library excludes jingles');
    jcheck(count(music_library_read($lib,'jingle'))===1&&count(music_library_read($lib,'all'))===2,'jingles view and shared source');
    $j=$tracks[1];$input=['id'=>$j['id'],'sha256'=>$j['sha256'],'revision'=>1,'name'=>'Test ID','category'=>'news','disabled'=>false];
    jreject(fn()=>jingle_edit($input,['role'=>'observer'],'x','x',$lib),'observer rejected');
    jreject(fn()=>jingle_edit($input,$actor,'bad','x',$lib),'CSRF rejected');
    jreject(fn()=>jingle_edit(array_replace($input,['sha256'=>str_repeat('a',64)]),$actor,'x','x',$lib),'changed file rejected');
    jingle_edit($input,$actor,'x','x',$lib);
    jreject(fn()=>jingle_edit($input,$actor,'x','x',$lib),'stale edit rejected');
    jreject(fn()=>jingle_edit(array_replace($input,['id'=>$tracks[0]['id'],'sha256'=>$tracks[0]['sha256']]),$actor,'x','x',$lib),'cannot convert a music track via jingle editor');
    jcheck(hash_file('sha256',$lib.'/originals/'.$j['id'].'.mp3')===$j['sha256'],'rename preserves original');
    $change=fn($body)=>music_studio_change($body,$actor,'x','x',$path,$lib);
    $change(['action'=>'schedule','revision'=>0,'schedule'=>['programId'=>'god-morgen-bomlo','date'=>'2026-11-01','time'=>'07:00']]);
    $change(['action'=>'add','revision'=>1,'trackId'=>$j['id'],'sha256'=>$j['sha256'],'kind'=>'music']);
    $q=music_play_queue_read($path);$all=music_library_read($lib,'all');
    jcheck($q['entries'][0]['kind']==='jingle','server assigns type, ignoring forged client type');
    $plan=music_schedule_plan($q,$all,[],1);
    jcheck(!$plan['rows'][0]['blocked']&&$plan['rows'][0]['title']==='Test ID','jingle needs no music artist or rotation policy');
    jcheck(!$plan['suggestions'],'unapproved music remains blocked and jingles are not suggested');
    $view=music_studio_view($path,$lib);jcheck(count($view['catalog'])===2&&$view['entries'][0]['kind']==='jingle','Studio selection includes stored music and jingles');
    $spoof=$q;$spoof['entries'][0]['kind']='music';jcheck((bool)music_schedule_plan($spoof,$all,[],0)['rows'][0]['blocked'],'queue/library type mismatch blocked');
    jreject(fn()=>$change(['action'=>'replace','revision'=>2,'id'=>$q['entries'][0]['id']]),'music replacement cannot replace jingle');
    $input['revision']=2;$input['disabled']=true;jingle_edit($input,$actor,'x','x',$lib);
    jreject(fn()=>$change(['action'=>'add','revision'=>2,'trackId'=>$j['id'],'sha256'=>$j['sha256']]),'disabled jingle cannot be added');
    jcheck((bool)music_schedule_plan($q,music_library_read($lib,'all'),[],0)['rows'][0]['blocked'],'disabled jingle blocked on resync');
    $entry=$plan['rows'][0];music_liquidsoap_entry($entry);
    jreject(fn()=>music_liquidsoap_entry(array_replace($entry,['kind'=>'program_break'])),'unsupported kind remains blocked in motor');
    $entry['startedAt']=gmdate('c');jcheck(music_playout_history_from_state(['outcomes'=>[$entry]])===[],'jingle remains in journal but outside music repetition history');
    $input['revision']=3;$input['disabled']=false;jingle_edit($input,$actor,'x','x',$lib);
    $music=$tracks[0];$q['studio']['policies'][$music['id']]=music_schedule_policy(['approved'=>true,'blocked'=>false,'programs'=>['god-morgen-bomlo'],'weekdays'=>[1,2,3,4,5,6,7],'periods'=>[],'fields'=>[]],$music['sha256']);
    $p=music_schedule_plan($q,music_library_read($lib,'all'),[],1);jcheck(count($p['suggestions'])===1&&$p['suggestions'][0]['trackId']===$music['id']&&str_contains($p['suggestions'][0]['plannedAt'],'07:00:05'),'jingle advances time without excluding music by same artist');
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);}
