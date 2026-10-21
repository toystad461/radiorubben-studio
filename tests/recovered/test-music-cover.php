<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/music-library-cover.php';
function cover_ok(bool $ok):void{if(!$ok)throw new RuntimeException('Cover assertion failed');}
function cover_denied(callable $f):void{try{$f();}catch(Throwable){return;}throw new RuntimeException('Expected cover rejection');}
$dir=sys_get_temp_dir().'/rr-cover-'.bin2hex(random_bytes(6));mkdir($dir,0700);$dir=realpath($dir);$root=$dir.'/media/music';
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
$u=['id'=>'fixture','role'=>'producer'];
try{
    $apic="\0image/png\0\3\0".$png;$frame='APIC'.pack('N',strlen($apic))."\0\0".$apic;$n=strlen($frame);
    $id3='ID3'."\3\0\0".chr(($n>>21)&127).chr(($n>>14)&127).chr(($n>>7)&127).chr($n&127).$frame;
    $bytes=$id3.str_repeat(hex2bin('fffb9000').str_repeat("\0",413),1000);
    $file=$dir.'/test.mp3';file_put_contents($file,$bytes);
    $r=music_library_upload(['name'=>'Cover.mp3','tmp_name'=>$file,'size'=>strlen($bytes),'error'=>0],$u,'c','c',$root,fn()=>true,fn($a,$b)=>copy($a,$b));$t=$r['track'];
    $indexBefore=hash_file('sha256',$root.'/library.json');
    cover_ok(music_cover_read($t,$root)===null);
    music_cover_save($t['id'],$t['sha256'],0,'embedded',null,$u,'c','c',$root);
    $v=music_cover_read($t,$root);cover_ok($v!==null&&$v['source']==='embedded'&&$v['bytes']===$png);
    cover_denied(fn()=>music_cover_save($t['id'],$t['sha256'],1,'manual',$png,['role'=>'observer'],'c','c',$root));
    cover_denied(fn()=>music_cover_save($t['id'],$t['sha256'],1,'manual',$png,$u,'bad','c',$root));
    cover_denied(fn()=>music_cover_save($t['id'],str_repeat('0',64),1,'manual',$png,$u,'c','c',$root));
    cover_denied(fn()=>music_cover_save($t['id'],$t['sha256'],0,'manual',$png,$u,'c','c',$root));
    cover_denied(fn()=>music_cover_validate('<svg xmlns="http://www.w3.org/2000/svg"/>'));
    cover_denied(fn()=>music_cover_validate(str_repeat('x',MUSIC_COVER_LIMIT+1)));
    music_cover_save($t['id'],$t['sha256'],1,'manual',$png,$u,'c','c',$root);
    music_cover_auto($t,$u,'c','c',$root);
    $v=music_cover_read($t,$root);cover_ok($v['source']==='manual'&&$v['revision']===2);
    music_cover_save($t['id'],$t['sha256'],2,'none',null,$u,'c','c',$root);
    music_cover_auto($t,$u,'c','c',$root);cover_ok(music_cover_read($t,$root)['source']==='none');
    cover_denied(fn()=>music_cover_save($t['id'],$t['sha256'],2,'embedded',null,$u,'c','c',$root));
    music_cover_save($t['id'],$t['sha256'],3,'embedded',null,$u,'c','c',$root);
    cover_ok(music_cover_read($t,$root)['revision']===4);
    cover_ok(hash_file('sha256',$root.'/library.json')===$indexBefore);
    cover_ok(hash_file('sha256',$root.'/originals/'.$t['id'].'.mp3')===$t['sha256']);
    $path=$root.'/covers/'.$t['id'].'.json';unlink($path);symlink($file,$path);
    cover_denied(fn()=>music_cover_save($t['id'],$t['sha256'],0,'manual',$png,$u,'c','c',$root));
    cover_ok(file_get_contents($file)===$bytes);
    echo "PASS: embedded APIC extraction, manual cover, roles/CSRF/source/revision, removal/manual precedence, invalid images, symlinks and unchanged audio/index.\n";
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f){if($f->isLink()||!$f->isDir())unlink($f->getPathname());else rmdir($f->getPathname());}rmdir($dir);}
