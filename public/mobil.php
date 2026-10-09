<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (studio_private_app_mode($config) !== 'on') { http_response_code(503); exit('Den private mobilappen er ikke aktivert.'); }
$user = current_user();
if (!$user) redirect('/login.php');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) { http_response_code(405); header('Allow: GET, HEAD'); exit; }
require_once dirname(__DIR__) . '/app/board.php';
$items = []; $boardUnavailable = false;
try {
    $items = studio_board_active(studio_board_read());
} catch (Throwable $e) {
    $boardUnavailable = true;
    error_log('Studio mobile: worklist unavailable.');
}
require dirname(__DIR__) . '/app/views/head.php';
?>
<main id="main" class="rr-mobile-home">
  <header class="rr-mobile-brand"><img src="/assets/radio-rubben-logo.png" alt="Radio Rubben" width="2172" height="724"><span class="rr-private-badge"><?= icon('ShieldCheck', 15) ?> Privat</span></header>
  <section class="rr-mobile-intro" aria-labelledby="rr-mobile-title"><p class="rr-eyebrow">DITT ARBEIDSROM</p><h1 id="rr-mobile-title">Studio på mobilen.</h1><p>Hei, <?= escape((string)$user['name']) ?>. Sakene og radiomanusene dine er samlet her.</p></section>
  <section class="rr-mobile-priority" aria-labelledby="rr-priority-title"><div><p class="rr-eyebrow">REDAKSJON</p><h2 id="rr-priority-title"><?= $boardUnavailable ? 'Arbeidslisten er utilgjengelig' : count($items) . ' aktive saker' ?></h2><p><?= $boardUnavailable ? 'Åpne kontrollsenteret for å prøve igjen.' : 'Åpne en sak, les kildene og fortsett behandlingen.' ?></p></div><a class="rr-primary-action" href="/control.php">Åpne sakene <?= icon('ArrowRight', 20) ?></a></section>
  <?php if ($items): ?><section class="rr-mobile-section" aria-labelledby="rr-current-title"><div class="rr-section-heading"><h2 id="rr-current-title">I arbeidslisten</h2><a href="/control.php">Se alle</a></div><ul class="rr-mobile-cases">
  <?php foreach (array_slice($items, 0, 4) as $item): ?>
  <li><a href="/case.php?item=<?= rawurlencode((string)$item['id']) ?>"><span><strong><?= escape((string)$item['title']) ?></strong><small><?= escape((string)($item['sourceName'] ?? '')) ?> · Åpne for kontroll og status</small></span><?= icon('ArrowUpRight', 20) ?></a></li>
  <?php endforeach; ?></ul><p class="rr-help">Viser arbeidslisten ved åpning. Antallet er ikke en godkjennings- eller publiseringsstatus.</p></section><?php endif; ?>
  <section class="rr-mobile-section" id="verktoy" aria-labelledby="rr-tools-title"><h2 id="rr-tools-title">Rett til verktøyene</h2><div class="rr-mobile-grid">
    <a href="/sending.php"><?= icon('FileText', 24) ?><strong>Radioliste</strong><span>Manus og rekkefølge</span></a>
    <a href="/newsdesk.php"><?= icon('LayoutDashboard', 24) ?><strong>Nyhetsdesk</strong><span>Kilder og nye saker</span></a>
    <a href="/ai-studio.php"><?= icon('AudioLines', 24) ?><strong>Studio</strong><span>Åpne produksjonsflaten</span></a>
    <a href="/learning.php"><?= icon('ShieldCheck', 24) ?><strong>Læring</strong><span>Regler og rettelser</span></a>
  </div></section>
  <section class="rr-file-note" aria-labelledby="rr-files-title"><?= icon('Cloud', 24) ?><div><h2 id="rr-files-title">OneDrive er filarkivet</h2><p>Ingen filer flyttes. Direkte mappekobling og opplasting fra appen er ikke koblet til ennå.</p></div></section>
  <details class="rr-install-help"><summary>Legg Radio Rubben på hjemskjermen</summary><p>Åpne denne siden i Safari. Velg Del, deretter Legg til på Hjem-skjerm. Slå på Åpne som nettapp dersom valget vises, og trykk Legg til.</p><p>Appen krever internett og innlogging. Pushvarsler, frakoblet arbeid og lydopptak er ikke med i første versjon.</p></details>
  <footer class="rr-mobile-footer"><p>Lokal · Inkluderende · Verdig · Engasjerende</p><form action="/logout.php" method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><button type="submit">Logg ut av Studio</button></form></footer>
</main>
</body></html>
