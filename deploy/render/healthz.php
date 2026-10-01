<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$ready = is_file('/opt/studio/vendor/autoload.php')
    && is_readable('/opt/studio/config/example.php')
    && is_writable('/var/studio/config')
    && is_writable('/var/studio/sessions');
http_response_code($ready ? 200 : 503);
echo json_encode(['status' => $ready ? 'ok' : 'unavailable', 'environment' => 'staging']);
