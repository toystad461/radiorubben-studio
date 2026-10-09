<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user=current_user();require_once dirname(__DIR__).'/app/crm-outreach.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
if(!studio_crm_allowed($user)){http_response_code(403);exit;}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
try{
    $id=studio_crm_text($_GET,'id',16);$token=studio_crm_text($_GET,'token',32);$row=null;
    foreach(studio_crm_read()['records']as$r)if($r['id']===$id)$row=$r;
    $demo=$row['proposal']['demo']??[];
    if(!isset($demo['asset'])||!hash_equals($demo['token']??'',$token))throw new RuntimeException('Ikke funnet.');
    $path=rr_audio_asset($demo['asset'],studio_crm_path());
    header('Content-Type: audio/wav');header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="Radio-Rubben-demoforslag.wav"');readfile($path);
}catch(Throwable $e){http_response_code(404);}
