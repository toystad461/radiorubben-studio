<?php
declare(strict_types=1);

/** Read-only diagnostics. Only --probe sends one metadata request; never commits it. */
function music_ai_diagnostic_transport(array $config, array $payload): array
{
    $body = '';
    $curl = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($curl, [
        CURLOPT_POST => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 35,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $config['openai_api_key']],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 1048576) return 0;
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    curl_exec($curl);
    $result = ['curlCode' => curl_errno($curl), 'httpStatus' => (int)curl_getinfo($curl, CURLINFO_HTTP_CODE), 'body' => $body];
    curl_close($curl);
    return $result;
}

/** Never emit remote error messages, raw response text, headers, or exceptions. */
function music_ai_diagnostic_response(array $result): array
{
    $code = (int)($result['curlCode'] ?? -1);
    $http = (int)($result['httpStatus'] ?? 0);
    $safe = ['curlCode' => $code, 'httpStatus' => $http];
    if ($code !== 0) return $safe + ['status' => 'transport_failed'];
    $v = json_decode((string)($result['body'] ?? ''), true);
    if ($http < 200 || $http >= 300) {
        $safe['status'] = 'http_failed';
        $allowed = ['invalid_api_key', 'insufficient_quota', 'model_not_found', 'invalid_json_schema', 'unsupported_parameter', 'rate_limit_exceeded', 'invalid_request_error', 'permission_denied', 'account_deactivated'];
        foreach (['code', 'type'] as $key) {
            $value = is_array($v) ? ($v['error'][$key] ?? null) : null;
            $safe['apiError' . ucfirst($key)] = in_array($value, $allowed, true) ? $value : 'other_or_missing';
        }
        return $safe;
    }
    if (!is_array($v)) return $safe + ['status' => 'invalid_json'];
    if (($v['status'] ?? null) !== 'completed') {
        $reason = $v['incomplete_details']['reason'] ?? null;
        return $safe + ['status' => 'response_not_completed', 'incompleteReason' => in_array($reason, ['max_output_tokens', 'content_filter'], true) ? $reason : 'other_or_missing'];
    }
    try { producer_text($v); }
    catch (Throwable) { return $safe + ['status' => 'response_text_unavailable']; }
    return $safe + ['status' => 'response_received'];
}

function music_ai_diagnostic_probe(array $config, array $track, ?callable $transport = null): array
{
    if (($track['kind'] ?? 'music') !== 'music' || empty($track['available']) ||
        ($track['audioQueue']['status'] ?? null) === 'running' ||
        !music_library_analysis_current($track) || !music_audio_profile_current($track)) {
        return ['status' => 'prerequisites_missing'];
    }
    try { music_library_producer_input($track); }
    catch (Throwable) { return ['status' => 'prerequisites_missing']; }
    if (empty($config['openai_api_key']) || empty($config['openai_model'])) return ['status' => 'configuration_required'];
    $safe = ['status' => 'request_not_completed'];
    try {
        $assessment = music_library_producer_assess($config, $track, static function (array $cfg, array $payload) use ($transport, &$safe): string {
            $response = ($transport ?? 'music_ai_diagnostic_transport')($cfg, $payload);
            $safe = music_ai_diagnostic_response($response);
            if ($safe['status'] !== 'response_received') throw new RuntimeException('Diagnostic request failed');
            return producer_text(json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR));
        });
        $safe['status'] = 'assessment_valid_not_saved';
        // A probe is diagnostic only; do not present an unsaved suggestion as current.
        $safe['confidence'] = $assessment['confidence'];
    } catch (Throwable) {
        if ($safe['status'] === 'response_received') $safe['status'] = 'assessment_validation_failed';
    }
    return $safe;
}

