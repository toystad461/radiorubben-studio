<?php
declare(strict_types=1);
require_once __DIR__.'/users.php';
require_once __DIR__.'/music-library-preparation.php';

// Stable typed occurrence contract. Only music can be created/played in this delivery.
const MUSIC_PLAY_QUEUE_KINDS=['music','jingle','program_break'];
// A saved rundown, not an on-air state or an audio transport.
function music_play_queue_path(): string { return (realpath(dirname(__DIR__).'/config') ?: dirname(__DIR__).'/config').'/music-play-queue.private.json'; }
function music_play_queue_empty(): array { return ['schemaVersion'=>1,'revision'=>0,'entries'=>[]]; }
function music_play_queue_load(string $path): array
{
    if (is_link($path)) throw new RuntimeException('Ugyldig køfil.');
    if (!file_exists($path)) return music_play_queue_empty();
    if (!is_file($path) || filesize($path)>2097152) throw new RuntimeException('Køfilen må kontrolleres.');
    try { $q=json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('Køfilen må kontrolleres.',0,$e); }
    if (!is_array($q) || ($q['schemaVersion']??null)!==1 || !is_int($q['revision']??null) || $q['revision']<0
        || !is_array($q['entries']??null) || !array_is_list($q['entries']) || count($q['entries'])>100) throw new RuntimeException('Køfilen må kontrolleres.');
    $seen=[];
    foreach ($q['entries'] as $e) {
        if (!is_array($e) || !in_array($e['kind']??null,MUSIC_PLAY_QUEUE_KINDS,true)) throw new RuntimeException('Ugyldig køinnslag.');
        foreach (['id'=>32,'trackId'=>32,'sha256'=>64] as $key=>$length) {
            if (!is_string($e[$key]??null) || !preg_match('/^[a-f0-9]{'.$length.'}$/D',$e[$key])) throw new RuntimeException('Ugyldig køreferanse.');
        }
        if (isset($e['locked']) && !is_bool($e['locked'])) throw new RuntimeException('Ugyldig innslagslås.');
        if (isset($seen[$e['id']])) throw new RuntimeException('Duplikat i køfilen.');
        $seen[$e['id']]=true;
    }
    return $q;
}
function music_play_queue_location(string $path): void
{
    $dir=dirname($path);
    if (!is_dir($dir) || is_link($dir) || realpath($dir)!==$dir || is_link($path) || is_link($path.'.lock')) throw new RuntimeException('Kølagringen er utilgjengelig.');
}
function music_play_queue_read(?string $path=null): array
{
    $path??=music_play_queue_path(); music_play_queue_location($path);
    if (!file_exists($path.'.lock')) {
        if (file_exists($path)) throw new RuntimeException('Kølåsen mangler.');
        return music_play_queue_empty();
    }
    $h=fopen($path.'.lock','rb');
    if (!$h) throw new RuntimeException('Køen kunne ikke leses.');
    try {
        if (!flock($h,LOCK_SH)) throw new RuntimeException('Køen er opptatt.');
        return music_play_queue_load($path);
    } finally { flock($h,LOCK_UN); fclose($h); }
}
function music_play_queue_change(array $input,?array $user,string $csrf,string $expected,?string $path=null,?string $libraryRoot=null): array
{
    if (!studio_can($user,'produce') || $expected==='' || !hash_equals($expected,$csrf)) throw new InvalidArgumentException('Køendringen er ikke autorisert.');
    if (!is_int($input['revision']??null) || $input['revision']<0) throw new InvalidArgumentException('Ugyldig køversjon.');
    $action=$input['action']??null;
    if (!in_array($action,['add','remove','move','clear'],true)) throw new InvalidArgumentException('Ukjent køhandling.');
    $track=null;
    if ($action==='add') {
        foreach (music_library_read($libraryRoot) as $candidate) if ($candidate['id']===($input['trackId']??null)) { $track=$candidate; break; }
        if (!$track || !$track['available'] || $track['sha256']!==($input['sha256']??null)) throw new InvalidArgumentException('Lydfilen mangler eller er endret. Oppdater biblioteket.');
    }
    $path??=music_play_queue_path(); music_play_queue_location($path);
    $h=fopen($path.'.lock','c+b');
    if (!$h) throw new RuntimeException('Køen kunne ikke låses.');
    $temp=null;
    try {
        if (!chmod($path.'.lock',0600) || !flock($h,LOCK_EX)) throw new RuntimeException('Køen er opptatt.');
        $q=music_play_queue_load($path);
        if ($q['revision']!==$input['revision']) throw new DomainException('Køen er endret i en annen fane. Oppdater før du prøver igjen.');
        $before=$q['entries'];
        if (isset($q['studio'])) throw new InvalidArgumentException('Bruk Musikkstudio for å endre den planlagte køen.');
        if ($action==='add') {
            if (count($before)>=100) throw new InvalidArgumentException('Køen kan ha inntil 100 innslag.');
            $q['entries'][]=['id'=>bin2hex(random_bytes(16)),'kind'=>'music','trackId'=>$track['id'],'sha256'=>$track['sha256']];
        } elseif ($action==='clear') {
            if (array_filter($q['entries'],fn($e)=>$e['locked']??false)) throw new InvalidArgumentException('Lås opp innslagene før tømming.');
            $q['entries']=[];
        } else {
            $at=array_search($input['id']??null,array_column($before,'id'),true);
            if ($at===false) throw new InvalidArgumentException('Innslaget finnes ikke lenger i køen.');
            $entry=$q['entries'][$at]; if ($entry['locked']??false) throw new InvalidArgumentException('Lås opp innslaget først.'); array_splice($q['entries'],$at,1);
            if ($action==='move') {
                if (!array_key_exists('beforeId',$input)) throw new InvalidArgumentException('Velg plassering i køen.');
                if ($input['beforeId']===$entry['id']) $q['entries']=$before;
                elseif ($input['beforeId']===null) $q['entries'][]=$entry;
                else {
                    $target=array_search($input['beforeId'],array_column($q['entries'],'id'),true);
                    if ($target===false) throw new InvalidArgumentException('Plasseringen finnes ikke lenger.');
                    array_splice($q['entries'],$target,0,[$entry]);
                }
            }
        }
        if ($before===$q['entries']) return $q;
        $q['revision']++; $q['updatedAt']=gmdate('c');
        $q['updatedBy']=(string)($user['id']??$user['name']??$user['role']);
        $temp=tempnam(dirname($path),'.music-play-queue-');
        $json=json_encode($q,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        if (!$temp || !chmod($temp,0600) || file_put_contents($temp,$json)!==strlen($json) || !rename($temp,$path)) throw new RuntimeException('Køen kunne ikke lagres.');
        $temp=null; return $q;
    } finally { if ($temp && is_file($temp)) unlink($temp); flock($h,LOCK_UN); fclose($h); }
}
function music_play_queue_snapshot(array $q,array $tracks): array
{
    $catalog=[];
    foreach ($tracks as $track) {
        $metadata=music_library_metadata($track); $fields=$metadata['fields']??[];
        foreach (['artist','title'] as $key) {
            $value=$track['manualOverrides'][$key]??null;
            if (is_string($value) && trim($value)!=='') $fields[$key]=$value;
        }
        $catalog[$track['id']]=[
            'trackId'=>$track['id'],'sha256'=>$track['sha256'],
            'title'=>$fields['title']??$track['originalName'],'artist'=>$fields['artist']??'Ukjent artist',
            'duration'=>$metadata['durationSeconds']??null,'available'=>($track['available']??false)===true,
        ];
    }
    $entries=[];
    foreach ($q['entries'] as $e) {
        $track=$catalog[$e['trackId']]??null;
        $available=$track && $track['available'] && $track['sha256']===$e['sha256'];
        $entries[]=array_merge($e,[
            'title'=>$track['title']??'Låt fjernet fra biblioteket','artist'=>$track['artist']??'',
            'duration'=>$track['duration']??null,'available'=>(bool)$available,
            'audioUrl'=>$available?'/music-library-audio.php?id='.$e['trackId'].'&version='.$e['sha256']:null,
        ]);
    }
    return ['revision'=>$q['revision'],'entries'=>$entries,'catalog'=>array_values($catalog),'updatedAt'=>$q['updatedAt']??null];
}
