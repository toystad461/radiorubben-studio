      <aside class="sidebar">
        <a href="/" class="brand">
          <img src="/assets/radio-rubben-logo.png" alt="Radio Rubben" width="2172" height="724">
          <small>STUDIO / ARBEIDSROM</small>
        </a>
        <?php if (($activePage ?? "") === "ai-studio"): ?>
        <span id="mic-live-banner" class="mic-live-banner" role="status" title="Fader 1-status fra MIDI; bekrefter ikke lydruting eller sending">MIC UKJENT</span>
        <?php endif; ?>
        <nav aria-label="Hovedmeny">
<?php
$menuGroups = [
 'Oversikt' => [
  ['overview','/control.php','LayoutDashboard','Kontrollsenter',''],
 ],
 'Redaksjon' => [
  ['newsdesk','/newsdesk.php','FileText','Nyhetsdesk',''],
  ['articles','/robot.php','FileText','Artikkelutkast',''],
  ['sending','/sending.php','FileText','Sendeliste',''],
  ['social','/workspace.php?section=social','Cloud','Sosiale medier',''],
 ],
 'Studio og lyd' => [
  ['ai-studio','/ai-studio.php','AudioLines','AI Studio','board'],
  ['music-plan','/music-plan.php','AudioLines','Musikkplan',''],
  ['music','/ai-studio.php#music-title','AudioLines','Musikkavspilling','music'],
  ['rode','/ai-studio.php#rode','AudioLines','RØDE-kontroll','rode'],
 ],
 'Administrasjon' => [
  ['learning','/learning.php','FileText','Robåt – læring',''],
  ['settings','/ai-studio.php#innstillinger','SlidersHorizontal','Innstillinger','settings'],
  ['users','/users.php','ShieldCheck','Brukere',''],
 ],
];
foreach ($menuGroups as $groupLabel => $groupItems):
 $visibleItems = array_filter($groupItems, static function ($item) use ($user) {
  if (in_array($item[0], ['rode','settings'], true)) return studio_can($user ?? null, 'settings');
  if ($item[0] === 'users') return studio_can($user ?? null, 'users');
  return true;
 });
 if (!$visibleItems) continue;
?>
<div class="nav-label" style="margin:16px 0 6px"><?= escape($groupLabel) ?></div>
<?php foreach ($visibleItems as [$key,$href,$symbol,$label,$target]): ?>
<a href="<?= escape($href) ?>" class="nav-item <?= ($activePage ?? '') === $key ? 'active' : '' ?>" style="padding-top:9px;padding-bottom:9px"<?= ($activePage ?? '') === $key ? ' aria-current="page"' : '' ?><?= $target ? ' data-studio-nav="'.escape($target).'"' : '' ?>><?= icon($symbol,18) ?> <?= escape($label) ?></a>
<?php endforeach; endforeach; ?>
        </nav>
        <div class="sidebar-bottom">
          <span class="station-dot"></span> Et sted for gode historier.
          <p>studio.radiorubben.no</p>
        </div>
      </aside>

