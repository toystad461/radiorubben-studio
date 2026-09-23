<?php
declare(strict_types=1);
$root = dirname(__DIR__);
if (!is_file($root.'/vendor/autoload.php') || !is_file($root.'/composer.lock')) throw new RuntimeException('Run composer install first.');
@mkdir($root.'/dist');
$zip = new ZipArchive();
if ($zip->open($root.'/dist/radiorubben-studio-uniweb.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create archive');
// Uniweb accepts a document root with one subdirectory only. Keep private code in a sibling.
foreach (['app', 'public', 'vendor', 'docs'] as $folder) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!$file->isFile()) continue;
        $relative = substr($file->getPathname(), strlen($root)+1);
        if ($folder === 'public') {
            $content = file_get_contents($file->getPathname());
            if ($file->getExtension() === 'php') {
                $content = str_replace(" . '/app/", " . '/studio-private/app/", $content);
            }
            $zip->addFromString('studio-public/'.substr($relative, 7), $content);
        } else {
            $zip->addFile($file->getPathname(), 'studio-private/'.$relative);
        }
    }
}
foreach (['config/example.php', 'README.md', 'composer.json', 'composer.lock', '.htaccess'] as $file) $zip->addFile($root.'/'.$file, 'studio-private/'.$file);
$zip->close();
echo "Built dist/radiorubben-studio-uniweb.zip with sibling public/private directories.\n";
