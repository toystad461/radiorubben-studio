<?php
declare(strict_types=1);

/** Deployment configuration only. Never accept a destination from a request or a story. */
function studio_wp_environment(): string {
    $env = getenv('STUDIO_WORDPRESS_ENVIRONMENT');
    $runtime = $GLOBALS['config'] ?? [];
    $env = $env === false ? (array_key_exists('wordpress_environment', $runtime) ? $runtime['wordpress_environment'] : 'production') : $env;
    if (!in_array($env, ['production', 'test'], true)
        || (gethostname() === 'vps-5363103' && $env !== 'test')) {
        throw new InvalidArgumentException('WordPress-miljøet må konfigureres på serveren.');
    }
    return $env;
}

/** A deliberately small allowlist. Adding another server requires a reviewed code change. */
function studio_wp_profile(): array {
    return studio_wp_environment() === 'test'
        ? ['environment'=>'test', 'site_url'=>'https://5.249.252.78/wordpress', 'hosts'=>['5.249.252.78'], 'prefix'=>'/wordpress/']
        : ['environment'=>'production', 'site_url'=>'https://www.radiorubben.no', 'hosts'=>['www.radiorubben.no','radiorubben.no'], 'prefix'=>'/'];
}

function studio_wp_destination(array $config): array {
    $profile = studio_wp_profile();
    // Existing production configuration stays compatible. Test has no implicit fallback.
    $environment = array_key_exists('environment', $config) ? $config['environment'] : 'production';
    $site = array_key_exists('site_url', $config) ? $config['site_url'] : ($environment === 'production' ? 'https://www.radiorubben.no' : null);
    if ($environment !== $profile['environment'] || $site !== $profile['site_url']) {
        throw new InvalidArgumentException('WordPress-målet samsvarer ikke med Studio-miljøet.');
    }
    return $profile;
}

function studio_wp_require_write(array $config): void {
    if (studio_wp_destination($config)['environment'] === 'test') {
        throw new InvalidArgumentException('WordPress-testkoblingen er foreløpig bare lesende. Overføring og publisering er sperret.');
    }
}

/** Only the existing API operations; no arbitrary URLs, redirects or user-chosen routes. */
function studio_wp_endpoint(array $config, string $method, string $route): string {
    $profile = studio_wp_destination($config);
    $path = explode('?', $route, 2)[0];
    if (!in_array($method, ['GET','POST'], true) || preg_match('/[\x00-\x20\x7f\\\\#]/', $route)
        || preg_match('/%(?:00|0a|0d|2e|2f|5c|25)/i', $path)
        || !preg_match('~^/(?:wp/v2/(?:posts(?:/[1-9][0-9]*)?|users/me)|rr-fotballrobot/v1/newsroom|rr-news-images/v1/catalog)$~D', $path)
        || ($method === 'POST' && (!in_array($path, ['/rr-fotballrobot/v1/newsroom'], true)
            && !preg_match('~^/wp/v2/posts(?:/[1-9][0-9]*)?$~D', $path)))
        || ($method === 'POST' && str_contains($route, '?'))) {
        throw new InvalidArgumentException('Ugyldig WordPress-operasjon.');
    }
    if ($method !== 'GET') studio_wp_require_write($config);
    if (str_contains($route, '?')) {
        parse_str(explode('?', $route, 2)[1], $query);
        $keys = str_starts_with($path, '/wp/v2/posts') ? ['context','_fields','slug','status','per_page']
            : ($path === '/wp/v2/users/me' ? ['context'] : []);
        if (array_diff(array_keys($query), $keys)) throw new InvalidArgumentException('Ugyldig WordPress-spørring.');
    }
    return $profile['site_url'].'/wp-json'.$route;
}

/** Returned post/media links must belong to the same deployment, including the WP subdirectory. */
function studio_wp_link_valid(string $url, bool $media = false): bool {
    try { $profile = studio_wp_profile(); } catch (InvalidArgumentException) { return false; }
    $u = parse_url($url);
    if (!is_array($u) || ($u['scheme'] ?? '') !== 'https' || !in_array($u['host'] ?? '', $profile['hosts'], true)
        || isset($u['user']) || isset($u['pass']) || isset($u['port']) || isset($u['fragment'])
        || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) return false;
    $path = $u['path'] ?? '/';
    if (!str_starts_with($path, $profile['prefix']) || str_contains($path, '..')
        || preg_match('/%(?:00|0a|0d|2e|2f|5c|25)/i', $path)) return false;
    return !$media || (!isset($u['query']) && str_starts_with($path, $profile['prefix'].'wp-content/uploads/'));
}

function studio_wp_published_label(): string {
    try { return studio_wp_environment() === 'test' ? 'Publisert i WordPress-test' : 'Publisert på radiorubben.no'; }
    catch (InvalidArgumentException) { return 'Publisert i WordPress'; }
}
