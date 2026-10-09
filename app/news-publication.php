<?php
declare(strict_types=1);

/** Verified on radiorubben.no on 2026-10-04; IDs belong to this site only. */
const STUDIO_NEWS_PUBLICATION_VERSION = 'news-web-1';
const STUDIO_NEWS_IMAGE = 1079;
const STUDIO_NEWS_IMAGE_URL = 'https://www.radiorubben.no/wp-content/uploads/2026/10/Radio-Rubben-–-Nyheter-1024x576.png';

function studio_web_is_news(array $item): bool
{
    return !empty($item['originId']) && studio_news_allowed_url((string)($item['sourceUrl'] ?? ''));
}

/** Source-based default; NRK stories are not automatically labelled as local to Bømlo. */
function studio_news_publication(array $item, ?string $scope = null): array
{
    $scope ??= parse_url((string)($item['sourceUrl'] ?? ''), PHP_URL_HOST) === 'www.bomlo.kommune.no' ? 'local' : 'news';
    if (!in_array($scope, ['news', 'local'], true)) throw new InvalidArgumentException('Velg Nyheter eller Nyheter og Lokale Nyheter.');
    return ['version'=>STUDIO_NEWS_PUBLICATION_VERSION, 'scope'=>$scope,
        'featured_media'=>STUDIO_NEWS_IMAGE, 'categories'=>$scope === 'local' ? [8, 27] : [8]];
}

function studio_news_publication_metadata(array $item): array
{
    $profile = $item['web']['publication'] ?? null;
    if ($profile === null) return [];
    if (!is_array($profile) || !is_string($profile['scope'] ?? null)
        || $profile !== studio_news_publication($item, $profile['scope']))
        throw new InvalidArgumentException('Nyhetsprofilen er endret. Lagre og godkjenn nettsaken på nytt.');
    return ['featured_media'=>$profile['featured_media'], 'categories'=>$profile['categories']];
}

function studio_web_presentation_ready(array $item): bool
{
    if (studio_web_is_news($item) && empty($item['web']['delivery']['id'])
        && !isset($item['web']['publication'])) return false;
    try { studio_news_publication_metadata($item); return true; }
    catch (InvalidArgumentException $e) { return false; }
}

/** Bind approval and delivery deduplication to text, attribution and saved presentation. */
function studio_web_approval_hash(array $item): string
{
    $w = $item['web'] ?? [];
    $payload=['version'=>'web-approval-2',
        'title'=>$w['title'] ?? '', 'intro'=>$w['intro'] ?? '', 'body'=>$w['body'] ?? '',
        'sourceName'=>$item['sourceName'] ?? '', 'sourceUrl'=>$item['sourceUrl'] ?? '',
        'publication'=>$w['publication'] ?? null];
    // Preserve delivered legacy hashes; bind new AI disclosure to new approvals.
    if (!empty($w['generation']['aiAssisted'])) {
        $payload['version']='web-approval-3'; $payload['aiAssisted']=true;
    }
    // New policy-aware drafts bind provenance as well as the visible text.
    // Do not rewrite delivered hashes for earlier drafts without this field.
    if (array_key_exists('aiPolicyVersion', $w['generation'] ?? [])) {
        $payload['version']='web-approval-4'; $payload['generation']=$w['generation'];
    }
    return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
}

function studio_web_status_label(array $item): string
{
    $w = $item['web'] ?? []; $d = $w['delivery'] ?? [];
    if (($d['state'] ?? '') === 'pending') return 'Overføring pågår';
    if (($d['state'] ?? '') === 'unknown') return 'Overføringen må avklares';
    if (($d['state'] ?? '') === 'confirmed' && ($d['hash'] ?? '') === studio_web_approval_hash($item))
        return ($d['status'] ?? '') === 'publish' ? 'Publisert på radiorubben.no' : 'WordPress-kladd lagret';
    return studio_web_checked($item) ? 'Venter på din sluttgodkjenning' : 'Trenger kontroll / rettelser';
}
