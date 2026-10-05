<?php
declare(strict_types=1);
require dirname(__DIR__) . '/studio-private/app/bootstrap.php';
require dirname(__DIR__) . '/studio-private/app/producer.php';
header('Content-Type: application/json; charset=utf-8');
function reply(int $status, array $data): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
if ($config['auth_mode'] !== 'demo' && !studio_can(current_user(), 'produce')) reply(403, ['error'=>'Rollen din gir ikke tilgang til AI-produksjon.']);
if (!producer_allowed(current_user())) reply(403, ['error'=>'AI krever innlogging eller aktivert lokal utvikling.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); reply(405, ['error'=>'Bruk POST.']); }
if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) reply(403, ['error'=>'Last siden på nytt før du prøver igjen.']);
if (trim($config['openai_api_key'] ?? '') === '') reply(503, ['error'=>'OpenAI-nøkkel mangler. Bruk lokal mal inntil nøkkelen er konfigurert.']);
$raw = file_get_contents('php://input', false, null, 0, 20001);
if (strlen($raw)>20000) reply(413, ['error'=>'For mye tekst.']);
try {
    $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new InvalidArgumentException();
    $input = producer_input($data);
    if (isset($data['autoReview']) && !is_bool($data['autoReview'])) throw new InvalidArgumentException();
} catch (JsonException | InvalidArgumentException $e) { reply(400, ['error'=>'Ugyldig forespørsel eller for mye tekst.']); }
$now = time();
$requests = array_values(array_filter($_SESSION['producer_requests'] ?? [], fn($t)=>$t>$now-60));
if (count($requests)>=6) reply(429, ['error'=>'Vent litt før neste forslag.']);
$requests[]=$now; $_SESSION['producer_requests']=$requests; session_write_close();
try {
    $text = producer_generate($config, $input);
    $review = ($data['autoReview'] ?? false) ? producer_review($config, $input, $text) : null;
    reply(200, ['text'=>$text, 'review'=>$review]);
}
catch (RuntimeException $e) { reply(502, ['error'=>$e->getMessage()]); }
