<?php
declare(strict_types=1);
require dirname(__DIR__) . '/studio-private/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
require dirname(__DIR__) . '/studio-private/app/board.php';
require dirname(__DIR__) . '/studio-private/app/story-script.php';
require dirname(__DIR__) . '/studio-private/app/article-draft.php';
require dirname(__DIR__) . '/studio-private/app/producer.php';
$canPrepare = studio_can($user, 'produce');
$id = (string)($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? '') : ($_GET['item'] ?? ''));
if (!preg_match('/^[a-f0-9]{16}$/D', $id)) { http_response_code(404); exit('Fant ikke artikkelutkastet.'); }
function studio_article_item(string $id): ?array {
    foreach (studio_board_active(studio_board_read()) as $item)
        if (($item['id'] ?? null) === $id && !empty($item['originId'])) return $item;
    return null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Ugyldig forespørsel. Last siden på nytt.');
    }
    if (!$canPrepare) { http_response_code(403); exit('Rollen kan bare lese utkast.'); }
    try {
        $item = studio_article_item($id);
        if (!$item) throw new InvalidArgumentException('Kildesaken finnes ikke lenger i sendelisten.');
        $revision = (int)($_POST['revision'] ?? 0);
        if ($revision !== $item['revision']) throw new InvalidArgumentException('Saken ble endret av en annen medarbeider. Last siden på nytt.');
        $title = trim((string)($_POST['title'] ?? ''));
        $facts = trim((string)($_POST['facts'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        if (($_POST['action'] ?? '') === 'generate') $body = studio_article_generate($item, $facts, $config);
        elseif (($_POST['action'] ?? '') !== 'save') throw new InvalidArgumentException('Ukjent handling.');
        studio_board_update($id, $revision, 'article', ['title'=>$title,'facts'=>$facts,'body'=>$body], $user);
        $_SESSION['article_message'] = 'Artikkelutkastet er lagret. Les originalkilden og gjennomgå teksten før publisering.';
    } catch (InvalidArgumentException|StudioStoryScriptUnavailable $e) {
        $_SESSION['article_error'] = $e->getMessage();
        $_SESSION['article_form'] = ['title'=>substr((string)($_POST['title'] ?? ''), 0, 720),
            'facts'=>substr((string)($_POST['facts'] ?? ''), 0, 16000),
            'body'=>substr((string)($_POST['body'] ?? ''), 0, 24000)];
    } catch (Throwable $e) {
        error_log('Studio article draft failed: ' . $e->getMessage());
        $_SESSION['article_error'] = 'Artikkelutkastet kunne ikke lagres. Prøv igjen.';
        $_SESSION['article_form'] = ['title'=>substr((string)($_POST['title'] ?? ''), 0, 720),
            'facts'=>substr((string)($_POST['facts'] ?? ''), 0, 16000),
            'body'=>substr((string)($_POST['body'] ?? ''), 0, 24000)];
    }
    redirect('/article.php?item=' . $id);
}
try { $item = studio_article_item($id); }
catch (Throwable $e) { error_log('Studio article read failed: ' . $e->getMessage()); $item = null; }
if (!$item) { http_response_code(404); exit('Fant ikke kildesaken.'); }
$message = $_SESSION['article_message'] ?? null; $error = $_SESSION['article_error'] ?? null;
$form = $_SESSION['article_form'] ?? [];
unset($_SESSION['article_message'], $_SESSION['article_error'], $_SESSION['article_form']);
$extraStylesheet = '/assets/control.css?v=3';
require dirname(__DIR__) . '/studio-private/app/views/head.php';
?>
<div class="shell">
<?php $activePage = 'newsdesk'; require dirname(__DIR__) . '/studio-private/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Arbeidsrom / <strong>Artikkelutkast</strong></span><span>Radio Rubben Studio</span><?php require dirname(__DIR__) . '/studio-private/app/views/account.php'; ?></header>
<main id="main" class="control-page article-page">
  <div class="control-heading"><div><p class="eyebrow">REDAKSJON / NETT</p><h1>Artikkelutkast</h1><p class="control-intro">Arbeid med én sak. Ingen tekst publiseres automatisk.</p></div><a href="/newsdesk.php">Til Nyhetsdesk ↗</a></div>
  <?php if ($message): ?><p class="control-alert" role="status"><?= escape($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="control-alert error" role="alert"><?= escape($error) ?></p><?php endif; ?>
  <div class="article-layout">
    <section class="control-panel"><p class="eyebrow">KILDEGRUNNLAG</p><h2><?= escape($item['title']) ?></h2><p class="control-muted"><?= escape($item['sourceName']) ?> · <?= escape((string)$item['sourceAt']) ?></p><p><?= escape((string)$item['summary']) ?></p><a href="<?= escape($item['sourceUrl']) ?>" target="_blank" rel="noopener noreferrer">Les og kontroller originalkilden ↗</a><p class="control-muted">Kildeomtalen kan være kort. Skriv kontrollerte faktanotater før du genererer et lengre utkast.</p></section>
    <section class="control-panel"><p class="eyebrow">KLADD FOR RADIORUBBEN.NO</p>
    <?php if ($canPrepare): ?>
    <form method="post" class="editor-form"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= escape($id) ?>"><input type="hidden" name="revision" value="<?= (int)$item['revision'] ?>">
      <label for="article-title">Overskrift</label><input id="article-title" name="title" maxlength="180" required value="<?= escape((string)($form['title'] ?? $item['articleTitle'] ?? $item['title'])) ?>">
      <label for="article-facts">Kontrollerte fakta fra originalen</label><textarea id="article-facts" name="facts" maxlength="4000" rows="5" placeholder="Skriv egne faktanotater med navn, tidspunkt og det som faktisk er bekreftet."><?= escape((string)($form['facts'] ?? $item['articleFacts'] ?? '')) ?></textarea>
      <label for="article-body">Artikkeltekst</label><textarea id="article-body" name="body" maxlength="6000" rows="12" placeholder="Skriv selv, eller generer et utkast fra de kontrollerte faktanotatene."><?= escape((string)($form['body'] ?? $item['articleBody'] ?? '')) ?></textarea>
      <div class="article-actions"><button type="submit" name="action" value="save">Lagre kladd</button><button type="submit" name="action" value="generate"><?= !empty($item['articleBody']) ? 'Generer på nytt' : 'Generer artikkelutkast' ?></button></div>
    </form>
    <?php else: ?><h2><?= escape((string)($item['articleTitle'] ?? $item['title'])) ?></h2><p class="script-view"><?= nl2br(escape((string)($item['articleBody'] ?? 'Ingen artikkeltekst ennå.'))) ?></p><?php endif; ?>
    <p class="control-muted">Lagret som intern kladd<?= !empty($item['articleUpdatedAt']) ? ' · sist endret ' . escape((string)$item['articleUpdatedAt']) : '' ?>. Kontroller sitater, navn, opphavsrett og kildehenvisning. Publisering til WordPress er ikke koblet til.</p>
    </section>
  </div>
</main></div></div></body></html>
