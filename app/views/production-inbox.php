<?php
declare(strict_types=1);
?>
<section id="produksjon" class="section" aria-labelledby="production-title">
  <p class="eyebrow">REDAKSJONELL INNBOKS</p>
  <h2 id="production-title">Saker til produksjon</h2>
  <p>Kildesaker og utkast fra Robåten. Webartikkel, språkvask, kvalitetskontroll, faktakontroll og Studio-stikk er ennå ikke koblet til denne flaten. Webpublisering skal kreve Thomas sin manuelle godkjenning; godkjenning er ennå ikke tilgjengelig.</p>
  <?php if (!$productionItems): ?>
    <div class="notice">Ingen gyldige kildesaker eller utkast er tilgjengelige i Robåtens eksport. Siden henter ikke RSS direkte.</div>
  <?php endif; ?>
  <?php foreach ($productionItems as $item): $event = $item['event']; $draft = $item['draft']; ?>
    <article class="robot-card" data-event-id="<?= escape($event['id']) ?>">
      <p class="eyebrow"><?= $event['type'] === 'news.item.discovered' ? 'KILDE TIL VURDERING' : 'UTKAST TIL GJENNOMGANG' ?></p>
      <h3><?= escape($draft['title']) ?></h3>
      <p><?= escape($draft['body']) ?></p>
      <p class="small">Sak-ID: <?= escape($event['id']) ?></p>
      <p class="small">Kilde: <?= escape((string) ($event['source']['name'] ?? 'Ukjent')) ?> · Kildestatus: <?= escape((string) ($event['verificationStatus'] ?? 'Ukjent')) ?></p>
      <p><a href="<?= escape($event['source']['url']) ?>" target="_blank" rel="noopener noreferrer">Åpne originalkilden ↗</a></p>
    </article>
  <?php endforeach; ?>
</section>
