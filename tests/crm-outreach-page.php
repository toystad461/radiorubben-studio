<?php
declare(strict_types=1);
$mode=$argv[1]??'view';$root=dirname(__DIR__);$tmp=sys_get_temp_dir().'/crm-demo-page-'.bin2hex(random_bytes(5));
foreach(['app/views','app/integrations','public','config']as$dir)mkdir($tmp.'/'.$dir,0700,true);
foreach(['crm.php','crm-brreg.php','crm-outreach.php','audio-storage.php','audio-profiles.php','programs.php','helpers.php','users.php']as$file)copy($root.'/app/'.$file,$tmp.'/app/'.$file);
copy($root.'/app/integrations/ElevenLabs.php',$tmp.'/app/integrations/ElevenLabs.php');
foreach(['head.php','sidebar.php','account.php']as$file)copy($root.'/app/views/'.$file,$tmp.'/app/views/'.$file);
foreach(['crm-outreach.php','crm-demo.php','crm-demo-job.php','crm-register.php']as$file)copy($root.'/public/'.$file,$tmp.'/public/'.$file);
file_put_contents($tmp.'/app/bootstrap.php','<?php require __DIR__."/helpers.php"; require __DIR__."/users.php"; function current_user(){return $GLOBALS["mode"]==="guest"?null:["name"=>"Test","role"=>str_contains($GLOBALS["mode"],"denied")?"observer":"admin"];} $config=[];');
require $tmp.'/app/crm-outreach.php';$path=$tmp.'/config/crm.private.json';$admin=['role'=>'admin','name'=>'Test'];
$id=studio_crm_apply('create',['company'=>'Fiktiv bedrift <test>','priority'=>'2','stage'=>'candidate','email'=>'fixture@example.test'],$admin,$path);
$input=['id'=>$id,'revision'=>'1'];studio_crm_outreach_apply('draft',$input,$admin,$path);
$before=file_get_contents($path);$_SESSION=['csrf'=>'fixture'];$_GET=['id'=>$id];$_POST=[];$_SERVER['REQUEST_METHOD']='GET';
$route='crm-outreach.php';
if(str_starts_with($mode,'lookup'))$route='crm-register.php';
if(str_starts_with($mode,'job'))$route='crm-demo-job.php';
if(str_starts_with($mode,'audio'))$route='crm-demo.php';
if(str_contains($mode,'csrf')||in_array($mode,['invalid','save'],true)){
 $_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf'=>str_contains($mode,'csrf')?'wrong':'fixture','id'=>$id,'revision'=>'2','action'=>'save',
 'subject'=>'Testemne','intro'=>'Behold denne e-postteksten.','script'=>'Et kort reklamemanus til kontroll.','sponsor'=>'Et uforpliktende programsamarbeid.'];
 if($mode==='invalid')$_POST['subject']='';
}
ob_start();register_shutdown_function(static function()use($tmp,$path,$mode,$before):void{
 $html=ob_get_clean();$code=http_response_code()?:200;$ok=true;
 if(str_contains($mode,'denied')||str_contains($mode,'csrf'))$ok=$code===403;
 elseif($mode==='guest')$ok=$code===303;
 elseif($mode==='job-method'||$mode==='lookup-method')$ok=$code===405;
 elseif($mode==='audio-missing')$ok=$code===404;
 elseif($mode==='save')$ok=$code===303&&studio_crm_read($path)['records'][0]['proposal']['version']===2;
 elseif($mode==='invalid')$ok=$code===422&&str_contains($html,'Behold denne e-postteksten.');
 else $ok=$code===200&&str_contains($html,'Fiktiv bedrift &lt;test&gt;')&&str_contains($html,'Introduksjonsmail')&&str_contains($html,'ikke aktivert');
 if($mode!=='save')$ok=$ok&&file_get_contents($path)===$before;
 if(getenv('CRM_OUTREACH_PREVIEW')&&$mode==='view')file_put_contents(getenv('CRM_OUTREACH_PREVIEW'),$html);
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);
 if(!$ok){fwrite(STDERR,"FAIL outreach route $mode HTTP $code\n".substr($html,0,300));exit(1);}echo "PASS outreach route $mode\n";
});require $tmp.'/public/'.$route;
