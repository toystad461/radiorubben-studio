<?php
declare(strict_types=1);
require_once __DIR__ . '/news-script.php';
require_once __DIR__ . '/programs.php';
require_once __DIR__ . '/source-identity.php';

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

/** Inputs shared by radio variants. Web prose alone is not an audio source. */
function studio_board_audio_identity(array $item): array
{
    return [$item['title'] ?? '', $item['script'] ?? '', $item['program'] ?? '',
        $item['sourceUrl'] ?? '', $item['sourceAt'] ?? '', $item['channel'] ?? 'both',
        ($item['status'] ?? '') === 'archived',
        $item['sourceCheck']['source']['sha256'] ?? '', $item['web']['check']['source']['sha256'] ?? ''];
}

/** Relevant source changes invalidate composite approvals permanently, including edit/restore. */
function studio_board_bulletin_identity(array $item):string {
    return hash('sha256',json_encode([studio_board_audio_identity($item),$item['sourceCheck']??[], $item['verified']??false,$item['approvedBy']??null,$item['status']??''],JSON_THROW_ON_ERROR));
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
        $previous = []; $pendingAudio = [];
        foreach ($board['items'] as $row) {
            $previous[$row['id']] = studio_board_audio_identity($row);
            foreach ($row['audioScripts'] ?? [] as $variant) {
                if (in_array($variant['audio']['status'] ?? '', ['generating', 'unknown'], true)) $pendingAudio[$row['id']] = true;
            }
        }
        $result = $change($board);
        foreach ($board['items'] as &$row) {
            if (($row['status'] ?? '') === 'archived' && isset($pendingAudio[$row['id']])) throw new InvalidArgumentException('Avklar pågående eller uavklart TTS før saken arkiveres.');
            if (!isset($previous[$row['id']]) || $previous[$row['id']] === studio_board_audio_identity($row)) continue;
            // Invalidations persist even if a later edit restores the old wording.
            foreach ($row['audioScripts'] ?? [] as $profile => $variant) {
                $row['audioScripts'][$profile]['scriptApproval'] = null;
                if (isset($variant['audio'])) $row['audioScripts'][$profile]['audio']['approval'] = null;
            }
            unset($row['audioQueue']);
        }
        unset($row);
        $live=array_column($board['items'],null,'id');
        foreach($board['items']as&$row){
            if(empty($row['bulletin']['valid']))continue;
            foreach($row['bulletin']['sources']as$source){
                if(!isset($live[$source['id']])||studio_board_bulletin_identity($live[$source['id']])!==$source['hash']){
                    $row['bulletin']['valid']=false;
                    foreach($row['audioScripts']??[]as$key=>$v){$row['audioScripts'][$key]['scriptApproval']=null;if(isset($v['audio']))$row['audioScripts'][$key]['audio']['approval']=null;}
                    unset($row['audioQueue']);$row['revision']++;break;
                }
            }
        }
        unset($row);
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
        if (($item['status'] ?? '') === 'ready' && ((isset($item['sourceCheck']) && !studio_news_check_current($item)) || !studio_news_radio_credit($item))) {
            $item['status'] = 'draft'; $item['approvedBy'] = null;
        }
    }
    unset($item);
    return $items;
}

/** Legacy items retain both editorial uses until an editor chooses. */
function studio_board_channel(array $item): string
{
    return in_array($item['channel'] ?? '', ['radio', 'web', 'both'], true) ? $item['channel'] : 'both';
}

function studio_board_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function studio_board_has_source(array $items, array $source): bool
{
    $identity = studio_source_identity((string)($source['url'] ?? ''));
    foreach ($items as $item) {
        if (!empty($source['id']) && ($item['originId'] ?? null) === $source['id']) return true;
        if ($identity !== null && !empty($item['originId'])
            && studio_source_identity((string)($item['sourceUrl'] ?? '')) === $identity) return true;
    }
    return false;
}

