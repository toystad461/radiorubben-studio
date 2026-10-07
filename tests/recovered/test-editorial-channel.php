<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/app/case-workflow.php';
function editorial_check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
$dir=sys_get_temp_dir().'/editorial-channel-'.bin2hex(random_bytes(5)); mkdir($dir,0700); $path=$dir.'/board.json';
$user=['role'=>'admin','name'=>'Testredaktør'];
try {
    studio_board_add_manual('Test', $user, $path);
    $item=studio_board_read($path)['items'][0]; $id=$item['id'];
    editorial_check(studio_board_channel($item)==='both', 'legacy items retain both channels');
    studio_board_update($id,1,'save',['title'=>'Test','script'=>'Bevart manus','notes'=>'Notat','verified'=>'1'],$user,$path);
    studio_board_update($id,2,'ready',[],$user,$path);
    studio_board_update($id,3,'channel',['channel'=>'web'],$user,$path);
    $item=studio_board_read($path)['items'][0];
    editorial_check($item['script']==='Bevart manus' && $item['notes']==='Notat' && $item['status']==='draft' && !$item['verified'] && $item['web']['approvedHash']===null, 'channel change preserves text and invalidates approvals');
    foreach ([
        [3,'radio',$user],
        [4,'invalid',$user],
        [4,'radio',['role'=>'observer']],
    ] as [$rev,$channel,$actor]) {
        $before=file_get_contents($path); $blocked=false;
        try { studio_board_update($id,$rev,'channel',['channel'=>$channel],$actor,$path); } catch (InvalidArgumentException $e) { $blocked=true; }
        editorial_check($blocked && file_get_contents($path)===$before,'reject stale revision, invalid channel or observer without writing');
    }
    $blocked=false; try {studio_board_update($id,4,'ready',[],$user,$path);}catch(InvalidArgumentException $e){$blocked=str_contains($e->getMessage(),'radiomateriale');}
    editorial_check($blocked,'web-only item cannot be marked ready for radio');
    studio_board_update($id,4,'channel',['channel'=>'radio'],$user,$path);
    $called=false; $blocked=false;
    try { studio_web_publish($id,5,'draft',$user,['username'=>'fixture','application_password'=>'fixture'],static function()use(&$called){$called=true;return [];},$path); }
    catch(InvalidArgumentException $e){$blocked=str_contains($e->getMessage(),'radiomateriale');}
    editorial_check($blocked && !$called,'radio-only item cannot reach WordPress transport');
    $before=studio_board_read($path); $stale=studio_board_snapshot($before);
    studio_board_update($id,5,'channel',['channel'=>'both'],$user,$path);
    $blocked=false;try{studio_board_clear($stale,$user,$path,[$id]);}catch(InvalidArgumentException $e){$blocked=true;}
    editorial_check($blocked,'discard rejects changed snapshot');
    $batch=studio_board_clear(studio_board_snapshot(studio_board_read($path)),$user,$path,[$id]);
    editorial_check(studio_board_active(studio_board_read($path))===[] && studio_board_read($path)['items'][0]['script']==='Bevart manus','discard archives without deleting text');
    studio_board_undo_clear($batch,$user,$path);
    editorial_check(studio_board_active(studio_board_read($path))[0]['status']==='draft','undo restores draft');
} finally { @unlink($path); @unlink($path.'.lock'); @rmdir($dir); }
