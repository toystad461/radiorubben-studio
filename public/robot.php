<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if ($config['auth_mode'] !== 'entra' || !current_user()) {
    http_response_code(403);
    exit('Ingen tilgang.');
}
// Keep old bookmarks working behind the same access and launch checks.
redirect('/#produksjon');
