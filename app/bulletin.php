<?php
declare(strict_types=1);
require_once __DIR__.'/weather-script.php';

function rr_bulletin_next_hour(?int $now=null):int { return (intdiv($now??time(),3600)+1)*3600; }
function rr_bulletin_time(int $at):DateTimeImmutable { return (new DateTimeImmutable('@'.$at))->setTimezone(new DateTimeZone('Europe/Oslo')); }
function rr_bulletin_intro(int $at):string {
    $d=rr_bulletin_time($at);$h=(int)$d->format('G');
    $day=['søndag','mandag','tirsdag','onsdag','torsdag','fredag','lørdag'][(int)$d->format('w')];
    $period=$h<5?'natt':($h<10?'morgen':($h<12?'formiddag':($h<18?'ettermiddag':'kveld')));
    $hours=['tolv','ett','to','tre','fire','fem','seks','sju','åtte','ni','ti','elleve'];
    return 'Det er '.$day.' '.$period.', klokken er '.$hours[$h%12].', og her er nyhetene på Radio Rubben.';
}
function rr_bulletin_places():array {
    return ['bomlo'=>['name'=>'Bømlo · Bremnes','lat'=>59.793,'lon'=>5.172], 'stord'=>['name'=>'Stord · Leirvik','lat'=>59.7798,'lon'=>5.5005], 'haugesund'=>['name'=>'Haugesund','lat'=>59.4136,'lon'=>5.268]];
}
function rr_bulletin_source_valid(array $i):bool {
    try {studio_relevance_require($i,$i['sourceCheck']['source']??[],'radio');} catch(InvalidArgumentException) {return false;}
    return empty($i['bulletin'])&&($i['status']??'')==='ready'&&!empty($i['verified'])&&!empty($i['approvedBy'])
        &&studio_board_channel($i)!=='web'&&studio_news_check_current($i)&&studio_news_original_read($i,$i['sourceCheck']??[])
        &&studio_news_radio_credit($i)&&(strtotime($i['sourceCheck']['source']['fetchedAt']??'')?:0)>=time()-3600;
}
function rr_bulletin_text(array $b):string {
    $parts=[rr_bulletin_intro($b['airAt'])];
    foreach($b['sources']as$n=>$s){if($n>0)$parts[]='Videre i nyhetene.';$parts[]=$s['item']['script'];}
    if($b['weather'])$parts[]=$b['weather']['text'];
    $parts[]='Det var nyhetene på Radio Rubben. Stemmen i denne sendingen er KI-generert.';
    return implode("\n\n",$parts);
}
/** Composite evidence is explicit; never pretend weather or multiple originals are one article. */
function rr_bulletin_checked(array $item,array $variant,?array $board=null):bool {
    $b=$item['bulletin']??[];
    if(($b['valid']??false)!==true||($b['airAt']??0)<=time()||empty($b['sources'])||($variant['profile']??'')!=='bulletin')return false;
    if($board!==null){$live=array_column($board['items'],null,'id');foreach($b['sources']as$s)if(!isset($live[$s['id']])||studio_board_bulletin_identity($live[$s['id']])!==$s['hash'])return false;}
    foreach($b['sources']as$s)if(!rr_bulletin_source_valid($s['item']))return false;
    if($b['weather']&&(!studio_weather_script_current($b['weather'])||($b['weather']['airAt']??null)!==$b['airAt']))return false;
    return ($variant['script']??'')===rr_bulletin_text($b)&&($item['script']??'')===$variant['script'];
}
function rr_bulletin_create(array $ids,int $airAt,string $area,array $user,?string $path=null,?callable $weather=null):string {
    rr_audio_role($user);
    if(count($ids)<1||count($ids)>5||count(array_unique($ids))!==count($ids)||array_filter($ids,fn($x)=>!is_string($x)||!preg_match('/^[a-f0-9]{16}$/D',$x)))throw new InvalidArgumentException('Velg fra én til fem nyhetssaker.');
    if($airAt%3600!==0||$airAt<=time()||$airAt>time()+8*3600)throw new InvalidArgumentException('Velg en kommende hel time, inntil åtte timer fram.');
    if($area!=='none'&&!isset(rr_bulletin_places()[$area]))throw new InvalidArgumentException('Velg et gyldig værområde.');
    $board=studio_board_read($path);$sources=[];$programs=[];
    // Retain the rundown's editorial order, regardless of POST parameter order.
    foreach($board['items']as$i)if(in_array($i['id'],$ids,true)){
        if(!rr_bulletin_source_valid($i))throw new InvalidArgumentException('Saken «'.$i['title'].'» må ha fersk kildekontroll og være merket klar først.');
        $copy=$i;unset($copy['history'],$copy['audioScripts'],$copy['web']);
        $sources[]=['id'=>$i['id'],'hash'=>studio_board_bulletin_identity($i),'item'=>$copy];$programs[]=$i['program']??'';
    }
    if(count($sources)!==count($ids))throw new InvalidArgumentException('En valgt sak finnes ikke lenger.');
    if(count(array_unique($programs))!==1)throw new InvalidArgumentException('Velg saker med samme programtilknytning.');
    $forecast=null;
    if($area!=='none'){
        $p=($weather??'studio_weather')(rr_bulletin_places()[$area],$airAt);
        // Reuse the reviewed weather renderer, now also supporting a selected single place.
        $forecast=['text'=>studio_weather_script_text([$p]),'places'=>[$p],'airAt'=>$airAt,'generatedAt'=>gmdate('c'),'expiresAt'=>gmdate('c',min(time()+3600,$airAt)),'source'=>'https://www.met.no/','status'=>'draft'];
    }
    $b=['version'=>1,'airAt'=>$airAt,'area'=>$area,'sources'=>$sources,'weather'=>$forecast,'valid'=>true];$text=rr_bulletin_text($b);
    if(mb_strlen($text)>2200)throw new InvalidArgumentException('Samlet manus er for langt. Kort ned enkeltsakene eller velg færre saker (maks 2200 tegn).');
    return studio_board_change(static function(&$board)use($b,$text,$user,$programs){
        if(count(studio_board_active($board))>=30)throw new InvalidArgumentException('Sendelisten er full.');
        $live=array_column($board['items'],null,'id');foreach($b['sources']as$s)if(!isset($live[$s['id']])||studio_board_bulletin_identity($live[$s['id']])!==$s['hash']||!rr_bulletin_source_valid($live[$s['id']]))throw new InvalidArgumentException('En sak ble endret. Lag sendingen på nytt.');
        $id=bin2hex(random_bytes(8));$board['items'][]=['id'=>$id,'originId'=>null,'title'=>'Nyhetssending '.rr_bulletin_time($b['airAt'])->format('d.m H:i T'),'sourceName'=>'Radio Rubben · samlet sending','sourceUrl'=>'','sourceAt'=>gmdate('c'),'capturedAt'=>gmdate('c'),'summary'=>'Samlet manus med sporbare enkeltsaker og vær.','channel'=>'radio','program'=>$programs[0],'script'=>$text,'notes'=>'','status'=>'draft','verified'=>false,'createdAt'=>gmdate('c'),'updatedAt'=>gmdate('c'),'createdBy'=>$user['name']??'Medarbeider','approvedBy'=>null,'revision'=>1,'bulletin'=>$b,'audioScripts'=>['bulletin'=>['profile'=>'bulletin','script'=>$text,'check'=>[],'scriptApproval'=>null,'generation'=>['aiPolicyVersion'=>RR_AI_POLICY_VERSION,'stylebookVersion'=>RR_STYLEBOOK_VERSION,'kind'=>'deterministic_bulletin_assembly']]]];return $id;
    },$path);
}
