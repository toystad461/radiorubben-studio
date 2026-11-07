<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/music-liquidsoap.php';
function recovery_ok(bool $v,string $s):void {if(!$v)throw new RuntimeException($s);echo "PASS $s\n";}
$r=sys_get_temp_dir().'/rr-recovery-'.bin2hex(random_bytes(8));mkdir($r,0700);
try {
 $h=music_playout_lock($r);$start=microtime(true);
 recovery_ok(music_liquidsoap_claim($r,str_repeat('a',32))==='', 'busy lock returns empty retry without claiming');
 recovery_ok(microtime(true)-$start<.5,'busy lock cannot reach five-second RPC kill');
 flock($h,LOCK_UN);fclose($h);
 $s=music_playout_empty();music_playout_event($r,$s,'service_started');
 recovery_ok(music_playout_state($r)==$s,'first replay');
 recovery_ok(music_playout_state($r)==$s,'cached replay');
 $s['mode']='playing';music_playout_event($r,$s,'started');
 recovery_ok(music_playout_state($r)==$s,'append invalidates replay cache');
 $raw=file_get_contents($r.'/events.jsonl');
 file_put_contents($r.'/events.jsonl',str_replace('"seq":1','"seq":9',$raw));
 try {music_playout_state($r);throw new LogicException('accepted corrupted journal');}catch(RuntimeException $e){recovery_ok(str_contains($e->getMessage(),'hendelsesrekkefølge'),'same-size corruption invalidates cache');}
 file_put_contents($r.'/events.jsonl',substr($raw,0,-1));
 try {music_playout_state($r);throw new LogicException('accepted truncation');}catch(RuntimeException $e){recovery_ok(str_contains($e->getMessage(),'Ufullstendig'),'partial append is rejected');}
 recovery_ok(!music_liquidsoap_start_expired(0,null,40),'cold initialization gets bounded grace');
 recovery_ok(music_liquidsoap_start_expired(0,null,91),'hung initialization still stops');
 recovery_ok(!music_liquidsoap_start_expired(0,40,59),'first audio window starts at connection');
 recovery_ok(music_liquidsoap_start_expired(0,40,61),'connected silence still times out');
} finally {foreach(glob($r.'/*') as $p)unlink($p);rmdir($r);}
