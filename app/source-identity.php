<?php
declare(strict_types=1);

/** Canonical public links; remove tracking without inventing a new article address. */
function studio_source_url(string $url): ?string
{
    if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) return null;
    $p = parse_url($url);
    if (!is_array($p) || ($p['scheme'] ?? '') !== 'https'
        || !in_array($p['host'] ?? '', ['www.nrk.no', 'www.bomlo.kommune.no'], true)
        || isset($p['user']) || isset($p['pass']) || isset($p['port'])) return null;
    $query = [];
    parse_str($p['query'] ?? '', $query);
    foreach (array_keys($query) as $key) if (preg_match('/^(?:utm_|fbclid$|gclid$)/i', (string)$key)) unset($query[$key]);
    ksort($query);
    return 'https://' . $p['host'] . ($p['path'] ?? '/')
        . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}

/** NRK article IDs survive headline/section changes and two different feed memberships. */
function studio_source_identity(string $url): ?string
{
    $url = studio_source_url($url);
    if ($url === null) return null;
    $p = parse_url($url);
    if ($p['host'] === 'www.nrk.no' && preg_match('~-(1\.[0-9]+)$~D', rtrim($p['path'] ?? '', '/'), $match))
        return 'nrk:' . $match[1];
    return 'url:' . $url;
}
