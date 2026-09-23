<?php
// Copy to config/local.php on the server, outside the public document root.
return [
    'site_mode' => 'coming-soon', // app enables the configured demo or Entra mode
    'auth_mode' => 'demo', // demo = public static content; entra = authentication required
    'base_url' => 'http://localhost:8080', // https://studio.radiorubben.no in production
    'tenant_id' => '',
    'client_id' => '',
    'client_secret' => '',
];
