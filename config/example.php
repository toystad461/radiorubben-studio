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
        'api_key' => '', // private elevenlabs.key, local.php or ELEVENLABS_API_KEY; never browser input
        'model_id' => 'eleven_v4',
        'ffmpeg_binary' => '', // Verified absolute server path, not browser input.
        'daily_character_limit' => 0, // Set a deliberate budget before enabling.
        'voices' => [
            'xF681s0UeE04gsf0mVsJ' => [
                'label' => 'Radio Rubben – alternativ prøvestemme',
                'approved' => false,
                'rights_reference' => 'Valgt av eier til prøve; bruksrettigheter verifiseres før produksjon',
                'scope' => 'all',
                'programs' => [],
            ],
            'azrGjm6gYkR15bxb9cVv' => [
                'label' => 'Radio Rubben',
                'approved' => true, // Owner selected the original voice again for the v4 trial.
                'rights_reference' => 'Eierbekreftelse i RR Audio-oppdraget, 2026-10-08',
                'scope' => 'all', // Owner approved all Radio Rubben contexts; editorial gates remain.
                'programs' => [],
            ],
        ],
        'audio_profile' => [
            'approved' => true, // Approved for trials, not production rollout.
            'version' => 'rr-tale-trial-0.1.0',
            'lufs' => -18.0,
            'true_peak_db' => -2.0,
            'sample_rate' => 44100,
            'send_format' => 'mp3',
            'max_silence_seconds' => 1.0,
            'bitrate_kbps' => 192,
        ],
    ],
    'vipps_environment' => 'test',
    'vipps_client_id' => '',
    'vipps_client_secret' => '',
    'vipps_allowed_phones' => [],
];