function studio_board_add_source(array $source, array $user, ?string $path = null): void
{
    studio_board_change(static function (array &$board) use ($source, $user): void {
        if (studio_board_has_source(studio_board_active($board), $source)) return;
        if (count(studio_board_active($board)) >= 30) throw new InvalidArgumentException('Sendelisten har plass til 30 aktive punkter. Arkiver et punkt først.');
        if (!is_string($source['id'] ?? null) || !is_string($source['title'] ?? null)) throw new InvalidArgumentException('Ugyldig kildesak.');
        $board['items'][] = [
            'id'=>bin2hex(random_bytes(8)), 'originId'=>$source['id'],
            'title'=>$source['title'], 'sourceName'=>$source['sourceName'] ?? 'Kilde',
            'sourceUrl'=>$source['url'] ?? '', 'sourceAt'=>$source['publishedAt'] ?? null,
            'capturedAt'=>$source['fetchedAt'] ?? gmdate('c'), 'summary'=>$source['summary'] ?? '',
            'sourceFeeds'=>$source['feedIds'] ?? [],
            'program'=>'god-morgen-vestland', 'script'=>'', 'notes'=>'', 'status'=>'draft', 'verified'=>false,
            'createdAt'=>gmdate('c'), 'updatedAt'=>gmdate('c'),
            'createdBy'=>$user['name'] ?? 'Medarbeider', 'approvedBy'=>null, 'revision'=>1,
        ];
    }, $path);
}

function studio_board_add_manual(string $title, array $user, ?string $path = null): void
{
    $title = trim($title);
    if ($title === '' || studio_board_length($title) > 180 || preg_match('/[\x00-\x1f\x7f]/', $title)) throw new InvalidArgumentException('Skriv en tittel på inntil 180 tegn.');
    studio_board_change(static function (array &$board) use ($title, $user): void {
        if (count(studio_board_active($board)) >= 30) throw new InvalidArgumentException('Sendelisten er full.');
        $board['items'][] = [
            'id'=>bin2hex(random_bytes(8)), 'originId'=>null, 'title'=>$title,
            'sourceName'=>'Eget punkt', 'sourceUrl'=>'', 'sourceAt'=>null,
            'capturedAt'=>null, 'summary'=>'', 'program'=>'god-morgen-vestland', 'script'=>'', 'notes'=>'',
            'status'=>'draft', 'verified'=>false, 'createdAt'=>gmdate('c'),
            'updatedAt'=>gmdate('c'), 'createdBy'=>$user['name'] ?? 'Medarbeider',
            'approvedBy'=>null, 'revision'=>1,
        ];
    }, $path);
}

