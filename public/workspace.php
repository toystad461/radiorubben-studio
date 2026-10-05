<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$user = current_user();
if ($config['auth_mode'] !== 'demo' && !$user) redirect('/login.php');
$sections = [
 'schedule'=>['Sendeplan','Sendeplanen er ikke bygget ennå. Programkontekst kan settes i AI Studio.','Åpne AI Studio','/ai-studio.php'],
 'social'=>['Sosiale medier','Publisering til sosiale medier er ikke tilkoblet ennå.','Til oversikten','/'],
 'sponsors'=>['Sponsorer','Sponsorregisteret er ikke bygget ennå. Godkjent sponsortekst kan brukes som grunnlag i Stikk & Manus.','Åpne Stikk & Manus','/ai-studio.php#producer-title'],
 'radio'=>['Radio.co','Radio.co er ikke koblet til denne Studio-installasjonen. Sendestatus vises i AI Studio når en sendekilde er tilgjengelig.','Åpne AI Studio','/ai-studio.php'],
];
$key = is_string($_GET['section'] ?? null) ? $_GET['section'] : '';
if (!isset($sections[$key])) { http_response_code(404); exit('Siden finnes ikke.'); }
[$title,$description,$label,$href] = $sections[$key];
require dirname(__DIR__) . '/app/views/head.php';
?>
<link rel="stylesheet" href="/users.css?v=20260924">
<div class="shell"><?php $activePage=$key; require dirname(__DIR__) . '/app/views/sidebar.php'; ?>
<div class="workspace"><header class="topbar"><span>Arbeidsrom / <strong><?= escape($title) ?></strong></span><?php require dirname(__DIR__) . '/app/views/account.php'; ?></header>
<main id="main"><p class="eyebrow">RADIO RUBBEN STUDIO</p><h1><?= escape($title) ?></h1><section class="users-panel"><p><?= escape($description) ?></p><a class="button" href="<?= escape($href) ?>"><?= escape($label) ?></a></section></main></div></div></body></html>
