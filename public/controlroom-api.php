<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/controlroom.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$reply=static function(int $code,array $body):never{http_response_code($code);echo json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);exit;};
$user=current_user();
if(!$user || !isset(studio_roles()[$user['role']??'']))$reply(401,['error'=>'Logg inn i Studio.']);
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){header('Allow: GET');$reply(405,['error'=>'Kun lesing.']);}
session_write_close();
try{$view=studio_controlroom_read(music_playout_config());if(!studio_can($user,'produce'))unset($view['streamUrl']);$reply(200,$view);}
catch(Throwable){$reply(503,['error'=>'Serverstatus kan ikke bekreftes.']);}
