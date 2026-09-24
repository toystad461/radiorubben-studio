<?php
// Display only selected scalar fields. Never embed a raw API response, endpoint URL or API key.
$text = static fn(mixed $value, string $fallback = 'Ukjent'): string => escape(is_string($value) ? $value : $fallback);
$count = $nowPlaying['listeners']['total'] ?? $nowPlaying['listeners']['current'] ?? null;
$song = $nowPlaying['now_playing']['song'] ?? [];
?>
<main id="main" class="login-wrap">
<section class="integration-card" aria-labelledby="azuracast-title">
    <p class="eyebrow">RADIO RUBBEN STUDIO / TEST</p>
    <h1 id="azuracast-title">AzuraCast</h1>
    <p>Lesende testintegrasjon · Ingen sendekontroller</p>
    <p><a href="/">Tilbake til Studio</a> · <a href="/azuracast-test.php">Oppdater visningen</a></p>
    <?php if ($error !== null): ?>
        <p role="alert"><?= escape($error) ?></p>
        <p>Ingen eksempeldata vises når en konfigurert server feiler.</p>
    <?php else: ?>
        <p class="badge"><?= $isMock ? 'EKSEMPELDATA · Ingen server tilkoblet' : 'TEST · Data fra konfigurert AzuraCast-server' ?></p>
        <h2><?= $text($nowPlaying['station']['name'] ?? null) ?></h2>
        <dl>
            <dt>Stream</dt><dd><?= $nowPlaying['is_online'] ? 'Online' : 'Offline' ?></dd>
            <dt>Kilde</dt><dd><?= !$nowPlaying['is_online'] ? 'Ingen aktiv stream' : ($nowPlaying['live']['is_live'] ? 'Live DJ' : 'Ingen live DJ meldt') ?></dd>
            <?php if ($nowPlaying['live']['is_live']): ?>
                <dt>Programleder</dt><dd><?= $text($nowPlaying['live']['streamer_name'] ?? null) ?></dd>
            <?php endif; ?>
            <dt>Spiller nå</dt><dd><?= $text($song['title'] ?? null, 'Ingen sang oppgitt') ?> · <?= $text($song['artist'] ?? null, '') ?></dd>
            <dt>Lyttere (totalt)</dt><dd><?= is_int($count) && $count >= 0 ? $count : 'Ukjent' ?></dd>
        </dl>
        <h2>Sist spilt</h2>
        <?php if ($nowPlaying['song_history'] === []): ?><p>Ingen historikk tilgjengelig.</p><?php endif; ?>
        <ol>
            <?php foreach (array_slice($nowPlaying['song_history'], 0, 10) as $entry): ?>
                <li><?= $text($entry['song']['title'] ?? null) ?> · <?= $text($entry['song']['artist'] ?? null, '') ?></li>
            <?php endforeach; ?>
        </ol>
        <p class="small">Øyeblikksbilde ved sideåpning. AzuraCast kan mellomlagre data. Spillelister, mediefiler og kø hentes ikke av denne siden.</p>
    <?php endif; ?>
</section>
</main>
</body></html>
