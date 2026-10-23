<?php
declare(strict_types=1);

// CLI-only engine adapter. It sees the existing private manifest/spool, never
// Studio credentials, sessions, the original library or an external API.
require_once __DIR__.'/music-playout.php';

function music_liquidsoap_id(mixed $value): bool {
    return is_string($value) && preg_match('/^[a-f0-9]{32}$/D', $value) === 1;
}

function music_liquidsoap_runtime(string $path): array {
    if (is_link($path) || !is_file($path)) throw new RuntimeException('Missing private engine configuration.');
    $config = music_playout_json($path);
    if (($config['host'] ?? null) !== '127.0.0.1'
        || !is_int($config['port'] ?? null) || $config['port'] < 1024 || $config['port'] > 65535
        || ($config['mount'] ?? null) !== '/rubben-test.mp3'
        || !is_string($config['password'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $config['password'])) {
        throw new RuntimeException('Only the configured private loopback test output is supported.');
    }
    return $config;
}

function music_liquidsoap_entry(array $entry): void {
    if (!music_liquidsoap_id($entry['id'] ?? null) || !music_liquidsoap_id($entry['trackId'] ?? null)
        || ($entry['kind'] ?? null) !== 'music'
        || !is_string($entry['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'])
        || !is_numeric($entry['duration'] ?? null) || !is_finite((float)$entry['duration'])
        || $entry['duration'] <= 0 || $entry['duration'] > 14400
        || !is_array($entry['blocked'] ?? null)) {
        throw new RuntimeException('Invalid music occurrence in the private manifest.');
    }
}

function music_liquidsoap_queued(array $control, string $id): ?array {
    foreach ($control['entries'] ?? [] as $entry) if (($entry['id'] ?? null) === $id) return $entry;
    return null;
}

function music_liquidsoap_outcome(string $root, array &$state, array $entry, string $event, string $message): void {
    $entry['status'] = $event;
    $entry['endedAt'] = gmdate('c');
    $entry['message'] = $message;
    $entry['evidence'] = 'liquidsoap-pcm';
    unset($state['active'][$entry['id']], $state['reserved'][$entry['id']]);
    $state['outcomes'][$entry['id']] = $entry;
    $state['active'] ??= [];
    $state['current'] = $state['active'] ? end($state['active']) : null;
    if ($event === 'completed') $state['lastCompleted'] = $entry;
    $state['message'] = $message;
    music_playout_event($root, $state, $event, ['entry' => $entry]);
}

/** Caller holds control.lock. Only a fresh explicit Start makes a new session. */
function music_liquidsoap_reset(string $root, array &$state, string $message): void {
    $active = $state['active'] ?? [];
    if ($state['current']) $active[$state['current']['id']] = $state['current'];
    $state['active'] = $active;
    foreach ($active as $entry) music_liquidsoap_outcome($root, $state, $entry, 'interrupted', $message);
    // A reserved occurrence has not reached PCM. Release it; never call it played.
    $state['reserved'] = [];
    $state['active'] = [];
    $state['current'] = null;
    $state['session'] = null;
    $state['connected'] = false;
    $state['position'] = 0.0;
    $state['message'] = $message;
}

/** request.dynamic calls this through a fixed CLI command, not through the web. */
function music_liquidsoap_claim(string $root, string $session): string {
    if (!music_liquidsoap_id($session)) throw new RuntimeException('Invalid engine session.');
    $lock = music_playout_lock($root);
    try {
        $state = music_playout_state($root);
        if (($state['engine'] ?? null) !== 'liquidsoap' || ($state['session'] ?? null) !== $session
            || !in_array($state['mode'], ['starting', 'playing'], true) || !($state['connected'] ?? false)) return '';
        $live = music_playout_json($root.'/heartbeat.json');
        if (!music_playout_heartbeat_fresh($state, $live)) return '';
        $control = music_playout_json($root.'/control.json');
        if (($control['ready'] ?? false) !== true) return '';
        // HTTP may have written Stop just before this prefetch process acquires
        // the lock. Do not wait for the supervisor's next tick to honour it.
        if (($control['command']['action'] ?? null) === 'stop'
            && ($control['command']['id'] ?? null) !== $state['ack']) return '';
        // One not-yet-started occurrence at a time. If a decoder rejects it,
        // request.dynamic must not silently skip ahead to the following song.
        // The supervisor turns that unresolved reservation into a visible fault.
        if (!empty($state['reserved'])) return '';
        $protected = music_playout_protected_ids($state);
        $entry = null;
        foreach ($control['entries'] ?? [] as $candidate) {
            if (!in_array($candidate['id'] ?? null, $protected, true)) { $entry = $candidate; break; }
        }
        if ($entry === null) return '';
        music_liquidsoap_entry($entry);
        if ($entry['blocked']) {
            $state['mode'] = 'blocked';
            $state['message'] = 'Køen er sperret: '.implode(' · ', $entry['blocked']);
            music_playout_event($root, $state, 'blocked', ['entryId' => $entry['id']]);
            return '';
        }
        $audio = $root.'/audio/'.$entry['sha256'].'.mp3';
        if (is_link($audio) || !is_file($audio) || hash_file('sha256', $audio) !== $entry['sha256']) {
            $state['mode'] = 'blocked';
            music_liquidsoap_outcome($root, $state, $entry, 'failed', 'Lydkopien mangler eller har feil kontrollsum.');
            return '';
        }
        $entry['status'] = 'reserved';
        $entry['reservedAt'] = gmdate('c');
        $entry['manifestRevision'] = $control['revision'] ?? null;
        $state['reserved'][$entry['id']] = $entry;
        music_playout_event($root, $state, 'reserved', ['entryId' => $entry['id']]);
        // Only generated hex IDs and a server-controlled spool path become a URI.
        // Untrusted titles, filenames, URLs and cue overrides never become code.
        return 'annotate:rr_entry="'.$entry['id'].'",rr_session="'.$session.'":'.$audio;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

/** Consume machine events from the current child; caller holds control.lock. */
function music_liquidsoap_observe(string $root, array &$state, array $event): void {
    if (($event['session'] ?? null) !== ($state['session'] ?? null) || !music_liquidsoap_id($event['session'] ?? null)) return;
    $kind = $event['event'] ?? '';
    if (!in_array($kind, ['connected', 'disconnected', 'output_error', 'started', 'ended', 'claim_error'], true)) {
        throw new RuntimeException('Unknown engine event.');
    }
    if ($kind === 'connected') {
        $state['connected'] = true;
        music_playout_event($root, $state, 'stream_connected');
        return;
    }
    if (in_array($kind, ['disconnected', 'output_error', 'claim_error'], true)) {
        $state['connected'] = false;
        $state['mode'] = 'blocked';
        $state['message'] = $kind === 'claim_error' ? 'Motoren kunne ikke bekrefte neste køinnslag.' : 'Forbindelsen til teststrømmen ble brutt. Kontroller før ny start.';
        music_playout_event($root, $state, 'engine_fault', ['kind' => $kind]);
        return;
    }
    $id = $event['entry'] ?? null;
    $clock = $event['clock'] ?? null;
    if (!music_liquidsoap_id($id) || (!is_int($clock) && !is_float($clock)) || !is_finite((float)$clock) || $clock < 0) {
        throw new RuntimeException('Invalid engine occurrence event.');
    }
    if (isset($state['outcomes'][$id])) return; // Duplicate callbacks cannot replay a terminal outcome.
    if ($kind === 'started') {
        if (isset($state['active'][$id])) return;
        $entry = $state['reserved'][$id] ?? null;
        if (!$entry || !($state['connected'] ?? false) || !in_array($state['mode'], ['starting', 'playing'], true)) {
            throw new RuntimeException('Engine started an occurrence without a valid reservation/connection.');
        }
        $control = music_playout_json($root.'/control.json');
        $queued = music_liquidsoap_queued($control, $id);
        if (($control['ready'] ?? false) !== true || !$queued || $queued['blocked'] || $queued['sha256'] !== $entry['sha256']) {
            throw new RuntimeException('Reserved occurrence changed before rendering.');
        }
        $audio = $root.'/audio/'.$entry['sha256'].'.mp3';
        if (is_link($audio) || !is_file($audio) || hash_file('sha256', $audio) !== $entry['sha256']) {
            $state['mode'] = 'blocked';
            music_liquidsoap_outcome($root, $state, $entry, 'failed', 'Den reserverte lydkopien mangler eller har endret kontrollsum.');
            return;
        }
        unset($state['reserved'][$id]);
        $entry['startedAt'] = gmdate('c');
        $entry['observedWallStart'] = microtime(true);
        $entry['renderClockStart'] = (float)$clock;
        $entry['status'] = 'started';
        $entry['evidence'] = 'liquidsoap-pcm';
        $state['active'][$id] = $entry;
        $state['current'] = $entry;
        $state['lastStarted'] = $entry;
        $state['mode'] = 'playing';
        $state['message'] = 'Liquidsoap avvikler musikk til den tilkoblede teststrømmen.';
        music_playout_event($root, $state, 'started', ['entryId' => $id]);
        return;
    }
    $entry = $state['active'][$id] ?? null;
    if (!$entry) throw new RuntimeException('Engine ended an occurrence that was never started.');
    $entry['renderClockEnd'] = (float)$clock;
    $entry['renderedSeconds'] = (float)$clock - $entry['renderClockStart'];
    $good = ($state['connected'] ?? false) && $state['mode'] === 'playing'
        // Source clocks have frame resolution: a valid very short file can
        // start and end within the same frame. The PCM callbacks still differ.
        && $entry['renderedSeconds'] >= 0
        && abs($entry['renderedSeconds'] - (float)$entry['duration']) <= max(1.0, (float)$entry['duration'] * .02);
    if (!$good) $state['mode'] = 'blocked';
    music_liquidsoap_outcome($root, $state, $entry, $good ? 'completed' : 'failed',
        $good ? 'Fullført i Liquidsoaps PCM-utgang mens teststrømmen var tilkoblet.' : 'Motorens lydlengde eller tilkobling kunne ikke bekreftes.');
}

function music_liquidsoap_script(string $root, string $session, array $config, string $php, string $rpc): string {
    if (!preg_match('#^/[A-Za-z0-9_./-]+$#D', $root) || !music_liquidsoap_id($session)) throw new RuntimeException('Unsupported private path/session.');
    $json = static fn(mixed $v): string => json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $template = file_get_contents(dirname(__DIR__).'/infra/studio-stream/radio.liq');
    if ($template === false) throw new RuntimeException('Missing engine template.');
    return strtr($template, [
        '{{PHP}}' => $json($php), '{{RPC}}' => $json($rpc), '{{ROOT}}' => $json($root),
        '{{SESSION}}' => $json($session), '{{PORT}}' => (string)$config['port'], '{{PASSWORD}}' => $json($config['password']),
        '{{ALLOW_ROOT}}' => getenv('RR_STREAM_TEST_ALLOW_ROOT') === '1' ? 'true' : 'false',
    ]);
}
