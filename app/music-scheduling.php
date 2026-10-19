<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-producer.php';
require_once __DIR__.'/music-library-cues.php';

/** Separate test profile; does not change the editorial/robot program registry. */
function music_schedule_defaults(): array {
    return ['programId'=>'god-morgen-bomlo','name'=>'God morgen Bømlo','description'=>'Varm, positiv morgenstemning, behagelig driv og gradvise energiskifter.',
        'hard'=>['artistMinutes'=>60,'trackMinutes'=>240,'weekdays'=>[1,2,3,4,5,6,7]],
        'preferences'=>['energy'=>'moderat','maxEnergyStep'=>1,'maxTempoStep'=>20,'tags'=>['Varm','Positiv','Lett','Melodisk'],'genres'=>[],'keyWeight'=>1],
        'proposal'=>true];
}
function music_schedule_profile(array $p): array {
    if (!preg_match('/^[a-z0-9-]{1,60}$/D',$p['programId']??'') || !is_string($p['name']??null) || trim($p['name'])==='' || mb_strlen($p['name'])>100 || !is_string($p['description']??null) || mb_strlen($p['description'])>500) throw new InvalidArgumentException('Ugyldig programprofil.');
    foreach (['artistMinutes','trackMinutes'] as $k) if (!is_int($p['hard'][$k]??null) || $p['hard'][$k]<0 || $p['hard'][$k]>10080) throw new InvalidArgumentException('Repetisjonsavstand må være 0–10080 minutter.');
    music_schedule_list($p['hard']['weekdays']??null,[1,2,3,4,5,6,7],7);
    $v=$p['preferences']??[];
    if (!in_array($v['energy']??null,['rolig','moderat','energisk'],true) || !is_int($v['maxEnergyStep']??null) || $v['maxEnergyStep']<0 || $v['maxEnergyStep']>2 || !is_numeric($v['maxTempoStep']??null) || $v['maxTempoStep']<0 || $v['maxTempoStep']>168 || !in_array($v['keyWeight']??null,[0,1,2],true)) throw new InvalidArgumentException('Kontroller energitrinn, temposkifte og toneartvekt.');
    foreach (['tags','genres'] as $k) music_schedule_texts($v[$k]??null,20);
    return array_intersect_key($p,array_flip(['programId','name','description','hard','preferences','proposal']));
}
function music_schedule_list(mixed $values,array $allowed,int $max): void {
    if (!is_array($values) || !array_is_list($values) || count($values)>$max) throw new InvalidArgumentException('Ugyldig regelverdi.');
    foreach ($values as $v) if (!in_array($v,$allowed,true)) throw new InvalidArgumentException('Ukjent regelverdi.');
}
function music_schedule_texts(mixed $values,int $max): void {
    if (!is_array($values) || !array_is_list($values) || count($values)>$max) throw new InvalidArgumentException('Ugyldig tekstliste.');
    foreach ($values as $v) if (!is_string($v) || trim($v)==='' || mb_strlen($v)>80 || preg_match('/[\x00-\x1f]/',$v)) throw new InvalidArgumentException('Ugyldig tekstverdi.');
}
/** Enumerate valid UTC candidates: never silently normalize a DST gap/fold. */
function music_schedule_time(string $date,string $time,string $occurrence=''): DateTimeImmutable {
    $local=$date.' '.$time;
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D',$local)) throw new InvalidArgumentException('Velg dato og klokkeslett.');
    $wall=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$local,new DateTimeZone('UTC'));
    if (!$wall || $wall->format('Y-m-d H:i')!==$local) throw new InvalidArgumentException('Ugyldig dato eller tid.');
    $zone=new DateTimeZone('Europe/Oslo');$matches=[];
    foreach (array_unique(array_column($zone->getTransitions($wall->getTimestamp()-172800,$wall->getTimestamp()+172800),'offset')) as $offset) {
        $t=$wall->setTimestamp($wall->getTimestamp()-$offset)->setTimezone($zone);
        if ($t->format('Y-m-d H:i')===$local) $matches[]=$t;
    }
    usort($matches,fn($a,$b)=>$a<=>$b);
    if (!$matches) throw new InvalidArgumentException('Tidspunktet finnes ikke ved overgang til sommertid. Velg en annen tid.');
    if (count($matches)>1 && !in_array($occurrence,['first','second'],true)) throw new InvalidArgumentException('Klokken forekommer to ganger. Velg første eller andre forekomst ved vintertid.');
    return $matches[$occurrence==='second' && count($matches)>1?1:0];
}
function music_schedule_policy(array $v,string $sha): array {
    foreach (['approved','blocked'] as $k) if (!is_bool($v[$k]??null)) throw new InvalidArgumentException('Velg godkjenning og sperre.');
    music_schedule_texts($v['programs']??null,20);music_schedule_list($v['weekdays']??null,[1,2,3,4,5,6,7],7);music_schedule_list($v['periods']??null,MUSIC_PRODUCER_PERIODS,8);
    $f=$v['fields']??[];
    if (!is_array($f) || array_diff(array_keys($f),['energy','mood'])) throw new InvalidArgumentException('Ukjent manuell musikkvurdering.');
    if (array_key_exists('energy',$f) && !in_array($f['energy'],[null,'rolig','moderat','energisk'],true)) throw new InvalidArgumentException('Ugyldig energi.');
    if (array_key_exists('mood',$f)) music_schedule_texts($f['mood'],20);
    return ['sha256'=>$sha,'approved'=>$v['approved'],'blocked'=>$v['blocked'],'programs'=>$v['programs'],'weekdays'=>$v['weekdays'],'periods'=>$v['periods'],'fields'=>$f,'source'=>'manual'];
}
function music_schedule_track(array $t,?array $policy=null): array {
    $m=music_library_metadata($t);$a=music_library_analysis_current($t);$ai=music_library_producer_current($t);$cue=music_library_cues_current($t);
    $fields=$m['fields']??[];$used=[];
    foreach (['artist','title','genre'] as $k) {
        $manual=$t['manualOverrides'][$k]??null;
        if (is_string($manual) && trim($manual)!=='') {$fields[$k]=$manual;$used[$k]='manuell rettelse';}
        elseif (!empty($fields[$k])) $used[$k]='filmetadata';
    }
    $manualPolicy=($policy['sha256']??null)===$t['sha256']?$policy:null;
    $f=$manualPolicy['fields']??[];$energy=array_key_exists('energy',$f)?$f['energy']:($ai['energy']??null);
    $tags=[];
    foreach (music_library_tag_labels($t) as $tag) {
        // An overridden genre/year must not re-enter selection through old automatic tags.
        $autoField=null;foreach ($t['autoTags']['tags']??[] as $auto) if (($auto['label']??null)===$tag) $autoField=$auto['field']??null;
        if ($autoField && !empty($t['manualOverrides'][$autoField]) && !in_array($tag,$t['tags']??[],true)) continue;
        $tags[$tag]=in_array($tag,$t['tags']??[],true)?'manuell merkelapp':'kildebundet merkelapp';
    }
    foreach ($ai['tags']??[] as $tag) {if (array_key_exists('energy',$f) && in_array($tag,['Rolig','Moderat','Energisk'],true)) continue;$tags[$tag]??='AI-vurdering';}
    if (array_key_exists('mood',$f)) { foreach ($tags as $tag=>$source) if ($source==='AI-vurdering') unset($tags[$tag]); foreach ($f['mood'] as $tag) $tags[$tag]='manuell vurdering'; }
    if ($energy!==null) $used['energy']=array_key_exists('energy',$f)?'manuell vurdering':'AI-vurdering';
    foreach (['bpm','key','scale'] as $k) if (isset($a[$k])) $used[$k]=array_key_exists($k,$a['manualCorrections']??[])?'manuell rettelse':'målt estimat';
    $warnings=[];
    if (!$m) $warnings[]='Gyldig varighet/filmetadata mangler';
    if (!$a) $warnings[]='Tempo og toneart er ukjent eller utdaterte';
    if (!$ai && isset($t['producerAssessment'])) $warnings[]='Utdatert AI-vurdering brukes ikke';
    if ($energy===null) $warnings[]='Energi ukjent';
    return ['trackId'=>$t['id'],'sha256'=>$t['sha256'],'title'=>$fields['title']??$t['originalName'],'artist'=>$fields['artist']??null,'genre'=>$fields['genre']??null,
        'duration'=>$m['durationSeconds']??null,'bpm'=>$a['bpm']??null,'key'=>$a?($a['key'].' '.$a['scale']):null,'energy'=>$energy,'tags'=>$tags,
        'periods'=>$ai['periods']??[],'policy'=>$manualPolicy,'available'=>($t['available']??false)===true,'sources'=>$used,'warnings'=>$warnings,
        'cuePoints'=>$cue,'audioAnalysis'=>$a,'analysisSha256'=>$a?hash('sha256',json_encode($a,JSON_THROW_ON_ERROR)):null];
}
function music_schedule_artist(?string $artist): string { return mb_strtolower(trim(preg_replace('/\s+/u',' ',$artist??''))); }
/** Only hard constraints exclude a track. Preferences can lose points without relaxing a rule. */
function music_schedule_evaluate(array $t,array $p,DateTimeImmutable $at,array $context,?array $previous): array {
    $blocked=[];$reasons=[];$score=0;$policy=$t['policy'];$mood=music_mood($at);
    if (!$t['available']) $blocked[]='Lydfil mangler eller er endret';
    if (!$t['duration'] || !$t['artist'] || music_schedule_artist($t['artist'])==='ukjent artist') $blocked[]='Klargjør varighet og artist';
    if (!$policy || !$policy['approved']) $blocked[]='Mangler manuell testgodkjenning for denne lydversjonen';
    if ($policy['blocked']??false) $blocked[]='Manuell sperre';
    if ($policy && !in_array($p['programId'],$policy['programs'],true)) $blocked[]='Ikke godkjent for dette programmet';
    $day=(int)$at->format('N');
    if (!in_array($day,$p['hard']['weekdays'],true) || ($policy && !in_array($day,$policy['weekdays'],true))) $blocked[]='Ukedagen er sperret';
    if ($policy && $policy['periods'] && !in_array($mood['period'],$policy['periods'],true)) $blocked[]='Sendetiden er sperret: '.$mood['period'];
    foreach ($context as $old) {
        $delta=abs($at->getTimestamp()-$old['at']);
        if ($old['trackId']===$t['trackId'] && $delta<$p['hard']['trackMinutes']*60) $blocked[]='Samme låt innen '.$p['hard']['trackMinutes'].' min';
        if (music_schedule_artist($old['artist'])!=='' && music_schedule_artist($old['artist'])===music_schedule_artist($t['artist']) && $delta<$p['hard']['artistMinutes']*60) $blocked[]='Samme artist innen '.$p['hard']['artistMinutes'].' min';
    }
    $pref=$p['preferences'];$levels=['rolig'=>0,'moderat'=>1,'energisk'=>2];
    if ($t['energy']!==null) {
        $score+=6-3*abs($levels[$t['energy']]-$levels[$pref['energy']]);
        $reasons[]='Energi '.$t['energy'].' ('.$t['sources']['energy'].')';
        if ($previous && $previous['energy']!==null) { $diff=abs($levels[$t['energy']]-$levels[$previous['energy']]);$score+=$diff<=$pref['maxEnergyStep']?3:-6;$reasons[]='Energiskifte '.$diff.' trinn'; }
    }
    if ($t['bpm']!==null) {
        $reasons[]='Tempo '.round($t['bpm'],1).' BPM ('.$t['sources']['bpm'].')';
        if ($previous && $previous['bpm']!==null) { $diff=abs($t['bpm']-$previous['bpm']);$score+=$diff<=$pref['maxTempoStep']?3:-3;$reasons[]='Temposkifte '.round($diff,1).' BPM'; }
    }
    foreach ($pref['tags'] as $tag) if (isset($t['tags'][$tag])) {$score+=2;$reasons[]=$tag.' ('.$t['tags'][$tag].')';}
    if ($previous) { $shared=array_intersect(array_keys($t['tags']),array_keys($previous['tags'])); if ($shared) {$score+=1;$reasons[]='Felles stemning/merkelapp: '.implode(', ',array_slice($shared,0,3));} }
    if ($t['genre'] && in_array($t['genre'],$pref['genres'],true)) {$score+=2;$reasons[]='Sjanger '.$t['genre'].' ('.$t['sources']['genre'].')';}
    if (in_array($mood['period'],$t['periods'],true)) {$score+=3;$reasons[]='AI-vurderingen foreslår '.$mood['period'];}
    if ($pref['keyWeight'] && $previous && $t['key'] && $t['key']===$previous['key']) {$score+=$pref['keyWeight'];$reasons[]='Samme toneart '.$t['key'].' ('.$t['sources']['key'].'); liten flytvekt';}
    $reasons[]=$mood['day'].' '.$at->format('H:i:s P').' · '.$mood['period'];
    return ['blocked'=>array_values(array_unique($blocked)),'score'=>$score,'reasons'=>$reasons,'plannedAt'=>$at->format('c'),'warnings'=>$t['warnings']];
}
function music_schedule_catalog(array $tracks,array $policies): array {
    $catalog=[];foreach ($tracks as $t) $catalog[$t['id']]=music_schedule_track($t,$policies[$t['id']]??null);return $catalog;
}
/** All existing positions, including locks, are retained. Unknown duration stops time propagation. */
function music_schedule_plan(array $q,array $tracks,array $history=[],int $count=3,?string $replace=null): array {
    $s=$q['studio']??[];$schedule=$s['schedule']??null;
    if (!$schedule) throw new InvalidArgumentException('Lagre program, dato og starttid først.');
    $profile=$s['profiles'][$schedule['programId']]??null;if (!$profile) throw new InvalidArgumentException('Programprofilen mangler.');
    $at=music_schedule_time($schedule['date'],$schedule['time'],$schedule['occurrence']??'');$catalog=music_schedule_catalog($tracks,$s['policies']??[]);
    $queueIds=array_column($q['entries'],'id');$played=array_column($history,null,'entryId');$history=array_values(array_filter($history,fn($h)=>!isset($h['entryId']) || !in_array($h['entryId'],$queueIds,true)));
    $context=$history;$previous=null;$rows=[];$suggestions=[];$missing=[];$replaceAt=null;
    foreach ($q['entries'] as $i=>$e) {
        if ($e['id']===$replace) { if ($e['locked']??false) throw new InvalidArgumentException('Lås opp innslaget før bytte.');$replaceAt=$i; }
        $t=$catalog[$e['trackId']]??null;
        if (!$t || $t['sha256']!==$e['sha256'] || !$t['duration']) { $missing[]='Køinnslag '.($i+1).' mangler gyldig fil/varighet. Beregning av senere spilletider er stoppet.';break; }
        $evaluation=music_schedule_evaluate($t,$profile,$at,$context,$previous);
        if ($e['kind']!=='music') $evaluation['blocked'][]='Jingler og programstikk krever egen innholds- og avviklingsgodkjenning; ikke aktivert ennå.';
        $rows[]=array_merge($e,$t,$evaluation);
        $context[]=['trackId'=>$t['trackId'],'artist'=>$t['artist'],'at'=>$played[$e['id']]['at']??$at->getTimestamp(),'entryId'=>$e['id']];
        $at=$at->modify('+'.(int)round($t['duration']*1000).' milliseconds');$previous=$t;
    }
    if ($replace!==null && $replaceAt===null) throw new InvalidArgumentException('Innslaget finnes ikke.');
    if ($missing) return compact('rows','suggestions','missing','profile');
    if ($replace!==null) {
        $at=new DateTimeImmutable($rows[$replaceAt]['plannedAt']);$previous=$replaceAt>0?$rows[$replaceAt-1]:null;
        $beforeIds=array_column(array_slice($rows,0,$replaceAt),'id');
        $context=array_values(array_filter($context,fn($c)=>!isset($c['entryId']) || !in_array($c['entryId'],$queueIds,true) || in_array($c['entryId'],$beforeIds,true)));$count=1;
    }
    for ($i=0;$i<$count;$i++) {
        $candidates=[];$deficits=[];
        foreach ($catalog as $t) {
            if ($replace!==null && $t['trackId']===$rows[$replaceAt]['trackId']) continue;
            $v=music_schedule_evaluate($t,$profile,$at,$context,$previous);
            // A replacement shifts later airtimes. Revalidate the entire resulting queue before offering it.
            if ($replace!==null && !$v['blocked']) {
                $futureAt=$at->modify('+'.(int)round($t['duration']*1000).' milliseconds');$before=array_slice($rows,0,$replaceAt);$futureContext=$history;
                foreach ($before as $b) $futureContext[]=['trackId'=>$b['trackId'],'artist'=>$b['artist'],'at'=>strtotime($b['plannedAt'])];
                $futureContext[]=['trackId'=>$t['trackId'],'artist'=>$t['artist'],'at'=>$at->getTimestamp()];$prior=$t;
                foreach (array_slice($rows,$replaceAt+1) as $later) { $check=music_schedule_evaluate($later,$profile,$futureAt,$futureContext,$prior);if ($check['blocked']) $v['blocked'][]='Byttet bryter regler for senere køinnslag';$futureContext[]=['trackId'=>$later['trackId'],'artist'=>$later['artist'],'at'=>$futureAt->getTimestamp()];$futureAt=$futureAt->modify('+'.(int)round($later['duration']*1000).' milliseconds');$prior=$later; }
            }
            if ($v['blocked']) {foreach ($v['blocked'] as $reason) $deficits[$reason]=($deficits[$reason]??0)+1;continue;}
            $candidates[]=array_merge($t,$v);
        }
        usort($candidates,fn($a,$b)=>($b['score']<=>$a['score'])?:strcmp($a['trackId'],$b['trackId']));
        if (!$candidates) { $missing[]=['slot'=>$i+1,'plannedAt'=>$at->format('c'),'excluded'=>$deficits,'alternatives'=>['Klargjør/godkjenn flere passende låter','Flytt planlagt starttid','Juster en synlig profilregel bevisst og be om nye forslag']];break; }
        $t=$candidates[0];$suggestions[]=$t;$context[]=['trackId'=>$t['trackId'],'artist'=>$t['artist'],'at'=>$at->getTimestamp()];$previous=$t;
        $at=$at->modify('+'.(int)round($t['duration']*1000).' milliseconds');
    }
    return compact('rows','suggestions','missing','profile');
}
