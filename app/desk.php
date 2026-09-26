<?php
declare(strict_types=1);

const DESK_CATEGORIES = ['Nyheter', 'Vær', 'Trafikk', 'Kollektiv', 'Politi', 'Sport', 'Lokalt'];
const DESK_STATES = ['draft'=>'Utkast', 'ready'=>'Godkjent', 'queued'=>'I sendingskø', 'read'=>'Lest på lufta'];

function desk_empty(): array { return ['revision'=>0, 'items'=>[]]; }

function desk_apply(array $data, array $input, string $actor): array
{
    if ((string)($input['revision'] ?? '') !== (string)$data['revision']) {
        throw new DomainException('Desken er endret i en annen fane. Last siden på nytt før du lagrer.');
    }
    $action = $input['action'] ?? '';
    $id = $input['id'] ?? '';
    $now = gmdate(DATE_ATOM);
    if ($action === 'create' || $action === 'save') {
        $title = trim((string)($input['title'] ?? ''));
        $script = trim((string)($input['script'] ?? ''));
        $source = trim((string)($input['source'] ?? ''));
        $url = trim((string)($input['url'] ?? ''));
        $category = $input['category'] ?? '';
        if ($title === '' || strlen($title) > 240 || strlen($script) > 20000 || strlen($source) > 240 || strlen($url) > 2000 || !in_array($category, DESK_CATEGORIES, true)) {
            throw new DomainException('Fyll inn tittel og kategori. Tittel/kilde kan ha inntil 240 byte og manus 20 000 byte.');
        }
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http','https'], true) || parse_url($url, PHP_URL_USER) !== null)) {
            throw new DomainException('Kildelenken må være en gyldig http- eller https-adresse uten innloggingsinformasjon.');
        }
        if ($action === 'create') {
            if (count($data['items']) >= 500) throw new DomainException('Desken er full. Arkivering må innføres før flere saker legges til.');
            $id = bin2hex(random_bytes(12));
            $data['items'][$id] = ['id'=>$id, 'created_at'=>$now, 'read_at'=>null];
        } elseif (!isset($data['items'][$id]) || $data['items'][$id]['status'] === 'read') {
            throw new DomainException('Saken finnes ikke eller er allerede lest på lufta.');
        }
        $data['items'][$id] = array_replace($data['items'][$id], ['title'=>$title, 'script'=>$script, 'source'=>$source, 'url'=>$url, 'category'=>$category, 'status'=>'draft', 'updated_at'=>$now, 'updated_by'=>$actor]);
        unset($data['items'][$id]['queued_at']);
    } else {
        if (!isset($data['items'][$id])) throw new DomainException('Saken finnes ikke.');
        $item = &$data['items'][$id];
        $target = ['approve'=>'ready', 'queue'=>'queued', 'read'=>'read', 'unqueue'=>'ready'][$action] ?? null;
        $allowed = ['approve'=>['draft'], 'queue'=>['ready'], 'read'=>['queued'], 'unqueue'=>['queued']];
        if (!$target || !in_array($item['status'], $allowed[$action], true)) throw new DomainException('Denne statusendringen er ikke tillatt.');
        if ($action === 'approve' && (trim($item['script']) === '' || trim($item['source']) === '')) throw new DomainException('Manus og kilde må fylles inn før godkjenning.');
        $item['status'] = $target;
        $item['updated_at'] = $now;
        $item['updated_by'] = $actor;
        if ($action === 'queue') $item['queued_at'] = $now;
        if ($action === 'read') $item['read_at'] = $now;
        if ($action === 'unqueue') unset($item['queued_at']);
        unset($item);
    }
    $data['revision']++;
    return $data;
}

// A separate lock file protects atomic rename and prevents lost updates.
function desk_store(string $directory, ?array $input = null, string $actor = ''): array
{
    $dir = realpath($directory);
    $project = realpath(dirname(__DIR__));
    $web = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__).'/public');
    if (!$dir || !is_writable($dir) || $dir === '/' || ($project && ($dir === $project || str_starts_with($dir, $project.'/'))) || ($web && ($dir === $web || str_starts_with($dir, $web.'/')))) {
        throw new RuntimeException('Configure a writable private directory outside the deployment and document root.');
    }
    $lock = fopen($dir.'/desk.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Cannot lock desk.');
    @chmod($dir.'/desk.lock', 0600);
    $tmp = null;
    try {
        $path = $dir.'/desk.json';
        $data = is_file($path) ? json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : desk_empty();
        if (!is_array($data) || !isset($data['revision'], $data['items']) || !is_array($data['items'])) throw new RuntimeException('Invalid desk data.');
        if ($input !== null) {
            $data = desk_apply($data, $input, $actor);
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $tmp = tempnam($dir, 'desk-');
            if (!$tmp || file_put_contents($tmp, $json) !== strlen($json) || !chmod($tmp, 0600) || !rename($tmp, $path)) throw new RuntimeException('Cannot save desk.');
            $tmp = null;
        }
        return $data;
    } finally {
        if ($tmp && is_file($tmp)) unlink($tmp);
        flock($lock, LOCK_UN); fclose($lock);
    }
}
