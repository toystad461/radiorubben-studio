<?php
declare(strict_types=1);
$package=dirname(__DIR__,2);
require (getenv('RR_MUSIC_AI_TEST_APP')?:$package).'/app/music-library-producer.php';
function producer_assert(bool $v):void{if(!$v)throw new RuntimeException('Producer assertion failed');}
function producer_denied(callable $f):void{try{$f();}catch(Throwable){return;}throw new RuntimeException('Expected producer denial');}
$dir=sys_get_temp_dir().'/rr-producer-'.bin2hex(random_bytes(6));mkdir($dir,0700);$dir=realpath($dir);$root=$dir.'/media/music';$user=['role'=>'producer','id'=>'test'];$config=['openai_model'=>'synthetic'];$calls=0;
$request=function($c,$p)use(&$calls){$calls++;producer_assert($p['store']===false);producer_assert(!str_contains($p['input'],'originals/'));return json_encode(['energy'=>'moderat','periods'=>['Ettermiddag'],'reason'=>'Passer som et forsiktig forslag.','confidence'=>'medium']);};
try{
 $bytes=str_repeat(hex2bin('fffb9000').str_repeat("\0",413),100);$file=$dir.'/test.mp3';file_put_contents($file,$bytes);
 $upload=music_library_upload(['name'=>'Test.mp3','tmp_name'=>$file,'size'=>strlen($bytes),'error'=>0],$user,'c','c',$root,fn()=>true,fn($a,$b)=>copy($a,$b));$t=$upload['track'];$id=$t['id'];$sha=$t['sha256'];
 $t=music_library_prepare($id,$sha,0,$user,'c','c',$root);$index=music_library_index($root);$index['tracks'][0]['metadata']['fields']['title']='Testlåt';$index['tracks'][0]['metadata']['fields']['artist']='Testartist';$index['tracks'][0]['tags']=['Manuelt program'];$index['tracks'][0]['manualOverrides']=['title'=>'Rettet'];file_put_contents($root.'/library.json',json_encode($index));$before=file_get_contents($root.'/library.json');
 producer_denied(fn()=>music_library_producer_save($config,$id,$sha,1,['role'=>'observer'],'c','c',$root,$request));producer_denied(fn()=>music_library_producer_save($config,$id,$sha,1,$user,'bad','c',$root,$request));producer_denied(fn()=>music_library_producer_save($config,$id,$sha,0,$user,'c','c',$root,$request));producer_assert($calls===0&&file_get_contents($root.'/library.json')===$before);
 $result=music_library_producer_save($config,$id,$sha,1,$user,'c','c',$root,$request);producer_assert($calls===1&&$result['revision']===2&&$result['tags']===['Manuelt program']&&$result['manualOverrides']['title']==='Rettet');producer_assert(music_library_producer_input($result)['effectiveFields']['title']==='Rettet');producer_assert($result['producerAssessment']['rotationApproved']===false&&music_library_producer_current($result)!==null);producer_assert(hash_file('sha256',$root.'/originals/'.$id.'.mp3')===$sha);
 $changed=$result;$changed['tags'][]='Endret';producer_assert(music_library_producer_current($changed)===null);
 $before=file_get_contents($root.'/library.json');producer_denied(fn()=>music_library_producer_save($config,$id,$sha,2,$user,'c','c',$root,fn()=>'{"energy":"energisk","periods":["Oppdiktet program"],"reason":"Test","confidence":"high"}'));producer_assert(file_get_contents($root.'/library.json')===$before);
 producer_denied(fn()=>music_library_producer_save($config,$id,$sha,2,$user,'c','c',$root,fn()=>throw new RuntimeException('timeout')));producer_assert(file_get_contents($root.'/library.json')===$before);
 $concurrent=function($c,$p)use($root,$request){$i=music_library_index($root);$i['tracks'][0]['revision']++;file_put_contents($root.'/library.json',json_encode($i));return $request($c,$p);};producer_denied(fn()=>music_library_producer_save($config,$id,$sha,2,$user,'c','c',$root,$concurrent));producer_assert(music_library_index($root)['tracks'][0]['producerAssessment']===$result['producerAssessment']);

 // Versioned confidence policy must survive real save/read and reject old proposals.
 $beforePolicy=file_get_contents($root.'/library.json');
 $policyRequest=function($c,$p){
     $input=json_decode($p['input'],true,512,JSON_THROW_ON_ERROR);
     producer_assert($input['assessmentPolicy']['version']===MUSIC_PRODUCER_PROMPT_VERSION);
     producer_assert($input['assessmentPolicy']['confidenceCap']==='medium');
     producer_assert(in_array('Helgemorgen',array_column($input['profiles'],'period'),true));
     producer_assert(in_array('Helgekveld',array_column($input['profiles'],'period'),true));
     producer_assert($p['text']['format']['schema']['properties']['confidence']['enum']===['low','medium']);
     return json_encode(['energy'=>'moderat','periods'=>['Ettermiddag'],'tags'=>[],'reason'=>'Grunnlag: filopplysninger. Forslag: ettermiddag. Usikkerhet: ingen lytting.','confidence'=>'high']);
 };
 $fresh=music_library_index($root)['tracks'][0];
 $policySaved=music_library_producer_save($config,$id,$sha,$fresh['revision'],$user,'c','c',$root,$policyRequest);
 $read=music_library_read($root)[0];$current=music_library_producer_current($read);
 producer_assert($current!==null&&$current['confidence']==='medium'&&$current['modelConfidence']==='high');
 producer_assert($read['tags']===$fresh['tags']&&$read['manualOverrides']===$fresh['manualOverrides']);
 producer_assert($current['rotationApproved']===false&&hash_file('sha256',$root.'/originals/'.$id.'.mp3')===$sha);
 $old=$read;$old['producerAssessment']['promptVersion']='music-producer-4';
 producer_assert(music_library_producer_current($old)===null);
 $oldInput=music_library_producer_input($read);unset($oldInput['assessmentPolicy']);
 $old=$read;$old['producerAssessment']['inputSha256']=hash('sha256',json_encode($oldInput,JSON_THROW_ON_ERROR));
 producer_assert(music_library_producer_current($old)===null);
 $tampered=$read;$tampered['producerAssessment']['confidence']='high';
 producer_assert(music_library_producer_current($tampered)===null);
 producer_assert(music_library_producer_current($read)!==null);

 echo "PASS music producer: roles/CSRF, source/revision, timeout, invalid program, concurrent edit, manual preservation, stale proposal, versioned confidence cap, weekend profiles and no rotation approval\n";
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($dir);}
