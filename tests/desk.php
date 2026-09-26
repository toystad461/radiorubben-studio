<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/desk.php';
function desk_reject(callable $fn, string $label): void {
    try { $fn(); } catch (DomainException $e) { check(true, $label); return; }
    throw new RuntimeException($label);
}
$d = desk_apply(desk_empty(), ['revision'=>'0','action'=>'create','title'=>'Lokalt stikk','category'=>'Nyheter','script'=>'Dette er et testmanus.','source'=>'Egen observasjon','url'=>'https://example.org/'], 'Test');
$id = array_key_first($d['items']);
check($d['items'][$id]['status'] === 'draft', 'new stories remain drafts');
desk_reject(fn()=>desk_apply($d,['revision'=>'0','action'=>'approve','id'=>$id],'Test'),'stale revisions rejected');
desk_reject(fn()=>desk_apply($d,['revision'=>'1','action'=>'queue','id'=>$id],'Test'),'draft cannot enter queue');
$d = desk_apply($d,['revision'=>'1','action'=>'approve','id'=>$id],'Test');
$d = desk_apply($d,['revision'=>'2','action'=>'queue','id'=>$id],'Test');
check($d['items'][$id]['status'] === 'queued','approved story can enter queue');
$input = ['revision'=>'3','action'=>'save','id'=>$id,'title'=>'Endret','category'=>'Lokalt','script'=>'Nytt manus','source'=>'Test','url'=>''];
$d = desk_apply($d,$input,'Test');
check($d['items'][$id]['status'] === 'draft' && !isset($d['items'][$id]['queued_at']),'editing removes approval and queue position');
desk_reject(fn()=>desk_apply($d,array_replace($input,['revision'=>'4','url'=>'javascript:alert(1)']),'Test'),'unsafe source URL rejected');
$d = desk_apply($d,['revision'=>'4','action'=>'approve','id'=>$id],'Test');
$d = desk_apply($d,['revision'=>'5','action'=>'queue','id'=>$id],'Test');
$d = desk_apply($d,['revision'=>'6','action'=>'read','id'=>$id],'Test');
check($d['items'][$id]['read_at'] !== null,'read stories retain broadcast timestamp');
desk_reject(fn()=>desk_apply($d,array_replace($input,['revision'=>'7']),'Test'),'broadcast history cannot be edited');
$dir = sys_get_temp_dir().'/rubben-desk-'.bin2hex(random_bytes(6));
mkdir($dir,0700);
try {
    $saved = desk_store($dir,['revision'=>'0','action'=>'create','title'=>'Persistent','category'=>'Vær','script'=>'','source'=>'','url'=>''],'Test');
    check(desk_store($dir) === $saved, 'desk persists across independent reads');
    $savedId = array_key_first($saved['items']);
    desk_reject(fn()=>desk_store($dir,['revision'=>'1','action'=>'approve','id'=>$savedId],'Test'),'empty manuscript cannot be approved');
    check(desk_store($dir) === $saved,'failed mutation preserves stored data');
    desk_reject(fn()=>desk_store($dir,['revision'=>'0','action'=>'read','id'=>$savedId],'Test'),'storage rejects stale writers');
} finally { foreach (glob($dir.'/*') as $file) unlink($file); rmdir($dir); }
