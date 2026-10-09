<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user=current_user();require_once dirname(__DIR__).'/app/crm-outreach.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(!studio_crm_allowed($user)){http_response_code(403);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit;}
try{
    $id=studio_crm_text($_POST,'id',16);$token=studio_crm_text($_POST,'token',32);
    // Release the session so the card and other pages stay usable during TTS.
    session_write_close();
    studio_crm_demo_run($id,$token,$user,$config['rr_audio']??[]);
    echo json_encode(['ok'=>true]);
}catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'Kontroller lagret jobbstatus før nytt forsøk.']);}
