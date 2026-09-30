<?php
declare(strict_types=1);
// Copy the account module into an isolated private root; never touch project users.
$overlay = sys_get_temp_dir().'/rubben-accounts-'.bin2hex(random_bytes(5));
mkdir($overlay.'/app/auth', 0700, true);
mkdir($overlay.'/config', 0700, true);
copy(dirname(__DIR__, 2).'/app/auth/StudioLocalUsers.php', $overlay.'/app/auth/StudioLocalUsers.php');
require $overlay.'/app/auth/StudioLocalUsers.php';
function overlay_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
overlay_check(studio_entra_is_admin(['provider'=>'entra','oid'=>STUDIO_OWNER_OID]), 'Owner must be admin');
overlay_check(!studio_entra_is_admin(['provider'=>'local','oid'=>STUDIO_OWNER_OID]), 'Local must not be admin');
overlay_check(!studio_entra_is_admin(['provider'=>'entra','oid'=>'00000000-0000-0000-0000-000000000000']), 'Other Entra account must not be admin');
$config = $overlay.'/config';
if (!is_dir($config)) mkdir($config, 0700, true);
$path = studio_local_users_path();
if (is_file($path)) throw new RuntimeException('Test user store already exists');
try {
    $password = studio_local_new_password();
    $record = studio_local_user_create('Test Person', 'TEST@example.org', $password, 'observer');
    overlay_check($record['role'] === 'observer' && $record['mustChange'] && password_verify($password, $record['hash']), 'Temporary credential');
    $roleRejected = false;
    try { studio_local_user_create('Test Person', 'admin@example.org', $password, 'admin'); }
    catch (InvalidArgumentException $e) { $roleRejected = true; }
    overlay_check($roleRejected, 'Cannot create local administrator');
    $role = studio_local_user_set_role($record['id'], 'presenter');
    overlay_check($role['role'] === 'presenter' && $role['version'] === 2, 'Role invalidates session');
    $changed = studio_local_user_set_password($record['id'], 'my-new-long-password-123');
    overlay_check(!$changed['mustChange'] && $changed['version'] === 3, 'Password change invalidates session');
    $disabled = studio_local_user_change($record['id'], false);
    overlay_check(!$disabled['enabled'] && $disabled['version'] === 4, 'Disable invalidates session');
} finally {
    if (is_file($path)) unlink($path);
    unlink($overlay.'/app/auth/StudioLocalUsers.php');
    rmdir($overlay.'/app/auth'); rmdir($overlay.'/app'); rmdir($overlay.'/config'); rmdir($overlay);
}
echo "Live Studio overlay syntax and account tests passed.\n";
