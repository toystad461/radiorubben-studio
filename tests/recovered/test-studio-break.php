<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/studio-break.php';
function sb_ok(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS Studio break: $label\n";}
function sb_no(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException){sb_ok(true,$label);return;}throw new RuntimeException($label);}
$dir=sys_get_temp_dir().'/rr-break-'.bin2hex(random_bytes(5));mkdir($dir,0700);$path=$dir.'/board.json';file_put_contents($path,'{"items":[]}');
$user=['id'=>'synthetic','role'=>'producer','name'=>'Testredaktør'];
$input=['action'=>'approve','confirmed'=>'1','csrf'=>'synthetic','type'=>'next','text'=>'Du hører Radio Rubben. Neste låt er Testartist med Testlåt.','notes'=>'Operatørens syntetiske musikkgrunnlag.','context'=>['source'=>'local','program'=>'Testprogram','nowPlaying'=>'Testartist – Første','next'=>'Testartist – Testlåt','nextTracks'=>['Testartist – Testlåt']],'aiAssisted'=>true];
try{
 $before=file_get_contents($path);
 sb_no(fn()=>studio_break_approve($input,array_replace($user,['role'=>'observer']),'synthetic',$path),'observer cannot approve');
 sb_no(fn()=>studio_break_approve(array_replace($input,['csrf'=>'bad']),$user,'synthetic',$path),'CSRF enforced');
 sb_no(fn()=>studio_break_approve(array_replace($input,['confirmed'=>'0']),$user,'synthetic',$path),'human approval required');
 sb_no(fn()=>studio_break_approve(array_replace($input,['type'=>'weather']),$user,'synthetic',$path),'weather cannot bypass its source workflow');
 sb_no(fn()=>studio_break_approve(array_replace($input,['text'=>'Neste låt: [Sett inn tittel]']),$user,'synthetic',$path),'placeholders blocked');
 sb_no(fn()=>studio_break_approve(array_replace($input,['text'=>'<script>bad</script>']),$user,'synthetic',$path),'HTML blocked');
 $demo=$input;$demo['context']['source']='demo';sb_no(fn()=>studio_break_approve($demo,$user,'synthetic',$path),'demo cannot create a real item');
 sb_ok(file_get_contents($path)===$before,'rejections preserve editorial data');
 $item=studio_break_approve($input,$user,'synthetic',$path);$v=$item['audioScripts']['host'];
 sb_ok(rr_audio_script_approved($item,$v),'one human action yields existing RR Audio manuscript approval');
 sb_ok($item['channel']==='radio'&&empty($item['approvedBy'])&&!isset($v['audio'])&&!isset($item['web']),'no audio, web publication or on-air approval');
 sb_ok($v['generation']['aiAssisted']&&$v['generation']['aiPolicyVersion']===RR_AI_POLICY_VERSION,'AI declaration preserved');
 $again=studio_break_approve($input,$user,'synthetic',$path);sb_ok($again['id']===$item['id']&&count(studio_board_read($path)['items'])===1,'retry reuses identical approved item');
 $calls=0;$cfg=['enabled'=>true,'test_only'=>true,'api_key'=>'mock','model_id'=>'eleven_multilingual_v2','daily_character_limit'=>5000,'voices'=>['synthetic'=>['approved'=>true,'rights_reference'=>'synthetic-only','scope'=>'all']]];
 $transport=static function($config,$voice,$payload)use(&$calls){$calls++;sb_ok(str_starts_with($payload['text'],"Denne stemmen er KI-generert.\n\n"),'TTS keeps audible synthetic disclosure');return str_repeat(pack('v',1000),24000);};
 rr_audio_generate($item['id'],$item['revision'],'host','synthetic',[],$user,$cfg,$path,$transport);
 $audio=studio_case_get($item['id'],$path)['audioScripts']['host']['audio'];
 sb_ok($calls===1&&$audio['status']==='needs_processing'&&!$audio['approval'],'existing TTS produces preview without audio approval');
 $altered=$item;$altered['studioBreak']['source']['next']='Changed song';sb_ok(!rr_audio_script_approved($altered,$v),'source metadata invalidates approval');
 $forged=$v;$forged['check']['sourceHash']=rr_audio_hash($altered['studioBreak']['source']);sb_ok(!rr_audio_script_approved($altered,$forged),'changed source with replaced check cannot reuse approval');
 $changed=$v;$changed['script']='Annen tekst';sb_ok(!rr_audio_script_approved($item,$changed),'manuscript change invalidates approval');
 $missing=$v;unset($missing['scriptApproval']);sb_ok(!rr_audio_script_approved($item,$missing),'missing approval blocked');
 studio_board_change(static function(array &$b){$b['items'][0]['studioBreak']['source']['next']='New';},$path);
 sb_ok(studio_case_get($item['id'],$path)['audioScripts']['host']['scriptApproval']===null,'metadata change permanently clears stored approval');
 $expired=$v;$expired['check']['checkedAt']=gmdate('c',time()-3601);sb_ok(!rr_audio_script_approved($item,$expired),'expired human source check blocked');
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($dir);}
