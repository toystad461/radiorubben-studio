<?php
declare(strict_types=1);
require dirname(__DIR__).'/studio-private/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
require dirname(__DIR__).'/studio-private/app/integrations/WeatherOverview.php';
$now = time(); $rows = [];
foreach (weather_overview_places() as $id=>$place) {
    $result = weather_overview_load($id,dirname(__DIR__).'/studio-private/config',$now);
    $result['place']=$place; $result['slots']=weather_overview_slots($result['forecast'],$now); $rows[$id]=$result;
}
$script = weather_overview_script($rows,$now);
$extraStylesheet='/assets/weather-overview.css?v=1';
require dirname(__DIR__).'/studio-private/app/views/head.php';
?>
<div class="shell">
<?php $activePage='weather'; require dirname(__DIR__).'/studio-private/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Arbeidsrom / <strong>Været</strong></span><?php require dirname(__DIR__).'/studio-private/app/views/account.php'; ?></header>
<main id="main" class="weather-page" data-loaded-at="<?= $now ?>">
<p id="weather-age-warning" role="alert" hidden>Visningen er over 15 minutter gammel. Oppdater før du bruker værstikket.</p>
<div class="weather-heading"><div><p class="eyebrow">RADIO RUBBEN / VESTLANDET</p><h1>Været</h1><p>Fem steder. Ett blikk før du går på lufta.</p></div><time class="weather-clock" id="weather-clock"><?= escape(weather_overview_time($now,'H:i')) ?></time></div>
<div class="weather-toolbar"><a href="/newsdesk.php">← Nyhetsdesken</a><a href="/weather.php">Oppdater visning</a><button type="button" id="weather-fullscreen" hidden>Fullskjerm</button><a href="#weather-script">Lag værstikk</a></div>
<p class="weather-note">Alle tall er prognoser. Temperatur gjelder tidspunktet som vises; symbol og nedbør gjelder de neste 1 eller 6 timene. I dag og i morgen vises kl. 12 norsk tid, ikke døgnmaks.</p>
<div class="weather-table-wrap"><table class="weather-table"><caption class="weather-sr">Værprognoser for Bømlo, Stord, Haugesund, Bergen og Stavanger</caption><thead><tr><th scope="col">Sted</th><th scope="col">Nå<small>Prognose</small></th><th scope="col">I dag<small><?= escape(weather_overview_time($now,'d.m')) ?> kl. 12</small></th><th scope="col">I morgen<small><?= escape((new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Europe/Oslo'))->modify('+1 day')->format('d.m')) ?> kl. 12</small></th></tr></thead><tbody>
<?php foreach ($rows as $id=>$row): ?>
<tr class="weather-row <?= escape($row['status']) ?>"><th scope="row"><strong><?= escape($row['place']['name']) ?></strong><small><?= escape($row['place']['detail']) ?></small><span class="weather-status"><?= match($row['status']) {'updated'=>'Oppdatert','stale'=>'Eldre data – ikke bruk på lufta',default=>'Utilgjengelig'} ?></span></th>
<?php foreach ($row['slots'] as $key=>$point): ?><td>
<?php if ($point): [$symbol,$description]=weather_overview_symbol($point['symbol']); ?>
<div class="weather-value"><span aria-hidden="true"><?= escape($symbol) ?></span><strong><?= escape((string)round($point['temperature'])) ?>°</strong></div><span class="weather-description"><?= escape($description) ?></span><small>Kl. <?= escape(weather_overview_time($point['time'],'H:i')) ?><?= $key==='today' && $point['time']<$now ? ' · passert tidspunkt' : '' ?></small>
<details><summary>Vind og nedbør</summary><p>Vind: <?= $point['wind']===null ? 'mangler' : escape(str_replace('.',',',(string)$point['wind'])).' m/s' ?><br>Nedbør: <?= $point['rain']===null ? 'mangler' : escape(str_replace('.',',',(string)$point['rain'])).' mm neste '.(int)$point['hours'].' t' ?></p></details>
<?php else: ?><span class="weather-missing">—</span><small><?= $key==='today' && weather_overview_time($now,'H')>='12' ? 'Kl. 12 er passert / data mangler' : 'Prognose mangler' ?></small><?php endif; ?>
</td><?php endforeach; ?></tr>
<?php endforeach; ?></tbody></table></div>
<div class="weather-source"><p>Værdata: <a href="https://www.met.no/">Meteorologisk institutt</a> · <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a>. Utvalg, avrunding og visning: Radio Rubben.</p><details><summary>Oppdatering og datagrunnlag</summary><p>Visningen ble lastet <?= escape(weather_overview_time($now)) ?> norsk tid. Oppdater visningen for å sjekke nytt grunnlag. Værstikk og tabell bruker samme datasett.</p><ul><?php foreach($rows as $row): ?><li><?= escape($row['place']['name']) ?>: <?php if($row['forecast']): ?>prognose oppdatert <?= escape(weather_overview_time($row['forecast']['updatedAt'])) ?> · hentet <?= escape(weather_overview_time((int)$row['fetchedAt'])) ?><?php else: ?>ingen tilgjengelige data<?php endif; ?></li><?php endforeach; ?></ul><p>Et stedsvarsel representerer koordinatene til stedet under navnet, ikke hele kommunen. Manglende verdier fylles aldri med eksempeldata.</p></details></div>
<section id="weather-script" class="weather-script"><p class="eyebrow">TIL LIVE-SENDINGEN</p><h2>Kort værstikk</h2><p>Et tekstutkast basert på temperaturprognosene over. Kontroller tid og data før opplesing.</p><label for="weather-script-text">Manus – kan redigeres og kopieres</label><textarea id="weather-script-text" rows="12"><?= escape($script) ?></textarea><button type="button" id="weather-copy" hidden>Kopier værstikk</button><span id="weather-copy-status" role="status"></span><p class="weather-note">Redigering her lagres ikke. Kopier til Sending, eller lagre i OneDrive → Manus &amp; Stikk.</p></section>
</main></div></div><script src="/assets/weather-overview.js?v=1" defer></script></body></html>
