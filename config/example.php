<?php
// Copy to config/local.php on the server, outside the public document root.
return [
    'site_mode' => 'coming-soon', // app enables the configured demo or Entra mode
    'auth_mode' => 'demo', // demo = public static content; entra = authentication required
    'base_url' => 'http://localhost:8080', // https://studio.radiorubben.no in production
    // Isolated AzuraCast TEST page; waiting mode and Entra still apply.
    'azuracast_test_enabled' => false,
    'azuracast_base_url' => '', // empty = synthetic data; e.g. http://127.0.0.1:8085 (no /api)
    'azuracast_station_id' => '1',
    'azuracast_api_key' => '', // server-side only; public Now Playing does not need a key
    'azuracast_allow_local_http' => false, // true only for explicit local MacBook/Docker testing
    'tenant_id' => '',
    'client_id' => '',
    'client_secret' => '',
];
