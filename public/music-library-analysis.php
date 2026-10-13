<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/music-library-analysis.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$user=current_user();
if(!$user){http_response_code(401);echo '{"error":"Logg inn på nytt."}';exit;}
if(!music_library_can_upload($user)){http_response_code(403);echo '{"error":"Rollen kan ikke lagre analyse."}';exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');echo '{"error":"Bruk POST."}';exit;}
try{
    if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)throw new InvalidArgumentException('For stor analyse.');
    $raw=file_get_contents('php://input',false,null,0,4097);if($raw===false||strlen($raw)>4096)throw new InvalidArgumentException('For stor analyse.');
    $v=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
    if(!is_array($v)||array_diff(array_keys($v),['id','version','revision','csrf','values'])||!is_string($v['id']??null)||!preg_match('/^[a-f0-9]{32}$/D',$v['id'])||!is_string($v['version']??null)||!preg_match('/^[a-f0-9]{64}$/D',$v['version'])||!is_int($v['revision']??null)||$v['revision']<0||!is_string($v['csrf']??null)||!is_array($v['values']??null))throw new InvalidArgumentException('Ugyldig analyse.');
    $t=music_library_analysis_save($v['id'],$v['version'],$v['revision'],$v['values'],$user,$v['csrf'],$_SESSION['csrf']);
    echo json_encode(['saved'=>true,'revision'=>$t['revision']],JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException|JsonException $e){http_response_code(409);echo json_encode(['error'=>'Analysen kunne ikke lagres. Last siden på nytt og kontroller låten.']);}
catch(Throwable){http_response_code(503);echo '{"error":"Lagringen kunne ikke bekreftes. Kontroller biblioteket før nytt forsøk."}';}
