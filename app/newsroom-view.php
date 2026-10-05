<?php
declare(strict_types=1);

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
    $p=parse_url($url);
    return is_array($p)&&($p['scheme']??'')==='https'&&($p['host']??'')==='www.radiorubben.no'
        &&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['port'])&&!isset($p['query'])&&!isset($p['fragment'])
        &&str_starts_with($p['path']??'','/wp-content/uploads/')&&!str_contains(rawurldecode($p['path']??''),'..')
        &&(bool)preg_match('/\.(?:png|jpe?g|webp)$/i',$p['path']??'');
}
