<?php
declare(strict_types=1);

const STUDIO_OWNER_OID = '41b640dc-f49b-47fc-87ef-85f7ba90c3d2';

function studio_entra_is_admin(?array $user): bool
{
    return $user !== null && ($user['provider'] ?? 'entra') === 'entra'
        && is_string($user['oid'] ?? null)
        && hash_equals(STUDIO_OWNER_OID, strtolower($user['oid']));
}

function studio_local_users_path(): string
{
    return dirname(__DIR__, 2) . '/config/studio-users.json';
}

function studio_local_users_read(): array
{
    $path = studio_local_users_path();
    if (!is_file($path)) return [];
    $file = fopen($path, 'rb');
    if (!$file) throw new RuntimeException('User store unavailable');
    try {
        if (!flock($file, LOCK_SH)) throw new RuntimeException('User store lock failed');
        $raw = stream_get_contents($file);
        $data = json_decode($raw ?: '', true);
        if (!is_array($data) || ($data['version'] ?? null) !== 1 || !is_array($data['users'] ?? null)) {
            throw new RuntimeException('Invalid user store');
        }
        return $data['users'];
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

/** @return mixed */
function studio_local_users_update(callable $mutation)
{
    $path = studio_local_users_path();
    $file = fopen($path, 'c+b');
    if (!$file) throw new RuntimeException('User store is not writable');
    chmod($path, 0600);
    try {
        if (!flock($file, LOCK_EX)) throw new RuntimeException('User store lock failed');
        rewind($file);
        $raw = stream_get_contents($file);
        $data = $raw === '' ? ['version' => 1, 'users' => []] : json_decode($raw, true);
        if (!is_array($data) || ($data['version'] ?? null) !== 1 || !is_array($data['users'] ?? null)) {
            throw new RuntimeException('Invalid user store');
        }
        $result = $mutation($data['users']);
        $encoded = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        rewind($file);
        if (!ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) || !fflush($file)) {
            throw new RuntimeException('Could not save user store');
        }
        return $result;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

function studio_local_valid_email(string $email): bool
{
    return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && !preg_match('/[\r\n\x00-\x1f]/', $email);
}

function studio_local_new_password(): string
{
    return 'Rr7!' . bin2hex(random_bytes(16)) . 'aA';
}

function studio_local_user_by_id(string $id): ?array
{
    try {
        foreach (studio_local_users_read() as $user) if (($user['id'] ?? null) === $id) return $user;
    } catch (Throwable $error) {
        error_log('Studio: private user store unavailable.');
    }
    return null;
}

function studio_local_user_by_email(string $email): ?array
{
    try {
        foreach (studio_local_users_read() as $user) if (($user['email'] ?? null) === strtolower($email)) return $user;
    } catch (Throwable $error) {
        error_log('Studio: private user store unavailable.');
    }
    return null;
}

function studio_local_user_create(string $name, string $email, string $password, string $role = 'presenter'): array
{
    $email = strtolower(trim($email));
    $name = trim($name);
    if (!studio_local_valid_email($email) || strlen($name) < 2 || strlen($name) > 120 || !in_array($role, ['producer','presenter','observer'], true)) {
        throw new InvalidArgumentException('Oppgi gyldig navn og e-postadresse.');
    }
    return studio_local_users_update(static function (array &$users) use ($name, $email, $password, $role): array {
        if (count($users) >= 100) throw new RuntimeException('User limit reached');
        foreach ($users as $user) if (($user['email'] ?? null) === $email) {
            throw new InvalidArgumentException('Denne e-postadressen er allerede registrert.');
        }
        $id = bin2hex(random_bytes(16));
        $user = ['id' => $id, 'email' => $email, 'name' => $name, 'role' => $role,
            'hash' => password_hash($password, PASSWORD_DEFAULT), 'enabled' => true,
            'mustChange' => true, 'version' => 1, 'createdAt' => gmdate('c')];
        $users[$id] = $user;
        return $user;
    });
}

function studio_local_user_set_role(string $id, string $role): array
{
    if (!in_array($role, ['producer','presenter','observer'], true)) throw new InvalidArgumentException('Ugyldig rolle.');
    return studio_local_users_update(static function (array &$users) use ($id, $role): array {
        if (!isset($users[$id])) throw new InvalidArgumentException('Brukeren finnes ikke.');
        $users[$id]['role'] = $role;
        $users[$id]['version']++;
        return $users[$id];
    });
}

function studio_local_user_mark_intro_sent(string $id): void
{
    studio_local_users_update(static function (array &$users) use ($id): void {
        if (!isset($users[$id])) throw new InvalidArgumentException('Brukeren finnes ikke.');
        // A delivery receipt is metadata. It must not invalidate an active login session.
        $users[$id]['introSentAt'] = gmdate('c');
    });
}

function studio_local_user_change(string $id, ?bool $enabled, ?string $password = null): array
{
    return studio_local_users_update(static function (array &$users) use ($id, $enabled, $password): array {
        if (!isset($users[$id])) throw new InvalidArgumentException('Brukeren finnes ikke.');
        if ($enabled !== null) $users[$id]['enabled'] = $enabled;
        if ($password !== null) {
            $users[$id]['hash'] = password_hash($password, PASSWORD_DEFAULT);
            $users[$id]['mustChange'] = true;
        }
        $users[$id]['version']++;
        return $users[$id];
    });
}

function studio_local_user_set_password(string $id, string $password): array
{
    return studio_local_users_update(static function (array &$users) use ($id, $password): array {
        if (!isset($users[$id]) || !$users[$id]['enabled']) throw new RuntimeException('User unavailable');
        $users[$id]['hash'] = password_hash($password, PASSWORD_DEFAULT);
        $users[$id]['mustChange'] = false;
        $users[$id]['version']++;
        return $users[$id];
    });
}

function studio_local_login_allowed(string $email, string $ip, string $action = 'check'): bool
{
    $path = dirname(__DIR__, 2) . '/config/studio-login-attempts.json';
    $key = hash('sha256', strtolower($email) . '|' . $ip);
    $file = fopen($path, 'c+b');
    if (!$file) return false;
    chmod($path, 0600);
    try {
        if (!flock($file, LOCK_EX)) return false;
        rewind($file);
        $data = json_decode(stream_get_contents($file) ?: '{}', true);
        if (!is_array($data)) return false;
        $now = time();
        foreach ($data as $k => $times) {
            $data[$k] = is_array($times) ? array_values(array_filter($times, static fn($t) => is_int($t) && $t > $now - 900)) : [];
            if (!$data[$k]) unset($data[$k]);
        }
        $allowed = count($data[$key] ?? []) < 5;
        if ($action === 'fail' && $allowed) $data[$key][] = $now;
        if ($action === 'success' && $allowed) unset($data[$key]);
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        rewind($file);
        if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) return false;
        return $allowed;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}
