<?php
declare(strict_types=1);
require __DIR__ . '/studio-private/app/story-script.php';
require __DIR__ . '/studio-private/app/board.php';
function story_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$dir = sys_get_temp_dir() . '/rubben-script-' . bin2hex(random_bytes(5));
mkdir($dir, 0700);
$path = $dir . '/board.json';
$user = ['name'=>'Redaktør'];
$source = ['id'=>'source-1','title'=>'Kommunen inviterer til møte','sourceName'=>'Bømlo kommune',
    'url'=>'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/mote/',
    'publishedAt'=>'2026-09-28T10:00:00Z',
    'summary'=>'Bømlo kommune inviterer pårørande til eit ope møte i kulturhuset torsdag klokka 18. Møtet er gratis.',
    'fetchedAt'=>'2026-09-28T10:05:00Z'];
try {
    studio_board_add_source($source, $user, $path);
    $item = studio_board_read($path)['items'][0];
    $seen = null;
    $text = studio_story_script_generate($item, ['openai_api_key'=>'test', 'openai_model'=>'test-model'],
        static function (array $config, array $payload) use (&$seen): string {
            $seen = $payload;
            return 'Bømlo kommune inviterer pårørende til et åpent møte torsdag klokka 18. Det melder kommunen.';
        });
    story_check(($seen['store'] ?? true) === false && str_contains($seen['input'], 'Bømlo kommune'), 'source passed without storage');
    story_check(!str_contains($seen['input'], 'test-model'), 'API key excluded from source input');
    studio_board_update($item['id'], 1, 'generated', ['script'=>$text], $user, $path);
    $saved = studio_board_read($path)['items'][0];
    story_check($saved['script'] === $text && $saved['status'] === 'draft' && !$saved['verified'] && !empty($saved['generatedAt']), 'draft saved unverified');
    $rejected = false;
    try { studio_board_update($item['id'], 1, 'generated', ['script'=>'old'], $user, $path); }
    catch (InvalidArgumentException $e) { $rejected = true; }
    story_check($rejected, 'concurrent edit rejected');
    $rejected = false;
    try { studio_board_update($item['id'], 2, 'ready', [], $user, $path); }
    catch (InvalidArgumentException $e) { $rejected = true; }
    story_check($rejected, 'AI draft cannot be marked ready');
    $rejected = false;
    try { studio_story_script_generate(array_replace($item, ['summary'=>'Kort']), ['openai_api_key'=>'test','openai_model'=>'test'], static fn(): string => 'Manus'); }
    catch (InvalidArgumentException $e) { $rejected = true; }
    story_check($rejected, 'thin excerpt rejected');
    $rejected = false;
    try { studio_story_script_generate($item, [], static fn(): string => 'Manus'); }
    catch (StudioStoryScriptUnavailable $e) { $rejected = str_contains($e->getMessage(), 'nøkkel'); }
    story_check($rejected, 'missing AI config identified');
    $rejected = false;
    try { studio_story_script_generate($item, ['openai_api_key'=>'test','openai_model'=>'test'],
        static function (): never { throw new RuntimeException('internal transport details'); }); }
    catch (StudioStoryScriptUnavailable $e) { $rejected = !str_contains($e->getMessage(), 'internal transport details'); }
    story_check($rejected, 'transport error sanitized');
    $rejected = false;
    try { studio_story_script_generate($item, ['openai_api_key'=>'test','openai_model'=>'test'], static fn(): string => 'INSUFFICIENT_SOURCE'); }
    catch (RuntimeException $e) { $rejected = true; }
    story_check($rejected, 'model insufficient response rejected');
    echo "Per-story script workflow OK\n";
} finally { @unlink($path); @unlink($path . '.lock'); @rmdir($dir); }
