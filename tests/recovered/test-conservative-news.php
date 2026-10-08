<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/case-workflow.php';
function conservative_expect(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
}
$config = ['openai_api_key'=>'mock-only', 'openai_model'=>'mock'];
$item = ['title'=>'Trafikkuhell', 'originId'=>'fixture', 'sourceUrl'=>'https://www.nrk.no/vestland/test-1.12345'];
$raw = 'Ein bil har køyrt inn i ein fjellvegg. Føraren har smertar i rygg og bein og blir frakta til legevakt, melder politiet. Vegen var stengd, men skal no vere open igjen.';
$source = ['url'=>$item['sourceUrl'], 'text'=>$raw, 'sha256'=>hash('sha256', $raw), 'fetchedAt'=>gmdate('c'), 'kind'=>'original_article'];
$corrected = 'Føreren har smerter i rygg og bein og blir fraktet til legevakt, melder politiet.';
$unsupported = 'Føreren er alvorlig skadet og politiet følger opp saken.';
foreach (['radio', 'web'] as $channel) {
    foreach ([$corrected=>'supported', $unsupported=>'unsupported'] as $draft=>$verdict) {
        $calls = 0;
        $request = static function ($c, $payload) use (&$calls, $source, $channel, $draft, $verdict): string {
            $calls++;
            $input = json_decode($payload['input'], true, 64, JSON_THROW_ON_ERROR);
            conservative_expect($input['source'] === $source, 'original source snapshot remains untouched');
            if (!isset($input['segments'])) {
                conservative_expect(str_contains($payload['instructions'], 'aldri et minstemål'), 'generation has a ceiling without a minimum');
                conservative_expect(!str_contains($payload['instructions'], '120–200') && !str_contains($payload['instructions'], '20–40'), 'old length pressure removed');
                conservative_expect(str_contains($payload['instructions'], 'smerter er ikke alvorlig skade'), 'generation preserves severity and certainty');
                return $channel === 'radio' ? $draft : json_encode(['title'=>'Trafikkuhell', 'intro'=>'En bil har kjørt inn i en fjellvegg.', 'body'=>$draft], JSON_UNESCAPED_UNICODE);
            }
            $rows = [];
            foreach ($input['segments'] as $segment) $rows[] = [
                'index'=>$segment['index'],
                'verdict'=>$segment['text'] === $draft ? $verdict : 'supported',
                'evidence_indexes'=>$segment['text'] === $draft && $verdict === 'unsupported' ? [] : [0],
                'reason'=>$verdict === 'unsupported' ? 'Skadegrad og oppfølging har ikke dekning.' : 'Trofast bokmålsform med samme fakta.',
            ];
            return json_encode(['segments'=>$rows, 'issues'=>[]], JSON_UNESCAPED_UNICODE);
        };
        $result = $channel === 'radio'
            ? studio_news_prepare($item, $config, [], null, $request, null, $source)
            : studio_web_prepare($item, $config, [], $request, null, $source);
        conservative_expect($calls === 2, 'one generation and one independent review, no automatic paid retries');
        conservative_expect($result['check']['status'] === ($verdict === 'supported' ? 'passed' : 'needs_review'), 'safe spelling accepted; added severity and follow-up remain blocked');
        conservative_expect($result['check']['source'] === $source, 'review retains exact original evidence');
        conservative_expect(!isset($result['approvedHash']), 'generation never approves publication');
    }
}
$short = studio_news_generation_rules(['text'=>str_repeat('ord ', 20)], 'web');
$long = studio_news_generation_rules(['text'=>str_repeat('ord ', 300)], 'web');
conservative_expect(str_contains($short, 'Maks 20 ord') && str_contains($long, 'Maks 120 ord'), 'web ceiling scales down with source length');
conservative_expect(str_contains(studio_news_generation_rules(['text'=>str_repeat('ord ', 300)], 'radio'), 'Maks 75 ord'), 'radio ceiling remains short');
echo "Conservative news generation checks passed (mock responses; no live model claim).\n";
