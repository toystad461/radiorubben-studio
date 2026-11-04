<?php
declare(strict_types=1);
$package = dirname(__DIR__, 2);
$app = getenv('RR_MUSIC_AI_TEST_APP') ?: $package;
require $app . '/app/music-library-queue.php';
define('RR_MUSIC_AI_INVENTORY_TEST', true);
require $package . '/scripts/music-ai-inventory.php';
require $package . '/scripts/music-ai-diagnostics.php';
function inventory_ok(bool $ok): void { if (!$ok) throw new RuntimeException('Inventory assertion failed'); }
$dir = sys_get_temp_dir() . '/rr-ai-inventory-' . bin2hex(random_bytes(6));
mkdir($dir, 0700); $dir = realpath($dir); $root = $dir . '/media/music';
$u = ['role' => 'producer', 'id' => 'fixture'];
try {
    $bytes = str_repeat(hex2bin('fffb9000') . str_repeat("\0", 413), 1000);
    $file = $dir . '/test.mp3'; file_put_contents($file, $bytes);
    $uploaded = music_library_upload(['name' => 'Test.mp3', 'tmp_name' => $file, 'size' => strlen($bytes), 'error' => 0], $u, 'c', 'c', $root, fn() => true, fn($a, $b) => copy($a, $b));
    $t = music_library_prepare($uploaded['track']['id'], $uploaded['track']['sha256'], 0, $u, 'c', 'c', $root);
    $id = $t['id']; $sha = $t['sha256'];
    $t = music_library_edit($id, $sha, $t['revision'], ['artist' => 'Fixture', 'title' => 'Test', 'tags' => 'Keep'], $u, 'c', 'c', $root);
    $t['available'] = true;
    $technicalOnly = $t;
    $technicalOnly['technicalCheck'] = ['sourceSha256' => $sha, 'ready' => true];
    $r = music_ai_inventory_rows([$technicalOnly]);
    inventory_ok($r['counts']['technicalCueReady'] === 1 && $r['counts']['readyForAi'] === 0);
    inventory_ok(in_array('tempo_key_missing_or_stale', $r['tracks'][0]['missing'], true));
    inventory_ok(in_array('full_audio_profile_missing_or_stale', $r['tracks'][0]['missing'], true));
    music_audio_enqueue($id, $sha, $u, 'c', 'c', $root);
    $job = music_audio_claim($u, 'c', 'c', $root);
    $duration = music_library_metadata($t)['durationSeconds'];
    $measure = ['engine' => MUSIC_ANALYSIS_ENGINE, 'bpm' => 120.0, 'key' => 'C', 'scale' => 'major', 'tempoConfidence' => 2.0, 'keyStrength' => .8, 'startSeconds' => 0.0, 'durationSeconds' => 15.0];
    $profile = ['engine' => MUSIC_AUDIO_QUEUE_ENGINE, 'durationSeconds' => $duration, 'rmsDbfs' => -12.0, 'peakDbfs' => -.5, 'clippedFraction' => 0.0, 'leadingSilenceSeconds' => .2, 'trailingSilenceSeconds' => .3, 'vocalStartSeconds' => 3.0, 'lastVocalSeconds' => 20.0, 'vocalScoreMax' => .8, 'voiceModel' => 'yamnet-tfjs-1', 'voiceCoverageSeconds' => $duration, 'waveformPeaks' => array_fill(0, 256, .2)];
    $t = music_audio_complete($id, $sha, $job['revision'], $job['token'], $measure, $profile, $u, 'c', 'c', $root);
    $t['available'] = true;
    $secret = 'sk-fixture-do-not-print';
    $config = ['openai_model' => 'gpt-4.1-mini', 'openai_api_key' => $secret];
    $valid = json_encode(['energy' => 'moderat', 'periods' => ['Morgen'], 'tags' => ['Melodisk'], 'reason' => 'Synthetic only.', 'confidence' => 'low']);
    $envelope = fn(string $text) => json_encode(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]]]]);
    $cases = [
        [['curlCode' => 60, 'httpStatus' => 0, 'body' => $secret], 'transport_failed'],
        [['curlCode' => 0, 'httpStatus' => 401, 'body' => json_encode(['error' => ['code' => 'invalid_api_key', 'message' => $secret]])], 'http_failed'],
        [['curlCode' => 0, 'httpStatus' => 429, 'body' => json_encode(['error' => ['code' => 'insufficient_quota', 'type' => $secret, 'message' => $secret]])], 'http_failed'],
        [['curlCode' => 0, 'httpStatus' => 400, 'body' => json_encode(['error' => ['code' => $secret, 'message' => $secret]])], 'http_failed'],
        [['curlCode' => 0, 'httpStatus' => 200, 'body' => $secret], 'invalid_json'],
        [['curlCode' => 0, 'httpStatus' => 200, 'body' => json_encode(['status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens']])], 'response_not_completed'],
        [['curlCode' => 0, 'httpStatus' => 200, 'body' => json_encode(['status' => 'completed', 'output' => []])], 'response_text_unavailable'],
        [['curlCode' => 0, 'httpStatus' => 200, 'body' => $envelope($secret)], 'assessment_validation_failed'],
        [['curlCode' => 0, 'httpStatus' => 200, 'body' => $envelope($valid)], 'assessment_valid_not_saved'],
    ];
    foreach ($cases as [$response, $expected]) {
        $calls = 0;
        $transport = function ($cfg, $payload) use (&$calls, $response, $secret) {
            $calls++;
            inventory_ok($cfg['openai_api_key'] === $secret && $payload['model'] === 'gpt-4.1-mini');
            inventory_ok($payload['store'] === false && $payload['text']['format']['name'] === 'radio_music_assessment');
            return $response;
        };
        $r = music_ai_diagnostic_probe($config, $t, $transport);
        inventory_ok($calls === 1 && $r['status'] === $expected);
        inventory_ok(!str_contains(json_encode($r), $secret));
    }
    $never = function () { throw new RuntimeException('Transport must not run'); };
    foreach (['running', 'missing_original', 'missing_profile'] as $condition) {
        $bad = $t;
        if ($condition === 'running') $bad['audioQueue']['status'] = 'running';
        elseif ($condition === 'missing_original') $bad['available'] = false;
        else unset($bad['musicAudioProfile']);
        inventory_ok(music_ai_diagnostic_probe($config, $bad, $never)['status'] === 'prerequisites_missing');
    }
    inventory_ok(music_ai_diagnostic_probe(['openai_model' => 'fixture'], $t, $never)['status'] === 'configuration_required');
    $inputKey = hash('sha256', json_encode([music_library_producer_input($t), $config['openai_model'], 'music-ai-worker-1'], JSON_THROW_ON_ERROR));
    music_worker_commit($root, $t, ['musicAiJob' => ['status' => 'failed', 'attempts' => 3, 'inputKey' => $inputKey]]);
    $before = hash_file('sha256', $root . '/library.json');
    $files = scandir($root);
    $r = music_ai_diagnostics($config, $app, $root, $id, fn() => ['curlCode' => 0, 'httpStatus' => 200, 'body' => $envelope($valid)]);
    inventory_ok($r['probe']['status'] === 'assessment_valid_not_saved');
    inventory_ok($r['exceptions'][0]['aiAttemptLimitForSameInput'] === true);
    inventory_ok($r['libraryHashBefore'] === $before && $r['libraryHashAfter'] === $before);
    inventory_ok($files === scandir($root) && !is_file($root . '/ai-worker.lock'));
    inventory_ok(hash_file('sha256', $root . '/originals/' . $id . '.mp3') === $sha);
    inventory_ok(!str_contains(json_encode($r), $secret));
    $r = music_ai_diagnostics($config, $app, $root, null, $never);
    inventory_ok(!isset($r['probe']) && $r['exceptions'][0]['aiAttemptLimitForSameInput'] === true);
    $config['openai_model'] = 'fixture-changed';
    $r = music_ai_diagnostics($config, $app, $root, null, $never);
    inventory_ok($r['exceptions'][0]['aiAttemptLimitForSameInput'] === false);
    echo "PASS: safe API diagnostics, transport/HTTP/schema/incomplete failures, one actual-schema request, retry-limit identity and no library/original writes.\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    rmdir($dir);
}
