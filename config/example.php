<?php
// Copy to config/local.php on the server, outside the public document root.
return [
    'site_mode' => 'coming-soon', // app enables the configured authentication mode
    'auth_mode' => 'demo', // demo = login page only; entra or vipps = authentication required
    'base_url' => 'http://localhost:8080', // https://studio.radiorubben.no in production
    'tenant_id' => '',
    'client_id' => '',
    'client_secret' => '',
    // Optional integrations are disabled until configured in private local.php.
    'openai_api_key' => '',
    'openai_model' => '',
    'rr_audio' => [
        'enabled' => false,
        'api_key' => '', // private local.php or ELEVENLABS_API_KEY; never browser input
        'model_id' => 'eleven_multilingual_v2',
        'ffmpeg_binary' => '', // Verified absolute server path, not browser input.
        'daily_character_limit' => 0, // Set a deliberate budget before enabling.
        'voices' => [
            'azrGjm6gYkR15bxb9cVv' => [
                'label' => 'Radio Rubben',
                'approved' => true, // Owner confirmed an approved voice in the RR Audio request.
                'rights_reference' => 'Eierbekreftelse i RR Audio-oppdraget, 2026-10-08',
                'programs' => [], // Explicit program IDs from the existing registry.
            ],
        ],
        'audio_profile' => [
            'approved' => false,
            'version' => '',
            'lufs' => null,
            'true_peak_db' => null,
            'sample_rate' => null,
            'send_format' => null,
            'max_silence_seconds' => null,
            'bitrate_kbps' => null,
        ],
    ],
    'vipps_environment' => 'test',
    'vipps_client_id' => '',
    'vipps_client_secret' => '',
    'vipps_allowed_phones' => [],
];
