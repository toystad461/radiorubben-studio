<?php
declare(strict_types=1);
require dirname(__DIR__).'/scripts/package.php';
$zip = new ZipArchive();
if ($zip->open(dirname(__DIR__).'/dist/radiorubben-studio-uniweb.zip') !== true) throw new RuntimeException('Missing package');
$publicCount = 0;
for ($i=0; $i<$zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (str_starts_with($name, 'studio-private/config/') && $name !== 'studio-private/config/example.php') throw new RuntimeException('Private runtime data in archive');
    if (preg_match('~(^|/)(?:snapshots|backups?|node_modules|\.git)(/|$)|\.(?:key|pem|bak|log)$~i', $name)) throw new RuntimeException('Unexpected archive entry: '.$name);
    if (!str_starts_with($name, 'studio-public/') || !str_ends_with($name, '.php')) continue;
    $publicCount++;
    $source = $zip->getFromIndex($i);
    if (preg_match("~dirname\(__DIR__(?:,\s*2)?\)\s*\.\s*'/(app|config)(?=/|')~", $source)) throw new RuntimeException('Unmapped path: '.$name);
    preg_match_all("~'/studio-private/(app/[^']+)'~", $source, $matches);
    foreach ($matches[1] as $path) if ($zip->locateName('studio-private/'.$path) === false) throw new RuntimeException('Missing dependency: '.$path);
}
if ($publicCount < 24 || $zip->locateName('studio-public/assets/radio-rubben-logo.png') === false) throw new RuntimeException('Incomplete runtime package');
if ($zip->locateName('studio-public/robot.php') !== false) throw new RuntimeException('Retired Robot page must not ship');
$redirects = $zip->getFromName('studio-public/.htaccess');
if (!is_string($redirects) || !str_contains($redirects, 'RedirectMatch 302 ^/robot\\.php$ /newsdesk.php')) throw new RuntimeException('Old Robot bookmarks must reach authenticated newsroom');
$zip->close();
echo "Package structure, private paths and data exclusions OK\n";
