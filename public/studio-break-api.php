<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require_once dirname(__DIR__).'/app/studio-break.php';
header('Content-Type: application/json; charset=utf-8');
$user=current_user();if(!$user||!studio_can($user,'produce')){http_response_code(403);echo json_encode(['error'=>'Ingen tilgang til manusgodkjenning.']);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit;}
try{$raw=file_get_contents('php://input',false,null,0,12001);if(strlen($raw)>12000)throw new InvalidArgumentException('Forespørselen er for stor.');$input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($input))throw new InvalidArgumentException('Ugyldig stikk.');
 $item=studio_break_approve($input,$user,$_SESSION['csrf']);echo json_encode(['id'=>$item['id'],'approved'=>true,'url'=>'/case.php?item='.$item['id'].'#audio-material']);
}catch(InvalidArgumentException $e){http_response_code(409);echo json_encode(['error'=>$e->getMessage()]);}catch(Throwable){http_response_code(503);echo json_encode(['error'=>'Kunne ikke lagre manusgodkjenningen. Ingen lyd er startet.']);}
