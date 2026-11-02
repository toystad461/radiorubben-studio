<?php
declare(strict_types=1);
require __DIR__.'/../app/music-liquidsoap.php';
function due_check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$s=['current'=>['id'=>md5('current'),'duration'=>180,'observedWallStart'=>1000.,'playback'=>['crossfadeOut'=>3]]];
$live=['currentId'=>$s['current']['id'],'timestamp'=>1170.,'position'=>100.];
due_check(!music_liquidsoap_claim_due($s,$live),'middle of song remains editable');
due_check(music_liquidsoap_claim_due($s,array_replace($live,['position'=>169.])), 'eleven-second transition preparation');
due_check(!music_liquidsoap_claim_due($s,array_replace($live,['position'=>168.99])), 'outside preparation window remains editable');
foreach([NAN,INF,-1,'169',null]as$p)due_check(!music_liquidsoap_claim_due($s,array_replace($live,['position'=>$p])),'invalid position cannot claim');
due_check(!music_liquidsoap_claim_due($s,array_replace($live,['currentId'=>md5('other'),'position'=>179.])), 'other-track heartbeat rejected');
due_check(!music_liquidsoap_claim_due($s,array_replace($live,['timestamp'=>999.,'position'=>179.])), 'heartbeat before new PCM start rejected');
unset($live['currentId']);due_check(!music_liquidsoap_claim_due($s,array_replace($live,['timestamp'=>1001.,'position'=>179.])), 'legacy previous-song position cannot claim early');due_check(music_liquidsoap_claim_due($s,array_replace($live,['position'=>170.])), 'existing supervisor supported without restart');
due_check(music_liquidsoap_claim_due(['current'=>null],[]),'initial request has no delay');
