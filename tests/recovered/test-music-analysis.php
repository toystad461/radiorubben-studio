<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/music-library-edit.php';
function analysis_ok(bool $v):void{if(!$v)throw new RuntimeException('Analysis assertion failed');}
function analysis_no(callable $f):void{try{$f();}catch(Throwable){return;}throw new RuntimeException('Expected analysis denial');}
$dir=sys_get_temp_dir().'/rr-analysis-'.bin2hex(random_bytes(6));mkdir($dir,0700);$dir=realpath($dir);$root=$dir.'/media/music';$u=['role'=>'producer','id'=>'test'];
try{
 $bytes=str_repeat(hex2bin('fffb9000').str_repeat("\0",413),1000);$file=$dir.'/test.mp3';file_put_contents($file,$bytes);$r=music_library_upload(['name'=>'Test.mp3','tmp_name'=>$file,'size'=>strlen($bytes),'error'=>0],$u,'c','c',$root,fn()=>true,fn($a,$b)=>copy($a,$b));$t=music_library_prepare($r['track']['id'],$r['track']['sha256'],0,$u,'c','c',$root);$t=music_library_edit($t['id'],$t['sha256'],1,['title'=>'Test','artist'=>'Testartist','tags'=>'Egen tag'],$u,'c','c',$root);
 $v=['engine'=>MUSIC_ANALYSIS_ENGINE,'bpm'=>120.0,'key'=>'C','scale'=>'major','tempoConfidence'=>3.0,'keyStrength'=>0.85,'startSeconds'=>0.0,'durationSeconds'=>15.0];$before=file_get_contents($root.'/library.json');
 analysis_no(fn()=>music_library_analysis_save($t['id'],$t['sha256'],2,$v,['role'=>'observer'],'c','c',$root));analysis_no(fn()=>music_library_analysis_save($t['id'],$t['sha256'],2,$v,$u,'bad','c',$root));analysis_no(fn()=>music_library_analysis_save($t['id'],$t['sha256'],1,$v,$u,'c','c',$root));
 foreach(['bpm'=>999,'key'=>'evil','keyStrength'=>2,'engine'=>'unknown','durationSeconds'=>900,'startSeconds'=>999,'tempoConfidence'=>NAN]as$k=>$bad){$invalid=$v;$invalid[$k]=$bad;analysis_no(fn()=>music_library_analysis_save($t['id'],$t['sha256'],2,$invalid,$u,'c','c',$root));}
 analysis_ok(file_get_contents($root.'/library.json')===$before);
 $oldKey=music_worker_key($t);$saved=music_library_analysis_save($t['id'],$t['sha256'],2,$v,$u,'c','c',$root);
 analysis_ok($saved['revision']===3&&music_library_analysis_current($saved)['bpm']===120.0&&music_worker_key($saved)!==$oldKey&&$saved['tags']===$t['tags']&&$saved['manualOverrides']===$t['manualOverrides']&&hash_file('sha256',$root.'/originals/'.$t['id'].'.mp3')===$t['sha256']);
 $input=music_library_producer_input($saved);analysis_ok($input['audioAnalysis']['key']==='C');$stale=$saved;$stale['sha256']=str_repeat('a',64);analysis_ok(music_library_analysis_current($stale)===null);
 $assessment=music_library_producer_assess(['openai_model'=>'synthetic'],$saved,fn()=>'{"energy":"moderat","periods":[],"tags":[],"reason":"Målt tempo er et estimat.","confidence":"low"}');$saved['producerAssessment']=$assessment;analysis_ok(music_library_producer_current($saved)!==null);$saved['audioAnalysis']['bpm']=121;analysis_ok(music_library_producer_current($saved)===null);
 echo "PASS audio analysis: roles/CSRF/revision/ranges, original/manual preservation, worker invalidation and producer grounding\n";
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($dir);}
