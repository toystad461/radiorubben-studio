<?php
declare(strict_types=1);
require __DIR__.'/studio-private/app/music-plan.php';
function music_check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function denied(callable $fn):void{try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Expected rejection');}
$dir=sys_get_temp_dir().'/music-test-'.bin2hex(random_bytes(5));mkdir($dir,0700);$path=$dir.'/board.json';$user=['name'=>'Produsent','role'=>'producer'];
try{
 $input=['artist'=>'Artist','title'=>'Sang','slot'=>'06:00'];
 denied(fn()=>studio_music_plan_change('add',$input,['role'=>'observer'],$path));
 denied(fn()=>studio_music_plan_change('add',array_replace($input,['slot'=>'26:00']),$user,$path));
 studio_music_plan_change('add',$input,$user,$path);$track=studio_board_read($path)['musicPlan'][0];
 music_check($track['program']==='god-morgen-vestland','program');
 $reserve=['id'=>$track['id'],'revision'=>1,'reserveArtist'=>'Annen artist','reserveTitle'=>'Reserve'];
 studio_music_plan_change('reserve',$reserve,$user,$path);$track=studio_board_read($path)['musicPlan'][0];
 music_check($track['reserve']['title']==='Reserve'&&$track['history'][0]['before']['reserve']===null,'reserve saved with history');
 music_check(!isset($track['available'])&&!isset($track['reserve']['available']),'availability is never persisted as a false guarantee');
 denied(fn()=>studio_music_plan_change('archive',['id'=>$track['id'],'revision'=>1],$user,$path));
 studio_music_plan_change('archive',['id'=>$track['id'],'revision'=>2],$user,$path);
 music_check(studio_board_read($path)['musicPlan'][0]['archived']===true,'archive preserves track');
 echo "Music plan workflow OK\n";
}finally{foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
