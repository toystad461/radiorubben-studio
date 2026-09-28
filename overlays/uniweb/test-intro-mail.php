<?php
declare(strict_types=1);
function studio_roles(): array { return ['presenter'=>'Programleder', 'observer'=>'Observatør']; }
require __DIR__ . '/studio-private/app/auth/StudioLocalUsers.php';
require __DIR__ . '/studio-private/app/intro-mail.php';

function intro_assert(bool $result, string $reason): void
{
    if (!$result) throw new RuntimeException($reason);
}
$record = ['name'=>'Test Bruker', 'email'=>'test@example.org', 'role'=>'presenter'];
$config = ['base_url'=>'https://studio.radiorubben.no', 'intro_from_email'=>'studio@radiorubben.no'];
$captured = null;
$accepted = studio_intro_send($record, $config, static function ($to, $subject, $body, $headers) use (&$captured): bool {
    $captured = compact('to', 'subject', 'body', 'headers');
    return true;
});
intro_assert($accepted && $captured['to'] === 'test@example.org', 'Correct recipient');
intro_assert(str_contains($captured['body'], 'https://studio.radiorubben.no/local/login.php'), 'Login URL');
intro_assert(str_contains($captured['body'], 'Manus & Stikk'), 'OneDrive instructions');
intro_assert(!str_contains($captured['body'], 'Rr7!'), 'No temporary password in message');
intro_assert($captured['headers']['From'] === 'Radio Rubben Studio <studio@radiorubben.no>', 'From header');
intro_assert(!studio_intro_send($record, $config, static fn(): bool => false), 'Delivery rejection remains visible');
$observer = $record; $observer['role'] = 'observer';
intro_assert(str_contains(studio_intro_message($observer, $config)['body'], 'kan du lese sendelisten'), 'Observer instructions');
$badSender = $config; $badSender['intro_from_email'] = "studio@radiorubben.no\r\nBcc: hidden@example.org";
intro_assert(!studio_intro_sender_ready($badSender), 'Reject header injection');
$blocked = false;
try { studio_intro_send($record, $badSender, static fn(): bool => true); }
catch (RuntimeException $e) { $blocked = true; }
intro_assert($blocked, 'Reject unconfigured sender before sending');
echo "Introduction mail tests passed.\n";
