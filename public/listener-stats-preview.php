<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}
$extraStylesheet = '/assets/home-preview.css?v=2';
require dirname(__DIR__) . '/app/views/head.php';
?>
<div class="shell">
<?php $activePage = 'listener-stats'; require dirname(__DIR__) . '/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Arbeidsrom / <strong>Lytterstatistikk – test</strong></span><span>Radio Rubben Studio</span><?php require dirname(__DIR__) . '/app/views/account.php'; ?></header>
<main id="main" class="rr-home rr-stats">
<header class="rr-home-header"><div><p class="rr-home-eyebrow">RADIO RUBBEN / INNSIKT</p><h1>Lytterstatistikk</h1><p>Sanntid, historikk og rapportering fra Radio.co.</p></div><span class="rr-home-preview">IKKE TILKOBLET · FORHÅNDSVISNING</span></header>
<div class="rr-home-panel rr-stats-notice" role="status"><strong>Radio.co-statistikk er ikke tilkoblet.</strong><p>Vi viser ingen fiktive lyttertall. API-tilgang, CSV-import og eventuelle rapporteringsrettigheter må kartlegges før integrasjon. Ingen data om enkeltlyttere lagres her.</p><a href="https://studio.radio.co/" target="_blank" rel="noopener noreferrer">Åpne Radio.co ↗</a></div>
<section class="rr-home-stats" aria-label="Lytternøkkeltall">
<?php foreach ([['Lyttere nå','Sanntid'],['Unike lyttere','Siste 24 timer'],['Lyttetimer (TTSL)','Siste 7 dager'],['Topp samtidige','Siste 24 timer']] as [$label,$period]): ?>
<div class="rr-home-stat"><strong>—</strong><span><?= escape($label) ?></span><small><?= escape($period) ?> · ikke tilgjengelig</small></div>
<?php endforeach; ?>
</section>
<div class="rr-home-grid">
<section class="rr-home-panel"><div class="rr-home-panel-head"><h2>Utvikling i lyttertall</h2><span>Historikk</span></div><p class="rr-home-empty">Tidsserie vises når godkjente statistikkdata er tilgjengelige. Flere-dagersperioder i Radio.co bruker UTC-døgn; dette skal merkes tydelig når tall innføres.</p><div class="rr-stats-placeholder" aria-label="Ingen grafdata tilgjengelig">Ingen data</div></section>
<section class="rr-home-panel"><h2>Enheter og områder</h2><p class="rr-home-empty">Fordeling på land, enheter og plattformer kommer når datagrunnlaget er verifisert. Vis kun aggregerte tall, ikke IP-adresser eller posisjoner for enkeltlyttere.</p><hr><h2>Rapporter</h2><p class="rr-home-empty">TTSL, unike lyttere og spillehistorikk. Radio.co oppgir at flere rapporter bare beholdes i to måneder, og nylig avspilte låter i 24 timer. Månedlig eksport foreslås.</p><a href="https://help.radio.co/en/articles/899780-statistics-reports" target="_blank" rel="noopener noreferrer">Les Radio.co-dokumentasjonen ↗</a></section>
</div><p class="rr-home-foot">RR-STUDIO-HOME-001 / lytterstatistikk. Lesebasert designskall uten lagring, lyttersporing eller overføring.</p>
</main></div></div></body></html>