function studio_board_update(string $id, int $revision, string $action, array $input, array $user, ?string $path = null): void
{
    studio_board_change(static function (array &$board) use ($id, $revision, $action, $input, $user): void {
        foreach (array_keys($board['items']) as $position) {
            if (!is_array($board['items'][$position]) || ($board['items'][$position]['id'] ?? null) !== $id
                || ($board['items'][$position]['status'] ?? '') === 'archived') continue;
            $item = &$board['items'][$position];
            if (($item['revision'] ?? 0) !== $revision) throw new InvalidArgumentException('Punktet ble endret av en annen medarbeider. Last siden på nytt.');
            // Keep the previous state before every successful editorial mutation.
            // Old items remain unassigned until a person chooses their program.
            $before = $item;
            unset($before['history']);
            if(isset($item['bulletin'])&&!in_array($action,['archive','up','down'],true))throw new InvalidArgumentException('Endre enkeltsakene og lag en ny samlet sending.');
            if ($action === 'channel') {
                if (!in_array($user['role'] ?? '', ['admin', 'producer', 'presenter'], true))
                    throw new InvalidArgumentException('Ingen skrivetilgang.');
                $channel = $input['channel'] ?? '';
                if (!is_string($channel) || !in_array($channel, ['radio', 'web', 'both'], true))
                    throw new InvalidArgumentException('Velg radio, nett eller begge.');
                if (in_array($item['web']['delivery']['state'] ?? '', ['pending', 'unknown'], true))
                    throw new InvalidArgumentException('Avklar nettoverføringen før du endrer bruksområdet.');
                if (($item['web']['delivery']['status'] ?? '') === 'publish' && $channel === 'radio')
                    throw new InvalidArgumentException('Saken er allerede publisert på nett. Kanalvalg trekker den ikke tilbake.');
                $item['channel'] = $channel;
                $item['status'] = 'draft'; $item['verified'] = false; $item['approvedBy'] = null;
                $item['web']['approvedHash'] = null;
            } elseif ($action === 'save') {
                $program = (string)($input['program'] ?? $item['program'] ?? '');
                if ($program !== '' && !isset(studio_program_registry()['programs'][$program]))
                    throw new InvalidArgumentException('Velg et gyldig program.');
                $item['program'] = $program;
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
                if (!studio_news_radio_credit($item)) throw new InvalidArgumentException('NRK må krediteres tidlig i radiomanuset før godkjenning.');
                if (studio_board_channel($item) === 'web') throw new InvalidArgumentException('Velg radiomateriale før godkjenning til sending.');
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


/** Detect edits, additions and reordering while the confirmation is open. */
function studio_board_snapshot(array $board): string
{
    return hash('sha256', json_encode(array_map(static fn($item) => [$item['id'], $item['revision']], studio_board_active($board)), JSON_THROW_ON_ERROR));
}

function studio_board_clear(string $snapshot, array $user, ?string $path = null, ?array $selected = null): string
{
    if (!in_array($user['role'] ?? '', ['admin', 'producer', 'presenter'], true)) throw new InvalidArgumentException('Ingen tilgang.');
    return studio_board_change(static function (array &$board) use ($snapshot, $user, $selected): string {
        if (!hash_equals(studio_board_snapshot($board), $snapshot)) throw new InvalidArgumentException('Sendelisten er endret. Last siden på nytt før du tømmer den.');
        if ($selected !== null) {
            $valid = array_column(studio_board_active($board), 'id');
            if (!$selected || count($selected) > 30 || array_diff($selected, $valid)) throw new InvalidArgumentException('Velg minst én gyldig sak.');
        }
        $batch = bin2hex(random_bytes(16));
        foreach ($board['items'] as &$item) {
            if (($item['status'] ?? '') === 'archived' || ($selected !== null && !in_array($item['id'], $selected, true))) continue;
            $before = $item; unset($before['history']);
            $item['history'][] = ['action'=>'archive_all', 'at'=>gmdate('c'), 'actor'=>$user['name'] ?? 'Medarbeider', 'before'=>$before];
            $item['status'] = 'archived'; $item['approvedBy'] = null;
            $item['archiveBatch'] = $batch; $item['revision']++; $item['updatedAt'] = gmdate('c');
        }
        unset($item); return $batch;
    }, $path);
}

function studio_board_undo_clear(string $batch, array $user, ?string $path = null): void
{
    if (!in_array($user['role'] ?? '', ['admin', 'producer', 'presenter'], true) || !preg_match('/^[a-f0-9]{32}$/D', $batch)) throw new InvalidArgumentException('Ugyldig gjenoppretting.');
    studio_board_change(static function (array &$board) use ($batch, $user): void {
        $count = 0;
        foreach ($board['items'] as $item) if (($item['archiveBatch'] ?? '') === $batch && $item['status'] === 'archived') $count++;
        if (!$count || count(studio_board_active($board)) + $count > 30) throw new InvalidArgumentException('Kan ikke gjenopprette: ingen punkter eller for lite plass i listen.');
        foreach ($board['items'] as &$item) {
            if (($item['archiveBatch'] ?? '') !== $batch || $item['status'] !== 'archived') continue;
            $before = $item; unset($before['history']);
            $item['history'][] = ['action'=>'undo_archive_all', 'at'=>gmdate('c'), 'actor'=>$user['name'] ?? 'Medarbeider', 'before'=>$before];
            $item['status'] = 'draft'; $item['verified'] = false; $item['approvedBy'] = null;
            unset($item['archiveBatch']); $item['revision']++; $item['updatedAt'] = gmdate('c');
        }
        unset($item);
    }, $path);
}
