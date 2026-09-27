<?php
declare(strict_types=1);

/** @return array<int, array{event:array, draft:array}> */
function robot_inbox_items(string $path): array
{
    if (!is_file($path) || filesize($path) > 1024 * 1024) return [];
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || ($data['schemaVersion'] ?? null) !== 1 || !isset($data['items']) || !is_array($data['items'])) return [];
    $items = [];
    foreach (array_slice($data['items'], 0, 100) as $item) {
        if (!is_array($item) || !isset($item['event'], $item['draft']) || !is_array($item['event']) || !is_array($item['draft'])) continue;
        $event = $item['event']; $draft = $item['draft'];
        if (!is_array($event['source'] ?? null) || !is_array($event['facts'] ?? null)) continue;
        $url = $event['source']['url'] ?? null;
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
        $football = ($event['type'] ?? null) === 'football.match.finished'
            && ($event['editorialStatus'] ?? null) === 'review'
            && $host === 'www.fotball.no';
        $news = ($event['type'] ?? null) === 'news.item.discovered'
            && ($event['editorialStatus'] ?? null) === 'new'
            && in_array($host, ['www.bomlo.kommune.no', 'www.nrk.no', 'nrk.no'], true);
        if ((!$football && !$news)
            || ($draft['status'] ?? null) !== 'review'
            || !is_string($event['id'] ?? null)
            || ($draft['eventId'] ?? null) !== $event['id']
            || !is_string($draft['title'] ?? null)
            || !is_string($draft['body'] ?? null)
            || !is_string($url)
            || parse_url($url, PHP_URL_SCHEME) !== 'https') continue;
        $items[] = $item;
    }
    return $items;
}
