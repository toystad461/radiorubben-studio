<?php
declare(strict_types=1);
// Run as root only inside the disposable CI/test environment. Child processes
// drop to two distinct UIDs sharing one GID, like rr-studio and rr-playout.
require dirname(__DIR__).'/app/music-playout.php';
if (!function_exists('posix_geteuid') || posix_geteuid() !== 0 || !function_exists('pcntl_fork')) {
    fwrite(STDERR, "This test requires root, POSIX and pcntl in a disposable test environment.\n");
    exit(2);
}
$root=sys_get_temp_dir().'/rr-playout-permissions-'.bin2hex(random_bytes(8));
mkdir($root,0770);chgrp($root,65534);chmod($root,02770);
function as_player_user(int $uid, callable $check): void {
    $pid=pcntl_fork();
    if ($pid===-1) throw new RuntimeException('Cannot fork permission test.');
    if ($pid===0) {
        try {
            if (!posix_setgid(65534) || !posix_setuid($uid)) throw new RuntimeException('Cannot drop test privileges.');
            set_error_handler(static function(int $severity,string $message,string $file,int $line): never {throw new ErrorException($message,0,$severity,$file,$line);});
            $check();exit(0);
        } catch (Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
    }
    pcntl_waitpid($pid,$status);
    if (!pcntl_wifexited($status) || pcntl_wexitstatus($status)!==0) throw new RuntimeException('Shared player permission check failed.');
}
try {
    as_player_user(65533,static function()use($root):void {
        umask(0022);$h=music_playout_lock($root);flock($h,LOCK_UN);fclose($h);
    });
    as_player_user(65532,static function()use($root):void {
        $h=music_playout_lock($root);
        try {music_playout_atomic($root.'/control.json',['ready'=>false]);}
        finally {flock($h,LOCK_UN);fclose($h);}
    });
    as_player_user(65533,static function()use($root):void {
        $h=music_playout_lock($root);
        try {if (music_playout_json($root.'/control.json')!==['ready'=>false]) throw new RuntimeException('Peer control file unreadable.');}
        finally {flock($h,LOCK_UN);fclose($h);}
    });
    echo "PASS separate player/web UIDs: shared lock, permissions and control file.\n";
} finally {
    foreach (glob($root.'/*')?:[] as $file) unlink($file);
    rmdir($root);
}
