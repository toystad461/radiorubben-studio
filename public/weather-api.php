<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/weather.php';
header('Content-Type: application/json; charset=utf-8');
if($config['auth_mode']!=='demo' && !current_user()){http_response_code(403);echo json_encode(['error'=>'Innlogging kreves.']);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);header('Allow: GET');exit;}
session_write_close();
try{echo json_encode(studio_weather(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
catch(RuntimeException $e){http_response_code(503);echo json_encode(['error'=>$e->getMessage()]);}
