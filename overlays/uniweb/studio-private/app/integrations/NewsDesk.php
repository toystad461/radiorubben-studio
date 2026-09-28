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
