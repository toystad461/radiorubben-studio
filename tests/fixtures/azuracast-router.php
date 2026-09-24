<?php
// Local fake upstream for cURL and authenticated HTTP integration tests; never shipped to public/.
header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_contains($path, '/redirect')) {
    header('Location: /api/nowplaying/test');
    http_response_code(302);
    exit;
}
if (str_contains($path, '/timeout')) { sleep(6); exit('{}'); }
if (str_contains($path, '/oversized')) { echo str_repeat('x', 2097153); exit; }
if (str_contains($path, '/invalid')) { echo '{broken'; exit; }
if (str_starts_with($path, '/api/nowplaying/')) {
    // The public request must not disclose a configured admin key, even to this upstream.
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) { http_response_code(400); exit('{}'); }
    $data = require __DIR__.'/../../app/integrations/fixtures/azuracast-nowplaying.php';
    $data['station']['name'] = '<script>alert("xss")</script>';
    $data['live']['is_live'] = true;
    $data['live']['streamer_name'] = 'Test-DJ';
    if (str_contains($path, '/offline')) {
        $data['now_playing'] = null;
        $data['song_history'] = [];
        $data['is_online'] = false;
        $data['live']['is_live'] = false;
    }
    echo json_encode($data);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || ($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer test-only-secret') {
    http_response_code(403); exit('{}');
}
echo '[{"id":1,"name":"Test"}]';
