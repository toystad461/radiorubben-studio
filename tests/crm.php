<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/crm.php';
$checks = 0;
function crm_check(bool $ok, string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; }
function crm_reject(callable $run, string $label): void { try { $run(); } catch (InvalidArgumentException $e) { crm_check(true, $label); return; } throw new RuntimeException('Expected rejection: '.$label); }
$tmp = sys_get_temp_dir().'/crm-test-'.bin2hex(random_bytes(6)); mkdir($tmp, 0700);
$path = $tmp.'/crm.json'; $admin = ['role'=>'admin', 'name'=>'Testadministrator'];
$fields = ['company'=>'Testbedrift ÆØÅ', 'stage'=>'candidate', 'priority'=>'1', 'nextStep'=>'Ring om et møte', 'followUp'=>'2026-10-07', 'owner'=>'Testadministrator'];
try {
    crm_check(studio_crm_read($path)['records'] === [] && !file_exists($path), 'Reading empty CRM has no write side effect');
    foreach (['producer', 'presenter', 'observer', ''] as $role) {
        crm_check(!studio_crm_allowed(['role'=>$role]), 'Role cannot read CRM');
        foreach (['create', 'seed', 'save', 'activity'] as $action) crm_reject(fn()=>studio_crm_apply($action, $fields, ['role'=>$role], $path), 'Role cannot mutate');
    }
    crm_check(!file_exists($path), 'Denied mutations never create a registry');
    $id = studio_crm_apply('create', $fields, $admin, $path);
    $row = studio_crm_read($path)['records'][0];
    crm_check($row['id'] === $id && $row['number'] === 1 && $row['revision'] === 1, 'Persist new card and stable identity');
    if (PHP_OS_FAMILY !== 'Windows') {
        crm_check((fileperms($path) & 0777) === 0600 && (fileperms($path.'.lock') & 0777) === 0600, 'Private file permissions');
    } else {
        crm_check(chmod($path, 0400), 'Make Windows target read-only');
        studio_crm_change(static function (array &$data): void { $data['replacementProbe'] = true; }, $path);
        crm_check(studio_crm_read($path)['replacementProbe'] === true, 'Replace read-only Windows register');
        $saved = file_get_contents($path);
        crm_check(!studio_crm_replace($tmp.'/missing.json', $path), 'Replacement failure reported');
        crm_check(file_get_contents($path) === $saved, 'Failed replacement preserves register');
    }
    $before = file_get_contents($path);
    crm_reject(fn()=>studio_crm_apply('create', array_replace($fields, ['company'=>' testbedrift æøå ']), $admin, $path), 'Duplicate company rejected');
    crm_check(file_get_contents($path) === $before, 'Duplicate failure preserves exact bytes');
    $changes = ['stage'=>'meeting', 'contact'=>'Testperson', 'opportunity'=>"Ønsker et møte.\r\nAvtale er ikke inngått.", 'oneDriveUrl'=>'https://example.sharepoint.com/:f:/s/test/123'];
    studio_crm_apply('save', array_replace($fields, $changes, ['id'=>$id, 'revision'=>1]), $admin, $path);
    $row = studio_crm_read($path)['records'][0];
    crm_check($row['revision'] === 2 && $row['stage'] === 'meeting', 'Save updates card and revision');
    crm_check($row['history'][1]['changes']['stage'] === ['before'=>'candidate', 'after'=>'meeting'], 'History keeps previous and new status');
    crm_check($row['lastContact'] === '', 'Status change does not invent a contact');
    $before = file_get_contents($path);
    crm_reject(fn()=>studio_crm_apply('save', $fields+['id'=>$id, 'revision'=>1], $admin, $path), 'Concurrent old edit rejected');
    crm_check(file_get_contents($path) === $before, 'Concurrent rejection preserves exact bytes');
    foreach ([['website'=>'javascript:alert(1)'], ['website'=>'https://user:password@example.com'], ['oneDriveUrl'=>'https://sharepoint.com.evil.example/test'], ['oneDriveUrl'=>'http://onedrive.live.com/test'], ['followUp'=>'2026-02-30'], ['followUp'=>'2026-10-07','nextStep'=>''], ['email'=>'bad'], ['phone'=>'123" onclick=evil'], ['stage'=>'invented'], ['priority'=>'9'], ['company'=>['bad']], ['contact'=>"bad\0name"], ['opportunity'=>str_repeat('a',2001)]] as $bad) {
        crm_reject(fn()=>studio_crm_apply('save', array_replace($fields, $bad, ['id'=>$id, 'revision'=>2]), $admin, $path), 'Reject invalid fields');
        crm_check(file_get_contents($path) === $before, 'Invalid fields preserve data');
    }
    crm_check(studio_crm_fields(array_replace($fields, ['oneDriveUrl'=>'https://1drv.ms/f/s!test']))['oneDriveUrl'] !== '', 'Personal OneDrive links supported');
    studio_crm_apply('activity', ['id'=>$id,'revision'=>2,'channel'=>'phone','date'=>'2026-01-26','text'=>'Avtalt å komme tilbake senere.'], $admin, $path);
    studio_crm_apply('activity', ['id'=>$id,'revision'=>3,'channel'=>'note','date'=>'2026-10-07','text'=>'Internt notat.'], $admin, $path);
    $row = studio_crm_read($path)['records'][0];
    crm_check($row['lastContact'] === '2026-01-26' && count($row['history']) === 4, 'Notes do not invent customer contact');
    $before = file_get_contents($path);
    crm_reject(fn()=>studio_crm_apply('activity', ['id'=>$id,'revision'=>3,'channel'=>'phone','date'=>'2026-01-26','text'=>'Retry'], $admin, $path), 'Repeated activity blocked by revision');
    crm_reject(fn()=>studio_crm_apply('activity', ['id'=>$id,'revision'=>4,'channel'=>'phone','date'=>'2100-01-01','text'=>'Future'], $admin, $path), 'Future completed contacts rejected');
    crm_check(file_get_contents($path) === $before, 'Rejected activities preserve bytes');
    crm_check(studio_crm_due($row,'2026-10-07') && !studio_crm_due($row,'2026-10-06'), 'Due day is inclusive');
    foreach (['paused','declined','archived'] as $stage) crm_check(!studio_crm_due(array_replace($row,['stage'=>$stage]), '2026-10-07'), 'Inactive records never show as due');
    crm_check(studio_crm_list([$row], 'æøå')[0]['id'] === $id, 'Norwegian case-insensitive search');
    studio_crm_apply('seed', [], $admin, $path);
    $records = studio_crm_read($path)['records'];
    crm_check(count($records) === 6 && $records[0] === $row, 'Seed preserves existing card and history');
    studio_crm_apply('seed', [], $admin, $path);
    crm_check(studio_crm_read($path)['records'] === $records, 'Seed repeat adds no duplicates or history');
    foreach (array_slice($records, 1) as $candidate) crm_check($candidate['stage'] === 'candidate' && $candidate['lastContact'] === '' && $candidate['followUp'] === '', 'Seeds do not invent contacts, agreements or dates');
    studio_crm_apply('save', array_replace($row, ['stage'=>'archived']), $admin, $path);
    $records = studio_crm_read($path)['records'];
    crm_check(count(studio_crm_list($records)) === 5 && studio_crm_list($records, '', 'archived')[0]['id'] === $id, 'Archive is retained and filterable');
    $before = file_get_contents($path);
    crm_reject(fn()=>studio_crm_apply('save', array_replace($row,['id'=>'0000000000000000']), $admin, $path), 'Unknown record rejected');
    crm_check(file_get_contents($path) === $before, 'Unknown record preserves bytes');
    file_put_contents($path, '{broken');
    try { studio_crm_apply('create', $fields, $admin, $path); throw new RuntimeException('Expected invalid JSON'); }
    catch (JsonException $e) { crm_check(file_get_contents($path) === '{broken', 'Corrupt registry is never overwritten'); }
    echo "OK CRM: $checks checks\n";
} finally { foreach (glob($tmp.'/*') as $file) unlink($file); rmdir($tmp); }
