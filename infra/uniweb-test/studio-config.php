<?php
// Isolated local login follows the existing test fixture; external auth is blocked.
return [
    'site_mode' => 'app', 'auth_mode' => 'entra',
    'base_url' => 'https://5.249.252.78', 'local_users_enabled' => true,
    'tenant_id' => '00000000-0000-0000-0000-000000000001',
    'client_id' => '00000000-0000-0000-0000-000000000002',
    'client_secret' => 'synthetic-local-test-only',
    'openai_api_key' => '', 'openai_model' => '', 'news_images_enabled' => false,
    'rr_audio' => ['enabled' => false, 'test_only' => true, 'api_key' => '',
        'daily_character_limit' => 0, 'crm_demo_enabled' => false],
];
