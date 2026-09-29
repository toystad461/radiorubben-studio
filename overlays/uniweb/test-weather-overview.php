<?php
declare(strict_types=1);
require __DIR__.'/studio-private/app/integrations/WeatherOverview.php';
$count=0;
function check_weather(bool $ok,string $label): void { global $count; if(!$ok) throw new RuntimeException($label); $count++; }
$now=strtotime('2026-09-29T05:30:00Z');
function weather_fixture(int $now): string {
    $series=[];
    for($i=-1;$i<50;$i++) $series[]=['time'=>gmdate('c',$now-1800+$i*3600),'data'=>[
        'instant'=>['details'=>['air_temperature'=>10+$i/10,'wind_speed'=>4]],
        'next_1_hours'=>['summary'=>['symbol_code'=>'partlycloudy_day'],'details'=>['precipitation_amount'=>0.2]]]];
    return json_encode(['properties'=>['meta'=>['updated_at'=>gmdate('c',$now-3600),'units'=>['air_temperature'=>'celsius','wind_speed'=>'m/s','precipitation_amount'=>'mm']],'timeseries'=>$series]]);
}
$body=weather_fixture($now);$forecast=weather_overview_parse($body,$now);
check_weather($forecast!==null,'Valid MET fixture');
$slots=weather_overview_slots($forecast,$now);
check_weather($slots['now']!==null,'Current model hour');
check_weather(weather_overview_time($slots['today']['time'])==='29.09 12:00','Today noon Oslo');
check_weather(weather_overview_time($slots['tomorrow']['time'])==='30.09 12:00','Tomorrow noon Oslo');
check_weather(weather_overview_parse('{}',$now)===null,'Malformed forecast');
check_weather(weather_overview_parse(str_replace('celsius','fahrenheit',$body),$now)===null,'Wrong units');
check_weather(weather_overview_parse(str_replace(gmdate('c',$now-3600),gmdate('c',$now+3600),$body),$now)===null,'Future model rejected');
check_weather(weather_overview_symbol('mystery')[1]==='Værtype ikke tilgjengelig','Unknown weather type');
check_weather(weather_overview_symbol('clearsky_night')[0]==='🌙','Night symbol');
check_weather(weather_overview_symbol('heavyrainshowers_day')[1]==='regnbyger','Shower symbol');
$missing=json_decode($body,true);foreach($missing['properties']['timeseries'] as &$p) unset($p['data']['next_1_hours'],$p['data']['instant']['details']['wind_speed']);unset($p);
$partial=weather_overview_parse(json_encode($missing),$now);
check_weather($partial['points'][0]['rain']===null && $partial['points'][0]['wind']===null,'Missing values not zero');
check_weather(weather_overview_slots($forecast,$now+5*86400)['now']===null,'Never show obsolete point as now');
foreach(['2026-03-28T23:30:00Z','2026-10-24T23:30:00Z','2026-12-31T23:30:00Z'] as $date) {
    $clock=strtotime($date);$f=weather_overview_parse(weather_fixture($clock),$clock);
    // Fixture is at whole UTC hours; offset is 30 minutes.
    $s=weather_overview_slots($f,$clock);
    check_weather($s['tomorrow']!==null && weather_overview_time($s['tomorrow']['time'],'H:i')==='12:00','DST/year rollover noon '.$date);
}
$dir=sys_get_temp_dir().'/weather-tests-'.bin2hex(random_bytes(4));mkdir($dir,0700);
$calls=0;
$fetch=static function($place,$modified) use(&$calls,$body,$now){$calls++;return ['status'=>200,'body'=>$body,'headers'=>['expires'=>gmdate('D, d M Y H:i:s \\G\\M\\T',$now+1800),'last-modified'=>gmdate('D, d M Y H:i:s \\G\\M\\T',$now-3600)]];};
try {
    $first=weather_overview_load('bomlo',$dir,$now,$fetch);
    check_weather($first['status']==='updated' && $calls===1,'Fresh data');
    weather_overview_load('bomlo',$dir,$now+1000,$fetch);
    check_weather($calls===1,'Honors upstream expiry');
    $conditional=weather_overview_load('bomlo',$dir,$now+2000,static function($p,$modified) use($now){check_weather($modified===gmdate('D, d M Y H:i:s \\G\\M\\T',$now-3600),'Conditional header');return ['status'=>304];});
    check_weather($conditional['status']==='updated' && $conditional['fetchedAt']===$now,'304 preserves body fetch time');
    $stale=weather_overview_load('bomlo',$dir,$now+3000,static fn()=>['status'=>503]);
    check_weather($stale['status']==='stale','Outage marks cached data stale');
    $retryCalls=0;weather_overview_load('bomlo',$dir,$now+3100,static function()use(&$retryCalls){$retryCalls++;return ['status'=>503];});
    check_weather($retryCalls===0,'Failure backoff');
    $old=weather_overview_load('bomlo',$dir,$now+90000,static fn()=>['status'=>503]);
    check_weather($old['status']==='unavailable' && $old['forecast']===null,'Expired data hidden');
    $other=weather_overview_load('bergen',$dir,$now,static fn()=>['status'=>0]);
    check_weather($other['status']==='unavailable','One city cannot borrow another forecast');
    $unknown=false;try {weather_overview_load('../escape',$dir,$now,$fetch);} catch(InvalidArgumentException $e){$unknown=true;}
    check_weather($unknown,'Unknown location rejected before filesystem');
    $text=weather_overview_script([['place'=>['name'=>'Bømlo'],'status'=>'stale','slots'=>$slots]],$now);
    check_weather(str_contains($text,'mangler ferskt')&&!str_contains($text,'grader'),'Stale data excluded from script');
    $text=weather_overview_script([['place'=>['name'=>'Bømlo'],'status'=>'updated','slots'=>$slots]],$slots['today']['time']+3600);
    check_weather(!str_contains($text,'i dag klokken 12'),'Past noon not spoken as future');
} finally {foreach(glob($dir.'/*') as $file) unlink($file);rmdir($dir);}
echo "OK $count weather checks\n";
