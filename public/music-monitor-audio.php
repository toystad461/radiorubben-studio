<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/music-playout.php';
$user=current_user();if (!$user) {http_response_code(401);exit;}if (!studio_can($user,'produce')) {http_response_code(403);exit;}
if (!in_array($_SERVER['REQUEST_METHOD']??'',['GET','HEAD'],true)) {http_response_code(405);header('Allow: GET, HEAD');exit;}
$name=$_GET['segment']??'';if (!is_string($name) || !preg_match('/^[a-f0-9]{24}-\d{6}\.wav$/D',$name)) {http_response_code(404);exit;}
session_write_close();
try {
    $config=music_playout_config();if (!$config) {http_response_code(404);exit;}
    $path=$config['root'].'/monitor/'.$name;
    if (is_link($path) || !is_file($path) || filesize($path)>192044) {http_response_code(404);exit;}
    header('Cache-Control: no-store');header('Content-Type: audio/wav');header('Content-Length: '.filesize($path));
    if ($_SERVER['REQUEST_METHOD']==='GET') readfile($path);
} catch (Throwable) {http_response_code(503);}
