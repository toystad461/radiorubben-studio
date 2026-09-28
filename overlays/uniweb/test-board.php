<?php
declare(strict_types=1);
require __DIR__ . '/app/board.php';
function board_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
$dir = sys_get_temp_dir() . '/rubben-board-' . bin2hex(random_bytes(5));
mkdir($dir, 0700);
$path = $dir . '/board.json';
$user = ['name'=>'Testprodusent'];
$source = ['id'=>'feed-1', 'title'=>'En lokal sak', 'sourceName'=>'Bømlo kommune',
    'url'=>'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/',
    'publishedAt'=>'2026-09-28T10:00:00Z', 'summary'=>'Kildetekst'];
try {
    board_check(studio_board_active(studio_board_read($path)) === [], 'empty board');
    studio_board_add_source($source, $user, $path);
    studio_board_add_source($source, $user, $path);
    $one = studio_board_read($path);
    board_check(count(studio_board_active($one)) === 1, 'persisted deduplicated item');
    $id = $one['items'][0]['id'];
    $blocked = false;
    try { studio_board_update($id, 1, 'ready', [], $user, $path); }
    catch (InvalidArgumentException $e) { $blocked = true; }
    board_check($blocked, 'cannot approve an unverified empty script');
    studio_board_update($id, 1, 'save', ['title'=>'En lokal sak', 'script'=>'Kort stikk.', 'notes'=>'Les rolig.', 'verified'=>'1'], $user, $path);
    $stale = false;
    try { studio_board_update($id, 1, 'save', ['title'=>'Gammel endring'], $user, $path); }
    catch (InvalidArgumentException $e) { $stale = true; }
    board_check($stale, 'stale edit rejected');
    studio_board_update($id, 2, 'ready', [], $user, $path);
    board_check(studio_board_read($path)['items'][0]['status'] === 'ready', 'verified script approved');
    studio_board_update($id, 3, 'archive', [], $user, $path);
    board_check(studio_board_active(studio_board_read($path)) === [], 'archive removes active item without deleting it');
    studio_board_add_manual('Nytt punkt', $user, $path);
    board_check(count(studio_board_active(studio_board_read($path))) === 1, 'manual item');
    $stale = false;
    try { studio_board_change(static function (array &$board): void { throw new InvalidArgumentException('no write'); }, $path); }
    catch (InvalidArgumentException $e) { $stale = true; }
    board_check($stale && count(studio_board_active(studio_board_read($path))) === 1, 'rejected mutation preserved data');
    board_check((fileperms($path) & 0777) === 0600, 'private file permissions');
    echo "Shared board storage OK\n";
} finally {
    @unlink($path); @unlink($path . '.lock'); @rmdir($dir);
}
