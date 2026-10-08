<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/audio-processing.php';
function bulletin_ok(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS Bulletin: $label\n";}
function bulletin_reject(callable $f,string $label):void {try{$f();}catch(InvalidArgumentException|RuntimeException){bulletin_ok(true,$label);return;}throw new RuntimeException('Expected rejection '.$label);}
$at=strtotime('2026-10-08T19:52:00+02:00');
bulletin_ok(rr_bulletin_time(rr_bulletin_next_hour($at))->format('Y-m-d H:i')==='2026-10-08 20:00','19:52 chooses next full hour in Oslo');
bulletin_ok(rr_bulletin_intro(rr_bulletin_next_hour($at))==='Det er torsdag kveld, klokken er åtte, og her er nyhetene på Radio Rubben.','agreed spoken introduction');
bulletin_ok(str_starts_with(rr_bulletin_intro(rr_bulletin_next_hour(strtotime('2026-10-08T23:52:00+02:00'))),'Det er fredag natt, klokken er tolv'),'midnight changes weekday');
bulletin_ok(str_contains(rr_bulletin_intro(strtotime('2026-10-09T07:00:00+02:00')),'fredag morgen, klokken er sju'),'morning introduction');
bulletin_ok(rr_bulletin_time(rr_bulletin_next_hour(strtotime('2026-03-29T01:52:00+01:00')))->format('H:i T')==='03:00 CEST','spring DST skips nonexistent hour');
bulletin_ok(rr_bulletin_time(rr_bulletin_next_hour(strtotime('2026-10-25T02:52:00+02:00')))->format('H:i T')==='02:00 CET','autumn DST distinguishes repeated hour');
$now=time();$air=rr_bulletin_next_hour();$data=['properties'=>['meta'=>['updated_at'=>gmdate('c',$now)],'timeseries'=>[]]];
foreach([$now,$air]as$n=>$t)$data['properties']['timeseries'][]=['time'=>gmdate('c',$t),'data'=>['instant'=>['details'=>['air_temperature'=>8+$n]],'next_1_hours'=>['summary'=>['symbol_code'=>'cloudy']]]];
bulletin_ok(studio_weather_summary($data,$now,$air)['temperature']===9.0,'forecast selected for airtime rather than creation time');
$stale=$data;$stale['properties']['meta']['updated_at']=gmdate('c',$now-22000);bulletin_reject(fn()=>studio_weather_summary($stale,$now,$air),'future airtime does not make stale weather fresh');
$dir=sys_get_temp_dir().'/rr-bulletin-'.bin2hex(random_bytes(5));mkdir($dir,0700);$path=$dir.'/board.json';$admin=['role'=>'admin','name'=>'Fixture editor'];
$sourceText=str_repeat('Kommunen inviterer til et møte om biblioteket. ',4);$sources=[];
foreach(['1111111111111111','2222222222222222']as$n=>$id){
 $i=['id'=>$id,'revision'=>1,'originId'=>'fixture-'.$n,'title'=>'Sak '.$n,'sourceName'=>'Bømlo kommune','sourceUrl'=>'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/mote.123.aspx','sourceAt'=>gmdate('c'),'program'=>'','script'=>'Dette melder Bømlo kommune. Kommunen inviterer til et møte.','status'=>'ready','verified'=>true,'approvedBy'=>'Fixture editor','channel'=>'radio'];
 $i['sourceCheck']=['status'=>'passed','policy'=>STUDIO_NEWS_POLICY,'checkedAt'=>gmdate('c'),'fingerprint'=>studio_news_fingerprint($i,$i['script']),'source'=>['url'=>$i['sourceUrl'],'text'=>$sourceText,'sha256'=>hash('sha256',$sourceText),'fetchedAt'=>gmdate('c'),'kind'=>'original_article']];$sources[]=$i;
}
$weather=static function($p,$t)use($data,$now){return studio_weather_summary($data,$now,$t)+['place'=>$p['name']];};
$ids=array_column($sources,'id');$get=fn($id)=>studio_case_get($id,$path);
try{
 file_put_contents($path,json_encode(['items'=>$sources]));
 bulletin_reject(fn()=>rr_bulletin_create($ids,$air,'bomlo',['role'=>'observer'],$path,$weather),'observer cannot compose');
 bulletin_reject(fn()=>rr_bulletin_create([$ids[0],$ids[0]],$air,'bomlo',$admin,$path,$weather),'duplicate stories rejected');
 bulletin_reject(fn()=>rr_bulletin_create($ids,$air-3600,'bomlo',$admin,$path,$weather),'past airtime rejected');
 bulletin_reject(fn()=>rr_bulletin_create($ids,$air+1,'bomlo',$admin,$path,$weather),'non-hour airtime rejected');
 bulletin_reject(fn()=>rr_bulletin_create($ids,$air,'invented',$admin,$path,$weather),'unknown weather area rejected');
 $id=rr_bulletin_create(array_reverse($ids),$air,'bomlo',$admin,$path,$weather);$i=$get($id);$v=$i['audioScripts']['bulletin'];
 bulletin_ok(array_column($i['bulletin']['sources'],'id')===$ids,'editorial rundown order retained');
 bulletin_ok(str_contains($v['script'],'KI-generert')&&str_contains($v['script'],'Meteorologisk institutt'),'spoken disclosure and weather attribution retained');
 bulletin_ok(rr_bulletin_checked($i,$v,studio_board_read($path)),'composite source and weather checks pass');
 $voice='azrGjm6gYkR15bxb9cVv';$c=['enabled'=>true,'test_only'=>true,'api_key'=>'fixture','model_id'=>'eleven_v4','daily_character_limit'=>10000,'voices'=>[$voice=>['approved'=>true,'rights_reference'=>'fixture','scope'=>'all']]];
 $calls=0;$transport=static function($c,$voice,$p)use(&$calls,$v){$calls++;bulletin_ok($p['text']==="Denne stemmen er KI-generert.\n\n".$v['script'],'audible disclosure and whole approved bulletin sent in one call');return str_repeat(pack('v',1000),24000);};
 $tts=fn()=>rr_audio_generate($id,$get($id)['revision'],'bulletin',$voice,[],$admin,$c,$path,$transport);
 bulletin_reject($tts,'no TTS without human composite approval');bulletin_ok($calls===0,'unapproved bulletin never reaches provider');
 rr_audio_change($id,$i['revision'],'approve_script',['profile'=>'bulletin','confirmed'=>'1'],$admin,$path);$tts();
 bulletin_ok($calls===1&&$get($id)['audioScripts']['bulletin']['audio']['status']==='needs_processing','existing audio pipeline stores one raw preview');
 $changed=$get($id);$changed['bulletin']['airAt']+=3600;bulletin_ok(!rr_audio_script_approved($changed,$changed['audioScripts']['bulletin']),'changed airtime invalidates approval');
 $changed=$get($id);$changed['bulletin']['weather']['expiresAt']=gmdate('c',time()-1);bulletin_ok(!rr_bulletin_checked($changed,$changed['audioScripts']['bulletin']),'expired weather blocks reuse');
 studio_board_change(static function(&$b)use($ids){$b['items'][0]['script'].=' Endret.';},$path);
 bulletin_ok(!$get($id)['bulletin']['valid']&&empty($get($id)['audioScripts']['bulletin']['scriptApproval']),'source edit permanently invalidates composite');
 studio_board_change(static function(&$b)use($sources){$b['items'][0]=$sources[0];},$path);
 bulletin_reject($tts,'restoring old source cannot resurrect composite approval');bulletin_ok($calls===1,'invalidated composite makes zero further TTS calls');
 studio_board_change(static function(&$b){$b['items'][0]['verified']=false;},$path);
 bulletin_reject(fn()=>rr_bulletin_create($ids,$air,'bomlo',$admin,$path,$weather),'missing source confirmation blocks composition');
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($dir);}
