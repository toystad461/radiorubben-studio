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
    'vipps_environment' => 'test',
    'vipps_client_id' => '',
    'vipps_client_secret' => '',
    'vipps_allowed_phones' => [],
];
