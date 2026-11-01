<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/music-liquidsoap.php';

if (PHP_SAPI !== 'cli' || count($argv) !== 4 || !in_array($argv[1], ['serve', 'claim'], true)) {
    fwrite(STDERR, "Usage: php scripts/music-liquidsoap.php serve <private-root> <private-engine.json>\n");
    exit(2);
}
$root = $argv[2];
music_playout_root($root);
umask(0007);
if ($argv[1] === 'claim') {
    try { echo music_liquidsoap_claim($root, $argv[3])."\n"; exit(0); }
    catch (Throwable) { fwrite(STDERR, "Queue claim could not be confirmed.\n"); exit(4); }
}
$config = music_liquidsoap_runtime($argv[3]);
// Executable generated code and its credentials must be outside the shared
// web-writable bus. The root-owned runtime parent protects this directory name.
$engineRoot = dirname((string)realpath($argv[3])).'/engine';
if (!is_dir($root.'/audio') || is_link($root.'/audio')
    || !is_dir($engineRoot) || is_link($engineRoot)) throw new RuntimeException('Prepare private spool and engine directories first.');
if (is_link($root.'/daemon.lock')) throw new RuntimeException('Invalid daemon lock.');
$singleton = fopen($root.'/daemon.lock', 'c+b');
if (!$singleton || !flock($singleton, LOCK_EX | LOCK_NB)) { fwrite(STDERR, "One active player only\n"); exit(3); }
$php = getenv('PHP_BIN') ?: PHP_BINARY;
$liquidsoap = getenv('LIQUIDSOAP_BIN') ?: '/usr/bin/liquidsoap';
$python = getenv('RR_PYTHON_BIN') ?: '/usr/bin/python3';
foreach ([$php, $liquidsoap, $python] as $binary) if ($binary[0] !== '/' || !is_executable($binary)) throw new RuntimeException('Use absolute executable engine paths.');
$child = null; $pipes = []; $buffer = ''; $exit = false;
$lastBeat = 0.0; $startedAt = 0.0; $drainAt = null; $waitingAt = null; $fatal = null;
$lastPcmAt = null; $lastPcmClock = null; $connectedAt = null;
pcntl_async_signals(true);
foreach ([SIGINT, SIGTERM] as $signal) pcntl_signal($signal, static function () use (&$exit): void { $exit = true; });

function liquidsoap_close(&$child, array &$pipes): void {
    if (is_resource($child)) {
        $status = proc_get_status($child);
        if ($status['running']) proc_terminate($child);
        $deadline = microtime(true) + 2;
        while (proc_get_status($child)['running'] && microtime(true) < $deadline) usleep(10000);
        if (proc_get_status($child)['running']) proc_terminate($child, SIGKILL);
    }
    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
    $pipes = [];
    if (is_resource($child)) proc_close($child);
    $child = null;
}

$lock = music_playout_lock($root);
try {
    $state = music_playout_state($root);
    $state['mode'] = 'stopped';
    music_liquidsoap_reset($root, $state, 'Tjenesten er startet. Ny manuell Start kreves.');
    $state['engine'] = 'liquidsoap';
    $control = music_playout_json($root.'/control.json');
    $state['ack'] = $control['command']['id'] ?? $state['ack'];
    music_playout_event($root, $state, 'service_started');
} finally { flock($lock, LOCK_UN); fclose($lock); }

