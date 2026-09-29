<?php
declare(strict_types=1);
require __DIR__ . '/studio-private/app/board.php';
require __DIR__ . '/studio-private/app/story-script.php';
require __DIR__ . '/studio-private/app/article-draft.php';
function article_check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
$dir = sys_get_temp_dir() . '/rubben-article-' . bin2hex(random_bytes(5));
mkdir($dir, 0700);
$path = $dir . '/board.json';
$user = ['name'=>'Redaktør'];
$source = ['id'=>'story-1','title'=>'Kommunen inviterer til møte','sourceName'=>'Bømlo kommune',
    'url'=>'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/mote/',
    'publishedAt'=>'2026-09-28T10:00:00Z',
    'summary'=>'Bømlo kommune inviterer pårørande til eit ope møte i kulturhuset torsdag klokka 18. Møtet er gratis.'];
$facts = 'Originalkilden bekrefter at kommunen inviterer pårørende til åpent møte i kulturhuset torsdag klokken 18, og at deltakelse er gratis.';
try {
    studio_board_add_source($source, $user, $path);
    $item = studio_board_read($path)['items'][0];
    $payload = null;
    $body = studio_article_generate($item, $facts, ['openai_api_key'=>'test','openai_model'=>'test'],
        static function (array $config, array $request) use (&$payload): string {
            $payload = $request;
            return 'Bømlo kommune inviterer til åpent møte for pårørende i kulturhuset torsdag klokka 18. Det opplyser kommunen. Deltakelse er gratis.';
        });
    article_check($payload['store'] === false && str_contains($payload['input'], 'verifiedFacts'), 'source notes passed without storage');
    studio_board_update($item['id'], 1, 'article', ['title'=>'Åpent møte for pårørende','facts'=>$facts,'body'=>$body], $user, $path);
    $saved = studio_board_read($path)['items'][0];
    article_check($saved['articleBody'] === $body && $saved['articleReviewed'] === false && $saved['status'] === 'draft', 'saved as unreviewed draft');
    $conflict = false;
    try { studio_board_update($item['id'], 1, 'article', ['title'=>'Gammelt','facts'=>'','body'=>''], $user, $path); }
    catch (InvalidArgumentException $e) { $conflict = true; }
    article_check($conflict, 'concurrent edit rejected');
    $rejected = false;
    try { studio_article_generate($item, 'For kort', ['openai_api_key'=>'test','openai_model'=>'test'], static fn(): string => 'Artikkel'); }
    catch (InvalidArgumentException $e) { $rejected = true; }
    article_check($rejected, 'unverified short facts rejected');
    $rejected = false;
    try { studio_article_generate($item, $facts, []); }
    catch (StudioStoryScriptUnavailable $e) { $rejected = true; }
    article_check($rejected, 'missing AI configuration identified');
    echo "Article draft workflow OK\n";
} finally { @unlink($path); @unlink($path . '.lock'); @rmdir($dir); }
