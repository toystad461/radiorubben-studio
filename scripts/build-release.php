<?php
declare(strict_types=1);
// Build from composer.lock using the existing Uniweb public/private packaging.
$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root . '/VERSION'));
$commit = $argv[1] ?? getenv('GITHUB_SHA') ?: '';
if (!preg_match('/^\d+\.\d+\.\d+$/D', $version)) {
    throw new RuntimeException('Invalid VERSION. Expected e.g. 0.0.1.');
}
if (!preg_match('/^[a-f0-9]{40}$/D', $commit)) {
    throw new RuntimeException('Pass the full source commit SHA.');
}
$notes = $root . '/docs/releases/' . $version . '.md';
if (!is_file($notes)) throw new RuntimeException('Missing release notes.');
require __DIR__ . '/package.php';
$source = $root . '/dist/radiorubben-studio-uniweb.zip';
$target = $root . '/dist/radiorubben-studio-' . $version . '-uniweb.zip';
if (!copy($source, $target)) throw new RuntimeException('Cannot copy package.');
$zip = new ZipArchive();
if ($zip->open($target) !== true) throw new RuntimeException('Cannot open package.');
$manifest = json_encode(['product' => 'Radio Rubben Studio', 'version' => $version,
    'commit' => $commit], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (!$zip->addFromString('studio-public/release.json', $manifest)
    || !$zip->addFromString('studio-private/VERSION', $version . "\n")) {
    throw new RuntimeException('Cannot add release metadata.');
}
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (!is_string($name) || str_contains($name, '\\') || str_contains($name, '..')
        || !preg_match('#^studio-(public|private)/#', $name)
        || preg_match('#(^|/)(local\.php|\.env(?:\.[^/]*)?|\.git|node_modules)(/|$)#', $name)) {
        throw new RuntimeException('Disallowed file in archive.');
    }
}
if (!$zip->close()) throw new RuntimeException('Cannot finish archive.');
file_put_contents($root . '/dist/release.json', $manifest);
file_put_contents($target . '.sha256', hash_file('sha256', $target) . '  ' . basename($target) . "\n");
echo 'Release package: ' . basename($target) . "\n";
