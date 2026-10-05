<?php
declare(strict_types=1);
$mode=$argv[1]??'get';$root=dirname(__DIR__,2);$tmp=sys_get_temp_dir().'/desk-page-'.bin2hex(random_bytes(4));
foreach(['/app/views','/app/integrations','/config','/public']as$dir)mkdir($tmp.$dir,0700,true);
foreach(['board','source-identity','news-script','web-publish','news-publication','case-workflow','newsroom','newsroom-view']as$name)copy($root.'/app/'.$name.'.php',$tmp.'/app/'.$name.'.php');
copy($root.'/app/views/newsroom.php',$tmp.'/app/views/newsroom.php');
copy($root.'/public/newsdesk.php',$tmp.'/public/newsdesk.php');
file_put_contents($tmp.'/app/bootstrap.php','<?php
function current_user(){return $GLOBALS["mode"]==="guest"?null:["name"=>"Editor","role"=>$GLOBALS["mode"]==="observer"?"observer":"admin"];}
function studio_can($u,$p){return $u["role"]==="admin";}
function escape($v){return htmlspecialchars((string)$v,ENT_QUOTES,"UTF-8");}
function redirect($url){$GLOBALS["redirected"]=$url;exit;}
$config=[];');
file_put_contents($tmp.'/app/newsroom-wordpress.php','<?php function studio_newsroom_wp(...$args){$GLOBALS["wpReads"]++;return ["items"=>[],"version"=>"newsroom-1"];}');
file_put_contents($tmp.'/app/integrations/NewsDesk.php','<?php function newsdesk_all($path){return [];} function newsdesk_sections($feeds){return [];}');
file_put_contents($tmp.'/app/producer.php','<?php');
foreach(['head','sidebar','account']as$name)file_put_contents($tmp.'/app/views/'.$name.'.php','');
$item=['id'=>'1234567890abcdef','originId'=>'fixture','title'=>'<script>bad()</script>','sourceName'=>'NRK','sourceUrl'=>'https://www.nrk.no/vestland/test-1.12345678','sourceAt'=>gmdate('c'),'program'=>'','script'=>'','notes'=>'','status'=>'draft','revision'=>1];
$path=$tmp.'/config/sending-board.json';file_put_contents($path,json_encode(['items'=>[$item]]));$before=file_get_contents($path);$wpReads=0;
$_SESSION=['csrf'=>'fixture'];$_GET=$mode==='fragment'?['view'=>'fragment']:[];$_POST=[];$_SERVER['REQUEST_METHOD']=$mode==='method'?'DELETE':(in_array($mode,['observer','csrf','forged'],true)?'POST':'GET');
if($_SERVER['REQUEST_METHOD']==='POST')$_POST=['csrf'=>$mode==='csrf'?'bad':'fixture','item'=>'studio:'.$item['id'],'revision'=>1,'action'=>$mode==='forged'?'approve':'prepare','confirmed'=>'1'];
ob_start();register_shutdown_function(static function()use($tmp,$mode,$path,$before):void{
    $html=ob_get_clean();if($mode==='fragment'){$result=json_decode($html,true,512,JSON_THROW_ON_ERROR);if(!$result['ok']||!isset($result['url']))throw new RuntimeException('Bad fragment');$html=$result['html'];}$ok=file_get_contents($path)===$before;
    if(in_array($mode,['get','forged','fragment'],true))$ok=$ok&&str_contains($html,'Trenger avklaring')&&str_contains($html,'RSS-omtalen er ikke nok')&&!str_contains($html,'<script>bad()</script>')&&str_contains($html,'&lt;script&gt;bad()&lt;/script&gt;')&&!str_contains($html,'Godkjenn og publiser</button>');
    elseif($mode==='guest')$ok=$ok&&($GLOBALS['redirected']??'')==='/login.php';
    else $ok=$ok&&http_response_code()===($mode==='method'?405:403)&&$GLOBALS['wpReads']===0;
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($files as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);
    if(!$ok){fwrite(STDERR,"FAIL newsroom page $mode\n".substr($html,-1000));exit(1);}echo "OK newsroom page $mode\n";
});
require $tmp.'/public/newsdesk.php';
