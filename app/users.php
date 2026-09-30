<?php
declare(strict_types=1);

function studio_roles(): array { return ['admin'=>'Administrator', 'producer'=>'Produsent', 'presenter'=>'Programleder', 'observer'=>'Observatør']; }
function studio_can(?array $user, string $capability): bool
{
    $role = $user['role'] ?? '';
    return match ($capability) {
        'users' => $role === 'admin',
        'settings' => in_array($role, ['admin','producer'], true),
        'produce' => in_array($role, ['admin','producer','presenter'], true),
        default => false,
    };
}
function studio_user_phone(string $phone): string
{
    $phone = preg_replace('/[\s().-]+/', '', trim($phone));
    if (preg_match('/^[0-9]{8}$/D', $phone)) $phone = '+47'.$phone;
    if (str_starts_with($phone, '00')) $phone = '+'.substr($phone, 2);
    if (!preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone)) throw new InvalidArgumentException('Skriv et gyldig telefonnummer med landskode.');
    return $phone;
}
function studio_users_path(): string { return dirname(__DIR__).'/config/users.private.json'; }
function studio_users_read(?string $path = null): array
{
    $path ??= studio_users_path();
    if (!is_file($path)) return [];
    $rows = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($rows) || !array_is_list($rows)) throw new RuntimeException('Invalid user registry');
    foreach ($rows as &$row) {
        if (is_array($row)) $row['role'] ??= 'producer';
        if (!is_array($row) || !is_string($row['phone'] ?? null) || !is_string($row['name'] ?? null)
            || !isset(studio_roles()[$row['role'] ?? '']) || studio_user_phone($row['phone']) !== $row['phone']) throw new RuntimeException('Invalid user registry');
    }
    return $rows;
}
function studio_users_change(array $config, string $action, string $phone, string $name, ?string $path = null, string $role = 'producer'): void
{
    $phone = studio_user_phone($phone);
    if (in_array($phone, $config['vipps_allowed_phones'], true)) throw new InvalidArgumentException('Administratortilgangen kan ikke endres her.');
    if (!in_array($action, ['add', 'remove', 'role'], true)) throw new InvalidArgumentException('Ukjent handling.');
    if (!isset(studio_roles()[$role])) throw new InvalidArgumentException('Velg en gyldig rolle.');
    $name = trim($name);
    if ($action === 'add' && ($name === '' || strlen($name) > 120 || preg_match('/[\x00-\x1f]/', $name))) throw new InvalidArgumentException('Skriv et navn på inntil 120 tegn.');
    $path ??= studio_users_path();
    $lock = fopen($path.'.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Cannot lock user registry');
    chmod($path.'.lock', 0600);
    $temp = null;
    try {
        $rows = studio_users_read($path);
        $found = array_filter($rows, fn($row) => $row['phone'] === $phone);
        if ($action === 'add' && $found) throw new InvalidArgumentException('Dette telefonnummeret har allerede tilgang.');
        if ($action === 'role' && !$found) throw new InvalidArgumentException('Brukeren finnes ikke lenger.');
        if ($action === 'role') {
            foreach ($rows as &$row) if ($row['phone'] === $phone) $row['role'] = $role;
            unset($row);
        } else $rows = array_values(array_filter($rows, fn($row) => $row['phone'] !== $phone));
        if ($action === 'add') {
            if (count($rows) >= 200) throw new InvalidArgumentException('Maksimalt 200 medarbeidere.');
            $rows[] = ['phone'=>$phone, 'name'=>$name, 'added_at'=>gmdate('c'), 'role'=>$role];
        }
        $temp = tempnam(dirname($path), '.users-');
        if (!$temp || !chmod($temp, 0600) || file_put_contents($temp, json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) === false || !rename($temp, $path)) throw new RuntimeException('Cannot save user registry');
    } finally {
        if ($temp && is_file($temp)) unlink($temp);
        flock($lock, LOCK_UN); fclose($lock);
    }
}
function studio_access_role(array $config, string $phone): ?string
{
    if (in_array($phone, $config['vipps_allowed_phones'] ?? [], true)) return 'admin';
    try {
        foreach (studio_users_read() as $row) if ($row['phone'] === $phone) return $row['role'];
    } catch (Throwable $e) { return null; }
    return null;
}
function studio_login_phones(array $config): array
{
    $phones = $config['vipps_allowed_phones'];
    try { foreach (studio_users_read() as $row) $phones[] = $row['phone']; } catch (Throwable $e) { /* Registered owner can repair access. */ }
    return array_values(array_unique($phones));
}
