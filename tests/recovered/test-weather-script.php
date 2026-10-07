<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/weather-script.php';
function weather_expect(bool $ok):void{if(!$ok)throw new RuntimeException('Weather assertion failed.');}
$now=strtotime('2026-10-07T11:55:00Z');
$places=[['place'=>'Bømlo · Bremnes','temperature'=>11.2,'symbol'=>'rain_day'],['place'=>'Stord · Leirvik','temperature'=>10.6,'symbol'=>'cloudy'],['place'=>'Haugesund','temperature'=>12.1,'symbol'=>'unknown']];
$text=studio_weather_script_text($places);
weather_expect(str_contains($text,'regn og rundt 11 grader')&&str_contains($text,'Haugesund er det meldt rundt 12 grader')&&!str_contains($text,'opphold'));
$path=sys_get_temp_dir().'/weather-test-'.bin2hex(random_bytes(6)).'.json';
$draft=['text'=>$text,'places'=>$places,'generatedAt'=>gmdate('c',$now),'expiresAt'=>gmdate('c',$now+3600),'status'=>'draft'];
try{
    weather_expect(studio_weather_script_tick($now-60,fn()=>$draft,$path)['status']==='waiting');
    weather_expect(studio_weather_script_tick($now,fn()=>$draft,$path)['status']==='prepared');
    weather_expect(studio_weather_script_tick($now,static function(){throw new RuntimeException('Must not fetch twice');},$path)['status']==='already-prepared');
    $old=studio_board_read($path)['draft'];weather_expect($old['revision']===1&&$old['status']==='draft');
    try{studio_weather_script_save($draft,0,'generate',$path);throw new RuntimeException('Stale revision accepted');}catch(InvalidArgumentException $e){}
    weather_expect(!studio_weather_script_current($draft,$now+3600));
    $future=$draft;$future['expiresAt']=gmdate('c',time()+7200);
    studio_weather_script_save($future,1,'generate',$path);
    studio_weather_script_save(['text'=>'Redigert manus'],2,'edit',$path);
    weather_expect(studio_weather_script_tick(time()-((int)gmdate('i')*60)+55*60,fn()=>$draft,$path)['status']==='manual-draft-preserved');
    weather_expect(count(studio_board_read($path)['history'])===2);
    $data=['properties'=>['meta'=>['updated_at'=>gmdate('c',$now-21601)],'timeseries'=>[['time'=>gmdate('c',$now),'data'=>['instant'=>['details'=>['air_temperature'=>12]]]]]]];
    try{studio_weather_summary($data,$now);throw new RuntimeException('Stale source accepted');}catch(RuntimeException $e){weather_expect($e->getMessage()==='Værgrunnlaget er for gammelt.');}
}finally{foreach([$path,$path.'.lock']as$file)if(is_file($file))unlink($file);}
echo "Weather script tests passed.\n";
