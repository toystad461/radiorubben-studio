<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/music-studio.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$reply=static function(int $status,array $data):never {http_response_code($status);echo json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);exit;};
$user=current_user();if (!$user) $reply(401,['error'=>'Logg inn i Studio.']);
$method=$_SERVER['REQUEST_METHOD']??'';if (!in_array($method,['GET','POST'],true)) {header('Allow: GET, POST');$reply(405,['error'=>'Metoden støttes ikke.']);}
try {
    $playout=music_playout_config();
    if ($method==='POST') {
        if (!studio_can($user,'produce')) $reply(403,['error'=>'Rollen kan ikke styre musikkstudio.']);
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';$expected=$_SESSION['csrf']??'';
        if (!is_string($csrf) || !is_string($expected) || $expected==='' || !hash_equals($expected,$csrf)) $reply(403,['error'=>'Last siden på nytt.']);
        $raw=file_get_contents('php://input',false,null,0,32769);if ($raw===false || strlen($raw)>32768) $reply(413,['error'=>'Forespørselen er for stor.']);
        $input=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if (!is_array($input) || array_is_list($input)) $reply(400,['error'=>'Ugyldige data.']);
        session_write_close();$result=music_studio_change($input,$user,$csrf,$expected,null,null,$playout);
        if (isset($result['proposal'])) $reply(200,$result);
    } else session_write_close();
    $reply(200,music_studio_view(null,null,$playout));
} catch (DomainException $e) {$reply(409,['error'=>$e->getMessage()]);}
catch (InvalidArgumentException $e) {$reply(400,['error'=>$e->getMessage()]);}
catch (JsonException) {$reply(400,['error'=>'Ugyldige data.']);}
catch (Throwable) {$reply(503,['error'=>'Kø/spiller kunne ikke bekreftes. Oppdater før ny handling; en endring kan være lagret.']);}
