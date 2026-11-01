<?php
declare(strict_types=1);
// Standalone CLI regression test. Uses only new synthetic temporary files.
require $argv[1] ?? dirname(__DIR__).'/app/music-library.php';
function check(bool $ok,string $message):void {
    if(!$ok)throw new RuntimeException($message);
    echo "PASS: ".$message."\n";
}
function rejected(callable $fn,string $message):void {
    try{$fn();}catch(RuntimeException|JsonException $e){echo "PASS: ".$message."\n";return;}
    throw new RuntimeException("Not rejected: ".$message);
}
$media=sys_get_temp_dir().'/rr-music-boundary-'.bin2hex(random_bytes(8));
mkdir($media,0700);
$root=$media.'/music';
try {
    // Preserve supported creation when the media directory does not exist yet.
    check(music_library_directory($media.'/nested/music',true),'missing media can be created inside permitted parent');
    rmdir($media.'/nested/music/originals');rmdir($media.'/nested/music');rmdir($media.'/nested');
    check(ini_set('open_basedir',$media)!==false,'restrict access to media directory only');
    set_error_handler(static function(int $severity,string $message):never {throw new ErrorException($message,0,$severity);});
    clearstatcache(true);
    check(music_library_read($root)===[],'empty library readable without access to media parent');
    check(!is_dir($root),'read does not create a library');
    check(music_library_directory($root,true),'create library inside allowed media directory');
    file_put_contents($root.'/library.json','{"schemaVersion":1,"tracks":[]}');
    file_put_contents($root.'/library.lock','');
    check(music_library_read($root)===[],'existing empty index readable');
    file_put_contents($root.'/originals/orphan.mp3','synthetic');
    rejected(fn()=>music_library_read($root),'unregistered original remains blocked');
    unlink($root.'/originals/orphan.mp3');
    unlink($root.'/library.lock');
    rejected(fn()=>music_library_read($root),'missing lock remains blocked');
    file_put_contents($root.'/library.lock','');
    file_put_contents($root.'/library.json','{broken');
    rejected(fn()=>music_library_read($root),'malformed index remains blocked');
    file_put_contents($root.'/library.json','{"schemaVersion":1,"tracks":[]}');
    symlink($root,$media.'/alias');
    rejected(fn()=>music_library_read($media.'/alias'),'symlink library remains blocked');
    unlink($media.'/alias');
    echo "OK: Media-boundary regression checks passed.\n";
} finally {
    restore_error_handler();
    // Only this test's randomly named synthetic fixture is removed.
    foreach([$media.'/alias',$root.'/originals/orphan.mp3',$root.'/library.lock',$root.'/library.json']as$p)
        if(is_link($p)||is_file($p))unlink($p);
    foreach([$root.'/originals',$root,$media.'/nested/music/originals',$media.'/nested/music',$media.'/nested',$media]as$p)
        if(is_dir($p))rmdir($p);
}
