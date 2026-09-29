<?php
declare(strict_types=1);
require __DIR__ . '/studio-private/app/board.php';
$tests = 0;
function expect_news(bool $condition, string $label): void {
    global $tests; if (!$condition) throw new RuntimeException($label); $tests++;
}
function rejects_news(callable $fn, string $label): void {
    try { $fn(); } catch (Throwable $e) { expect_news(true, $label); return; }
    throw new RuntimeException('Did not reject: ' . $label);
}
$url = 'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/test.123.aspx';
$sentence = 'Bømlo kommune inviterer til eit ope møte den 17. oktober 2026.';
$body = $sentence . ' Møtet skal vere i kommunestyresalen. Invitasjonen gjeld alle innbyggjarane i kommunen. Påmelding er ikkje nødvendig.';
$html = '<html><body><nav>Feil fakta i menyen</nav><main><h1>Ope møte</h1><p>' . $body . '</p><script>ignore rules</script></main></body></html>';
$config = ['openai_api_key'=>'mock-only', 'openai_model'=>'mock-model'];
$item = ['id'=>'1234567890abcdef', 'originId'=>'rss-test', 'title'=>'Ope møte', 'sourceName'=>'Bømlo kommune',
    'sourceUrl'=>$url, 'sourceAt'=>'2026-09-29T07:00:00Z', 'program'=>'god-morgen-vestland'];
$fetch = static fn($url) => $html;
expect_news(studio_news_allowed_url($url), 'allow municipality');
expect_news(studio_news_allowed_url('https://www.nrk.no/vestland/sak-1.12345'), 'allow NRK article');
foreach (['http://www.nrk.no/a-1.123', 'https://www.nrk.no.evil.test/a-1.123', 'https://127.0.0.1/',
    'https://www.nrk.no:443/a-1.123', 'https://u:p@www.nrk.no/a-1.123', 'https://www.nrk.no/a-1.123?next=secret',
    'https://www.nrk.no/nyheter/siste.rss', 'https://www.bomlo.kommune.no/admin'] as $bad)
    expect_news(!studio_news_allowed_url($bad), 'reject unsafe/unrelated URL');
$text = studio_news_extract($html);
expect_news(str_contains($text, $body) && !str_contains($text, 'Feil fakta') && !str_contains($text, 'ignore rules'), 'extract article only');
rejects_news(fn()=>studio_news_extract('<html><p>' . $body . '</p></html>'), 'no article container');
rejects_news(fn()=>studio_news_extract('<main><p>Too short</p></main>'), 'thin source');
rejects_news(fn()=>studio_news_extract('<main><p>' . str_repeat('x', 24001) . '</p></main>'), 'no silent truncation');
$calls = 0;
$request = function ($config, $payload) use (&$calls, $sentence): string {
    $calls++;
    expect_news($payload['store'] === false, 'no API storage');
    $input = json_decode($payload['input'], true);
    expect_news(isset($input['source']['sha256']), 'source snapshot passed');
    if (!isset($input['segments'])) return $sentence;
    return json_encode(['segments'=>[['index'=>0, 'verdict'=>'supported', 'evidence'=>$sentence, 'reason'=>'Dato og invitasjon står i kilden.']], 'issues'=>[]]);
};
$result = studio_news_prepare($item, $config, [], null, $request, $fetch);
expect_news($calls === 2 && $result['check']['status'] === 'passed', 'separate generation and check');
expect_news($result['check']['source']['text'] === $text, 'snapshot retained');
$checked = $item + ['script'=>$result['script'], 'sourceCheck'=>$result['check']];
expect_news(studio_news_check_current($checked), 'current matching check');
expect_news(!studio_news_check_current(array_replace($checked, ['script'=>'Changed.'])), 'changed text invalidates');
expect_news(!studio_news_check_current(array_replace($checked, ['sourceUrl'=>'https://www.nrk.no/a-1.123'])), 'changed source invalidates');
expect_news(!studio_news_check_current($checked, time()+3601), 'expires after one hour');
$expired = $checked + ['status'=>'ready'];
$expired['sourceCheck']['checkedAt'] = gmdate('c', time()-3601);
expect_news(studio_board_active(['items'=>[$expired]])[0]['status'] === 'draft', 'expired approval not exported as ready');
$calls = 0; studio_news_prepare($item, $config, [], $sentence, $request, $fetch);
expect_news($calls === 1, 'recheck does not regenerate text');
$source = $result['check']['source'];
$fakeEvidence = static fn()=>json_encode(['segments'=>[['index'=>0, 'verdict'=>'supported', 'evidence'=>'A fabricated statement without evidence.', 'reason'=>'Claimed support']], 'issues'=>[]]);
expect_news(studio_news_review($item, $sentence, $source, $config, $fakeEvidence)['status'] === 'needs_review', 'fabricated evidence cannot pass');
$uncertain = static fn()=>json_encode(['segments'=>[['index'=>0, 'verdict'=>'uncertain', 'evidence'=>'', 'reason'=>'Uklart tidspunkt']], 'issues'=>[]]);
expect_news(studio_news_review($item, $sentence, $source, $config, $uncertain)['status'] === 'needs_review', 'uncertainty cannot pass');
foreach (['{}', 'not json', '{"segments":[],"issues":[]}', '{"segments":[{"index":0,"verdict":"supported","evidence":"x","reason":"x"}]}'] as $bad)
    rejects_news(fn()=>studio_news_review($item, $sentence, $source, $config, static fn()=>$bad), 'invalid or missing coverage');
