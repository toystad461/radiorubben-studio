<?php
declare(strict_types=1);
require_once __DIR__.'/weather.php';
require_once __DIR__.'/board.php';

function studio_weather_script_path(): string { return dirname(__DIR__).'/config/weather-script.json'; }
function studio_weather_script_text(array $places): string {
    if(count($places)<1||count($places)>3)throw new RuntimeException('Venter på værgrunnlag for valgte steder.');
    $sentences=['Her er været, ifølge Meteorologisk institutt.'];
    foreach($places as $p){
        if(!is_numeric($p['temperature']??null))throw new RuntimeException('Temperatur mangler.');
        $temperature=(int)round((float)$p['temperature']);
        $degrees=$temperature===1?'grad':'grader';
        $symbol=preg_replace('/_(day|night|polarday)$/','',(string)($p['symbol']??''));
        $description=['clearsky'=>'klarvær','fair'=>'lettskyet vær','partlycloudy'=>'delvis skyet vær','cloudy'=>'skyet vær','fog'=>'tåke','rain'=>'regn','lightrain'=>'lett regn','heavyrain'=>'kraftig regn','snow'=>'snø','lightsnow'=>'lett snø','heavysnow'=>'kraftig snø','sleet'=>'sludd','rainshowers'=>'regnbyger','lightrainshowers'=>'lette regnbyger','heavyrainshowers'=>'kraftige regnbyger'][$symbol]??null;
        $place=['Bømlo · Bremnes'=>'På Bømlo, ved Bremnes','Stord · Leirvik'=>'På Stord, ved Leirvik','Haugesund'=>'I Haugesund'][$p['place']]??$p['place'];
        $sentences[]=$place.' er det meldt '.($description?$description.' og ':'').'rundt '.$temperature.' '.$degrees.'.';
    }
    return implode(' ',$sentences);
}
function studio_weather_script_generate(): array {
    $places=[];
    foreach([['name'=>'Bømlo · Bremnes','lat'=>59.793,'lon'=>5.172],['name'=>'Stord · Leirvik','lat'=>59.7798,'lon'=>5.5005],['name'=>'Haugesund','lat'=>59.4136,'lon'=>5.268]] as $place)$places[]=studio_weather($place);
    return ['text'=>studio_weather_script_text($places),'places'=>$places,'generatedAt'=>gmdate('c'),'expiresAt'=>gmdate('c',time()+3600),'source'=>'https://www.met.no/','status'=>'draft'];
}
function studio_weather_script_current(array $draft, ?int $now=null): bool {
    $now??=time();
    return isset($draft['expiresAt']) && strtotime($draft['expiresAt'])>$now;
}
function studio_weather_script_save(array $draft,int $revision,string $action,?string $path=null): array {
    return studio_board_change(static function(array &$board)use($draft,$revision,$action):array{
        $old=$board['draft']??[];
        if((int)($old['revision']??0)!==$revision)throw new InvalidArgumentException('Manuset er endret. Last siden på nytt.');
        if($action==='edit'){
            if(!studio_weather_script_current($old))throw new InvalidArgumentException('Lag et nytt manus med ferske værdata.');
            $text=trim((string)($draft['text']??''));
            if($text==='' || strlen($text)>4000)throw new InvalidArgumentException('Skriv et kort værmanus.');
            $draft=$old;$draft['text']=$text;$draft['edited']=true;
        }elseif($action!=='generate')throw new InvalidArgumentException('Ukjent handling.');
        if($old){$board['history'][]=$old;$board['history']=array_slice($board['history'],-48);}
        $draft['revision']=$revision+1;$draft['status']='draft';
        $board['draft']=$draft;
        return $draft;
    },$path??studio_weather_script_path());
}
/** Called by the existing private newsroom scheduler, once per hourly slot after :55. */
function studio_weather_script_tick(?int $now=null,?callable $generate=null,?string $path=null): array {
    $now??=time();$path??=studio_weather_script_path();
    if((int)gmdate('i',$now)<55)return ['status'=>'waiting'];
    $slot=gmdate('Y-m-d\TH:00:00\Z',$now+300);
    $board=studio_board_read($path);$old=$board['draft']??[];
    if(($board['hourlySlot']??null)===$slot)return ['status'=>'already-prepared'];
    if(!empty($old['edited']) && studio_weather_script_current($old,$now))return ['status'=>'manual-draft-preserved'];
    $draft=($generate??'studio_weather_script_generate')();
    return studio_board_change(static function(array &$board)use($draft,$slot,$old):array{
        if(($board['hourlySlot']??null)===$slot)return ['status'=>'already-prepared'];
        if((int)($board['draft']['revision']??0)!==(int)($old['revision']??0))return ['status'=>'concurrent-edit-preserved'];
        if($old){$board['history'][]=$old;$board['history']=array_slice($board['history'],-48);}
        $draft['revision']=(int)($old['revision']??0)+1;$draft['status']='draft';
        $board['draft']=$draft;$board['hourlySlot']=$slot;
        return ['status'=>'prepared','slot'=>$slot];
    },$path);
}
