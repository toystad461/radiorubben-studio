<?php
declare(strict_types=1);

/** Fixed, server-side sources. Never accept a feed URL from a request. */
function newsdesk_sources(): array
{
    return [
        'bomlo' => [
            'name' => 'Bømlo kommune',
            'feed' => 'https://www.bomlo.kommune.no/ArtikkelRSS.ashx?NyhetsKategoriId=26&Spraak=Nynorsk',
            'host' => 'www.bomlo.kommune.no',
            'path' => '/aktuelt-og-kunngjeringar/',
            'ttl' => 900,
        ],
        'nrk' => [
            'name' => 'NRK – Siste nyheter',
            'feed' => 'https://www.nrk.no/nyheter/siste.rss',
            'host' => 'www.nrk.no',
            'path' => '/',
            'ttl' => 300,
        ],
    ];
}

function newsdesk_clean(string $text, int $limit = 360): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    return function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);
}

function newsdesk_parse_rss(string $body, string $source, array $spec, string $fetchedAt): array
{
    if (strlen($body) > 1000000 || stripos($body, '<!DOCTYPE') !== false
        || stripos($body, '<!ENTITY') !== false || !function_exists('simplexml_load_string')) return [];
    $prior = libxml_use_internal_errors(true);
    try { $rss = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($prior); }
    if ($rss === false || $rss->getName() !== 'rss' || !isset($rss->channel)) return [];
    $items = []; $seen = [];
    foreach ($rss->channel->item as $item) {
        $title = newsdesk_clean((string)$item->title, 180);
        $link = trim((string)$item->link);
        $url = parse_url($link);
        $published = strtotime((string)$item->pubDate);
        if ($title === '' || $published === false || !is_array($url)
            || ($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== $spec['host']
            || isset($url['user']) || isset($url['pass']) || isset($url['port'])
            || !str_starts_with($url['path'] ?? '', $spec['path'])
            || str_ends_with($url['path'] ?? '', '.rss')) continue;
        $id = hash('sha256', $source . ':' . $link);
        if (isset($seen[$id])) continue;
        $seen[$id] = true;
        $items[] = [
            'id' => $id, 'source' => $source, 'sourceName' => $spec['name'],
            'title' => $title, 'summary' => newsdesk_clean((string)$item->description),
            'url' => $link, 'publishedAt' => gmdate('c', $published),
            'fetchedAt' => $fetchedAt,
        ];
        if (count($items) >= 20) break;
    }
    usort($items, static fn(array $a, array $b): int => strcmp($b['publishedAt'], $a['publishedAt']));
    return $items;
}

function newsdesk_fetch_rss(string $url): ?string
{
    if (!function_exists('curl_init')) return null;
    $body = '';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml, text/xml'],
        CURLOPT_USERAGENT => 'RadioRubben-Studio/1.0 (+https://www.radiorubben.no/kontakt/)',
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 1000000) return 0;
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $type = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);
    return $ok && $status === 200 && preg_match('/\b(?:rss\+xml|xml)\b/i', $type) ? $body : null;
}

