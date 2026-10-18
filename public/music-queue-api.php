<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/music-play-queue.php';
header('Content-Type: application/json; charset=utf-8');
$reply=static function(int $status,array $data):never { http_response_code($status); echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE); exit; };
$user=current_user();
if (!$user) $reply(401,['error'=>'Logg inn i Studio.']);
$method=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); $reply(405,['error'=>'Metoden støttes ikke.']); }
try {
    if ($method==='POST') {
        if (!studio_can($user,'produce')) $reply(403,['error'=>'Rollen kan ikke endre musikkøen.']);
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??''; $expected=$_SESSION['csrf']??'';
        if (!is_string($csrf) || !is_string($expected) || $expected==='' || !hash_equals($expected,$csrf)) $reply(403,['error'=>'Last siden på nytt før du endrer køen.']);
        $raw=file_get_contents('php://input',false,null,0,8193);
        if ($raw===false || strlen($raw)>8192) $reply(413,['error'=>'Forespørselen er for stor.']);
        $input=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
        if (!is_array($input) || array_is_list($input)) $reply(400,['error'=>'Ugyldig køhandling.']);
        music_play_queue_change($input,$user,$csrf,$expected);
    }
    $reply(200,music_play_queue_snapshot(music_play_queue_read(),music_library_read()));
} catch (DomainException $e) { $reply(409,['error'=>$e->getMessage()]); }
catch (JsonException|InvalidArgumentException $e) { $reply(400,['error'=>$e instanceof JsonException?'Ugyldige data.':$e->getMessage()]); }
catch (Throwable) { $reply(503,['error'=>'Køen eller biblioteket kunne ikke bekreftes. Oppdater før nytt forsøk.']); }
