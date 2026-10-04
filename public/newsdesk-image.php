<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!current_user()){http_response_code(401);exit;}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
require_once dirname(__DIR__).'/app/newsroom-view.php';
$key=(string)($_GET['key']??'');$url=$_SESSION['newsroom_images'][$key]??null;
session_write_close();
if(!is_string($url)||!studio_newsroom_image_url($url)){http_response_code(404);exit;}
// The URL comes only from the authenticated WordPress queue, never from the request.
$body='';$curl=curl_init($url);
curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_PROXY=>'',
    CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>10,
    CURLOPT_WRITEFUNCTION=>static function($h,string $part)use(&$body):int{if(strlen($body)+strlen($part)>4000000)return 0;$body.=$part;return strlen($part);}]);
$ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
$info=$ok&&$status===200?@getimagesizefromstring($body):false;
if(!$info||!in_array($info['mime']??'',['image/png','image/jpeg','image/webp'],true)||$info[0]*$info[1]>25000000){http_response_code(502);exit;}
header('Content-Type: '.$info['mime']);header('Cache-Control: private, max-age=300');header('X-Content-Type-Options: nosniff');echo $body;
