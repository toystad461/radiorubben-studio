<?php
declare(strict_types=1);

/** Read NRK's latest news RSS as source material for the private Studio inbox. */
function nrk_rss_cards(): array
{
    if (!function_exists('curl_init') || !function_exists('simplexml_load_string')) return [];
    $body = '';
    $curl = curl_init('https://www.nrk.no/nyheter/siste.rss');
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml, text/xml'],
        CURLOPT_USERAGENT => 'RadioRubben-Studio/0.1 (+https://www.radiorubben.no/)',
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 1000000) return 0;
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);
    if (!$ok || $code !== 200 || !preg_match('/\b(?:rss\+xml|xml)\b/i', $contentType)) return [];
    return nrk_rss_parse($body, gmdate('c'));
}

function nrk_rss_parse(string $body, string $fetchedAt): array
{
    if (strlen($body) > 1000000 || stripos($body, '<!DOCTYPE') !== false || stripos($body, '<!ENTITY') !== false
        || !function_exists('simplexml_load_string')) return [];
    $prior = libxml_use_internal_errors(true);
    $rss = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($prior);
    if ($rss === false || $rss->getName() !== 'rss' || !isset($rss->channel)) return [];
    $items = []; $seen = [];
    foreach ($rss->channel->item as $item) {
        $title = trim((string) $item->title);
        $link = trim((string) $item->link);
        $guid = trim((string) $item->guid) ?: $link;
        $published = strtotime((string) $item->pubDate);
        $parts = parse_url($link);
        if (!$title || !$guid || $published === false || !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || !in_array($parts['host'] ?? '', ['www.nrk.no', 'nrk.no'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || !str_starts_with($parts['path'] ?? '', '/')
            || str_ends_with($parts['path'] ?? '', '.rss')) continue;
        $id = 'news:nrk:' . substr(hash('sha256', $guid), 0, 24);
        if (isset($seen[$id])) continue;
        $seen[$id] = true;
        $summary = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $item->description)) ?? '');
        $event = [
            'id' => $id, 'type' => 'news.item.discovered', 'editorialStatus' => 'new',
            'verificationStatus' => 'unverified',
            'source' => ['name' => 'NRK – Siste nyheter', 'url' => $link, 'fetchedAt' => $fetchedAt],
            'facts' => ['title' => $title, 'summary' => $summary, 'publishedAt' => gmdate('c', $published), 'sourceGuid' => $guid],
        ];
        $items[] = ['event' => $event, 'draft' => [
            'eventId' => $id, 'status' => 'review', 'title' => $title, 'body' => $summary,
        ]];
        if (count($items) >= 30) break;
    }
    return $items;
}
