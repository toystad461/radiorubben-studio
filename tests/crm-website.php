<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/crm-outreach.php';
function website_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function website_deny(callable $f,string $label):void{try{$f();}catch(InvalidArgumentException|RuntimeException|JsonException $e){echo "PASS $label\n";return;}throw new LogicException('Accepted: '.$label);}
$public=fn()=>['8.8.8.8'];
foreach(['http://example.test','https://127.0.0.1','https://user:pass@example.test','https://example.test:443','file:///etc/passwd','https://example.test/#x']as$url)
 website_deny(fn()=>studio_crm_website_target($url,$public),'Unsafe URL rejected');
foreach(['127.0.0.1','10.0.0.1','169.254.169.254','192.168.1.1','100.64.0.1','0.0.0.0','224.0.0.1','192.0.2.1']as$ip)
 website_deny(fn()=>studio_crm_website_target('https://example.test',fn()=>[$ip]),'Non-public address rejected '.$ip);
website_deny(fn()=>studio_crm_website_target('https://example.test',fn()=>['8.8.8.8','10.0.0.1']),'Mixed DNS response rejected');
$html='<html><body><nav>Unwanted navigation</nav><main><h1>Fiktiv Sykkel AS</h1><p>Fiktiv Sykkel AS reparerer sykler og selger sykkelutstyr. Besøk verkstedet for å snakke om sykkelen din. Vi presenterer tjenester og utstyr på denne hjemmesiden.</p><script>evil()</script></main></body></html>';
$calls=0;
$source=studio_crm_website_fetch('https://example.test',$public,function($url,$target)use(&$calls,$html){
 $calls++;website_check($target['ip']==='8.8.8.8','Validated address passed to pinned transport');
 return $calls===1?[301,'text/html','','https://www.example.test/']:[200,'text/html',$html,''];
});
website_check($calls===2&&!str_contains($source['text'],'evil')&&!str_contains($source['text'],'Unwanted'),'Safe same-site redirect and clean source');
website_deny(fn()=>studio_crm_website_fetch('https://example.test',$public,fn()=>[302,'text/html','','https://evil.test/']),'Cross-domain redirect blocked');
website_deny(fn()=>studio_crm_website_fetch('https://example.test',$public,fn()=>[200,'application/json','{}','']),'Non HTML response blocked');
website_deny(fn()=>studio_crm_website_extract('<html><body>Short</body></html>'),'Insufficient source blocked');
$path=sys_get_temp_dir().'/crm-website-'.bin2hex(random_bytes(5)).'.json';$admin=['role'=>'admin','name'=>'Test'];
$config=['openai_api_key'=>'fixture-only','openai_model'=>'fixture-model'];
$fetch=fn()=>$source;$script='Trenger sykkelen din en gjennomgang? Fiktiv Sykkel AS reparerer sykler og selger sykkelutstyr. Besøk verkstedet og ta en prat om sykkelen din. Les mer på hjemmesiden.';
$reply=['script'=>$script,'evidence'=>[['claim'=>'Sykkelreparasjon og utstyr','quote'=>'Fiktiv Sykkel AS reparerer sykler og selger sykkelutstyr.']]];
$request=function($config,$payload)use($reply){
 $input=json_decode($payload['input'],true);website_check(!isset($input['email'])&&!isset($input['contact'])&&!isset($input['history']),'Only public company/source data sent to generator');
 website_check($payload['store']===false&&!isset($payload['tools']),'No stored response or model tools');
 return json_encode($reply);
};
try{
 studio_crm_apply('create',['company'=>'Fiktiv Sykkel AS','website'=>'https://example.test','email'=>'test@example.test','stage'=>'candidate','priority'=>'2'],$admin,$path);
 $get=fn()=>studio_crm_read($path)['records'][0];$input=fn()=>['id'=>$get()['id'],'revision'=>(string)$get()['revision']];
 website_deny(fn()=>studio_crm_website_prepare($input(),['role'=>'observer'],$config,$path,$fetch,$request),'Observer cannot fetch or generate');
 website_deny(fn()=>studio_crm_website_prepare($input(),$admin,[],$path,$fetch,$request),'Missing configuration blocks generation');
 studio_crm_website_prepare($input(),$admin,$config,$path,$fetch,$request);
 $row=$get();website_check($row['proposal']['script']===$script&&$row['proposal']['generation']['source']['text']===$source['text'],'Generated script and source stored together');
 website_check(str_contains(studio_crm_message($row['proposal']),$script),'Message contains canonical script');
 website_deny(fn()=>studio_crm_text_eml($get()),'Text export requires human approval');
 studio_crm_outreach_apply('approve_text',$input()+['confirmed'=>'1','recipient'=>'1'],$admin,$path);
 $mail=studio_crm_text_eml($get());$body=base64_decode(explode("\r\n\r\n",$mail,2)[1]);
 website_check(str_contains($body,$script)&&!str_contains($mail,'audio/wav'),'Approved text export works without TTS');
 $voice='SyntheticVoice1234';$audio=['crm_demo_enabled'=>true,'crm_demo_daily_character_limit'=>5000,'crm_demo_voice_ids'=>[$voice],'api_key'=>'fixture','model_id'=>'eleven_v4','voices'=>[$voice=>['approved'=>true,'rights_reference'=>'Fixture']]];
 website_deny(fn()=>studio_crm_demo_queue($input()+['voice'=>$voice],$admin,$audio,$path),'Text approval does not approve TTS');
 studio_crm_outreach_apply('approve_script',$input()+['confirmed'=>'1'],$admin,$path);
 studio_crm_demo_queue($input()+['voice'=>$voice],$admin,$audio,$path);
 website_check($get()['proposal']['demo']['text']===rr_audio_disclosure()['text']."\n\n".$script,'TTS uses exact canonical script with audible disclosure');
 website_deny(fn()=>studio_crm_website_prepare($input(),$admin,$config,$path,$fetch,$request),'Queued audio blocks regeneration');
 studio_crm_outreach_apply('resolve',$input()+['confirmed'=>'1','resolution'=>'Fixture job cancelled without provider call.'],$admin,$path);
 $before=$get();studio_crm_outreach_apply('save',$input()+array_replace($before['proposal'],['script'=>$script.' Velkommen.']),$admin,$path);
 website_deny(fn()=>studio_crm_text_eml($get()),'Edited script invalidates text export');
 website_check(isset($get()['proposal']['generation']['originalScript']),'Manual edits preserve original and source');
 $tampered=$get();$tampered['proposal']['generation']['source']['text'].='Changed';
 website_check(!studio_crm_proposal_current($tampered),'Changed source rejected');
 $expired=$get();$expired['proposal']['generation']['source']['fetchedAt']=gmdate('c',time()-90000);
 website_check(!studio_crm_proposal_current($expired),'Expired source rejected');
 $bad=$reply;$bad['evidence'][0]['quote']='A claim that does not exist in the source.';
 $before=file_get_contents($path);
 website_deny(fn()=>studio_crm_website_prepare($input(),$admin,$config,$path,$fetch,fn()=>json_encode($bad)),'Unsupported source quote rejected');
 website_check(file_get_contents($path)===$before,'Failed generation leaves previous proposal intact');
 $stale=$input();
 studio_crm_apply('save',array_replace($get(),['nextStep'=>'Changed concurrently']),$admin,$path);
 website_deny(fn()=>studio_crm_website_prepare($stale,$admin,$config,$path,$fetch,$request),'Stale revision rejected before external calls');
 $concurrent=function($c,$p)use($path,$get,$admin,$reply){studio_crm_apply('save',array_replace($get(),['nextStep'=>'Changed during request']),$admin,$path);return json_encode($reply);};
 website_deny(fn()=>studio_crm_website_prepare($input(),$admin,$config,$path,$fetch,$concurrent),'Change during generation cannot overwrite card');
}finally{foreach([$path,$path.'.lock']as$f)if(is_file($f))unlink($f);}
echo "CRM website draft OK\n";
