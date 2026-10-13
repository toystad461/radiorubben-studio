<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/music-library-cues.php';
function cues_ok(bool $v):void{if(!$v)throw new RuntimeException('Cue assertion failed');}
function cues_no(callable $f):void{try{$f();}catch(Throwable){return;}throw new RuntimeException('Expected cue denial');}
$dir=sys_get_temp_dir().'/rr-cues-'.bin2hex(random_bytes(6));mkdir($dir,0700);$dir=realpath($dir);$root=$dir.'/media/music';$u=['role'=>'producer','id'=>'test'];
try{
 $bytes=str_repeat(hex2bin('fffb9000').str_repeat("\0",413),1000);$file=$dir.'/test.mp3';file_put_contents($file,$bytes);$r=music_library_upload(['name'=>'Test.mp3','tmp_name'=>$file,'size'=>strlen($bytes),'error'=>0],$u,'c','c',$root,fn()=>true,fn($a,$b)=>copy($a,$b));$t=music_library_prepare($r['track']['id'],$r['track']['sha256'],0,$u,'c','c',$root);$id=$t['id'];$sha=$t['sha256'];$before=file_get_contents($root.'/library.json');$v=['start'=>0.5,'vocalStart'=>2.0,'transition'=>12.0,'end'=>15.0];
 cues_no(fn()=>music_library_cues_save($id,$sha,1,$v,['role'=>'presenter'],'c','c',$root));cues_no(fn()=>music_library_cues_save($id,$sha,1,$v,$u,'wrong','c',$root));cues_no(fn()=>music_library_cues_save($id,$sha,0,$v,$u,'c','c',$root));cues_no(fn()=>music_library_cues_save($id,str_repeat('a',64),1,$v,$u,'c','c',$root));
 foreach(['start'=>-1,'vocalStart'=>16,'transition'=>1,'end'=>0.5]as$k=>$bad){$invalid=$v;$invalid[$k]=$bad;cues_no(fn()=>music_library_cues_save($id,$sha,1,$invalid,$u,'c','c',$root));}
 cues_no(fn()=>music_cue_values($v+['unexpected'=>1],20));$invalid=$v;$invalid['start']=NAN;cues_no(fn()=>music_cue_values($invalid,20));cues_no(fn()=>music_cue_form_values(['start'=>'1e3','vocalStart'=>'','transition'=>'','end'=>'15']));
 cues_ok(file_get_contents($root.'/library.json')===$before);$key=music_worker_key($t);
 $saved=music_library_cues_save($id,$sha,1,$v,$u,'c','c',$root);foreach(['revision'=>$saved['revision']===2,'points'=>music_library_cues_current($saved)['points']==$v,'metadata'=>$saved['metadata']==$t['metadata'],'job'=>music_worker_key($saved)===$key,'source'=>hash_file('sha256',$root.'/originals/'.$id.'.mp3')===$sha]as$name=>$ok)if(!$ok)throw new RuntimeException('Cue save failed: '.$name);
 $next=music_cue_form_values(['start'=>'0','vocalStart'=>'','transition'=>'','end'=>'20']);$saved=music_library_cues_save($id,$sha,2,$next,$u,'c','c',$root);cues_ok($saved['cueHistory'][0]['points']==$v&&$saved['cuePoints']['points']['vocalStart']===null);
 $stale=$saved;$stale['sha256']=str_repeat('b',64);cues_ok(music_library_cues_current($stale)===null);
 $index=music_library_index($root);$index['tracks'][0]['cueHistory']=array_fill(0,21,$saved['cuePoints']);file_put_contents($root.'/library.json',json_encode($index,JSON_THROW_ON_ERROR));usleep(200000);$saved=music_library_cues_save($id,$sha,$saved['revision'],$next,$u,'c','c',$root);cues_ok(count($saved['cueHistory'])===20);
 echo "PASS manual cues: roles/CSRF/source/revision, ordered bounded times, nullable unknowns, history and original preservation\n";
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($dir);}
