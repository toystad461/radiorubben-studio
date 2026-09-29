<?php
declare(strict_types=1);
require_once __DIR__ . '/programs.php';
require_once __DIR__ . '/news-script.php';

/** Shared editorial rundown. This file lives outside the public document root. */
function studio_board_path(): string
{
    return dirname(__DIR__) . '/config/sending-board.json';
}

function studio_board_read(?string $path = null): array
{
    $path ??= studio_board_path();
    if (!is_file($path)) return ['items'=>[], 'updatedAt'=>null];
    $data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !is_array($data['items'] ?? null)) throw new RuntimeException('Ugyldig sendeliste.');
    return $data;
}

/** Lock a separate file so atomic rename never invalidates another writer's lock. */
function studio_board_change(callable $change, ?string $path = null): mixed
{
    $path ??= studio_board_path();
    $lock = fopen($path . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Sendelisten er midlertidig utilgjengelig.');
    chmod($path . '.lock', 0600);
    $temp = null;
    try {
        $board = studio_board_read($path);
        $result = $change($board);
        $board['updatedAt'] = gmdate('c');
        $temp = tempnam(dirname($path), '.sending-');
        if (!$temp || !chmod($temp, 0600)
            || file_put_contents($temp, json_encode($board, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) === false
            || !rename($temp, $path)) throw new RuntimeException('Sendelisten kunne ikke lagres.');
        return $result;
    } finally {
        if ($temp && is_file($temp)) unlink($temp);
        flock($lock, LOCK_UN); fclose($lock);
    }
}

function studio_board_active(array $board): array
{
    $items = array_values(array_filter($board['items'] ?? [], static fn($item) => is_array($item) && ($item['status'] ?? '') !== 'archived'));
    // Expiry must also affect exports and consumers of the rundown, not only the badge.
    foreach ($items as &$item) {
        if (($item['status'] ?? '') === 'ready' && isset($item['sourceCheck']) && !studio_news_check_current($item)) {
            $item['status'] = 'draft'; $item['approvedBy'] = null;
        }
    }
    unset($item);
    return $items;
}

function studio_board_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function studio_board_add_source(array $source, array $user, ?string $path = null, ?array $registry = null): void
{
    studio_board_change(static function (array &$board) use ($source, $user, $registry): void {
        foreach (studio_board_active($board) as $item) {
            if (($item['originId'] ?? null) === ($source['id'] ?? null)) return;
        }
        if (count(studio_board_active($board)) >= 30) throw new InvalidArgumentException('Sendelisten har plass til 30 aktive punkter. Arkiver et punkt først.');
        if (!is_string($source['id'] ?? null) || !is_string($source['title'] ?? null)) throw new InvalidArgumentException('Ugyldig kildesak.');
        $board['items'][] = [
            'id'=>bin2hex(random_bytes(8)), 'originId'=>$source['id'],
            'title'=>$source['title'], 'sourceName'=>$source['sourceName'] ?? 'Kilde',
            'sourceUrl'=>$source['url'] ?? '', 'sourceAt'=>$source['publishedAt'] ?? null,
            'capturedAt'=>$source['fetchedAt'] ?? gmdate('c'), 'summary'=>$source['summary'] ?? '',
            'program'=>studio_program_default($registry), 'script'=>'', 'notes'=>'', 'status'=>'draft', 'verified'=>false,
            'createdAt'=>gmdate('c'), 'updatedAt'=>gmdate('c'),
            'createdBy'=>$user['name'] ?? 'Medarbeider', 'approvedBy'=>null, 'revision'=>1,
        ];
    }, $path);
}

function studio_board_add_manual(string $title, array $user, ?string $path = null, ?array $registry = null): void
{
    $title = trim($title);
    if ($title === '' || studio_board_length($title) > 180 || preg_match('/[\x00-\x1f\x7f]/', $title)) throw new InvalidArgumentException('Skriv en tittel på inntil 180 tegn.');
    studio_board_change(static function (array &$board) use ($title, $user, $registry): void {
        if (count(studio_board_active($board)) >= 30) throw new InvalidArgumentException('Sendelisten er full.');
        $board['items'][] = [
            'id'=>bin2hex(random_bytes(8)), 'originId'=>null, 'title'=>$title,
            'sourceName'=>'Eget punkt', 'sourceUrl'=>'', 'sourceAt'=>null,
            'capturedAt'=>null, 'summary'=>'', 'program'=>studio_program_default($registry), 'script'=>'', 'notes'=>'',
            'status'=>'draft', 'verified'=>false, 'createdAt'=>gmdate('c'),
            'updatedAt'=>gmdate('c'), 'createdBy'=>$user['name'] ?? 'Medarbeider',
            'approvedBy'=>null, 'revision'=>1,
        ];
    }, $path);
}

function studio_board_update(string $id, int $revision, string $action, array $input, array $user, ?string $path = null, ?array $registry = null): void
{
    studio_board_change(static function (array &$board) use ($id, $revision, $action, $input, $user, $registry): void {
        foreach (array_keys($board['items']) as $position) {
            if (!is_array($board['items'][$position]) || ($board['items'][$position]['id'] ?? null) !== $id
                || ($board['items'][$position]['status'] ?? '') === 'archived') continue;
            $item = &$board['items'][$position];
            if (($item['revision'] ?? 0) !== $revision) throw new InvalidArgumentException('Punktet ble endret av en annen medarbeider. Last siden på nytt.');
            // Keep the previous state before every successful editorial mutation.
            // Old items remain unassigned until a person chooses their program.
            $before = $item;
            unset($before['history']);
            if ($action === 'save') {
                $program = (string)($input['program'] ?? $item['program'] ?? '');
                studio_program_profile($program, $registry);
                // Saving an unassigned legacy item must not add a program field.
                if ($program !== '' || array_key_exists('program', $item)) $item['program'] = $program;
                $title = trim((string)($input['title'] ?? ''));
                $script = trim((string)($input['script'] ?? ''));
                $notes = trim((string)($input['notes'] ?? ''));
                if ($title === '' || studio_board_length($title) > 180 || preg_match('/[\x00-\x1f\x7f]/', $title)
                    || studio_board_length($script) > 5000 || studio_board_length($notes) > 1000)
                    throw new InvalidArgumentException('Kontroller lengden på tittel, manus og notater.');
                $item['title'] = $title; $item['script'] = $script; $item['notes'] = $notes;
                $item['verified'] = ($input['verified'] ?? '') === '1';
                $item['status'] = 'draft'; $item['approvedBy'] = null;
            } elseif ($action === 'generated') {
                $script = trim((string)($input['script'] ?? ''));
                if (!$item['originId'] || $script === '' || studio_board_length($script) > 2200)
                    throw new InvalidArgumentException('Manusutkastet kan ikke lagres for dette punktet.');
                $item['script'] = $script;
                $item['generatedAt'] = gmdate('c');
                $item['generation'] = $input['generation'] ?? [];
                if (isset($input['sourceCheck'])) $item['sourceCheck'] = $input['sourceCheck'];
                elseif (isset($item['sourceCheck'])) $item['sourceCheck'] = ['status'=>'needs_review'];
                $item['generatedOriginal'] = $script;
                $item['verified'] = false;
                $item['status'] = 'draft'; $item['approvedBy'] = null;
            } elseif ($action === 'source_checked') {
                if (!is_array($input['sourceCheck'] ?? null)) throw new InvalidArgumentException('Ugyldig kildekontroll.');
                $item['sourceCheck'] = $input['sourceCheck'];
                $item['verified'] = false;
                $item['status'] = 'draft'; $item['approvedBy'] = null;
            } elseif ($action === 'ready') {
                if (isset($item['sourceCheck']) && !studio_news_check_current($item))
                    throw new InvalidArgumentException('Kjør kildekontroll på nytt. Manuset er endret, kontrollen har avvik eller den er eldre enn én time.');
                if (trim((string)($item['script'] ?? '')) === '' || empty($item['verified']))
                    throw new InvalidArgumentException('Lagre manus og bekreft kildekontroll før du merker punktet klart.');
                $item['status'] = 'ready'; $item['approvedBy'] = $user['name'] ?? 'Medarbeider';
            } elseif ($action === 'draft') {
                $item['status'] = 'draft'; $item['approvedBy'] = null;
            } elseif ($action === 'archive') {
                $item['status'] = 'archived'; $item['approvedBy'] = null;
            } elseif ($action === 'up' || $action === 'down') {
                $direction = $action === 'up' ? -1 : 1;
                $other = $position + $direction;
                while (isset($board['items'][$other]) && ($board['items'][$other]['status'] ?? '') === 'archived') $other += $direction;
                if (isset($board['items'][$other])) {
                    unset($item);
                    [$board['items'][$other], $board['items'][$position]] = [$board['items'][$position], $board['items'][$other]];
                    $item = &$board['items'][$other];
                }
            } else throw new InvalidArgumentException('Ukjent handling.');
            if (!in_array($action, ['up', 'down'], true)) {
                $item['history'][] = ['action'=>$action, 'at'=>gmdate('c'),
                    'actor'=>$user['name'] ?? 'Medarbeider', 'before'=>$before];
            }
            $item['updatedAt'] = gmdate('c'); $item['revision']++;
            unset($item);
            return;
        }
        throw new InvalidArgumentException('Punktet finnes ikke lenger.');
    }, $path);
}

