<?php if ($user): ?>
<div class="account-menu"><span>Innlogget som <strong><?= escape($user['name'] ?? 'Medarbeider') ?></strong> · <?= escape(studio_roles()[$user['role'] ?? ''] ?? 'Medarbeider') ?></span><form method="post" action="/logout.php"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><button type="submit">Logg ut</button></form></div>
<?php endif; ?>
