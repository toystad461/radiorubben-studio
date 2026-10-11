<?php
declare(strict_types=1);
// Real controller, RSS cache, storage and views. Only authentication is a synthetic fixture.
$mode=$argv[1]??'get';$root=dirname(__DIR__);$tmp=sys_get_temp_dir().'/rr-input-page-'.bin2hex(random_bytes(5));
mkdir($tmp.'/config',0700,true);mkdir($tmp.'/public',0700,true);mkdir($tmp.'/app',0700,true);
$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app',FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
foreach($files as $file){$relative=substr($file->getPathname(),strlen($root.'/app')+1);$dest=$tmp.'/app/'.$relative;$file->isDir()?mkdir($dest,0700,true):copy($file->getPathname(),$dest);}
copy($root.'/public/news-input.php',$tmp.'/public/news-input.php');
file_put_contents($tmp.'/app/bootstrap.php','<?php require __DIR__."/helpers.php";require_once __DIR__."/users.php";function current_user(){return $GLOBALS["mode"]==="guest"?null:["id"=>"test-only","name"=>"Test","role"=>$GLOBALS["mode"]==="observer"?"observer":"producer"];} $config=[];');
require $tmp.'/app/news-input.php';
$at=gmdate('c');$source=['id'=>hash('sha256','synthetic-source'),'source'=>'nrk','sourceName'=>'NRK Vestland','title'=>'Syntetisk <script>alert(1)</script>','rssTitle'=>'Syntetisk <script>alert(1)</script>','summary'=>'Testutdrag','rssText'=>'Dette er en syntetisk RSS-sak, uten ekte publisering.','url'=>'https://www.nrk.no/vestland/test-1.12345678','publishedAt'=>$at,'fetchedAt'=>$at];
foreach(newsdesk_sources() as $id=>$spec)file_put_contents($tmp.'/config/newsdesk-'.$id.'.json',json_encode(['checkedAt'=>time(),'fetchedAt'=>$at,'items'=>$id==='bomlo'?[]:[$source],'failed'=>false]));
$board=['items'=>[]];$path=studio_input_path();file_put_contents($path,json_encode($board));$before=file_get_contents($path);
$rundown=$tmp.'/config/sending-board.json';file_put_contents($rundown,json_encode(['items'=>[['id'=>'synthetic','revision'=>9,'script'=>'Manuelt testmanus']]]));$rundownBefore=file_get_contents($rundown);
$_SESSION=['csrf'=>'test-token'];$_GET=['source'=>$source['id']];$_POST=[];
$_SERVER['REQUEST_METHOD']=in_array($mode,['save','observer','csrf','source-changed','conflict','forged','invalid'],true)?'POST':($mode==='method'?'DELETE':'GET');
if($_SERVER['REQUEST_METHOD']==='POST')$_POST=['csrf'=>'test-token','action'=>'review','source'=>$source['id'],'sourceVersion'=>studio_input_version($source),'reviewRevision'=>'0','channel'=>'web','reason'=>'Syntetisk vurdering av nytte for vårt lokale publikum.'];
if($mode==='csrf')$_POST['csrf']='wrong';
if($mode==='source-changed')$_POST['sourceVersion']=str_repeat('0',64);
if($mode==='conflict')$_POST['reviewRevision']='3';
if($mode==='forged')$_POST['action']='publish';
if($mode==='invalid'){$_POST['reason']='Dette er <b>mitt utkast</b> som skal beholdes.';$_POST['channel']='radio';}
ob_start();register_shutdown_function(static function()use($mode,$tmp,$path,$before,$rundown,$rundownBefore):void {
    $html=(string)ob_get_clean();$after=studio_board_read($path);$code=http_response_code();
    $ok=match($mode){
        'guest'=>$code===303,
        'observer','csrf'=>$code===403,
        'method'=>$code===405,
        'save'=>$code===303&&count($after['rssIntakeReviews']??[])===1&&$after['items']===[],
        'source-changed','conflict','forged'=>$code===409,
        'invalid'=>$code===409&&str_contains($html,'Dette er &lt;b&gt;mitt utkast&lt;/b&gt; som skal beholdes.')&&str_contains($html,'value="radio" checked')&&!str_contains($html,'<b>mitt utkast</b>'),
        default=>str_contains($html,'Nyhetsinngang')&&str_contains($html,'&lt;script&gt;')&&!str_contains($html,'<script>alert(1)</script>'),
    };
    if($mode!=='save')$ok=$ok&&file_get_contents($path)===$before;
    $ok=$ok&&file_get_contents($rundown)===$rundownBefore;
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file)$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());rmdir($tmp);
    if(!$ok){fwrite(STDERR,"FAIL route $mode code $code\n".substr($html,0,300));exit(1);}echo "PASS route: $mode\n";
});
require $tmp.'/public/news-input.php';