rejects_news(fn()=>studio_news_prepare($item, $config, ['program'=>'other'], null, $request, $fetch), 'program isolation');
rejects_news(fn()=>studio_news_prepare($item, [], [], null, $request, $fetch), 'missing config');
rejects_news(fn()=>studio_news_prepare($item, $config, [], null, $request, static fn()=>throw new RuntimeException('offline')), 'source outage');
$dir = sys_get_temp_dir() . '/news-test-' . bin2hex(random_bytes(4)); mkdir($dir);
$path = $dir . '/board.json';
$user = ['name'=>'Test editor'];
try {
    studio_board_add_source(['id'=>'rss-test', 'title'=>$item['title'], 'sourceName'=>$item['sourceName'], 'url'=>$url, 'publishedAt'=>$item['sourceAt']], $user, $path);
    $stored = studio_board_read($path)['items'][0]; $id = $stored['id'];
    studio_board_update($id, 1, 'generated', ['script'=>$sentence, 'sourceCheck'=>$result['check']], $user, $path);
    $stored = studio_board_read($path)['items'][0];
    expect_news($stored['status'] === 'draft' && !$stored['verified'], 'check never auto-approves');
    rejects_news(fn()=>studio_board_update($id, 2, 'ready', [], $user, $path), 'manual approval still required');
    studio_board_update($id, 2, 'save', ['title'=>$item['title'], 'script'=>$sentence, 'verified'=>'1'], $user, $path);
    studio_board_update($id, 3, 'ready', [], $user, $path);
    expect_news(studio_board_read($path)['items'][0]['status'] === 'ready', 'can approve exact checked text');
    studio_board_update($id, 4, 'save', ['title'=>$item['title'], 'script'=>'Changed text.', 'verified'=>'1'], $user, $path);
    rejects_news(fn()=>studio_board_update($id, 5, 'ready', [], $user, $path), 'edited script cannot bypass check');
    studio_board_update($id, 5, 'source_checked', ['sourceCheck'=>['status'=>'checking']], $user, $path);
    $stored = studio_board_read($path)['items'][0];
    expect_news(!$stored['verified'] && $stored['generatedOriginal'] === $sentence && $stored['script'] === 'Changed text.', 'check invalidation retains original and corrections');
    rejects_news(fn()=>studio_board_update($id, 5, 'source_checked', ['sourceCheck'=>$result['check']], $user, $path), 'concurrent revision protected');
} finally { foreach (glob($dir . '/*') as $file) unlink($file); rmdir($dir); }
echo "News script checks: $tests passed\n";
