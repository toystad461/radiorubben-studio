<?php
(static function (?array $user, string $activePage): void {
// Shared menu; retain existing server-side visibility rules.
$menuGroups = [
 'Kontrollrom' => [['ai-studio','/ai-studio.php','AudioLines','Kontrollrom','board'],['overview','/control.php','LayoutDashboard','Redaksjonsoversikt','']],
 'Musikkbibliotek' => [['music-library','/music-library.php','AudioLines','Musikkbibliotek',''],['jingles','/jingles.php','AudioLines','Jingler','']],
 'Musikkø / planlegging' => [['music-studio','/music-studio.php','AudioLines','Serverkø og avspilling',''],['music-queue','/music-queue.php','AudioLines','Musikkø · lokal prøvelytting',''],['music-plan','/music-plan.php','FileText','Musikkplan · redaksjonell','']],
 'Nyhetsdesk' => [['newsdesk','/newsdesk.php','FileText','Nyhetsdesk',''],['sending','/sending.php','FileText','Sendeliste','']],
 'Produksjon / RR Audio' => [['production','/desk.php','FileText','Saksarbeid og RR Audio',''],['script','/ai-studio.php#producer-title','FileText','Manus og stikk',''],['music','/ai-studio.php#music-title','AudioLines','Lokale musikkverktøy','music']],
 'CRM' => [['crm','/crm.php','FileText','Samarbeidspartnere','']],
 'System / administrasjon' => [['learning','/learning.php','FileText','Robåt – læring',''],['settings','/ai-studio.php#innstillinger','SlidersHorizontal','Innstillinger','settings'],['rode','/ai-studio.php#rode','AudioLines','RØDE-kontroll','rode'],['users','/users.php','ShieldCheck','Brukere','']],
];
?>
<nav class="studio-navigation" aria-label="Hovedmeny">
<?php foreach ($menuGroups as $groupLabel => $groupItems):
 $visibleItems = array_filter($groupItems, static function ($item) use ($user) {
  if (in_array($item[0], ['rode','settings'], true)) return studio_can($user ?? null, 'settings');
  if ($item[0] === 'users') return studio_can($user ?? null, 'users');
  if ($item[0] === 'crm') return ($user['role'] ?? '') === 'admin';
  $route=parse_url($item[1],PHP_URL_PATH);
  return is_string($route) && is_file(dirname(__DIR__,2).'/public'.$route);
 });
 if (!$visibleItems) continue;
?>
<div class="nav-label"><?= escape($groupLabel) ?></div>
<?php foreach ($visibleItems as [$key,$href,$symbol,$label,$target]): ?>
<a href="<?= escape($href) ?>" class="nav-item <?= ($activePage ?? '') === $key ? 'active' : '' ?>"<?= ($activePage ?? '') === $key ? ' aria-current="page"' : '' ?><?= $target ? ' data-studio-nav="'.escape($target).'"' : '' ?>><?= icon($symbol,18) ?> <?= escape($label) ?></a>
<?php endforeach; endforeach; ?>
<div class="nav-label">Planlagt / ikke tilkoblet</div><span class="nav-planned">Sosiale medier · ikke aktiv</span><span class="nav-planned">Tidsstyrt sendeplan · ikke aktiv</span>
</nav>
<?php })($user ?? null, $activePage ?? ''); ?>
