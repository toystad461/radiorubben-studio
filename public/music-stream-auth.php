<?php
declare(strict_types=1);
// Intended for an internal Nginx auth_request. It never proxies audio in PHP
// and therefore does not loosen the Studio pool's network/exec restrictions.
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/music-playout.php';
header('Cache-Control: no-store');
if (!in_array($_SERVER['REQUEST_METHOD']??'', ['GET','HEAD'], true)) { http_response_code(405); exit; }
$user=current_user();
if (!$user) { http_response_code(401); exit; }
if (!studio_can($user,'produce')) { http_response_code(403); exit; }
session_write_close();
try {
    $playout=music_playout_config();
    if (!$playout || ($playout['stream_path']??null)!=='/music-test-stream/') { http_response_code(404); exit; }
    http_response_code(204);
} catch (Throwable) { http_response_code(503); }
