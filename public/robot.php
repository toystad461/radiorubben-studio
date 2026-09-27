<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if ($config['auth_mode'] !== 'entra' || !current_user()) {
    http_response_code(403);
    exit('Ingen tilgang.');
}
require dirname(__DIR__) . '/app/integrations/RobotInbox.php';
$items = robot_inbox_items(dirname(__DIR__) . '/config/robot-inbox.json');
$extraStylesheet = '/assets/robot.css';
require dirname(__DIR__) . '/app/views/head.php';
?>
<main id="main" class="robot-inbox">
  <p class="eyebrow">RADIO RUBBEN STUDIO / RR ROBOT</p>
  <h1>Redaksjonell innboks</h1>
  <p class="intro">Kampdata og utkast som venter på kontroll. Ingen sak publiseres herfra.</p>
  <p><a href="/">← Til oversikten</a></p>
  <?php if (!$items): ?>
    <div class="notice">Ingen kampforslag er eksportert til Studio ennå.</div>
  <?php endif; ?>
  <?php foreach ($items as $item): $event = $item['event']; $draft = $item['draft']; ?>
    <article class="robot-card">
      <p class="eyebrow">TIL GJENNOMGANG · <?= escape($event['type']) ?></p>
      <h2><?= escape($draft['title']) ?></h2>
      <p><?= escape($draft['body']) ?></p>
      <p class="small">Kilde: <?= escape((string) ($event['source']['name'] ?? 'Ukjent')) ?> · Observert: <?= escape((string) ($event['facts']['statusObservedAt'] ?? 'Ukjent')) ?> · Verifisering: <?= escape((string) ($event['verificationStatus'] ?? 'Ukjent')) ?></p>
      <p><a href="<?= escape($event['source']['url']) ?>" target="_blank" rel="noopener noreferrer">Kontroller kamp hos Fotball.no ↗</a></p>
    </article>
  <?php endforeach; ?>
</main>
</body></html>
