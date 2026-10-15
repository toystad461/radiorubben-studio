<?php
declare(strict_types=1);
// Invoked only by the owner-operated root helper, never exposed over HTTP.
if (PHP_SAPI !== 'cli' || gethostname() !== 'vps-5363103') exit(1);
require '/srv/radiorubben-test/studio/app/auth/StudioLocalUsers.php';
$password = rtrim((string)stream_get_contents(STDIN), "\r\n");
if (strlen($password) < 14 || strlen($password) > 72) exit(1);
if (studio_local_user_by_email('thomas@example.test')) {
    fwrite(STDERR, "Testkontoen finnes allerede; passordet er ikke endret.\n");
    exit(1);
}
$user = studio_local_user_create('Thomas — TEST', 'thomas@example.test', $password, 'producer');
unset($password);
// Root-only provisioning of the owner's isolated test account, not a new web
// permission or production authentication bypass. Runtime role checks remain.
studio_local_users_update(static function (array &$users) use ($user): void {
    $users[$user['id']]['role'] = 'admin';
    $users[$user['id']]['mustChange'] = false;
});
echo "Studio-testkonto opprettet.\n";