/** Cache prevents a studio page load from polling upstream on every visit. */
function newsdesk_feed(string $source, array $spec, string $cacheDir): array
{
    $path = rtrim($cacheDir, '/') . '/newsdesk-' . $source . '.json';
    $handle = @fopen($path, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        if ($handle) fclose($handle);
        return ['status'=>'unavailable', 'items'=>[], 'fetchedAt'=>null];
    }
    @chmod($path, 0600);
    try {
        $cache = json_decode(stream_get_contents($handle) ?: '', true);
        if (!is_array($cache)) $cache = [];
        $now = time();
        if (($cache['checkedAt'] ?? 0) + $spec['ttl'] <= $now) {
            $body = newsdesk_fetch_rss($spec['feed']);
            $fetchedAt = gmdate('c', $now);
            $items = $body === null ? [] : newsdesk_parse_rss($body, $source, $spec, $fetchedAt);
            if ($body !== null && $items) {
                $cache = ['checkedAt'=>$now, 'fetchedAt'=>$fetchedAt, 'items'=>$items, 'failed'=>false];
            } else {
                // Retry later without turning a transient outage into an empty news day.
                $cache['checkedAt'] = $now - $spec['ttl'] + 60;
                $cache['failed'] = true;
            }
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            fflush($handle);
        }
        $age = $now - (int)($cache['checkedAt'] ?? 0);
        $freshAge = $now - (int)(strtotime((string)($cache['fetchedAt'] ?? '')) ?: 0);
        $status = !empty($cache['items']) && empty($cache['failed']) && $freshAge <= $spec['ttl'] + 60 ? 'updated'
            : (!empty($cache['items']) && $freshAge <= 21600 ? 'stale' : 'unavailable');
        return ['status'=>$status, 'items'=>$status === 'unavailable' ? [] : $cache['items'],
            'fetchedAt'=>$cache['fetchedAt'] ?? null, 'checkedAt'=>$age];
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

function newsdesk_all(string $cacheDir): array
{
    $result = [];
    foreach (newsdesk_sources() as $id=>$source) $result[$id] = newsdesk_feed($id, $source, $cacheDir);
    return $result;
}

/** Vegvesen's public WFS SituationSimple feed, limited to the Bømlo/Sunnhordland area. */
function newsdesk_traffic_url(): string
{
    return 'https://ogckart-sn1.atlas.vegvesen.no/datex_3_1/ows?' . http_build_query([
        'service'=>'WFS', 'version'=>'1.0.0', 'request'=>'GetFeature',
        'typeName'=>'datex_3_1:SituationSimple', 'outputFormat'=>'application/json',
        'bbox'=>'4.75,59.45,5.8,60.05,EPSG:4326', 'maxFeatures'=>'200',
    ], '', '&', PHP_QUERY_RFC3986);
}

function newsdesk_traffic_parse(string $body, string $fetchedAt): ?array
{
    $data = json_decode($body, true);
    if (!is_array($data) || ($data['type'] ?? null) !== 'FeatureCollection'
        || !isset($data['features']) || !is_array($data['features'])) return null;
    // A truncated result must never be presented as a complete local overview.
    if (count($data['features']) >= 200 || (int)($data['totalFeatures'] ?? 0) > count($data['features'])) return null;
    $items = []; $seen = [];
    foreach ($data['features'] as $feature) {
        if (!is_array($feature) || !is_array($feature['properties'] ?? null)) continue;
        $p = $feature['properties'];
        if (($p['@featureType'] ?? null) !== 'geoJsonSituationSimple'
            || ($p['isMainRecord'] ?? null) !== true
            || ($p['confidentiality'] ?? null) !== 'noRestriction') continue;
        $situationId = $p['situationId'] ?? null;
        $location = $p['locationDescription'] ?? null;
        $description = $p['description'] ?? null;
        $updated = is_string($p['lastUpdateTime'] ?? null) ? strtotime($p['lastUpdateTime']) : false;
        $end = is_string($p['endTime'] ?? null) ? strtotime($p['endTime']) : false;
        if (!is_string($situationId) || $situationId === '' || isset($seen[$situationId])
            || !is_string($location) || trim($location) === '' || !is_string($description)
            || trim($description) === '' || $updated === false || $updated > time() + 300
            || ($end !== false && $end < time())) continue;
        $seen[$situationId] = true;
        $summary = newsdesk_clean(str_replace('|', ' ', $description), 360);
        $title = newsdesk_clean($location, 180);
        $items[] = [
            'id'=>hash('sha256', 'vegvesen:' . $situationId), 'source'=>'vegvesen',
            'sourceName'=>'Statens vegvesen', 'title'=>$title, 'summary'=>$summary,
            'url'=>'https://www.vegvesen.no/trafikk/', 'publishedAt'=>gmdate('c', $updated),
            'fetchedAt'=>$fetchedAt, 'severity'=>newsdesk_clean((string)($p['severity'] ?? ''), 30),
        ];
    }
    usort($items, static fn(array $a, array $b): int => strcmp($b['publishedAt'], $a['publishedAt']));
    return array_slice($items, 0, 20);
}

function newsdesk_traffic(string $cacheDir): array
{
    $path = rtrim($cacheDir, '/') . '/newsdesk-traffic.json';
    $handle = @fopen($path, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        if ($handle) fclose($handle);
        return ['status'=>'unavailable', 'items'=>[], 'fetchedAt'=>null];
    }
    @chmod($path, 0600);
    try {
        $cache = json_decode(stream_get_contents($handle) ?: '', true);
        if (!is_array($cache)) $cache = [];
        $now = time();
        if (($cache['checkedAt'] ?? 0) + 300 <= $now) {
            $body = newsdesk_fetch_json(newsdesk_traffic_url());
            $items = $body === null ? null : newsdesk_traffic_parse($body, gmdate('c', $now));
            if ($items !== null) {
                $cache = ['checkedAt'=>$now, 'fetchedAt'=>gmdate('c', $now), 'items'=>$items, 'failed'=>false];
            } else {
                $cache['checkedAt'] = $now - 240; // retry after a minute
                $cache['failed'] = true;
            }
            rewind($handle); ftruncate($handle, 0);
            fwrite($handle, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            fflush($handle);
        }
        $age = $now - (int)(strtotime((string)($cache['fetchedAt'] ?? '')) ?: 0);
        $status = $age <= 360 && empty($cache['failed']) ? 'updated'
            : ($age <= 900 && isset($cache['items']) ? 'stale' : 'unavailable');
        return ['status'=>$status, 'items'=>$status === 'unavailable' ? [] : $cache['items'],
            'fetchedAt'=>$cache['fetchedAt'] ?? null];
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

function newsdesk_fetch_json(string $url): ?string
{
    if (!function_exists('curl_init')) return null;
    $body = '';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>12,
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_HTTPHEADER=>['Accept: application/json'],
        CURLOPT_USERAGENT=>'RadioRubben-Studio/1.0 (+https://www.radiorubben.no/kontakt/)',
        CURLOPT_WRITEFUNCTION=>static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 3000000) return 0;
            $body .= $chunk; return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $type = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);
    return $ok && $status === 200 && preg_match('~^application/(?:[\w.-]+\+)?json\b~i', $type) ? $body : null;
}