try {
    while (!$exit) {
        // Drain callbacks before taking the shared lock: request.dynamic's CLI
        // may need that lock while the audio thread continues to produce frames.
        $events = [];
        if (isset($pipes[1])) {
            $bytes = stream_get_contents($pipes[1]);
            if ($bytes !== false) $buffer .= $bytes;
            if (strlen($buffer) > 65536) throw new RuntimeException('Engine event buffer exceeded.');
            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newline); $buffer = substr($buffer, $newline + 1);
                if (!str_starts_with($line, 'RR_EVENT ')) continue;
                if (strlen($line) > 4096) throw new RuntimeException('Oversized engine event.');
                $event = json_decode(substr($line, 9), true, 8, JSON_THROW_ON_ERROR);
                if (!is_array($event)) throw new RuntimeException('Invalid engine event.');
                $events[] = $event;
            }
        }
        $stopChild = false; $launch = null;
        $lock = music_playout_lock($root);
        try {
            $state = music_playout_state($root);
            $control = music_playout_json($root.'/control.json');
            foreach ($events as $event) {
                try {
                    if (($event['event'] ?? null) === 'heartbeat') {
                        if (($event['session'] ?? null) !== ($state['session'] ?? null)
                            || !music_liquidsoap_id($event['session'] ?? null)) continue;
                        $clock = $event['clock'] ?? null;
                        if ((!is_int($clock) && !is_float($clock)) || !is_finite((float)$clock)
                            || $clock < 0 || ($lastPcmClock !== null && $clock <= $lastPcmClock)) {
                            throw new RuntimeException('Invalid or stalled PCM clock.');
                        }
                        $lastPcmClock = (float)$clock; $lastPcmAt = microtime(true);
                        continue;
                    }
                    music_liquidsoap_observe($root, $state, $event);
                    if (($event['event'] ?? null) === 'connected' && ($state['connected'] ?? false)) $connectedAt = microtime(true);
                }
                catch (Throwable) {
                    $state['mode'] = 'blocked'; $state['message'] = 'Motorhendelsen kunne ikke bekreftes. Kontroller før ny start.';
                    music_playout_event($root, $state, 'engine_fault');
                    break;
                }
            }
            $command = $control['command'] ?? null;
            if ($command && $command['id'] !== $state['ack']) {
                $state['ack'] = $command['id'];
                $action = $command['action'] ?? '';
                if ($action === 'stop') {
                    $state['mode'] = 'stopped'; $state['message'] = 'Stoppet av operatør.'; $stopChild = true;
                } elseif ($action === 'start' && !is_resource($child) && ($control['ready'] ?? false) === true) {
                    $state['session'] = bin2hex(random_bytes(16)); $state['connected'] = false;
                    $state['mode'] = 'starting'; $state['position'] = 0.0;
                    $state['message'] = 'Klargjør teststrøm. Venter på Icecast og faktisk lydstart.';
                    $launch = $state['session'];
                } elseif ($action === 'start' && is_resource($child)) {
                    $state['message'] = 'Motoren kjører allerede; køen startes ikke på nytt.';
                } else {
                    $state['message'] = 'Kommandoen støttes ikke eller køen må synkroniseres.';
                }
                music_playout_event($root, $state, 'command', ['action' => $action]);
            }
            if (is_resource($child)) {
                // A publication barrier or changed/removed manifest occurrence
                // invalidates prefetched material. Stop the whole child, not just
                // future request.dynamic calls (which cannot revoke its buffer).
                $invalid = ($control['ready'] ?? false) !== true;
                foreach (music_playout_inflight_entries($state) as $entry) {
                    $queued = music_liquidsoap_queued($control, $entry['id']);
                    if (!$queued || $queued['sha256'] !== $entry['sha256'] || $queued['blocked']) $invalid = true;
                }
                if ($invalid) {
                    $state['mode'] = 'blocked'; $state['message'] = 'Køgrunnlaget ble endret eller synkronisering feilet. Kontroller før ny start.';
                    music_playout_event($root, $state, 'manifest_invalidated');
                }
                $status = proc_get_status($child);
                if (!$status['running'] && !in_array($state['mode'], ['blocked', 'stopped'], true)) {
                    $state['mode'] = 'blocked'; $state['message'] = 'Liquidsoap avsluttet uventet.';
                    music_playout_event($root, $state, 'engine_exited');
                }
                if ($state['mode'] === 'starting' && microtime(true) - $startedAt > 20) {
                    $state['mode'] = 'blocked'; $state['message'] = 'Ingen bekreftet lydstart innen tidsgrensen.';
                    music_playout_event($root, $state, 'engine_timeout');
                }
                if (($state['connected'] ?? false) && in_array($state['mode'], ['starting', 'playing'], true)
                    && microtime(true) - ($lastPcmAt ?? $connectedAt ?? $startedAt) > 5) {
                    $state['mode'] = 'blocked'; $state['connected'] = false;
                    $state['message'] = 'Lydmotoren sender ikke ferske lydrammer. Kontroller før ny start.';
                    music_playout_event($root, $state, 'engine_pcm_timeout');
                }
                foreach ($state['active'] ?? [] as $entry) {
                    $duration = (float)$entry['duration'];
                    $deadline = (float)$entry['observedWallStart'] + $duration + max(5.0, $duration * .02);
                    if (microtime(true) > $deadline && $state['mode'] === 'playing') {
                        $state['mode'] = 'blocked';
                        $state['message'] = 'Motoren bekreftet ikke slutten av låten innen tidsgrensen. Kontroller før ny start.';
                        music_playout_event($root, $state, 'engine_timeout', ['entryId' => $entry['id']]);
                    }
                }
                if (!$state['current'] && !empty($state['reserved']) && in_array($state['mode'], ['starting', 'playing'], true)) {
                    $waitingAt ??= microtime(true);
                    if (microtime(true) - $waitingAt > 15) {
                        $state['mode'] = 'blocked'; $state['message'] = 'En reservert lydfil kunne ikke dekodes. Kontroller før ny start.';
                        foreach ($state['reserved'] as $entry) music_liquidsoap_outcome($root, $state, $entry, 'failed', $state['message']);
                    }
                } else $waitingAt = null;
                $remaining = array_filter($control['entries'] ?? [], static fn($e) => !isset($state['outcomes'][$e['id']]));
                if (!$remaining && !music_playout_inflight_entries($state) && ($state['connected'] ?? false)
                    && in_array($state['mode'], ['starting', 'playing'], true)) {
                    $drainAt ??= microtime(true);
                    // Allow the final frame/MP3 encoder buffer to reach Icecast.
                    if (microtime(true) - $drainAt >= 1) {
                        $state['mode'] = 'stopped'; $state['message'] = 'Køen er ferdig. Teststrømmen stoppes; ingen automatisk gjentakelse.';
                        music_playout_event($root, $state, 'queue_finished');
                    }
                } else $drainAt = null;
                if (in_array($state['mode'], ['blocked', 'stopped'], true)) $stopChild = true;
            }
            if (microtime(true) - $lastBeat >= .2 || $launch || $stopChild) {
                $position = $state['current'] && $lastPcmClock !== null
                    ? max(0, $lastPcmClock - (float)$state['current']['renderClockStart']) : 0.0;
                $pcmFresh = $lastPcmAt !== null && microtime(true) - $lastPcmAt < 5;
                music_playout_atomic($root.'/heartbeat.json', ['timestamp' => microtime(true), 'engine' => 'liquidsoap',
                    'connected' => (bool)($state['connected'] ?? false) && $pcmFresh, 'position' => $position, 'segments' => [], 'pid' => getmypid()]);
                $lastBeat = microtime(true);
            }
        } finally { flock($lock, LOCK_UN); fclose($lock); }

        if ($stopChild) {
            // Wait for actual engine exit before releasing reservations or lock.
            liquidsoap_close($child, $pipes); $buffer = ''; $drainAt = null; $waitingAt = null;
            $lastPcmAt = null; $lastPcmClock = null; $connectedAt = null;
            $lock = music_playout_lock($root);
            try {
                $state = music_playout_state($root);
                music_liquidsoap_reset($root, $state, $state['message']);
                music_playout_event($root, $state, 'engine_stopped');
                music_playout_atomic($root.'/heartbeat.json', ['timestamp' => microtime(true), 'engine' => 'liquidsoap', 'connected' => false, 'position' => 0, 'segments' => [], 'pid' => getmypid()]);
            } finally { flock($lock, LOCK_UN); fclose($lock); }
        }
        if ($launch) {
            $script = music_liquidsoap_script($root, $launch, $config, $php, __FILE__);
            $scriptPath = $engineRoot.'/current.liq';
            if (is_link($scriptPath)) throw new RuntimeException('Invalid engine script path.');
            $temp = tempnam($engineRoot, '.script-');
            if (!$temp || file_put_contents($temp, $script) !== strlen($script) || !chmod($temp, 0600) || !rename($temp, $scriptPath)) throw new RuntimeException('Cannot prepare private engine script.');
            $log = $engineRoot.'/engine.log';
            if (is_link($log)) throw new RuntimeException('Invalid engine log path.');
            $child = proc_open([$python, __DIR__.'/liquidsoap-child.py', (string)getmypid(), $liquidsoap, $scriptPath],
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $log, 'a'], 3 => $singleton], $pipes);
            if (!is_resource($child)) throw new RuntimeException('Cannot start Liquidsoap.');
            chmod($log, 0600);
            stream_set_blocking($pipes[1], false); $buffer = ''; $startedAt = microtime(true); $drainAt = null;
            $lastPcmAt = null; $lastPcmClock = null; $connectedAt = null;
        }
        usleep(20000);
    }
} catch (Throwable $error) {
    fwrite(STDERR, "Liquidsoap adapter stopped: ".$error->getMessage()."\n");
    $fatal = 'Motoren stoppet fordi kontrollgrunnlaget ikke kunne bekreftes.';
} finally {
    liquidsoap_close($child, $pipes);
    $lock = music_playout_lock($root);
    try {
        $state = music_playout_state($root); $state['mode'] = $fatal ? 'blocked' : 'stopped';
        music_liquidsoap_reset($root, $state, $fatal ?? 'Spillertjenesten ble stoppet. Ny manuell Start kreves.');
        music_playout_event($root, $state, 'service_stopped');
        music_playout_atomic($root.'/heartbeat.json', ['timestamp' => 0, 'engine' => 'liquidsoap', 'connected' => false, 'position' => 0, 'segments' => []]);
    } finally { flock($lock, LOCK_UN); fclose($lock); fclose($singleton); }
}
if ($fatal !== null) exit(1);
