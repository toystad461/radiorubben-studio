<?php
require dirname(__DIR__).'/app/bootstrap.php';
if(!current_user())redirect('/login.php');
require_once dirname(__DIR__).'/app/rr-red-e2e.php';
rr_e2e_config();header('X-Robots-Tag: noindex, nofollow');header('Content-Type: text/html; charset=utf-8');
echo '<h1>TEST – ingen virkelig hendelse</h1><article>';foreach(rr_e2e_lines() as $line)echo '<p>'.htmlspecialchars($line).'</p>';echo '</article>';
