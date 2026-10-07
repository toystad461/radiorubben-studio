<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (!current_user()) redirect('/login.php');
redirect(studio_private_app_mode($config) === 'on' ? '/mobil.php' : '/control.php');
