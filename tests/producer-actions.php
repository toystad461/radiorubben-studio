<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/producer.php';
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
function denied(callable $fn): void { try { $fn(); } catch (RuntimeException $e) { return; } throw new RuntimeException('Expected rejection'); }
$base = ['nowPlaying'=>'CURRENT SONG', 'next'=>'NEXT SONG', 'draft'=>'OLD MUSIC BREAK',
    'notes'=>'SYNTHETIC: closed bridge, source Test Authority, 10:00.', 'program'=>'Test'];
foreach (['local','traffic','sponsor'] as $action) {
    $input = producer_action_context(producer_input($base + ['action'=>$action]));
    $payload = producer_payload(['openai_model'=>'test'], $input);
    foreach (['CURRENT SONG','NEXT SONG','OLD MUSIC BREAK'] as $old) check(!str_contains($payload['input'], $old), 'Unrelated music leaked');
    check(str_contains($payload['input'], 'SYNTHETIC'), 'Missing supplied facts');
    check(str_contains($payload['instructions'], producer_action_instruction($action)), 'Missing action instruction');
    check(count(producer_review_rules(str_repeat('Et ord ', 20), $input)) > 0, 'Editorial action auto-approved');
    denied(fn()=>producer_action_context(producer_input(array_replace($base, ['action'=>$action,'notes'=>' ']))));
}
$source = ['text'=>'SYNTHETIC WEATHER: 7 grader.', 'places'=>[['place'=>'Teststed','temperature'=>7]],
    'source'=>'https://www.met.no/', 'generatedAt'=>gmdate('c'), 'expiresAt'=>gmdate('c',time()+3600), 'status'=>'draft'];
$weather = producer_input($base + ['action'=>'weather']);
$input = producer_action_context($weather, fn()=>$source);
check($input['weatherSource'] === $source, 'Weather provenance lost');
check($input['notes'] === '' && $input['draft'] === '' && $input['next'] === '', 'Old weather or music retained');
check(count(producer_review_rules(str_repeat('Et ord ', 20), $input)) > 0, 'Weather auto-approved');
denied(fn()=>producer_action_context($weather, fn()=>throw new RuntimeException('offline')));
denied(fn()=>producer_action_context($weather, fn()=>array_replace($source,['expiresAt'=>gmdate('c',time()-1)])));
denied(fn()=>producer_action_context($weather, fn()=>[]));
$short = producer_action_context(producer_input($base + ['action'=>'shorter']));
check($short['draft'] === $base['draft'] && $short['next'] === '' && $short['notes'] === '', 'Shorten changes topic');
denied(fn()=>producer_action_context(producer_input(array_replace($base,['action'=>'shorter','draft'=>'']))));
$next = producer_action_context(producer_input($base + ['action'=>'next']));
check($next['next'] === 'NEXT SONG' && $next['draft'] === '', 'Next song lost');
check(producer_review_rules(str_repeat('Et ord ',20),array_replace($next,['next'=>''])) !== [], 'Missing next approved');
try { producer_input($base + ['action'=>'invented']); throw new RuntimeException('Unknown action accepted'); }
catch (InvalidArgumentException $e) {}
echo "PASS action-specific facts, MET provenance, expired/unavailable weather, missing notes, manual review and shorter\n";
