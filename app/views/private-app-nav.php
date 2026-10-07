<?php
$rrMobilePath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$rrMobileLinks = [
    ['/mobil.php', 'Hjem', 'LayoutDashboard', ['/mobil.php']],
    ['/control.php', 'Saker', 'FileText', ['/control.php', '/case.php', '/newsdesk.php', '/desk.php']],
    ['/sending.php', 'Radio', 'AudioLines', ['/sending.php']],
    ['/mobil.php#verktoy', 'Mer', 'SlidersHorizontal', ['/ai-studio.php', '/learning.php', '/music-plan.php']],
];
?>
<nav class="rr-app-nav" aria-label="Mobilmeny">
<?php foreach ($rrMobileLinks as [$href, $label, $symbol, $paths]): ?>
<a href="<?= escape($href) ?>"<?= in_array($rrMobilePath, $paths, true) ? ' aria-current="page"' : '' ?>><?= icon($symbol, 22) ?><span><?= escape($label) ?></span></a>
<?php endforeach; ?>
</nav>
<p class="rr-network-message" id="rr-network-message" role="status" hidden></p>
