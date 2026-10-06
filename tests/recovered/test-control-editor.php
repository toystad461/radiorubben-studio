<?php
declare(strict_types=1);
if (empty($argv[1])) {
    foreach (['get','csrf','observer','channel','discard','stale','undo'] as $mode) {
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($mode),$code);
        if ($code) exit($code);
    }
    exit;
}
$mode=$argv[1];$root=dirname(__DIR__,2);$tmp=sys_get_temp_dir().'/control-editor-'.bin2hex(random_bytes(5));
foreach(['/app/views','/app/integrations','/public','/config'] as $dir)mkdir($tmp.$dir,0700,true);
foreach(['board','news-script','source-identity','web-publish','news-publication'] as $name)copy($root.'/app/'.$name.'.php',$tmp.'/app/'.$name.'.php');
copy($root.'/public/control.php',$tmp.'/public/control.php');
file_put_contents($tmp.'/app/bootstrap.php','<?php function current_user(){return ["name"=>"Fixture","role"=>$GLOBALS["mode"]==="observer"?"observer":"admin"];} function studio_can($u,$p){return $u["role"]==="admin";} function escape($s){return htmlspecialchars((string)$s,ENT_QUOTES,"UTF-8");} function redirect($url){$GLOBALS["redirected"]=$url;exit;}');
file_put_contents($tmp.'/app/integrations/NewsDesk.php','<?php function newsdesk_sources(){return ["bomlo"=>[]];} function newsdesk_feed(...$a){return ["status"=>"updated","items"=>[]];} function newsdesk_traffic(...$a){return ["status"=>"updated","items"=>[]];}');
file_put_contents($tmp.'/app/weather.php','<?php function studio_weather(){return null;}');
foreach(['head','sidebar','account'] as $name)file_put_contents($tmp.'/app/views/'.$name.'.php','');
require $tmp.'/app/board.php';
$user=['name'=>'Fixture','role'=>'admin'];studio_board_add_manual('<Test & sak>',$user);
$path=$tmp.'/config/sending-board.json';$board=studio_board_read();$item=$board['items'][0];$id=$item['id'];
$_SESSION=['csrf'=>'fixture'];$_GET=['item'=>$id];$_POST=[];$_SERVER['REQUEST_METHOD']='GET';
if($mode==='undo'){$_SESSION['control_undo']=studio_board_clear(studio_board_snapshot($board),$user);}
$before=file_get_contents($path);
if($mode!=='get'){
    $_SERVER['REQUEST_METHOD']='POST';
    $_POST=['csrf'=>$mode==='csrf'?'bad':'fixture','id'=>$id,'revision'=>1,'channel'=>'web','confirmed'=>'1','snapshot'=>$mode==='stale'?'bad':studio_board_snapshot($board),'action'=>in_array($mode,['discard','stale'],true)?'discard':($mode==='undo'?'undo':'channel')];
}
ob_start();
register_shutdown_function(static function()use($tmp,$mode,$path,$before){
    $html=ob_get_clean();$stored=json_decode(file_get_contents($path),true);$ok=true;
    if($mode==='get')$ok=str_contains($html,'Saker til behandling')&&str_contains($html,'&lt;Test &amp; sak&gt;')&&str_contains($html,'id="case-treatment"')&&str_contains($html,'Forkast sak')&&str_contains($html,'name="snapshot"')&&str_contains($html,'value="web"')&&str_contains($html,'#radio-material')&&str_contains($html,'#web-material');
    elseif(in_array($mode,['csrf','observer'],true))$ok=http_response_code()===403&&file_get_contents($path)===$before;
    elseif($mode==='channel')$ok=$stored['items'][0]['channel']==='web'&&$stored['items'][0]['revision']===2;
    elseif($mode==='discard')$ok=$stored['items'][0]['status']==='archived'&&!empty($_SESSION['control_undo']);
    elseif($mode==='stale')$ok=file_get_contents($path)===$before&&!empty($_SESSION['control_error']);
    elseif($mode==='undo')$ok=$stored['items'][0]['status']==='draft'&&!isset($_SESSION['control_undo']);
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);
    if(!$ok){fwrite(STDERR,"FAIL control editor $mode\n");exit(1);}echo "PASS control editor $mode\n";
});
require $tmp.'/public/control.php';
