<?php
declare(strict_types=1);
require __DIR__ . '/studio-private/app/broadcast.php';
function broadcast_check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "OK $label\n";
}
function broadcast_reject(callable $fn, string $label): void {
    try { $fn(); } catch (InvalidArgumentException $e) { broadcast_check(true, $label); return; }
    throw new RuntimeException($label);
}
$dir = sys_get_temp_dir() . '/broadcast-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
$path = $dir . '/board.json';
$user = ['role'=>'presenter','name'=>'Test presenter'];
$config = ['openai_api_key'=>'mock-secret','openai_model'=>'mock-model'];
$program = studio_program_default();
$input = ['program'=>$program,'date'=>'2026-10-05','presenter'=>'Thomas','sources'=>[], 'token'=>str_repeat('a',32)];
$source = ['id'=>'source-a', 'title'=>'Kommunen inviterer til møte', 'sourceName'=>'Bømlo kommune',
    'publishedAt'=>'2026-10-04T12:00:00Z', 'fetchedAt'=>'2026-10-04T12:10:00Z',
    'url'=>'https://example.org/source', 'summary'=>'Kommunen inviterer til et åpent møte i kulturhuset torsdag klokka 18. Møtet er gratis.'];
try {
    studio_board_add_source($source, $user, $path);
    $before = studio_board_read($path); $sourceId=$before['items'][0]['id'];
    $id = studio_broadcast_create($input, $user, [], $path);
    $board = studio_board_read($path); $draft=$board['broadcastDrafts'][0];
    broadcast_check($board['items'] === $before['items'], 'existing sending items unchanged');
    broadcast_check(count($draft['blocks']) === 16 && $draft['blocks'][0]['start'] === '05:00' && $draft['blocks'][15]['end'] === '09:00', 'four-hour template stored');
    $last='05:00'; $music=0;
    foreach ($draft['blocks'] as $block) { broadcast_check($block['start'] === $last, 'contiguous block '.$block['id']); $last=$block['end']; $music+=count($block['music']); }
    broadcast_check($music === 52 && count($draft['reserves']) === 8, '52 music candidates and eight reserves');
    broadcast_check($draft['status']==='draft' && $draft['archiveStatus']==='unknown' && $draft['timingStatus']==='unmeasured', 'no readiness or archive claims');
    broadcast_check($draft['stories'] === [] && !str_contains(json_encode($draft), 'Blub') && !str_contains(json_encode($draft), '23. september'), 'no dated sample news or web story imported');
    broadcast_check(!str_contains(studio_broadcast_export($draft),'{{presenter}}') && str_contains(studio_broadcast_export($draft),'Thomas'), 'presenter substitution and readable export');
    broadcast_check(studio_broadcast_create($input,$user,[],$path) === $id && count(studio_board_read($path)['broadcastDrafts'])===1, 'repeat request is idempotent');
    broadcast_reject(fn()=>studio_broadcast_create($input,['role'=>'observer'],[],$path), 'observer cannot create');
    foreach (['2026-02-30','bad-date','2026-10-05<script>'] as $date)
        broadcast_reject(fn()=>studio_broadcast_create(array_replace($input,['date'=>$date]),$user,[],$path), 'invalid date rejected');
    broadcast_reject(fn()=>studio_broadcast_create(array_replace($input,['program'=>'unknown']),$user,[],$path), 'unknown profile rejected');
    broadcast_reject(fn()=>studio_broadcast_create(array_replace($input,['program'=>'']),$user,[],$path), 'legacy is not silently assigned');
    $registry=studio_program_registry();
    $registry['programs']['test-profile']=['id'=>'test-profile','name'=>'Test','profileVersion'=>1,'style'=>'TEST_ONLY','learningEnabled'=>true];
    $other=$before['items'][0]; $other['id']='other'; $other['program']='test-profile';
    $legacy=$before['items'][0]; $legacy['id']='legacy'; unset($legacy['program']);
    studio_board_change(static function (&$board) use($other,$legacy) {$board['items'][]=$other; $board['items'][]=$legacy;},$path);
    $input['token']=str_repeat('b',32);
    foreach (['other','legacy','missing'] as $sourceKey)
        broadcast_reject(fn()=>studio_broadcast_create(array_replace($input,['sources'=>[$sourceKey]]),$user,[],$path,null,$registry), 'foreign legacy or missing source rejected');
    broadcast_reject(fn()=>studio_broadcast_create(array_replace($input,['sources'=>[$sourceId,$sourceId]]),$user,[],$path), 'duplicate source rejected');
    broadcast_reject(fn()=>studio_broadcast_create(array_replace($input,['sources'=>[['malformed']]]),$user,[],$path), 'malformed source rejected');
    $original=file_get_contents($path);
    studio_memory_change('propose',['program'=>$program,'text'=>'Bruk korte setninger for morgenradio.','styleOnly'=>'1'],$user,$path);
    $rule=studio_board_read($path)['editorialRules'][0];
    studio_memory_change('approve',['id'=>$rule['id'],'revision'=>1],['role'=>'admin','name'=>'Admin'],$path);
    studio_memory_change('propose',['program'=>'test-profile','text'=>'FOREIGN_RULE must not leak.','styleOnly'=>'1'],$user,$path,$registry);
    $rules=studio_board_read($path)['editorialRules'];
    studio_memory_change('approve',['id'=>$rules[1]['id'],'revision'=>1],['role'=>'admin','name'=>'Admin'],$path,$registry);
    $input['sources']=[$sourceId]; $input['generateNews']='1'; $seen=null;
    $mock=static function($config,$payload) use (&$seen) { $seen=$payload; return 'Kommunen inviterer til møte. Det melder kommunen.'; };
    studio_broadcast_create($input,$user,$config,$path,$mock);
    $board=studio_board_read($path);$generated=$board['broadcastDrafts'][1];
    broadcast_check(str_contains($seen['instructions'],'Bruk korte setninger') && !str_contains($seen['instructions'],'FOREIGN_RULE'), 'only approved own-program rules enter robot');
    broadcast_check(str_contains($seen['instructions'],'styleExamples') && str_contains($seen['instructions'],'Kildekravene over har alltid forrang') && $seen['store']===false, 'profile examples and source controls reach AI');
    broadcast_check($generated['stories'][0]['scriptOrigin']==='ai' && !$generated['stories'][0]['verified'] && $generated['stories'][0]['status']==='draft', 'generated story never automatically approved');
    broadcast_check($generated['stories'][0]['sourceSnapshot']['sourceUrl']===$source['url'] && $generated['editorial']['profileVersion']===2, 'source and profile versions preserved');
    broadcast_check(!str_contains(file_get_contents($path),'mock-secret'), 'API secret not persisted');
    $savedSource=$board['items'][0];
    broadcast_check($savedSource['generatedOriginal']===$generated['stories'][0]['script'] && $savedSource['revision']===2
        && $savedSource['generation']['editorial']===$generated['editorial'] && count($savedSource['history'])===1,
        'AI draft editable in Sending with original and learning context');
    studio_board_update($sourceId,2,'save',['title'=>$source['title'],'script'=>''],$user,$path);
    $input['token']=str_repeat('c',32); $beforeFailure=file_get_contents($path);
    try { studio_broadcast_create($input,$user,$config,$path,static fn()=> 'INSUFFICIENT_SOURCE'); throw new LogicException('expected failure'); }
    catch (RuntimeException $e) { broadcast_check(file_get_contents($path)===$beforeFailure,'insufficient AI result saves no partial plan'); }
    try { studio_broadcast_create($input,$user,[],$path,$mock); throw new LogicException('expected failure'); }
    catch (RuntimeException $e) { broadcast_check(file_get_contents($path)===$beforeFailure,'missing API config saves no partial plan'); }
    broadcast_reject(function() use($input,$user,$config,$path,$sourceId) {
        studio_broadcast_create($input,$user,$config,$path,static function() use($path,$sourceId,$user) {
            studio_board_update($sourceId,3,'save',['title'=>'Changed during AI call'],$user,$path);
            return 'Et utkast.';
        });
    },'concurrent source edit rejects stale generated plan');
    broadcast_check(count(studio_board_read($path)['broadcastDrafts'])===2,'concurrent rejection preserves existing plans');
    $input['generateNews']='';
    studio_broadcast_create($input,$user,[],$path);
    $drafts=studio_board_read($path)['broadcastDrafts'];
    broadcast_check($drafts[2]['stories'][0]['scriptOrigin']==='missing','no-AI option leaves missing script explicit');
    // Frozen draft is not overwritten when the source or profile changes.
    broadcast_check($drafts[1] === $generated,'earlier plan and context remain immutable');
    echo "Broadcast workflow passed\n";
} finally { foreach(glob($dir.'/*') as $file) unlink($file); rmdir($dir); }
