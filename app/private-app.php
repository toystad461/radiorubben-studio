<?php
declare(strict_types=1);

/** Opt-in for this entire Studio host, not just the mobile landing page. */
function studio_private_app_mode(array $config): string
{
    if (!array_key_exists('private_app_enabled', $config) || $config['private_app_enabled'] === false) return 'off';
    return $config['private_app_enabled'] === true && ($config['auth_mode'] ?? '') === 'entra' ? 'on' : 'invalid';
}

/** Called only after current_user() has validated expiry, provider and account. */
function studio_private_app_filter_user(array $config, ?array $user): ?array
{
    $mode = studio_private_app_mode($config);
    if ($mode === 'off') return $user;
    if ($mode !== 'on' || !$user || ($user['provider'] ?? '') !== 'entra') return null;
    // Reuse Studio's existing, verified owner OID. Never trust a name/email/role.
    return studio_entra_is_admin($user) ? $user : null;
}

function studio_private_app_manifest(): array
{
    return [
        'id'=>'/mobil.php', 'name'=>'Radio Rubben Studio', 'short_name'=>'RR Studio',
        'description'=>'Radio Rubbens private arbeidsrom.', 'lang'=>'nb',
        'start_url'=>'/mobil.php', 'scope'=>'/', 'display'=>'standalone',
        'background_color'=>'#101014', 'theme_color'=>'#101014',
        'icons'=>[['src'=>'/assets/studio-app-icon.png', 'sizes'=>'512x512', 'type'=>'image/png', 'purpose'=>'any']],
    ];
}
