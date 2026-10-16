<?php
declare(strict_types=1);
require_once __DIR__.'/news-script.php';
require_once __DIR__.'/wordpress-destination.php';

function studio_newsroom_url(string $selected='',string $filter='all'): string {
    $query=['filter'=>$filter];if($selected!=='')$query['item']=$selected;
    return '/newsdesk.php?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);
}
/** Moving on is read-only and happens only after a confirmed explicit decision. */
function studio_newsroom_selection(array $cards,string $selected,string $filter,bool $advance=false): array {
    $labels=['ready'=>'Til godkjenning','attention'=>'Trenger avklaring','working'=>'Under arbeid','published'=>'Publisert'];
    if($filter==='')$filter=$cards[$selected]['status']??(array_filter($cards,static fn($c)=>$c['status']==='ready')?'ready':'all');
    if($filter!=='all'&&!isset($labels[$filter]))$filter='all';
    if($advance){
        $remaining=array_filter($cards,static fn($c,$key)=>$key!==$selected&&$c['status']==='ready',ARRAY_FILTER_USE_BOTH);
        $selected=$remaining?(string)array_key_first($remaining):'';$filter='ready';
    }
    if(!$advance&&isset($cards[$selected])&&$filter!=='all'&&$cards[$selected]['status']!==$filter)$filter=$cards[$selected]['status'];
    $visible=array_filter($cards,static fn($c)=>$filter==='all'||$c['status']===$filter);
    uasort($visible,static fn($a,$b)=>array_search($a['status'],array_keys($labels))<=>array_search($b['status'],array_keys($labels)));
    if(!$advance&&(!isset($cards[$selected])||($selected!==''&&!isset($visible[$selected]))))$selected=$visible?(string)array_key_first($visible):'';
    return ['selected'=>$selected,'filter'=>$filter,'visible'=>$visible];
}
function studio_newsroom_image_url(string $url): bool {
    return studio_wp_link_valid($url, true)
        && (studio_wp_environment() === 'test' || parse_url($url, PHP_URL_HOST) === 'www.radiorubben.no')
        && (bool)preg_match('/\.(?:png|jpe?g|webp)$/i', parse_url($url, PHP_URL_PATH) ?? '');
}

/** Presentation only. Invalid timestamps never become the current time. */
function studio_newsroom_status_time(mixed $value): ?string {
    if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D',$value))return null;
    try{$date=new DateTimeImmutable($value);}catch(Throwable){return null;}
    $errors=DateTimeImmutable::getLastErrors();
    if($errors!==false&&($errors['warning_count']||$errors['error_count']))return null;
    return $date->setTimezone(new DateTimeZone('Europe/Oslo'))->format('d.m.Y H:i');
}
function studio_newsroom_operational_status(array $status,bool $available=true): array {
    if(!$available)return ['preparation'=>['at'=>null,'message'=>'Siste klargjøring kan ikke bekreftes.'],'notification'=>['at'=>null]];
    $notification=is_array($status['notification']??null)?$status['notification']:[];
    $last=is_array($notification['lastPreparation']??null)?$notification['lastPreparation']:[];
    $at=studio_newsroom_status_time($last['at']??null);
    $state=is_string($last['state']??null)?$last['state']:'';
    $messages=[
        'prepared'=>'Et nytt forslag er klargjort. Publisering krever din godkjenning.',
        'rechecked'=>'En eksisterende tekst er kontrollert på nytt.',
        'limit'=>'Dagens grense eller grensen for aktive saker er nådd.',
        'busy'=>'En annen klargjøring pågikk ved siste forsøk.',
        'paused'=>'Klargjøringen var satt på pause ved siste forsøk.',
        'idle'=>'Ingen nye aktuelle kandidater ved siste forsøk.',
        'assessed'=>'En kilde er vurdert. Vurderingen er ikke publiseringsgodkjenning.'
    ];
    return ['preparation'=>['at'=>$at,'message'=>$at?($messages[$state]??'Siste klargjøring kan ikke bekreftes.'):'Siste klargjøring kan ikke bekreftes.'],
        'notification'=>['at'=>studio_newsroom_status_time($notification['acceptedAt']??null)]];
}
