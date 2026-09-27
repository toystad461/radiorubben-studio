<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if ($config['auth_mode'] !== 'entra' || !current_user()) {
    http_response_code(403);
    exit('Ingen tilgang.');
}
require dirname(__DIR__) . '/app/integrations/RobotInbox.php';
$news = robot_inbox_items(dirname(__DIR__) . '/app/../config/robot-news.json');
if (!$news) {
    require dirname(__DIR__) . '/app/integrations/MunicipalityRss.php';
    $news = municipality_rss_cards();
}
require dirname(__DIR__) . '/app/integrations/NrkRss.php';
$items = array_merge(
    $news,
    nrk_rss_cards(),
    robot_inbox_items(dirname(__DIR__) . '/app/../config/robot-inbox.json')
);
$extraStylesheet = '/assets/robot.css';
require dirname(__DIR__) . '/app/views/head.php';
?>
<main id="main" class="robot-inbox">
  <p class="eyebrow">RADIO RUBBEN STUDIO / RR ROBOT</p>
  <h1>Redaksjonell innboks</h1>
  <p class="intro">Kildesaker og utkast som venter på kontroll. Ingen sak publiseres herfra.</p>
  <p><a href="/">← Til oversikten</a></p>
  <?php if (!$items): ?>
    <div class="notice">Ingen kildesaker eller utkast er eksportert til Studio ennå.</div>
  <?php endif; ?>
  <?php foreach ($items as $item): $event = $item['event']; $draft = $item['draft']; ?>
    <article class="robot-card">
      <p class="eyebrow"><?= $event['type'] === 'news.item.discovered' ? 'KILDE TIL VURDERING' : 'UTKAST TIL GJENNOMGANG' ?> · <?= escape($event['type']) ?></p>
      <h2><?= escape($draft['title']) ?></h2>
      <p><?= escape($draft['body']) ?></p>
      <p class="small">Kilde: <?= escape((string) ($event['source']['name'] ?? 'Ukjent')) ?> · <?= $event['type'] === 'news.item.discovered' ? 'Publisert' : 'Sluttstatus observert' ?>: <?= escape((string) ($event['facts']['publishedAt'] ?? $event['facts']['statusObservedAt'] ?? 'Ukjent')) ?> · Verifisering: <?= escape((string) ($event['verificationStatus'] ?? 'Ukjent')) ?></p>
      <p><a href="<?= escape($event['source']['url']) ?>" target="_blank" rel="noopener noreferrer">Åpne originalkilden ↗</a></p>
    </article>
  <?php endforeach; ?>
</main>
</body></html>
