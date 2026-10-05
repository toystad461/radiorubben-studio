<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/editorial-memory.php';
require dirname(__DIR__, 2) . '/app/story-script.php';
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); echo "OK $label\n"; }
function rejected(callable $fn, string $label): void { try { $fn(); } catch (InvalidArgumentException $e) { check(true, $label); return; } throw new RuntimeException($label); }
$dir=sys_get_temp_dir().'/learning-'.bin2hex(random_bytes(5)); mkdir($dir,0700); $path=$dir.'/board.json';
$admin=['name'=>'Thomas','role'=>'admin']; $presenter=['name'=>'Programleder','role'=>'presenter']; $observer=['role'=>'observer'];
$ruleText='Del lange setninger i korte setninger som er lette å lese høyt.';
$proposal=['text'=>$ruleText,'styleOnly'=>'1'];
$source=['id'=>'traffic:fixture','title'=>'Kommunen inviterer til møte','sourceName'=>'Bømlo kommune','url'=>'https://www.bomlo.kommune.no/mote/','publishedAt'=>'2026-09-28T10:00:00Z','summary'=>'Bømlo kommune inviterer pårørende til et åpent møte i kulturhuset torsdag klokka 18. Møtet er gratis.'];
$config=['openai_api_key'=>'test','openai_model'=>'mock-model'];
try {
    studio_board_add_source($source,$presenter,$path);
    $item=studio_board_read($path)['items'][0]; $id=$item['id'];
    check($item['program']==='god-morgen-vestland','new stories use the morning program');
    studio_board_update($id,1,'generated',['script'=>'Originalutkast.','generation'=>['editorial'=>studio_memory_context([], 'god-morgen-vestland')]],$presenter,$path);
    rejected(fn()=>studio_board_update($id,2,'ready',[],$presenter,$path),'AI output still requires source approval');
    studio_board_update($id,2,'save',['title'=>$item['title'],'script'=>'Rettet og kontrollert manus.','verified'=>'1','program'=>'god-morgen-vestland'],$presenter,$path);
    studio_board_update($id,3,'ready',[],$presenter,$path);
    $item=studio_board_read($path)['items'][0];
    check($item['generatedOriginal']==='Originalutkast.' && $item['history'][1]['before']['script']==='Originalutkast.','original and edits survive save and approval');
    $linked=$proposal+['item'=>$id,'itemRevision'=>4];
    rejected(fn()=>studio_memory_change('propose',$linked,$observer,$path),'observer cannot propose');
    rejected(fn()=>studio_memory_change('propose',array_replace($linked,['itemRevision'=>3]),$presenter,$path),'stale manuscript evidence rejected');
    studio_memory_change('propose',$linked,$presenter,$path);
    $board=studio_board_read($path); $rule=$board['editorialRules'][0];
    check($rule['evidence']['before']==='Originalutkast.' && $rule['evidence']['after']==='Rettet og kontrollert manus.','rule keeps immutable correction evidence');
    check(studio_memory_context($board,'god-morgen-vestland')['rules']===[],'pending rule is excluded');
    $approve=['id'=>$rule['id'],'revision'=>1];
    rejected(fn()=>studio_memory_change('approve',$approve,$presenter,$path),'presenter cannot approve');
    studio_memory_change('approve',$approve,$admin,$path);
    $ctx=studio_memory_context(studio_board_read($path),'god-morgen-vestland'); $payload=null;
    studio_story_script_generate($item,$config,static function($config,$p) use (&$payload) { $payload=$p; return 'Kontrollerbart utkast.'; },$ctx);
    check(str_contains($payload['instructions'],$ruleText) && str_contains($payload['instructions'],'Kildekravene over har alltid forrang'),'approved rule reaches the next AI request below source constraints');
    check(!str_contains($payload['instructions'],'Originalutkast.'),'old manuscript facts are excluded from generator instructions');
    check($payload['store']===false,'API storage remains disabled');
    check(studio_memory_context(studio_board_read($path),'another-program') === [],'unknown program receives no memory');
    studio_board_update($id,4,'generated',['script'=>'Nytt utkast.','generation'=>['model'=>'mock-model','editorial'=>$ctx]],$presenter,$path);
    $item=studio_board_read($path)['items'][0];
    check($item['history'][3]['before']['script']==='Rettet og kontrollert manus.' && $item['status']==='draft' && !$item['verified'],'regeneration preserves approved manuscript and resets approval');
    check($item['generation']['editorial']['rules'][0]['version']===2,'generation records rule version');
    rejected(fn()=>studio_memory_change('disable',$approve,$admin,$path),'stale rule decision rejected');
    studio_memory_change('disable',['id'=>$rule['id'],'revision'=>2],$admin,$path);
    check(studio_memory_context(studio_board_read($path),'god-morgen-vestland')['rules']===[],'disabled rule disappears from future requests');
    check(studio_board_read($path)['items'][0]['generation']['editorial']['rules'][0]['text']===$ruleText,'old generation retains its rule snapshot');
    studio_memory_change('propose',['text'=>'Bruk naturlige overganger mellom setningene.','styleOnly'=>'1'],$presenter,$path);
    $rule2=studio_board_read($path)['editorialRules'][1];
    studio_memory_change('reject',['id'=>$rule2['id'],'revision'=>1],$admin,$path);
    check(studio_memory_context(studio_board_read($path),'god-morgen-vestland')['rules']===[],'rejected rule excluded');
    // Legacy storage is readable without adding a misleading historical program assignment.
    file_put_contents($dir.'/legacy.json',json_encode(['items'=>[['id'=>'legacy','title'=>'Old','script'=>'Old script','notes'=>'','status'=>'draft','revision'=>1]],'updatedAt'=>null]));
    studio_board_update('legacy',1,'save',['title'=>'Old','script'=>'Edited'],$presenter,$dir.'/legacy.json');
    $legacy=studio_board_read($dir.'/legacy.json')['items'][0];
    check(($legacy['program'] ?? '') === '' && $legacy['history'][0]['before']['script']==='Old script','legacy items preserved and remain unassigned');
    echo "Editorial memory workflow passed\n";
} finally { foreach (glob($dir.'/*') as $file) unlink($file); rmdir($dir); }

