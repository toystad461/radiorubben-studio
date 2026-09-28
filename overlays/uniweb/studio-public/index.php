<?php
declare(strict_types=1);
require dirname(__DIR__) . '/studio-private/app/bootstrap.php';
if (!current_user()) redirect('/login.php');
redirect('/control.php');
