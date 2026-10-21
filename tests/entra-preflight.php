<?php
declare(strict_types=1);
require dirname(__DIR__).'/scripts/entra-preflight.php';
require dirname(__DIR__).'/app/users.php';
function entra_check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: ".$message.PHP_EOL;
}
$valid = ['auth_mode'=>'entra','base_url'=>'https://studio-test.example.org',
    'tenant_id'=>'11111111-1111-4111-8111-111111111111',
    'client_id'=>'22222222-2222-4222-8222-222222222222',
    'client_secret'=>'synthetic-test-only','local_users_enabled'=>true];
entra_check(!in_array(false, entra_preflight($valid), true), 'complete isolated test configuration');
foreach (['tenant_id','client_id','client_secret','base_url'] as $key)
    entra_check(in_array(false, entra_preflight(array_replace($valid, [$key=>''])), true), 'missing '.$key.' blocks test readiness');
foreach (['tenant_id'=>'tenant_uuid', 'client_id'=>'client_uuid'] as $key=>$check)
    foreach (['00000000-0000-0000-0000-000000000000','00000000-0000-0000-0000-000000000001'] as $placeholder)
        entra_check(!entra_preflight(array_replace($valid, [$key=>$placeholder]))[$check], 'placeholder '.$key.' blocks readiness');
entra_check(entra_preflight(array_replace($valid, ['base_url'=>'https://5.249.252.78']))['https_origin'], 'HTTPS IP accepted by local configuration check; Entra registration remains manual');
foreach (['common','organizations','consumers'] as $tenant)
    entra_check(!entra_preflight(array_replace($valid,['tenant_id'=>$tenant]))['tenant_uuid'], 'tenant-specific authority required: '.$tenant);
foreach (['http://example.org','https://user@example.org','https://example.org/path','https://example.org?x=1','https://example.org#x'] as $url)
    entra_check(!entra_preflight(array_replace($valid,['base_url'=>$url]))['https_origin'], 'unsafe test origin rejected');
entra_check(!entra_preflight(array_replace($valid,['local_users_enabled'=>false]))['local_fallback_enabled'], 'fallback must be explicitly enabled');
foreach (['users','settings','produce'] as $capability)
    entra_check(!studio_can(['provider'=>'entra','role'=>'observer'], $capability), 'guest observer cannot '.$capability);
entra_check(studio_can(['provider'=>'entra','role'=>'admin'],'users'), 'administrator can manage users');
echo "Synthetic checks only; no real Entra authentication performed.\n";
