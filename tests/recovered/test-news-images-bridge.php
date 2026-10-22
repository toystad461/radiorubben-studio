<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$dir=sys_get_temp_dir().'/rrni-bridge-'.bin2hex(random_bytes(8));
mkdir($dir,0700);mkdir($dir.'/data',0700);
try {
    copy($root.'/app/news-images-contract.php',$dir.'/contract.php');
    copy($root.'/app/data/01-BILDEREGISTER.json',$dir.'/data/01-BILDEREGISTER.json');
    require $dir.'/contract.php'; // Same load order as the WordPress newsroom bridge.
    require $root.'/app/news-images.php';
    if(!function_exists('studio_images_prepare')||count(rrni_assets())!==21)throw new RuntimeException('Shared image contract did not load');
    echo "OK: WordPress contract reused; pinned registry remains verified\n";
} finally {unlink($dir.'/contract.php');unlink($dir.'/data/01-BILDEREGISTER.json');rmdir($dir.'/data');rmdir($dir);}
