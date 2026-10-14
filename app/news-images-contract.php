<?php
declare(strict_types=1);

const RRNI_PACKAGE = 'RadioRubben-Nyhetsbilder-v1.2';
const RRNI_MANIFEST_SHA = '0f090e476d8042339e1dab75b7653b34548ff24496294bb38908b3db0fabe0da';
const RRNI_CAPTION = 'KI-generert illustrasjonsbilde. Radio Rubben.';

function rrni_manifest(): array {
    static $manifest;
    if ($manifest !== null) return $manifest;
    $raw = file_get_contents(__DIR__.'/data/01-BILDEREGISTER.json');
    if (!is_string($raw) || !hash_equals(RRNI_MANIFEST_SHA, hash('sha256', $raw))) throw new RuntimeException('Bilderegisteret er endret eller mangler.');
    return $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
}
function rrni_assets(): array {
    return array_column(rrni_manifest()['assets'], null, 'asset_id');
}
function rrni_themes(): array {
    return array_column(array_filter(rrni_assets(), static fn($a) => $a['format'] === 'liggende'), null, 'theme_id');
}
function rrni_check_bytes(string $bytes, array $asset): void {
    $info = @getimagesizefromstring($bytes);
    if (strlen($bytes) !== $asset['file_size_bytes'] || !hash_equals($asset['sha256'], hash('sha256', $bytes))
        || !$info || $info[0] !== $asset['width_px'] || $info[1] !== $asset['height_px'] || ($info['mime'] ?? '') !== 'image/png') {
        throw new RuntimeException('Fil, dimensjoner eller kontrollsum avviker: '.$asset['asset_id']);
    }
}
/** Never extract untrusted ZIP paths. Validate the entire pinned package before writes. */
function rrni_read_package(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Bildepakken kan ikke åpnes.');
    try {
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (isset($names[$name]) || str_contains($name, '..') || str_contains($name, '\\') || !str_starts_with($name, RRNI_PACKAGE.'/')) throw new RuntimeException('Ugyldig eller gjentatt ZIP-sti.');
            $names[$name] = true;
        }
        $raw = $zip->getFromName(RRNI_PACKAGE.'/01-BILDEREGISTER.json', 100000);
        if (!is_string($raw) || !hash_equals(RRNI_MANIFEST_SHA, hash('sha256', $raw))) throw new RuntimeException('Pakkeregisteret er ikke den avtalte v1.2-versjonen.');
        $files = [];
        foreach (rrni_assets() as $id => $a) {
            $bytes = $zip->getFromName(RRNI_PACKAGE.'/'.$a['relative_path'], $a['file_size_bytes'] + 1);
            if (!is_string($bytes)) throw new RuntimeException('Mangler '.$a['relative_path']);
            rrni_check_bytes($bytes, $a); $files[$id] = $bytes;
        }
        if (count(array_filter(array_keys($names), static fn($n) => str_ends_with(strtolower($n), '.png'))) !== 21) throw new RuntimeException('Pakken skal inneholde nøyaktig 21 PNG-filer.');
        return $files;
    } finally { $zip->close(); }
}
function rrni_url_valid(string $url): bool {
    $u = parse_url($url);
    return is_array($u) && ($u['scheme'] ?? '') === 'https' && in_array($u['host'] ?? '', ['www.radiorubben.no', 'radiorubben.no'], true)
        && !isset($u['user'], $u['pass']) && !isset($u['user']) && !isset($u['pass']) && !isset($u['port']) && !isset($u['query']) && !isset($u['fragment'])
        && str_starts_with($u['path'] ?? '', '/wp-content/uploads/') && !str_contains(rawurldecode($u['path'] ?? ''), '..') && str_ends_with($u['path'] ?? '', '.png');
}
function rrni_catalog_validate(array $catalog): array {
    if (($catalog['package'] ?? '') !== RRNI_PACKAGE || ($catalog['manifest_sha256'] ?? '') !== RRNI_MANIFEST_SHA) throw new RuntimeException('Mediekoblingen gjelder feil bildepakke.');
    $result = [];
    foreach (rrni_assets() as $id => $a) {
        $row = $catalog['assets'][$id] ?? null;
        if (!is_array($row) || !is_int($row['media_id'] ?? null) || $row['media_id'] < 1 || !rrni_url_valid((string)($row['url'] ?? ''))
            || ($row['sha256'] ?? '') !== $a['sha256'] || ($row['width_px'] ?? 0) !== $a['width_px'] || ($row['height_px'] ?? 0) !== $a['height_px']) {
            throw new RuntimeException('Mediefil eller opplasting mangler: '.$id);
        }
        $result[$id] = $row;
    }
    if (count(array_unique(array_column($result, 'media_id'))) !== 21) throw new RuntimeException('Dupliserte medieidentiteter i registeret.');
    return $result;
}
function rrni_context(array $item): array {
    $w = $item['web'] ?? [];
    return ['title'=>$w['title'] ?? $item['title'] ?? '', 'intro'=>$w['intro'] ?? '', 'body'=>$w['body'] ?? '',
        'category'=>$w['publication']['scope'] ?? $item['category'] ?? '', 'entities'=>$item['entities'] ?? []];
}
function rrni_context_hash(array $context): string { return hash('sha256', json_encode($context, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }
function rrni_pending(string $reason, string $status = 'INGEN_PASSENDE_BILDE'): array {
    return ['status'=>$status, 'package'=>RRNI_PACKAGE, 'package_version'=>'1.2', 'manifest_sha256'=>RRNI_MANIFEST_SHA, 'theme_id'=>null, 'variants'=>[], 'reason'=>$reason, 'mode'=>'automatic', 'locked'=>false];
}
/** An LLM proposes semantics; strict structure, literal evidence and club guards fail closed. */
function rrni_assessment(array $context, array $answer): array {
    $theme = $answer['theme_id'] ?? null;
    $reason = trim((string)($answer['reason'] ?? ''));
    if ($theme === null) return rrni_pending($reason ?: 'Ingen av motivene har dokumentert samsvar med hovedinnholdet.');
    if (!is_string($theme) || !isset(rrni_themes()[$theme]) || strlen($reason) < 12 || strlen($reason) > 1600
        || ($answer['motif_fits'] ?? false) !== true || !is_numeric($answer['confidence'] ?? null) || $answer['confidence'] < 0.8) return rrni_pending('Tematisk samsvar er usikkert. Velg bilde manuelt.');
    $text = implode("\n", array_map(static fn($v) => is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE), $context));
    $evidence = $answer['evidence'] ?? [];
    if (!is_array($evidence) || !$evidence || count($evidence) > 5) return rrni_pending('Bildebegrunnelsen mangler belegg i saken.');
    foreach ($evidence as $quote) if (!is_string($quote) || strlen($quote) < 8 || !str_contains($text, $quote)) return rrni_pending('Bildebegrunnelsen kan ikke knyttes til teksten.');
    $angle = $answer['main_angle'] ?? '';
    $allowed = ['politikk'=>['political_decision','political_debate'], 'kommunesaker'=>['municipal_service','administration','planning'],
        'bremnes-fotball'=>['bremnes_football'], 'sport'=>['sport_training','sport_event'], 'kultur-arrangement'=>['music','stage_culture'],
        'naeringsliv'=>['maritime','industry','craft'], 'lokalnyheter'=>['local_community']];
    if (!in_array($angle, $allowed[$theme], true)) return rrni_pending('Motivet og den oppgitte hovedvinklingen samsvarer ikke.');
    if ($theme === 'bremnes-fotball' && (($answer['bremnes_focus'] ?? false) !== true || !in_array($answer['primary_club'] ?? '', ['Bremnes','Bremnes IL'], true)
        || !preg_match('/\bBremnes\b/ui', implode(' ', $evidence)))) return rrni_pending('Bremnes-logo krever en uttrykkelig Bremnes IL-fotballvinkling.');
    return ['status'=>'selected', 'package'=>RRNI_PACKAGE, 'package_version'=>'1.2', 'manifest_sha256'=>RRNI_MANIFEST_SHA, 'theme_id'=>$theme, 'variants'=>[], 'reason'=>$reason,
        'mode'=>'automatic', 'locked'=>false, 'evidence'=>$evidence, 'assessment'=>['main_angle'=>$angle,'primary_club'=>$answer['primary_club'] ?? null]];
}
function rrni_bind(array $choice, array $catalog): array {
    if (($choice['status'] ?? '') !== 'selected') return $choice;
    $media = rrni_catalog_validate($catalog); $choice['variants'] = [];
    foreach (rrni_assets() as $id => $a) if ($a['theme_id'] === $choice['theme_id']) {
        $choice['variants'][$a['placement']] = ['asset_id'=>$id, 'media_id'=>$media[$id]['media_id'], 'url'=>$media[$id]['url'], 'sha256'=>$a['sha256'],
            'width_px'=>$a['width_px'], 'height_px'=>$a['height_px'], 'format'=>$a['format'], 'alt_text'=>$a['alt_text'], 'caption'=>$a['caption'], 'crop_allowed'=>false];
    }
    return $choice;
}
function rrni_selection_valid(array $choice): bool {
    if (($choice['package'] ?? '') !== RRNI_PACKAGE || ($choice['package_version'] ?? '') !== '1.2' || ($choice['manifest_sha256'] ?? '') !== RRNI_MANIFEST_SHA || !in_array($choice['mode'] ?? '', ['automatic','manual'], true)) return false;
    if (($choice['status'] ?? '') === 'manual_none') return $choice['mode'] === 'manual' && ($choice['locked'] ?? false) === true && strlen($choice['reason'] ?? '') >= 12;
    if (($choice['status'] ?? '') !== 'selected' || !isset(rrni_themes()[$choice['theme_id'] ?? '']) || count($choice['variants'] ?? []) !== 3) return false;
    foreach (rrni_assets() as $id => $a) if ($a['theme_id'] === $choice['theme_id']) {
        $v = $choice['variants'][$a['placement']] ?? [];
        foreach (['asset_id','sha256','width_px','height_px','format','alt_text','caption','crop_allowed'] as $key) if (($v[$key] ?? null) !== $a[$key]) return false;
        if (!is_int($v['media_id'] ?? null) || $v['media_id'] < 1 || !rrni_url_valid($v['url'] ?? '')) return false;
    }
    return true;
}
