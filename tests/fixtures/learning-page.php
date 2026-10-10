<?php
declare(strict_types=1);
// Render the actual page with isolated private data and a fixture identity; no network or writes.
$root=dirname(__DIR__,2);$tmp=sys_get_temp_dir().'/learning-ui-'.bin2hex(random_bytes(5));
foreach(['/app/views','/public','/config'] as $dir)mkdir($tmp.$dir,0700,true);
foreach(['web-publish','news-publication','editorial-memory','board','programs','audio-profiles','stylebook','news-script','news-relevance','source-identity'] as $name)copy($root.'/app/'.$name.'.php',$tmp.'/app/'.$name.'.php');
foreach(['head','sidebar','account'] as $name)copy($root.'/app/views/'.$name.'.php',$tmp.'/app/views/'.$name.'.php');
copy($root.'/public/learning.php',$tmp.'/public/learning.php');
$role=($argv[2]??'')==='observer'?'observer':'admin';
file_put_contents($tmp.'/app/bootstrap.php','<?php function current_user(){return ["name"=>"Fixture","role"=>$GLOBALS["role"]];} function studio_can($u,$p){return $u["role"]==="admin";} function studio_roles(){return ["admin"=>"Administrator","observer"=>"Leser"];} function escape($s){return htmlspecialchars((string)$s,ENT_QUOTES,"UTF-8");} function icon(...$a){return "";} function redirect($url){throw new RuntimeException("Unexpected redirect");}');
$item=['id'=>'1111111111111111','revision'=>3,'program'=>'god-morgen-vestland','status'=>'ready','title'=>'Rettet <eksempel>','verified'=>true,'approvedBy'=>'Fixture','generatedOriginal'=>'Et <script>utkast</script> som skal rettes.','script'=>'Et rettet manus.'];
require $tmp.'/app/board.php';
require $tmp.'/app/web-publish.php';
$webItem=$item;$webItem['id']='2222222222222222';$webItem['status']='draft';$webItem['title']='Rettet nettartikkel';
$webItem['originId']='fixture';$webItem['sourceName']='Bømlo kommune';$webItem['sourceUrl']='https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/mote.123.aspx';
$webItem['sourceAt']=gmdate('c');
$webItem['web']=['title'=>'Rettet nettittel','intro'=>'Kommunen inviterer.','body'=>'Møtet er åpent.',
'generatedOriginal'=>'Et tidligere nettutkast.','approvedBy'=>'Fixture','publication'=>studio_news_publication($webItem)];
$sourceText=str_repeat('Kommunen inviterer til et åpent møte. ',5);
$webItem['web']['check']=['status'=>'passed','policy'=>STUDIO_NEWS_POLICY,'checkedAt'=>gmdate('c'),
'fingerprint'=>studio_news_fingerprint($webItem,studio_web_text($webItem['web'])),
'source'=>['url'=>$webItem['sourceUrl'],'text'=>$sourceText,'sha256'=>hash('sha256',$sourceText),'fetchedAt'=>gmdate('c')]];
$webItem['web']['approvedHash']=studio_web_approval_hash($webItem);
$evidence=['title'=>$item['title'],'before'=>$item['generatedOriginal'],'after'=>$item['script']];
$rules=[];
foreach(['pending'=>'Bevar forbeholdene i kilden.','approved'=>'Del lange setninger i korte setninger.','disabled'=>'En gammel regel.'] as $status=>$text)$rules[]=['id'=>$status,'program'=>'god-morgen-vestland','status'=>$status,'revision'=>1,'text'=>$text,'createdBy'=>'Fixture','evidence'=>$evidence,'history'=>[['at'=>'2026-10-07T10:00:00Z','actor'=>'Fixture','action'=>'propose']]];
$rules[]=array_replace($rules[0],['id'=>'other-program','program'=>'other-program','text'=>'Skal ikke vises.']);
file_put_contents($tmp.'/config/sending-board.json',json_encode(['items'=>[$item,$webItem],'editorialRules'=>$rules]));
$_SERVER['REQUEST_METHOD']='GET';$_SESSION=['csrf'=>'fixture'];$_GET=['view'=>$argv[1]??'pending','channel'=>$argv[3]??'radio','item'=>($argv[3]??'')==='general'?'':(($argv[3]??'radio')==='web'?$webItem['id']:$item['id'])];$_POST=[];
register_shutdown_function(static function()use($tmp){foreach(['/app/views','/app','/public','/config',''] as $dir){foreach(glob($tmp.$dir.'/*') as $file)if(is_file($file))unlink($file);rmdir($tmp.$dir);}});
require $tmp.'/public/learning.php';
