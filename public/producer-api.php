<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/producer.php';
require dirname(__DIR__).'/app/controlroom.php';
$config=producer_transport_config($config);
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
    $playout=music_playout_config();
    if($playout && in_array($input['action'], ['new','next','refresh'], true)){
        $live=studio_controlroom_read($playout);
        $label=static fn($t)=>$t['kind']==='jingle'?'Jingle: '.$t['title']:trim($t['artist'].' – '.$t['title']);
        $input['program']='';$input['nowPlaying']=$live['now']?$label($live['now']):'';
        $input['next']=($live['status']==='playing'&&$live['queueKnown']&&!empty($live['next']))?$label($live['next'][0]):'';
        if($input['action']==='next'&&$input['next']==='')throw new RuntimeException('Ingen fersk, bekreftet neste lyd fra VPS. Oppdater før du lager stikk.');
    }
    $input = producer_action_context($input);
    $text = producer_generate($config, $input);
    if($playout&&in_array($input['action'],['new','next','refresh'],true)){
        $after=studio_controlroom_read($playout);
        if($after['status']!=='playing'||$after['now']!==$live['now']||$after['next']!==$live['next'])throw new RuntimeException('Køen endret seg mens stikket ble skrevet. Lag et nytt forslag.');
    }
    if (isset($input['weatherSource']) && strtotime($input['weatherSource']['expiresAt']) <= time()) throw new RuntimeException('Værgrunnlaget utløp under generering. Lag et nytt værstikk.');
    $review = ($data['autoReview'] ?? false) ? producer_review($config, $input, $text) : null;
    reply(200, ['text'=>$text, 'review'=>$review, 'weatherSource'=>$input['weatherSource'] ?? null]);
}
catch (RuntimeException $e) { reply(502, ['error'=>$e->getMessage()]); }
