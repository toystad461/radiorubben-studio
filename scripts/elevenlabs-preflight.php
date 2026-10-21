<?php
declare(strict_types=1);
/**
 * Read-only ElevenLabs connection check. No synthesis, writes, activation or retry.
 * Run from CLI: php elevenlabs-preflight.php /absolute/path/to/studio
 * Offline tests: php elevenlabs-preflight.php --self-test
 * Reuses Studio's load_config(); does NOT print secrets or raw provider errors.
 */
const PEIS_CHECK_VOICE = 'azrGjm6gYkR15bxb9cVv';
const PEIS_CHECK_MODEL = 'eleven_v4';

function peis_check_error_code(mixed $body): string
{
    $detail = is_array($body) ? ($body['detail'] ?? null) : null;
    $code = is_array($detail) ? ($detail['code'] ?? $detail['status'] ?? null) : null;
    $allowed = ['invalid_api_key', 'missing_permissions', 'voice_not_found',
        'model_not_found', 'quota_exceeded', 'insufficient_credits',
        'paid_plan_required', 'ip_not_allowed'];
    return in_array($code, $allowed, true) ? $code : 'unclassified';
}

/** Fixed host, fixed GET routes, verified TLS, no redirects or response logging. */
function peis_check_get(string $path, string $key): array
{
    $allowed = ['/v1/models', '/v1/voices/'.PEIS_CHECK_VOICE, '/v1/user/subscription'];
    if (!in_array($path, $allowed, true)) {
        return ['http' => 0, 'error' => 'route_not_allowed'];
    }
    if (!extension_loaded('curl')) return ['http' => 0, 'error' => 'php_curl_missing'];
    $body = '';
    $curl = curl_init('https://api.elevenlabs.io'.$path);
    curl_setopt_array($curl, [
        CURLOPT_HTTPGET => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_PROXY => '',
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'xi-api-key: '.$key],
        CURLOPT_WRITEFUNCTION => static function ($h, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 2_000_000) return 0;
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $errno = curl_errno($curl);
    curl_close($curl);
    if ($ok === false) return ['http' => $status, 'error' => 'transport_error', 'curl_errno' => $errno];
    $data = json_decode($body, true);
    if ($status !== 200) return ['http' => $status, 'error' => peis_check_error_code($data)];
    if (!is_array($data)) return ['http' => $status, 'error' => 'unexpected_response'];
    return ['http' => $status, 'data' => $data];
}

function peis_check_result(array $response): array
{
    $result = ['http' => (int) ($response['http'] ?? 0)];
    if (isset($response['error'])) $result['error'] = $response['error'];
    if (isset($response['curl_errno'])) $result['curl_errno'] = (int) $response['curl_errno'];
    return $result;
}

/** Returns ONLY whitelisted non-secret facts. The injected getter is for offline tests. */
function peis_check_collect(array $audio, callable $get): array
{
    $key = $audio['api_key'] ?? null;
    $result = [
        'mode' => 'read_only_no_audio', 'generation_requests' => 0,
        'requested_voice' => PEIS_CHECK_VOICE, 'requested_model' => PEIS_CHECK_MODEL,
        'key_present' => is_string($key) && trim($key) !== '',
        'studio_audio_enabled' => ($audio['enabled'] ?? false) === true,
        'generation_verified' => false,
    ];
    if (!$result['key_present']) return $result + ['status' => 'key_missing'];
    if (!preg_match('/^[\x21-\x7e]{20,512}$/D', $key)) return $result + ['status' => 'key_format_invalid'];
    $models = $get('/v1/models', $key);
    $result['models'] = peis_check_result($models);
    $found = false;
    if (isset($models['data']) && is_array($models['data'])) {
        foreach ($models['data'] as $m) {
            if (!is_array($m) || ($m['model_id'] ?? null) !== PEIS_CHECK_MODEL) continue;
            $found = true;
            foreach (['maximum_text_length_per_request', 'can_do_text_to_speech', 'requires_alpha_access'] as $field) {
                $value = $m[$field] ?? null;
                if (is_bool($value) || is_int($value)) $result['models'][$field] = $value;
            }
            break;
        }
    }
    $result['models']['requested_model_listed'] = $found;
    // Do not repeat a rejected authentication or a rate-limited request.
    if (in_array($models['http'] ?? 0, [401, 429], true) || ($models['http'] ?? 0) === 0) {
        return $result + ['status' => 'connection_blocked'];
    }
    $voice = $get('/v1/voices/'.PEIS_CHECK_VOICE, $key);
    $result['voice'] = peis_check_result($voice);
    $voiceOk = ($voice['data']['voice_id'] ?? null) === PEIS_CHECK_VOICE && ($voice['http'] ?? 0) === 200;
    $result['voice']['requested_voice_accessible'] = $voiceOk;
    if (in_array($voice['http'] ?? 0, [401, 429], true)) return $result + ['status' => 'connection_blocked'];
    $subscription = $get('/v1/user/subscription', $key);
    $result['subscription'] = peis_check_result($subscription);
    $s = $subscription['data'] ?? [];
    foreach (['character_count', 'character_limit'] as $field) {
        if (isset($s[$field]) && is_int($s[$field]) && $s[$field] >= 0) $result['subscription'][$field] = $s[$field];
    }
    // Account quota is not necessarily the key's separate credit limit.
    $result['subscription']['key_credit_limit_verified'] = false;
    $result['status'] = $voiceOk && $found && ($models['http'] ?? 0) === 200
        ? 'read_access_verified_generation_not_tested' : 'access_or_model_unresolved';
    return $result;
}

function peis_check_selftest(): int
{
    $checks = 0;
    $assert = static function (bool $ok) use (&$checks): void {
        $checks++;
        if (!$ok) throw new RuntimeException('Offline check failed: '.$checks);
    };
    $key = str_repeat('K', 32); // synthetic, never a provider key
    $calls = [];
    $get = static function (string $path, string $givenKey) use (&$calls, $key): array {
        if ($givenKey !== $key) throw new RuntimeException('Unexpected test key');
        $calls[] = $path;
        return ['http' => 200, 'data' => match ($path) {
            '/v1/models' => [['model_id' => PEIS_CHECK_MODEL, 'maximum_text_length_per_request' => 10000]],
            '/v1/voices/'.PEIS_CHECK_VOICE => ['voice_id' => PEIS_CHECK_VOICE, 'description' => 'PRIVATE'],
            '/v1/user/subscription' => ['character_count' => 123, 'character_limit' => 456, 'open_invoices' => ['PRIVATE']],
            default => throw new RuntimeException('Unexpected route'),
        }];
    };
    $r = peis_check_collect([], $get);
    $assert($r['status'] === 'key_missing' && count($calls) === 0);
    $r = peis_check_collect(['api_key' => "bad\nkey"], $get);
    $assert($r['status'] === 'key_format_invalid' && count($calls) === 0);
    $r = peis_check_collect(['api_key' => $key], $get);
    $assert($r['status'] === 'read_access_verified_generation_not_tested' && count($calls) === 3);
    $assert($r['generation_verified'] === false && $r['generation_requests'] === 0);
    $assert(!str_contains(json_encode($r), $key) && !str_contains(json_encode($r), 'PRIVATE'));
    $r = peis_check_collect(['api_key' => $key], static fn () => ['http' => 401, 'error' => 'invalid_api_key']);
    $assert($r['status'] === 'connection_blocked');
    $r = peis_check_collect(['api_key' => $key], static fn () => ['http' => 429, 'error' => 'unclassified']);
    $assert($r['status'] === 'connection_blocked');
    $r = peis_check_collect(['api_key' => $key], static fn () => ['http' => 0, 'error' => 'transport_error']);
    $assert($r['status'] === 'connection_blocked');
    $r = peis_check_collect(['api_key' => $key], static fn () => ['http' => 200, 'data' => []]);
    $assert($r['status'] === 'access_or_model_unresolved');
    $assert(peis_check_error_code(['detail' => ['message' => $key]]) === 'unclassified');
    $assert(peis_check_error_code(['detail' => ['status' => 'missing_permissions', 'message' => $key]]) === 'missing_permissions');
    $assert(peis_check_error_code(['detail' => $key]) === 'unclassified');
    $assert(peis_check_get('https://untrusted.example/', $key)['error'] === 'route_not_allowed');
    echo json_encode(['offline_checks_passed' => $checks, 'network_requests' => 0, 'audio_generated' => false], JSON_PRETTY_PRINT).PHP_EOL;
    return 0;
}

function peis_check_main(array $args): int
{
    if (($args[1] ?? '') === '--self-test' && count($args) === 2) return peis_check_selftest();
    if (count($args) !== 2 || !str_starts_with($args[1], '/')) {
        echo "Bruk: php elevenlabs-preflight.php /absolutt/sti/til/studio\n";
        return 2;
    }
    $root = realpath($args[1]);
    if ($root === false || !is_file($root.'/app/config.php')) {
        echo json_encode(['status' => 'studio_path_or_loader_missing', 'generation_requests' => 0]).PHP_EOL;
        return 2;
    }
    if (!is_readable($root.'/app/config.php')) {
        echo json_encode(['status' => 'config_loader_not_readable', 'generation_requests' => 0]).PHP_EOL;
        return 2;
    }
    // Discard output from private configuration files, including on exception.
    $level = ob_get_level();
    ob_start();
    try {
        require_once $root.'/app/config.php';
        $config = load_config();
        $audio = $config['rr_audio'] ?? [];
    } catch (Throwable $e) {
        while (ob_get_level() > $level) ob_end_clean();
        echo json_encode(['status' => 'config_load_failed_check_key_file_owner_and_mode_600', 'generation_requests' => 0]).PHP_EOL;
        return 2;
    }
    while (ob_get_level() > $level) ob_end_clean();
    if (!is_array($audio)) $audio = [];
    $r = peis_check_collect($audio, 'peis_check_get');
    $r['runtime'] = [
        'php_version' => PHP_VERSION, 'php_curl' => extension_loaded('curl'),
        'scope' => 'current_cli_user_not_web_worker',
        'empty_environment_key_override' => getenv('ELEVENLABS_API_KEY') === '',
        'existing_key_file_present' => file_exists($root.'/config/elevenlabs.key'),
        'existing_key_file_readable' => is_readable($root.'/config/elevenlabs.key'),
    ];
    echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    return $r['status'] === 'read_access_verified_generation_not_tested' ? 0 : 2;
}

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Never show exception text or raw config/provider responses in diagnostics.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
try { exit(peis_check_main($argv)); }
catch (Throwable $e) {
    echo json_encode(['status' => 'check_failed_redacted', 'generation_requests' => 0]).PHP_EOL;
    exit(2);
}
