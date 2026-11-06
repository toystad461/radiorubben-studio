<?php
(static function (?array $user, string $activePage): void {
// Keep existing routes and server-side visibility rules during the WP transition.
$groups = [
 'studio' => ['Studio', false, '', [
  ['ai-studio','/ai-studio.php','AudioLines','Kontrollrom','board'],
  ['music-studio','/music-studio.php','AudioLines','Sendekø',''],
  ['script','/ai-studio.php#producer-title','FileText','Lydproduksjon og stikk','scripts'],
 ]],
 'library' => ['Lydbibliotek', true, '', [
  ['music-library','/music-library.php','AudioLines','Musikk',''],
  ['jingles','/jingles.php','AudioLines','Jingler',''],
 ]],
 'preparation' => ['Forberedelse', true, '', [
  ['music-queue','/music-queue.php','AudioLines','Lokal prøvelytting',''],
  ['music-plan','/music-plan.php','FileText','Musikkplan',''],
  ['music','/ai-studio.php#music-title','AudioLines','Musikkverktøy','music'],
 ]],
 'editorial' => ['Redaksjon', true, 'Eksisterende redaksjonsverktøy i Studio. WordPress-tilkoblingen på VPS er ikke klar.', [
  ['overview','/control.php','LayoutDashboard','Redaksjonsoversikt',''],
  ['newsdesk','/newsdesk.php','FileText','Nyhetsdesk',''],
  ['sending','/sending.php','FileText','Redaksjonell kjøreplan',''],
  ['production','/desk.php','FileText','Saksarbeid og RR Audio',''],
 ]],
 'robot' => ['Robåt', true, 'Eksisterende regler og læring. Teknisk jobboversikt er ikke tilkoblet.', [
  ['learning','/learning.php','FileText','Regler og læring',''],
 ]],
 'cooperation' => ['Samarbeid', true, '', [
  ['crm','/crm.php','FileText','Samarbeidspartnere',''],
 ]],
 'settings' => ['Innstillinger', true, '', [
  ['settings','/ai-studio.php#innstillinger','SlidersHorizontal','Studioinnstillinger','settings'],
  ['rode','/ai-studio.php#rode','AudioLines','RØDE-kontroll','rode'],
  ['users','/users.php','ShieldCheck','Brukere',''],
 ]],
];
$aliases = ['case'=>'production', 'desk'=>'production', 'news-input'=>'newsdesk',
 'learning-rules'=>'learning', 'crm-register'=>'crm', 'crm-outreach'=>'crm', 'crm-demo'=>'crm'];
$selected = $aliases[$activePage] ?? $activePage;
$visible = static function (array $item) use ($user): bool {
 if (in_array($item[0], ['rode','settings'], true) && !studio_can($user, 'settings')) return false;
 if ($item[0] === 'users' && !studio_can($user, 'users')) return false;
 if ($item[0] === 'crm' && ($user['role'] ?? '') !== 'admin') return false;
 $route = parse_url($item[1], PHP_URL_PATH);
 return is_string($route) && is_file(dirname(__DIR__,2).'/public'.$route);
};
?>
<nav class="studio-navigation" aria-label="Hovedmeny">
<?php foreach ($groups as $id => [$label, $folded, $note, $items]):
 $items = array_values(array_filter($items, $visible));
 if (!$items) continue;
 $containsActive = in_array($selected, array_column($items, 0), true);
 if ($id === 'editorial'): ?><div class="nav-label">Arbeidsrom</div><?php endif;
 if ($folded): ?>
<details class="nav-group" data-nav-group="<?= escape($id) ?>"<?= $containsActive ? ' open' : '' ?>>
 <summary class="nav-group-title"><?= escape($label) ?></summary>
 <div class="nav-group-content">
 <?php if ($note !== ''): ?><p class="nav-group-note"><?= escape($note) ?></p><?php endif; ?>
<?php else: ?><div class="nav-label"><?= escape($label) ?></div><?php endif; ?>
<?php foreach ($items as [$key,$href,$symbol,$text,$target]): ?>
 <a href="<?= escape($href) ?>" class="nav-item<?= $selected === $key ? ' active' : '' ?>"<?= $selected === $key ? ' aria-current="page"' : '' ?><?= $target ? ' data-studio-nav="'.escape($target).'"' : '' ?>><?= icon($symbol,18) ?> <?= escape($text) ?></a>
<?php endforeach;
 if ($folded): ?></div></details><?php endif;
 endforeach; ?>
</nav>
<?php })($user ?? null, $activePage ?? ''); ?>
