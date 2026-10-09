<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/crm-outreach.php';
function ensure(bool $ok,string $why):void {if(!$ok)throw new RuntimeException($why);echo "PASS $why\n";}
function deny(callable $call,string $why):void {try{$call();}catch(InvalidArgumentException|RuntimeException $e){echo "PASS $why\n";return;}throw new RuntimeException('Not rejected: '.$why);}
$tmp=sys_get_temp_dir().'/crm-outreach-'.bin2hex(random_bytes(5));mkdir($tmp,0700);$path=$tmp.'/crm.json';
$admin=['role'=>'admin','name'=>'Testadministrator'];$voice='SyntheticVoice1234';
$config=['crm_demo_enabled'=>true,'crm_demo_daily_character_limit'=>10000,'crm_demo_voice_ids'=>[$voice],'api_key'=>'fixture-only','model_id'=>'eleven_v4','voices'=>[$voice=>['approved'=>true,'rights_reference'=>'Synthetic test only','label'=>'Fixture']]];
$base=['company'=>'Fiktiv bedrift AS','stage'=>'candidate','priority'=>'2','email'=>'fixture@example.test','website'=>'https://example.test','orgNumber'=>'999999999'];
$get=static fn()=>studio_crm_read($path)['records'][0];
$input=static fn()=>['id'=>$get()['id'],'revision'=>(string)$get()['revision']];
try{
    // All company data and audio in this file are synthetic. No network, mail or paid TTS.
    ensure(studio_crm_org_number('999 999 999')==='999999999','Organization number checksum and normalization');
    deny(fn()=>studio_crm_org_number('123456789'),'Reject invalid checksum');
    deny(fn()=>studio_crm_brreg('999999999',fn()=>[404,'']),'Missing business is explicit');
    deny(fn()=>studio_crm_brreg('999999999',fn()=>[200,'{"organisasjonsnummer":"111111111","navn":"Wrong"}']),'Reject mismatched response');
    $source=studio_crm_brreg('999999999',function($url){ensure($url==='https://data.brreg.no/enhetsregisteret/api/enheter/999999999','Fixed public endpoint');return [200,json_encode(['organisasjonsnummer'=>'999999999','navn'=>'Fiktiv bedrift AS','hjemmeside'=>'example.test','forretningsadresse'=>['adresse'=>['Testvegen 1'],'postnummer'=>'0000','poststed'=>'TEST'],'naeringskode1'=>['beskrivelse'=>'Fiktiv bransje']])];});
    ensure($source['fields']['website']==='https://example.test'&&$source['fields']['businessAddress']==='Testvegen 1, 0000 TEST','Homepage and address mapped with source');
    studio_crm_apply('create',$base,$admin,$path,$source);
    ensure($get()['registryEvidence']['sha256']===$source['sha256'],'Source snapshot stored');
    deny(fn()=>studio_crm_apply('create',array_replace($base,['company'=>'Annet navn']),$admin,$path),'Duplicate org number rejected');
    deny(fn()=>studio_crm_outreach_apply('draft',$input(),['role'=>'observer'],$path),'Observer denied');
    studio_crm_outreach_apply('draft',$input(),$admin,$path);
    ensure(str_contains($get()['proposal']['intro'],'Fiktiv bedrift AS'),'Personalized editable draft');
    deny(fn()=>studio_crm_eml($get(),$path),'No mail export before approval');
    deny(fn()=>studio_crm_demo_queue($input()+['voice'=>$voice],$admin,$config,$path),'TTS requires script approval');
    studio_crm_outreach_apply('approve_script',$input()+['confirmed'=>'1'],$admin,$path);
    deny(fn()=>studio_crm_demo_queue($input()+['voice'=>$voice],$admin,array_replace($config,['crm_demo_daily_character_limit'=>1]),$path),'Budget enforced before request');
    deny(fn()=>studio_crm_demo_queue($input()+['voice'=>$voice],$admin,array_replace($config,['crm_demo_voice_ids'=>[]]),$path),'Commercial voice permission required');
    studio_crm_demo_queue($input()+['voice'=>$voice],$admin,$config,$path);$job=$get()['proposal']['demo'];
    deny(fn()=>studio_crm_demo_queue($input()+['voice'=>$voice],$admin,$config,$path),'Duplicate queue rejected');
    $calls=0;$transport=function($c,$v,$payload)use(&$calls){$calls++;ensure(str_starts_with($payload['text'],'Denne stemmen er KI-generert.'),'Audible disclosure sent to provider');return str_repeat(pack('v',1000).pack('v',64536),24000*16/2);};
    studio_crm_demo_run($get()['id'],$job['token'],$admin,$config,$path,$transport);
    ensure($calls===1&&studio_crm_demo_current($get())&&$get()['proposal']['demo']['measure']['duration']===16,'One call creates measured private demo');
    deny(fn()=>studio_crm_demo_run($get()['id'],$job['token'],$admin,$config,$path,$transport),'Repeated job does not call provider');
    deny(fn()=>studio_crm_outreach_apply('approve_package',$input()+['heard'=>'1','recipient'=>'1'],$admin,$path),'Audible disclosure confirmation required');
    studio_crm_outreach_apply('approve_package',$input()+['heard'=>'1','recipient'=>'1','disclosure'=>'1'],$admin,$path);
    $eml=studio_crm_eml($get(),$path);
    ensure(str_contains($eml,"X-Unsent: 1\r\nTo: fixture@example.test")&&str_contains($eml,'Content-Type: audio/wav'),'MIME draft contains text and audio attachment');
    $parts=explode('Content-Transfer-Encoding: base64',$eml);ensure(count($parts)===3,'Exactly two MIME parts');
    $emailPart=trim(explode('--rr-',explode("\r\n\r\n",$parts[1],2)[1])[0]);
    ensure(str_contains(base64_decode($emailPart),'FORSLAG TIL REKLAMEMANUS'),'Draft contains commercial proposal');
    $audioPart=trim(explode('--rr-',explode("\r\n\r\n",$parts[2],2)[1])[0]);
    ensure(hash('sha256',base64_decode($audioPart,true))===$get()['proposal']['demo']['asset']['sha256'],'Exact approved WAV bytes in attachment');
    deny(fn()=>studio_crm_eml($get(),$path,array_replace($config,['crm_demo_voice_ids'=>[]])),'Revoked commercial voice blocks export');
    $assetPath=rr_audio_asset($get()['proposal']['demo']['asset'],$path);$original=file_get_contents($assetPath);file_put_contents($assetPath,'corrupt');
    deny(fn()=>studio_crm_eml($get(),$path),'Changed audio bytes block export');file_put_contents($assetPath,$original);
    $old=$get();studio_crm_apply('save',array_replace($old,['email'=>'changed@example.test','revision'=>(string)$old['revision']]),$admin,$path);
    deny(fn()=>studio_crm_eml($get(),$path),'Recipient change invalidates export');
    studio_crm_apply('save',array_replace($old,['revision'=>(string)$get()['revision']]),$admin,$path);
    deny(fn()=>studio_crm_eml($get(),$path),'Restoring old recipient cannot resurrect approval');
    studio_crm_outreach_apply('save',$input()+array_intersect_key($get()['proposal'],array_flip(['subject','intro','script','sponsor'])),$admin,$path);
    studio_crm_outreach_apply('approve_script',$input()+['confirmed'=>'1'],$admin,$path);
    studio_crm_demo_queue($input()+['voice'=>$voice],$admin,$config,$path);$job=$get()['proposal']['demo'];
    studio_crm_demo_run($get()['id'],$job['token'],$admin,$config,$path,fn()=>throw new RRAudioUncertainException('Fixture timeout'));
    ensure($get()['proposal']['demo']['status']==='unknown','Timeout persists as unknown');
    deny(fn()=>studio_crm_demo_queue($input()+['voice'=>$voice],$admin,$config,$path),'Unknown result blocks further cost');
    studio_crm_outreach_apply('resolve',$input()+['confirmed'=>'1','resolution'=>'Fixture provider result checked.'],$admin,$path);
    studio_crm_demo_queue($input()+['voice'=>$voice],$admin,$config,$path);$job=$get()['proposal']['demo'];
    studio_crm_demo_run($get()['id'],$job['token'],$admin,$config,$path,fn()=>str_repeat(pack('v',1000),24000*25));
    ensure($get()['proposal']['demo']['status']==='needs_revision','Out of range demo cannot be exported');
    deny(fn()=>studio_crm_outreach_apply('approve_package',$input()+['heard'=>'1','recipient'=>'1','disclosure'=>'1'],$admin,$path),'Reject unsuitable duration');
    ensure($get()['lastContact']==='','Export never invents customer contact');
}finally{
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);
}
echo "CRM registry lookup, proposal, TTS and Outlook draft tests OK\n";
