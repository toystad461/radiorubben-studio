<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (studio_private_app_mode($config) !== 'on') { http_response_code(503); exit('Mobilappen er ikke aktivert.'); }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) { http_response_code(405); header('Allow: GET, HEAD'); exit; }
// This endpoint contains only public branding; never session/user/editorial data.
header('Content-Type: application/manifest+json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
echo json_encode(studio_private_app_manifest(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
