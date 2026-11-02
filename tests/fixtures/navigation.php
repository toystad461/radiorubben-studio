<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/users.php';
function escape($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function icon(...$args){return '';}
$user=['role'=>$argv[1]??'admin'];
$activePage=$argv[2]??'sending';
require dirname(__DIR__,2).'/app/views/head.php';
?>
<div class="shell">
<?php require dirname(__DIR__,2).'/app/views/sidebar.php'; ?>
<div class="workspace"><main id="main" class="content"><h1>Redaksjonell arbeidsliste</h1><p>Isolert menytest – ingen innholdshandlinger.</p></main></div>
</div></body></html>
