<?php
declare(strict_types=1);
function config_valid(array $config): bool
{
    $mode = $config['auth_mode'] ?? '';
    if ($mode === 'demo') return true;
    if (!in_array($mode, ['entra', 'vipps'], true)) return false;
    $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    $url = parse_url($config['base_url'] ?? '');
    $validUrl = is_array($url) && !isset($url['user']) && !isset($url['pass'])
        && !isset($url['query']) && !isset($url['fragment'])
        && in_array($url['path'] ?? '', ['', '/'], true)
        && (($url['scheme'] ?? '') === 'https' || (($url['scheme'] ?? '') === 'http' && in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], true)));
    if ($mode === 'vipps') {
        $phones = $config['vipps_allowed_phones'] ?? [];
        return $validUrl && !empty($url['host'])
            && in_array($config['vipps_environment'] ?? '', ['test', 'production'], true)
            && is_string($config['vipps_client_id'] ?? null) && trim($config['vipps_client_id']) !== ''
            && is_string($config['vipps_client_secret'] ?? null) && trim($config['vipps_client_secret']) !== ''
            && is_array($phones) && count($phones) > 0
            && count(array_filter($phones, fn($p) => is_string($p) && preg_match('/^\+[1-9][0-9]{7,14}$/D', $p))) === count($phones);
    }
    return $validUrl && !empty($url['host'])
        && preg_match($uuid, $config['tenant_id'] ?? '') === 1
        && preg_match($uuid, $config['client_id'] ?? '') === 1
        && trim($config['client_secret'] ?? '') !== '';
}
function load_config(): array
{
    $config = require dirname(__DIR__) . '/config/example.php';
    $local = dirname(__DIR__) . '/config/local.php';
    if (is_file($local)) $config = array_replace($config, require $local);
    $keyFile = dirname(__DIR__) . '/config/openai.key';
    if (is_file($keyFile)) $config['openai_api_key'] = trim(file_get_contents($keyFile));
    foreach (['OPENAI_API_KEY'=>'openai_api_key', 'OPENAI_MODEL'=>'openai_model', 'STUDIO_AUTH_MODE'=>'auth_mode', 'STUDIO_BASE_URL'=>'base_url', 'ENTRA_TENANT_ID'=>'tenant_id', 'ENTRA_CLIENT_ID'=>'client_id', 'ENTRA_CLIENT_SECRET'=>'client_secret', 'VIPPS_CLIENT_ID'=>'vipps_client_id', 'VIPPS_CLIENT_SECRET'=>'vipps_client_secret', 'VIPPS_ENVIRONMENT'=>'vipps_environment'] as $env=>$key) {
        if (getenv($env) !== false) $config[$key] = getenv($env);
    }
    if (getenv('VIPPS_ALLOWED_PHONES') !== false) $config['vipps_allowed_phones'] = array_values(array_filter(array_map('trim', explode(',', getenv('VIPPS_ALLOWED_PHONES')))));
    $audioKeyFile = dirname(__DIR__) . '/config/elevenlabs.key';
    if (getenv('ELEVENLABS_API_KEY') === false && empty($config['rr_audio']['api_key']) && (file_exists($audioKeyFile) || is_link($audioKeyFile))) {
        if (is_link($audioKeyFile) || !is_file($audioKeyFile) || (fileperms($audioKeyFile) & 0077) !== 0 || filesize($audioKeyFile) > 512)
            throw new RuntimeException('ElevenLabs-nøkkelfilen må være privat og ha filrettighet 600.');
        $key = trim((string)file_get_contents($audioKeyFile));
        if (!preg_match('/^[\x21-\x7e]{20,512}$/D', $key)) throw new RuntimeException('ElevenLabs-nøkkelfilen er ugyldig.');
        $config['rr_audio']['api_key'] = $key;
    }
    if (getenv('ELEVENLABS_API_KEY') !== false) $config['rr_audio']['api_key'] = getenv('ELEVENLABS_API_KEY');
    return $config;
}
