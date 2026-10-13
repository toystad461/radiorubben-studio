<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/music-library.php';
function ml_ok(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS music library: $label\n";}
function ml_no(callable $fn,string $label):void{try{$fn();}catch(Throwable){ml_ok(true,$label);return;}throw new RuntimeException($label);}
$dir=sys_get_temp_dir().'/rr-library-'.bin2hex(random_bytes(5));mkdir($dir,0700);$dir=realpath($dir);$root=$dir.'/media/music';$mp3=str_repeat(hex2bin('fffb9000').str_repeat("\0",413),5);$user=['id'=>'test','role'=>'producer'];
$fakeUploaded=static fn($p)=>is_file($p);$fakeMove=static fn($a,$b)=>copy($a,$b);
function ml_file(string $dir,string $bytes,string $name='Test.mp3'):array{$p=tempnam($dir,'upload-');file_put_contents($p,$bytes);return ['name'=>$name,'tmp_name'=>$p,'size'=>strlen($bytes),'error'=>0];}
try{
 ml_ok(music_library_read($root)===[]&&!is_dir($dir.'/media'),'reading empty library creates nothing');$f=ml_file($dir,$mp3);
 ml_no(fn()=>music_library_upload($f,['role'=>'observer'],'c','c',$root,$fakeUploaded,$fakeMove),'observer denied');
 ml_no(fn()=>music_library_upload($f,['role'=>'presenter'],'c','c',$root,$fakeUploaded,$fakeMove),'presenter cannot upload');
 ml_no(fn()=>music_library_upload($f,$user,'bad','c',$root,$fakeUploaded,$fakeMove),'CSRF denied');
 ml_no(fn()=>music_library_upload($f,$user,'c','c',$root),'local arbitrary file is not a real upload');
 ml_no(fn()=>music_library_upload(array_replace($f,['name'=>'../escape.mp3']),$user,'c','c',$root,$fakeUploaded,$fakeMove),'path traversal rejected');
 ml_no(fn()=>music_library_upload(ml_file($dir,'<?php echo 1;','fake.mp3'),$user,'c','c',$root,$fakeUploaded,$fakeMove),'disguised PHP rejected');
 ml_no(fn()=>music_library_upload(array_replace($f,['size'=>2]),$user,'c','c',$root,$fakeUploaded,$fakeMove),'size mismatch rejected');
 ml_no(fn()=>music_library_upload(array_replace($f,['error'=>UPLOAD_ERR_PARTIAL]),$user,'c','c',$root,$fakeUploaded,$fakeMove),'partial upload rejected');
 ml_ok(!is_dir($root),'rejected inputs create no library');
 $r=music_library_upload($f,$user,'c','c',$root,$fakeUploaded,$fakeMove);$id=$r['track']['id'];
 ml_ok(!$r['duplicate']&&preg_match('/^[a-f0-9]{32}$/D',$id)===1,'stable private identity');
 ml_ok(file_get_contents($root.'/originals/'.$id.'.mp3')===$mp3,'original bytes preserved');
 ml_ok($r['track']['metadata']===null&&$r['track']['tags']===[]&&$r['track']['status']==='awaiting_preparation','no invented metadata or tagging');
 $before=file_get_contents($root.'/library.json');$again=music_library_upload(ml_file($dir,$mp3,'Other name.mp3'),$user,'c','c',$root,$fakeUploaded,$fakeMove);
 ml_ok($again['duplicate']&&$again['track']['id']===$id&&file_get_contents($root.'/library.json')===$before,'duplicate preserves ID and index');
 $withId3="ID3\x03\x00\x00\x00\x00\x00\x00".$mp3;ml_ok(music_library_mp3(ml_file($dir,$withId3)['tmp_name']),'ID3-prefixed MP3 recognized');
 ml_ok(music_library_read($root)[0]['available'],'original available');
 ml_no(fn()=>music_library_upload(ml_file($dir,$withId3),$user,'c','c',$root,$fakeUploaded,static fn()=>false),'failed file move rejected');
 ml_ok(file_get_contents($root.'/library.json')===$before&&count(glob($root.'/originals/*'))===1,'failed move preserves originals/index');
 file_put_contents($root.'/originals/orphan.mp3',$mp3);ml_no(fn()=>music_library_upload(ml_file($dir,$withId3),$user,'c','c',$root,$fakeUploaded,$fakeMove),'unregistered original blocks upload');unlink($root.'/originals/orphan.mp3');
 file_put_contents($root.'/library.json','broken');ml_no(fn()=>music_library_upload($f,$user,'c','c',$root,$fakeUploaded,$fakeMove),'corrupt index never reset');ml_ok(file_get_contents($root.'/library.json')==='broken','corrupt data preserved');file_put_contents($root.'/library.json',$before);
 unlink($root.'/originals/'.$id.'.mp3');ml_ok(!music_library_read($root)[0]['available'],'missing original shown');ml_no(fn()=>music_library_upload($f,$user,'c','c',$root,$fakeUploaded,$fakeMove),'missing original blocks write');
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($dir);}