function music_ai_diagnostics(array $config, string $app, string $root, ?string $probeId = null, ?callable $transport = null): array
{
    $before = is_file($root . '/library.json') ? hash_file('sha256', $root . '/library.json') : null;
    $tracks = music_library_read($root);
    $report = ['schemaVersion' => 1, 'generatedAtUtc' => gmdate('c'), 'appRoot' => $app,
        'model' => in_array($config['openai_model'] ?? null, ['gpt-4.1-mini', 'gpt-5-mini'], true) ? $config['openai_model'] : 'other_or_missing',
        'apiKeyPresent' => !empty($config['openai_api_key']),
        'proxyMatchesExpected' => (getenv('https_proxy') ?: getenv('HTTPS_PROXY')) === 'http://127.0.0.1:3140',
        'noProxyEmpty' => !getenv('NO_PROXY') && !getenv('no_proxy'),
        'libraryHashBefore' => $before, 'exceptions' => []];
    foreach (['app/config.php', 'app/producer.php', 'app/music-library-producer.php', 'app/music-library-queue.php',
              'public/music-library.php', 'app/views/music-library-queue.php', 'public/assets/music-upload-queue.mjs',
              'public/assets/music-auto-worker.js'] as $file) {
        $report['codeHashes'][$file] = is_file($app . '/' . $file) ? hash_file('sha256', $app . '/' . $file) : null;
    }
    $worker = '/etc/rr-music-ai/worker.php';
    $report['installedWorkerSha256'] = is_file($worker) && is_readable($worker) ? hash_file('sha256', $worker) : null;
    foreach ($tracks as $track) {
        $audio = $track['audioQueue'] ?? [];
        $ai = $track['musicAiJob'] ?? [];
        if (($audio['status'] ?? null) !== 'running' && !in_array($ai['status'] ?? null, ['retry', 'failed', 'running'], true)) continue;
        $row = ['trackId' => $track['id']];
        foreach (['audio' => $audio, 'ai' => $ai] as $name => $job) {
            $state = $job['status'] ?? null;
            $row[$name . 'Status'] = in_array($state, ['queued', 'running', 'prepared', 'retry', 'failed'], true) ? $state : null;
            $row[$name . 'Attempts'] = is_int($job['attempts'] ?? null) ? $job['attempts'] : null;
        }
        $row['audioLeaseActive'] = ($audio['status'] ?? null) === 'running' && (int)($audio['leaseUntil'] ?? 0) > time();
        $row['aiRetryWaitActive'] = (int)($ai['nextAttemptAt'] ?? 0) > time();
        $row['aiAttemptLimitForSameInput'] = false;
        if (($ai['attempts'] ?? 0) >= 3 && !empty($config['openai_model'])) {
            try {
                $key = hash('sha256', json_encode([music_library_producer_input($track), $config['openai_model'], 'music-ai-worker-1'], JSON_THROW_ON_ERROR));
                $row['aiAttemptLimitForSameInput'] = ($ai['inputKey'] ?? null) === $key;
            } catch (Throwable) { /* No credential or metadata error text is returned. */ }
        }
        $report['exceptions'][] = $row;
    }
    if ($probeId !== null) {
        $report['probe'] = ['status' => 'track_not_found', 'trackId' => $probeId];
        foreach ($tracks as $track) if ($track['id'] === $probeId) {
            $report['probe'] = music_ai_diagnostic_probe($config, $track, $transport) + ['trackId' => $probeId];
            break;
        }
    }
    clearstatcache();
    $report['libraryHashAfter'] = is_file($root . '/library.json') ? hash_file('sha256', $root . '/library.json') : null;
    $report['status'] = $before === $report['libraryHashAfter'] ? 'diagnostics_no_library_changes_observed' : 'library_changed_during_read';
    return $report;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    ini_set('display_errors', '0'); ini_set('log_errors', '0');
    try {
        $args = $argv; array_shift($args); $mode = array_shift($args);
        if (!in_array($mode, ['--check', '--probe'], true) || count($args) !== ($mode === '--probe' ? 2 : 1) ||
            !str_starts_with($args[0], '--app-root=')) throw new RuntimeException('Invalid arguments');
        $app = realpath(substr($args[0], 11));
        if (!$app || !is_file($app . '/app/config.php') || !is_file($app . '/app/music-library-queue.php')) throw new RuntimeException('Application unavailable');
        $id = null;
        if ($mode === '--probe') {
            if (!preg_match('/^--track-id=([a-f0-9]{16,64})$/D', $args[1], $match)) throw new RuntimeException('Invalid track');
            $id = $match[1];
        }
        require_once $app . '/app/config.php';
        require_once $app . '/app/music-library-queue.php';
        $config = load_config();
        $config['openai_model'] = getenv('RR_MUSIC_AI_MODEL') ?: 'gpt-4.1-mini';
        echo json_encode(music_ai_diagnostics($config, $app, music_library_root(), $id), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } catch (Throwable) {
        fwrite(STDERR, "STOPP: Diagnostic setup failed. No credentials or exception text are printed.\n");
        exit(1);
    }
}
