<?php
(static function (?array $user, string $activePage): void {
// Shared menu; retain existing server-side visibility rules.
$menuGroups = [
 'Oversikt' => [['overview','/control.php','LayoutDashboard','Studiooversikt','']],
 'Redaksjon' => [['newsdesk','/newsdesk.php','FileText','Nyhetsdesk',''],['sending','/sending.php','FileText','Sendeliste','']],
 'Studio og lyd' => [['ai-studio','/ai-studio.php','AudioLines','Studio','board'],['music-plan','/music-plan.php','AudioLines','Musikkplan',''],['music','/ai-studio.php#music-title','AudioLines','Musikkavspilling','music']],
 'Samarbeid' => [['crm','/crm.php','FileText','Samarbeidspartnere','']],
 'Administrasjon' => [['learning','/learning.php','FileText','Robåt – læring',''],['settings','/ai-studio.php#innstillinger','SlidersHorizontal','Innstillinger','settings'],['rode','/ai-studio.php#rode','AudioLines','RØDE-kontroll','rode'],['users','/users.php','ShieldCheck','Brukere','']],
 'Ikke tilkoblet' => [['social','/workspace.php?section=social','Cloud','Sosiale medier','']],
];
?>
<nav class="studio-navigation" aria-label="Hovedmeny">
<?php foreach ($menuGroups as $groupLabel => $groupItems):
 $visibleItems = array_filter($groupItems, static function ($item) use ($user) {
  if (in_array($item[0], ['rode','settings'], true)) return studio_can($user ?? null, 'settings');
  if ($item[0] === 'users') return studio_can($user ?? null, 'users');
  if ($item[0] === 'crm') return ($user['role'] ?? '') === 'admin';
  return true;
 });
 if (!$visibleItems) continue;
?>
<div class="nav-label"><?= escape($groupLabel) ?></div>
<?php foreach ($visibleItems as [$key,$href,$symbol,$label,$target]): ?>
<a href="<?= escape($href) ?>" class="nav-item <?= ($activePage ?? '') === $key ? 'active' : '' ?>"<?= ($activePage ?? '') === $key ? ' aria-current="page"' : '' ?><?= $target ? ' data-studio-nav="'.escape($target).'"' : '' ?>><?= icon($symbol,18) ?> <?= escape($label) ?></a>
<?php endforeach; endforeach; ?>
</nav>
<?php })($user ?? null, $activePage ?? ''); ?>
