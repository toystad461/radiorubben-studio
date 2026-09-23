<?php
declare(strict_types=1);
$root = dirname(__DIR__);
if (!is_file($root.'/vendor/autoload.php') || !is_file($root.'/composer.lock')) throw new RuntimeException('Run composer install first.');
@mkdir($root.'/dist');
$zip = new ZipArchive();
if ($zip->open($root.'/dist/radiorubben-studio-uniweb.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create archive');
foreach (['app', 'public', 'vendor', 'docs'] as $folder) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) $zip->addFile($file->getPathname(), 'studio-app/'.substr($file->getPathname(), strlen($root)+1));
    }
}
foreach (['config/example.php', 'README.md', 'composer.json', 'composer.lock', '.htaccess'] as $file) $zip->addFile($root.'/'.$file, 'studio-app/'.$file);
$zip->close();
echo "Built dist/radiorubben-studio-uniweb.zip without local configuration or secrets.\n";
