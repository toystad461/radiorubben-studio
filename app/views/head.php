<?php $rrPrivateApp = function_exists('studio_private_app_mode') && studio_private_app_mode($config ?? []) === 'on'; ?>
<!doctype html>
<html lang="nb"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1<?= $rrPrivateApp ? ',viewport-fit=cover' : '' ?>"><meta name="robots" content="noindex,nofollow"><meta name="description" content="Arbeidsrommet for Radio Rubben"><link rel="icon" href="/assets/favicon.ico"><title>Radio Rubben Studio</title><link rel="stylesheet" href="/assets/studio.css?v=20260923-brand"><link rel="stylesheet" href="/assets/theme-bridge.css?v=20260928"><?php if (isset($extraStylesheet)): ?><link rel="stylesheet" href="<?= escape($extraStylesheet) ?>"><?php endif; ?>
<?php if ($rrPrivateApp): ?>
<link rel="manifest" href="/app-manifest.php" crossorigin="use-credentials">
<link rel="apple-touch-icon" href="/assets/studio-app-icon.png">
<meta name="theme-color" content="#101014">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="RR Studio">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" href="/assets/private-app.css?v=1">
<script src="/assets/private-app.js?v=1" defer></script>
<?php endif; ?></head><body<?= $rrPrivateApp ? ' class="rr-private-app"' : '' ?>>
<a class="skip-link" href="#main">Hopp til innhold</a>
<?php if ($rrPrivateApp && isset($user) && studio_private_app_filter_user($config, $user)) require __DIR__ . '/private-app-nav.php'; ?>
