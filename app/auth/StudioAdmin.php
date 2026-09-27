<?php
declare(strict_types=1);

// Entra object ID is stable when an account's address or display name changes.
const STUDIO_OWNER_OID = '41b640dc-f49b-47fc-87ef-85f7ba90c3d2';

function studio_is_admin(?array $user): bool
{
    return $user !== null
        && is_string($user['oid'] ?? null)
        && hash_equals(STUDIO_OWNER_OID, strtolower($user['oid']));
}

function studio_valid_upn(string $upn): bool
{
    return strlen($upn) <= 254
        && preg_match('/^[a-z0-9][a-z0-9._-]{0,63}@radiorubben\.no$/iD', $upn) === 1;
}

function studio_temporary_password(): string
{
    // 32 random bytes, encoded with all four character classes required by Entra.
    return 'Rr7!' . bin2hex(random_bytes(16)) . 'aA';
}
