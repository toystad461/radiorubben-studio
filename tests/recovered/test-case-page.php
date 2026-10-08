<?php
declare(strict_types=1);
$mode=$argv[1]??'get';
$render=$mode==='render';
if($render)$mode='get';
$root=dirname(__DIR__,2);$tmp=sys_get_temp_dir().'/case-page-'.bin2hex(random_bytes(4));
foreach(['/app/views','/app/integrations','/config','/public']as$dir)mkdir($tmp.$dir,0700,true);
foreach(['board','source-identity','programs','audio-profiles','stylebook','news-script','web-publish','news-publication','case-workflow','bulletin','weather','weather-script','audio-workflow','audio-processing','audio-pronunciation','audio-storage']as$name)copy($root.'/app/'.$name.'.php',$tmp.'/app/'.$name.'.php');
copy($root.'/app/integrations/ElevenLabs.php',$tmp.'/app/integrations/ElevenLabs.php');
copy($root.'/app/views/case-audio.php',$tmp.'/app/views/case-audio.php');
copy($root.'/public/case.php',$tmp.'/public/case.php');
file_put_contents($tmp.'/app/bootstrap.php','<?php
function current_user(){return $GLOBALS["mode"]==="guest"?null:["name"=>"Editor","role"=>$GLOBALS["mode"]==="observer"?"observer":"admin"];}
function studio_can($user,$permission){return $user["role"]==="admin";}
function escape($text){return htmlspecialchars((string)$text,ENT_QUOTES,"UTF-8");}
function redirect($url){$GLOBALS["redirected"]=$url;exit;}
$config=[];
function icon(...$a){return "";}
function studio_roles(){return ["admin"=>"Administrator"];}

');
file_put_contents($tmp.'/app/integrations/NewsDesk.php','<?php function newsdesk_all($path){return [["items"=>[["id"=>"new-feed-id","title"=>"Ny tittel","sourceName"=>"NRK","summary"=>"Kort omtale","url"=>"https://www.nrk.no/vestland/endret-1.12345678"]]]];}');
foreach(['editorial-memory','producer']as$name)file_put_contents($tmp.'/app/'.$name.'.php','<?php');
foreach(['head','sidebar']as$name)file_put_contents($tmp.'/app/views/'.$name.'.php','');
if($render)foreach(['head','sidebar','account']as$name)copy($root.'/app/views/'.$name.'.php',$tmp.'/app/views/'.$name.'.php');
file_put_contents($tmp.'/config/wordpress.php','<?php return ["username"=>"fixture","application_password"=>"fixture"];');
require $tmp.'/app/case-workflow.php';
$item=['id'=>'1234567890abcdef','originId'=>'old-feed-id','title'=>'Opprinnelig tittel','sourceName'=>'NRK',
    'sourceUrl'=>'https://www.nrk.no/vestland/test-1.12345678','sourceAt'=>gmdate('c'),'program'=>'',
    'script'=>'Redigert radiomanus.','notes'=>'','status'=>'draft','revision'=>1,
    'web'=>['title'=>'Redigert nettsak','intro'=>'Kort ingress.','body'=>'Lagret artikkeltekst.']];
if($mode!=='legacy')$item['web']['publication']=studio_news_publication($item);
$item['web']['check']=['source'=>['url'=>$item['sourceUrl'],'text'=>str_repeat('Et kontrollert kildebelegg. ',8),'sha256'=>hash('sha256',str_repeat('Et kontrollert kildebelegg. ',8)),'fetchedAt'=>gmdate('c')],'status'=>'passed','policy'=>STUDIO_NEWS_POLICY,'checkedAt'=>gmdate('c'),'fingerprint'=>studio_news_fingerprint($item,studio_web_text($item['web']))];
if($mode==='published')$item['web']['delivery']=['id'=>123,'state'=>'confirmed','status'=>'publish','hash'=>studio_web_approval_hash($item),'link'=>'https://www.radiorubben.no/test/'];
if($render)$item['audioScripts']=['news-short'=>['profile'=>'news-short','script'=>'Dette melder NRK. Et selvstendig muntlig manus for lokalradio.','check'=>[]]];
$path=$tmp.'/config/sending-board.json';file_put_contents($path,json_encode(['items'=>[$item]]));
$_SESSION=['csrf'=>'fixture'];$_GET=['item'=>$item['id']];$_POST=[];
$_SERVER['REQUEST_METHOD']=in_array($mode,['csrf','observer','open','save','audio-disabled'],true)?'POST':'GET';
if($_SERVER['REQUEST_METHOD']==='POST')$_POST=['id'=>$item['id'],'revision'=>1,'csrf'=>$mode==='csrf'?'bad':'fixture',
    'action'=>$mode==='audio-disabled'?'audio_tts':($mode==='open'?'open_source':'save_web'),'profile'=>'news-short','voice'=>'azrGjm6gYkR15bxb9cVv','source'=>'new-feed-id','news_scope'=>'local']+$item['web'];
ob_start();
register_shutdown_function(static function()use($tmp,$mode,$path,$item,$render):void{
    $html=ob_get_clean();$stored=json_decode(file_get_contents($path),true)['items'];$ok=count($stored)===1;
    if($mode==='save')$ok=$ok&&$stored[0]['web']['publication']['categories']===[8,27]&&$stored[0]['web']['approvedHash']===null&&$stored[0]['web']['check']===[];
    else $ok=$ok&&$stored[0]===$item;
    if($mode==='get'||$mode==='open')$ok=$ok&&str_contains($html,'/assets/radio-rubben-nyheter.png')&&str_contains($html,'form="web-editor"')&&str_contains($html,'Redigert nettsak')&&str_contains($html,'Sluttgodkjenn og publiser');
    if($mode==='audio-disabled')$ok=$ok&&str_contains($html,'Ekte TTS er deaktivert');
    if($mode==='get')$ok=$ok&&str_contains($html,'src="/assets/case.js?v=20261008-audio1"')&&!str_contains($html,'<style>')&&!str_contains($html,'<script>');
    if($mode==='published')$ok=$ok&&str_contains($html,'Publisert på radiorubben.no')&&str_contains($html,'Bilde og kategorier beholdes fra WordPress.')&&!str_contains($html,'<figure class="case-news-image">');
    if($mode==='legacy')$ok=$ok&&str_contains($html,'Lagre nettsaken for å knytte til nyhetsbildet')&&!str_contains($html,'value="approve_publish"');
    if($mode==='guest')$ok=$ok&&($GLOBALS['redirected']??'')==='/login.php';
    if($mode==='csrf'||$mode==='observer')$ok=$ok&&http_response_code()===403;
    if($mode==='open')$ok=$ok&&str_contains($html,'data-auto-prepare="0"');
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);
    if($render){echo $html;return;}
    if(!$ok){fwrite(STDERR,"FAIL case page $mode\n".substr($html,-1000));exit(1);}echo "OK case page $mode\n";
});
require $tmp.'/public/case.php';
