<?php
declare(strict_types=1);

/** Read the public municipal RSS only after Studio authentication. */
function municipality_rss_cards(): array
{
    if (!function_exists('curl_init') || !function_exists('simplexml_load_string')) return [];
    $url = 'https://www.bomlo.kommune.no/ArtikkelRSS.ashx?NyhetsKategoriId=26&Spraak=Nynorsk';
    $body = '';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Accept: application/rss+xml'],
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
    return municipality_rss_parse($body, gmdate('c'));
}

function municipality_rss_parse(string $body, string $fetchedAt): array
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
            || ($parts['host'] ?? '') !== 'www.bomlo.kommune.no'
            || !str_starts_with($parts['path'] ?? '', '/aktuelt-og-kunngjeringar/')) continue;
        $id = 'news:bomlo-kommune:' . substr(hash('sha256', $guid), 0, 24);
        if (isset($seen[$id])) continue;
        $seen[$id] = true;
        $summary = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $item->description)) ?? '');
        $event = [
            'id' => $id, 'type' => 'news.item.discovered', 'editorialStatus' => 'new',
            'verificationStatus' => 'unverified',
            'source' => ['name' => 'Bømlo kommune – Aktuelt og kunngjeringar', 'url' => $link, 'fetchedAt' => $fetchedAt],
            'facts' => ['title' => $title, 'summary' => $summary, 'publishedAt' => gmdate('c', $published), 'sourceGuid' => $guid],
        ];
        $items[] = ['event' => $event, 'draft' => [
            'eventId' => $id, 'status' => 'review', 'title' => $title, 'body' => $summary,
        ]];
    }
    return $items;
}
