<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/hue.php';
header('Content-Type: application/json; charset=utf-8');
function hue_reply(int $code,array $data): never {http_response_code($code);echo json_encode($data);exit;}
if ($config['auth_mode'] !== 'demo' && !studio_can(current_user(), 'settings')) hue_reply(403,['error'=>'Rollen din gir ikke tilgang til denne funksjonen.']);
if(PHP_SAPI!=='cli-server' || getenv('STUDIO_LOCAL_HUE')!=='1' || !in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)) hue_reply(403,['error'=>'Hue krever lokal Studio-forbindelse.']);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST') hue_reply(405,['error'=>'Bruk POST.']);
$raw=file_get_contents('php://input',false,null,0,2049);$data=json_decode($raw,true);
if(strlen($raw)>2048 || !is_array($data)) hue_reply(400,['error'=>'Ugyldig forespørsel.']);
if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??($data['csrf']??''))) hue_reply(403,['error'=>'Last Studio på nytt.']);
if(!array_key_exists('live',$data) || (!is_bool($data['live']) && $data['live']!==null) || !preg_match('/^[a-f0-9-]{36}$/D',$data['tab']??'')) hue_reply(400,['error'=>'Ugyldig lysstatus.']);
$action=$data['action']??'sync';
if(!in_array($action,['sync','off','resume'],true)) hue_reply(400,['error'=>'Ugyldig lyshandling.']);
$owner=hash('sha256',session_id().$data['tab']); session_write_close();
try {hue_reply(200,hue_sync(hue_config(),$owner,$data['live'],false,null,null,$action));}
catch(RuntimeException $e){hue_reply(503,['error'=>$e->getMessage()]);}
