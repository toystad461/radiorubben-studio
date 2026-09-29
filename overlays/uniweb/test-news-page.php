<?php
declare(strict_types=1);
$mode = $argv[1] ?? 'get';
$tmp = sys_get_temp_dir() . '/news-page-' . bin2hex(random_bytes(4));
mkdir($tmp . '/studio-private/app/views', 0700, true);
mkdir($tmp . '/studio-private/config', 0700, true);
mkdir($tmp . '/studio-public', 0700, true);
foreach (['board', 'programs', 'news-script', 'story-script'] as $name)
    copy(__DIR__ . '/studio-private/app/' . $name . '.php', $tmp . '/studio-private/app/' . $name . '.php');
copy(__DIR__ . '/studio-public/sending.php', $tmp . '/studio-public/sending.php');
file_put_contents($tmp . '/studio-private/app/bootstrap.php', '<?php
function current_user() { return $GLOBALS["mode"] === "guest" ? null : ["name"=>"Test editor"]; }
function studio_can($user, $permission) { return $GLOBALS["mode"] !== "observer"; }
function escape($text) { return htmlspecialchars((string)$text, ENT_QUOTES, "UTF-8"); }
function redirect($url) { $GLOBALS["redirected"] = $url; exit; }
$config = [];
');
file_put_contents($tmp . '/studio-private/app/editorial-memory.php', '<?php function studio_memory_context($board, $program) { return []; }');
file_put_contents($tmp . '/studio-private/app/producer.php', '<?php');
foreach (['head', 'sidebar', 'account'] as $name) file_put_contents($tmp . '/studio-private/app/views/' . $name . '.php', '');
$item = ['id'=>'1234567890abcdef', 'originId'=>'fixture', 'title'=>'Test news', 'sourceName'=>'Bømlo kommune',
    'sourceUrl'=>'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/test.123.aspx', 'sourceAt'=>gmdate('c'),
    'summary'=>'Kildeomtale', 'program'=>'god-morgen-vestland', 'script'=>'Test script.', 'notes'=>'',
    'status'=>'draft', 'verified'=>false, 'approvedBy'=>null, 'revision'=>1,
    'sourceCheck'=>['status'=>'needs_review', 'issues'=>['<script>alert(1)</script>'], 'segments'=>[]]];
$path = $tmp . '/studio-private/config/sending-board.json';
file_put_contents($path, json_encode(['items'=>[$item]]));
$before = file_get_contents($path);
$_SESSION = ['csrf'=>'fixture-csrf']; $_GET = []; $_POST = [];
$_SERVER['REQUEST_METHOD'] = $mode === 'method' ? 'DELETE' : (in_array($mode, ['observer', 'csrf', 'forged'], true) ? 'POST' : 'GET');
if ($_SERVER['REQUEST_METHOD'] === 'POST') $_POST = ['csrf'=>$mode === 'csrf' ? 'bad' : 'fixture-csrf',
    'id'=>$item['id'], 'revision'=>1, 'action'=>$mode === 'forged' ? 'source_checked' : 'generate',
    'sourceCheck'=>['status'=>'passed']];
ob_start();
register_shutdown_function(static function () use ($tmp, $mode, $path, $before): void {
    $html = ob_get_clean(); $ok = file_get_contents($path) === $before;
    if ($mode === 'get') $ok = $ok && str_contains($html, 'Lag og kildekontroller nyhetsmanus')
        && str_contains($html, 'Robåtens kildekontroll') && str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;')
        && !str_contains($html, '<script>alert(1)</script>');
    elseif ($mode === 'guest') $ok = $ok && ($GLOBALS['redirected'] ?? '') === '/login.php';
    elseif ($mode === 'method') $ok = $ok && http_response_code() === 405;
    elseif ($mode === 'forged') $ok = $ok && ($_SESSION['sending_error'] ?? '') === 'Ukjent handling.';
    else $ok = $ok && http_response_code() === 403;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($tmp);
    if (!$ok) { fwrite(STDERR, "News page $mode failed\n"); exit(1); }
    echo "News page $mode OK\n";
});
require $tmp . '/studio-public/sending.php';
