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
        || !in_array($entry['kind'] ?? null, ['music','jingle'], true)
        || !is_string($entry['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'])
        || !is_numeric($entry['duration'] ?? null) || !is_finite((float)$entry['duration'])
        || $entry['duration'] <= 0 || $entry['duration'] > 14400
        || !is_array($entry['blocked'] ?? null)) {
        throw new RuntimeException('Invalid music occurrence in the private manifest.');
    }
    if(isset($entry['playback'])){
        $p=$entry['playback'];$d=$entry['sourceDuration']??null;
        if(!is_array($p)||!is_numeric($d)||!is_finite((float)$d)||$d<=0||$d>14400)throw new RuntimeException('Invalid source duration.');
        foreach(['cueIn','cueOut','crossfadeOut']as$k)if((!is_int($p[$k]??null)&&!is_float($p[$k]??null))||!is_finite((float)$p[$k]))throw new RuntimeException('Invalid playback numbers.');
        if($p['cueIn']<0||$p['cueOut']>$d+.001||$p['cueOut']-$p['cueIn']<.1||abs($entry['duration']-($p['cueOut']-$p['cueIn']))>.001||$p['crossfadeOut']<0||$p['crossfadeOut']>10||$p['crossfadeOut']>$entry['duration']/2||($entry['kind']==='jingle'&&$p['crossfadeOut']!=0))throw new RuntimeException('Unsafe playback points.');
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

/** PCM position, not wall time, decides the short preparation window. */
function music_liquidsoap_claim_due(array $state,array $live):bool {
    $current=$state['current']??null;if(!$current)return true;
    // Older supervisors have no currentId. A heartbeat predating the PCM start
    // is rejected, allowing a compatible selective rollout without a restart.
    if(isset($live['currentId'])&&$live['currentId']!==$current['id'])return false;
    if(($live['timestamp']??0)<($current['observedWallStart']??INF))return false;
    $position=$live['position']??null;
    if((!is_int($position)&&!is_float($position))||!is_finite((float)$position)||$position<0)return false;
    return (float)$current['duration']-$position<=8.+(float)($current['playback']['crossfadeOut']??0);
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
        // Keep the next occurrence editable until the output PCM approaches
        // its transition. Local verified files need only a short decode window.
        if (($control['editableNext'] ?? false) && $state['current']) {
            if(!music_liquidsoap_claim_due($state,$live))return '';
        }
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
        $annotations='rr_entry="'.$entry['id'].'",rr_session="'.$session.'",rr_kind="'.$entry['kind'].'"';
        if(isset($entry['playback'])){foreach(['cueIn'=>'liq_cue_in','cueOut'=>'liq_cue_out','crossfadeOut'=>'rr_cross']as$key=>$name)$annotations.=','.$name.'="'.sprintf('%.3F',$entry['playback'][$key]).'"';}
        return 'annotate:'.$annotations.':'.$audio;
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
        if (($control['ready'] ?? false) !== true || !$queued || $queued['blocked'] || $queued['sha256'] !== $entry['sha256'] || ($queued['playback']??null)!==($entry['playback']??null)) {
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
        '{{ROTATION_MODE}}' => (music_playout_json($root.'/control.json')['transitionVersion']??1)===2?'true':'false',
        '{{ALLOW_ROOT}}' => getenv('RR_STREAM_TEST_ALLOW_ROOT') === '1' ? 'true' : 'false',
    ]);
}
