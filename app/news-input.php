<?php
declare(strict_types=1);
require_once __DIR__.'/board.php';
require_once __DIR__.'/users.php';
require_once __DIR__.'/integrations/NewsDesk.php';

function studio_input_labels(): array {
    return ['both'=>'Radio og nett','web'=>'Nett','radio'=>'Radio','review'=>'Vurder senere','skip'=>'Forkastet'];
}

/** Same atomic storage primitive, separate file: intake cannot invalidate rundown/audio state. */
function studio_input_path(): string {return dirname(__DIR__).'/config/rss-input.private.json';}

/** Reuse canonical source IDs and feed membership. Do not create an editorial case. */
function studio_input_sources(array $feeds, string $filter='all'): array {
    // Filter before grouping: a feed-specific view must show that feed's supplied text.
    if($filter!=='all')foreach($feeds as $id=>&$feed)if($id!==$filter)$feed['items']=[];
    unset($feed);
    $items=[];
    foreach(newsdesk_sections($feeds) as $section) foreach($section['items'] as $source) {
        if($filter!=='all'&&!in_array($filter,$source['feedIds'],true)) continue;
        // In the combined view, prefer the richer RSS copy only when both copies are
        // equally fresh and identically dated. Never mix their text into a new story.
        foreach($source['feedIds'] as $feedId)foreach($feeds[$feedId]['items']??[] as $copy){
            if($copy['id']!==$source['id']||$copy['publishedAt']!==$source['publishedAt']
                ||($feeds[$feedId]['status']??'')!==($source['feedStatus']??''))continue;
            if(strlen($copy['rssText']??$copy['summary'])>strlen($source['rssText']??$source['summary'])){
                foreach(['rssTitle','rssText','rssFeed','title','summary','url','fetchedAt'] as $field){
                    unset($source[$field]);if(array_key_exists($field,$copy))$source[$field]=$copy[$field];
                }
            }
        }
        $items[$source['id']]=$source;
    }
    uasort($items,static fn($a,$b)=>strcmp($b['publishedAt'],$a['publishedAt'])?:strcmp($a['id'],$b['id']));
    return $items;
}

/** Retrieval time is not a content revision. A changed source invalidates the displayed choice. */
function studio_input_version(array $source): string {
    return hash('sha256',json_encode([$source['id'],$source['url'],$source['publishedAt'],
        $source['rssTitle']??$source['title'],$source['rssText']??$source['summary'],
        array_key_exists('rssText',$source)],JSON_THROW_ON_ERROR));
}

function studio_input_review(array $board,array $source): ?array {
    $review=$board['rssIntakeReviews'][studio_source_identity($source['url'])]??null;
    return is_array($review)&&($review['sourceVersion']??'')===studio_input_version($source)?$review:null;
}

/** A changed source returns to the working list; discarded source/history is retained. */
function studio_input_visible_sources(array $sources,array $board,string $view='active'):array {
    if($view==='all')return $sources;
    return array_filter($sources,static function(array $source)use($board,$view):bool {
        $discarded=(studio_input_review($board,$source)['channel']??null)==='skip';
        if($view==='updates')return in_array(studio_input_update($board,$source)['state'],['changed','unverified'],true)
            ||(studio_input_review($board,$source)['updateDecision']??'')==='draft';
        return $view==='discarded'?$discarded:!$discarded;
    });
}

/** Transparent first-version guidance. No learning, approval or external article fetch. */
function studio_input_suggestion(array $source):array {
    $text=($source['rssTitle']??$source['title']).' '.($source['rssText']??$source['summary']);
    if(preg_match('/\b(Bømlo|Bremnes|Svortland|Rubbestadneset|Moster|Finnås|Espevær|Folderøy|Langevåg)\b/ui',$text,$match))
        return ['label'=>'Aktuell å vurdere','reason'=>'RSS-teksten nevner '.$match[1].'. Kontroller hva saken betyr for vårt publikum.','version'=>'rss-relevance-1'];
    if(in_array('bomlo',$source['feedIds']??[],true))
        return ['label'=>'Aktuell å vurdere','reason'=>'Saken kommer fra Bømlo kommune. Lokal nytte må fortsatt vurderes.','version'=>'rss-relevance-1'];
    return ['label'=>'Usikker relevans','reason'=>'RSS-teksten gir ikke et tydelig lokalt treff. Det betyr ikke at saken er uaktuell.','version'=>'rss-relevance-1'];
}

/** Compare the same feed to the last human-reviewed snapshot; never equate freshness with truth. */
function studio_input_update(array $board,array $source):array {
    $old=$board['rssIntakeReviews'][studio_source_identity($source['url'])]??null;
    if(!is_array($old))return ['state'=>'new','label'=>'Ikke vurdert','changes'=>[]];
    if(($source['feedStatus']??'')!=='updated'||!empty($source['missingFromFeed'])
        ||($old['rssFeed']??null)!==($source['rssFeed']??null))
        return ['state'=>'unverified','label'=>'Kunne ikke kontrollere samme RSS-grunnlag','changes'=>[]];
    $changes=[];
    foreach(['sourceTitle'=>['Overskrift',$source['rssTitle']??$source['title']],
        'rssText'=>['RSS-tekst',$source['rssText']??$source['summary']],
        'sourceUrl'=>['Kildelenke',$source['url']],
        'publishedAt'=>['Kildens publiseringstid',$source['publishedAt']]] as $field=>[$label,$current]){
        if(($old[$field]??null)!==$current)$changes[]=['label'=>$label,'before'=>$old[$field]??'','after'=>$current];
    }
    $changed=($old['sourceVersion']??'')!==studio_input_version($source);
    return ['state'=>$changed?'changed':'unchanged','label'=>$changed?'Kilden er oppdatert':'RSS uendret siden vurdering','changes'=>$changes];
}

/** Watch missing accepted sources without fetching their full articles or erasing their snapshots. */
function studio_input_watch_sources(array $sources,array $board,array $feeds,string $filter='all'):array {
    foreach($board['rssIntakeReviews']??[] as $old){
        if(!in_array($old['channel']??'', ['both','web','radio'],true))continue;
        if(isset($sources[$old['sourceId']??''])){
            // Top stories and latest news may supply different text for the same URL.
            // The combined monitoring view compares the feed the editor actually read.
            if($filter==='all')foreach($feeds[$old['rssFeed']??'']['items']??[] as $copy){
                if(($copy['id']??'')!==$old['sourceId'])continue;
                $id=$old['sourceId'];$copy['feedIds']=$sources[$id]['feedIds'];
                $copy['feedStatus']=$feeds[$old['rssFeed']]['status']??'unavailable';
                $sources[$id]=array_replace($sources[$id],$copy);break;
            }
            continue;
        }
        if($filter!=='all'&&!in_array($filter,$old['feedIds']??[],true))continue;
        $feed=$old['rssFeed']??'';$spec=newsdesk_sources()[$feed]??null;
        if(!$spec||!is_string($old['sourceUrl']??null)||!is_string($old['sourceId']??null))continue;
        $sources[$old['sourceId']]=['id'=>$old['sourceId'],'url'=>$old['sourceUrl'],
            'title'=>$old['sourceTitle'],'rssTitle'=>$old['sourceTitle'],'summary'=>$old['rssText'],'rssText'=>$old['rssText'],
            'publishedAt'=>$old['publishedAt'],'fetchedAt'=>$old['fetchedAt'],'rssFeed'=>$feed,
            'feedIds'=>$old['feedIds']??[],'sourceName'=>$spec['name'],'feedStatus'=>'unavailable','missingFromFeed'=>true];
    }
    return $sources;
}

/** Studio delivery receipts only; this does not query or claim current remote WordPress state. */
function studio_input_related(array $source,array $editorial):array {
    $related=[];$identity=studio_source_identity($source['url']);
    foreach($editorial['items']??[] as $item){
        if(!preg_match('/^[a-f0-9]{16}$/D',(string)($item['id']??'')))continue;
        if(($item['originId']??null)!==$source['id']&&(!$identity||studio_source_identity((string)($item['sourceUrl']??''))!==$identity))continue;
        $delivery=$item['web']['delivery']??[];
        $related[]=['id'=>$item['id'],'title'=>$item['web']['title']??$item['title'],
            'published'=>($delivery['state']??'')==='confirmed'&&($delivery['status']??'')==='publish',
            'uncertain'=>in_array($delivery['state']??'', ['pending','unknown'],true),
            'webBody'=>$item['web']['body']??'','script'=>$item['script']??''];
    }
    return $related;
}

/** Read-only live example, fully synthetic. No cache, registry, AI or WordPress requests. */
function studio_input_demo():array {
    $feed='nrk_vestland_siste';$spec=newsdesk_sources()[$feed];$at=gmdate('c',time()-600);
    $source=['id'=>hash('sha256','rr-synthetic-update-v1'),'url'=>'https://www.nrk.no/vestland/rr-synthetic-example-1.12345678',
        'title'=>'Eksempelsak: endret åpningstid på Bømlo','rssTitle'=>'Eksempelsak: endret åpningstid på Bømlo',
        'summary'=>'Kun test: åpningstiden er endret til klokken 12.','rssText'=>'Kun test: åpningstiden er endret til klokken 12.',
        'publishedAt'=>$at,'fetchedAt'=>$at,'rssFeed'=>$feed,'feedIds'=>[$feed],'sourceName'=>'Syntetisk eksempel','feedStatus'=>'updated'];
    $previous=array_replace($source,['rssText'=>'Kun test: åpningstiden er klokken 10.','summary'=>'Kun test: åpningstiden er klokken 10.']);
    $review=['sourceId'=>$source['id'],'sourceUrl'=>$source['url'],'sourceVersion'=>studio_input_version($previous),
        'sourceTitle'=>$previous['rssTitle'],'rssText'=>$previous['rssText'],'rssFeed'=>$feed,'rssComplete'=>true,
        'feedIds'=>[$feed],'publishedAt'=>$at,'fetchedAt'=>$at,'revision'=>1,'channel'=>'both',
        'reason'=>'Syntetisk lokal informasjon for å vise oppdateringsflyten.','actor'=>'Testredaktør','at'=>$at];
    $feeds=[];foreach(newsdesk_sources() as $id=>$row)$feeds[$id]=['status'=>'updated','items'=>$id===$feed?[$source]:[],'fetchedAt'=>$at];
    return ['feeds'=>$feeds,'board'=>['items'=>[],'rssIntakeReviews'=>[studio_source_identity($source['url'])=>$review]],
        'editorial'=>['items'=>[['id'=>'0123456789abcdef','originId'=>$source['id'],'title'=>$source['title'],
            'sourceUrl'=>$source['url'],'script'=>'Syntetisk radiomanus – åpning klokken 10.',
            'web'=>['body'=>'Syntetisk publisert tekst – åpning klokken 10.','delivery'=>['state'=>'confirmed','status'=>'publish']]]]]];
}

/** Intake judgement only: no manuscript approval, generation, delivery or editorial rules. */
function studio_input_save(array $source,array $post,array $user,string $csrf,?string $path=null): array {
    $path ??= studio_input_path();
    if(!studio_can($user,'produce')||!is_string($post['csrf']??null)||!hash_equals($csrf,$post['csrf']))
        throw new InvalidArgumentException('Ingen tilgang. Last siden på nytt.');
    if(!in_array($post['action']??'', ['review','discard','draft_update','keep_published'],true)||($post['source']??'')!==$source['id']||($post['sourceVersion']??'')!==studio_input_version($source))
        throw new InvalidArgumentException('Kilden er endret. Les siste versjon før du lagrer.');
    if(($source['feedStatus']??'updated')!=='updated'||!empty($source['missingFromFeed']))
        throw new InvalidArgumentException('Kilden kunne ikke kontrolleres. Vent på fersk RSS før ny vurdering.');
    $channel=($post['action']??'')==='discard'?'skip':($post['channel']??null);$reason=$post['reason']??null;
    if(!is_string($channel)||!isset(studio_input_labels()[$channel])||!is_string($reason)
        ||mb_strlen(trim($reason))<15||mb_strlen($reason)>1000||strip_tags($reason)!==$reason)
        throw new InvalidArgumentException('Velg bruksområde og begrunn vurderingen med 15–1000 tegn.');
    $revision=filter_var($post['reviewRevision']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if($revision===false)throw new InvalidArgumentException('Ugyldig vurderingsversjon.');
    $action=$post['action'];$draft=$post['updateDraft']??'';
    if($action==='draft_update'&&(!is_string($draft)||mb_strlen(trim($draft))<15||mb_strlen($draft)>10000||strip_tags($draft)!==$draft))
        throw new InvalidArgumentException('Skriv et oppdateringsutkast på 15–10000 tegn uten HTML.');
    return studio_board_change(static function(array &$board)use($source,$channel,$reason,$user,$revision,$action,$draft):array {
        $key=studio_source_identity($source['url']);$old=$board['rssIntakeReviews'][$key]??null;
        if(($old['revision']??0)!==$revision)throw new InvalidArgumentException('Vurderingen er endret i et annet vindu. Last siden på nytt.');
        if(in_array($action,['draft_update','keep_published'],true)&&(!is_array($old)
            ||!in_array($old['channel']??'', ['both','web','radio'],true)||!in_array($channel,['both','web','radio'],true)))
            throw new InvalidArgumentException('Vurder saken for radio eller nett før oppdateringsvalg.');
        $record=['revision'=>$revision+1,'sourceId'=>$source['id'],'sourceUrl'=>$source['url'],
            'sourceVersion'=>studio_input_version($source),'sourceTitle'=>$source['rssTitle']??$source['title'],
            'rssText'=>$source['rssText']??$source['summary'],'rssComplete'=>array_key_exists('rssText',$source),
            'rssFeed'=>$source['rssFeed']??null,'feedIds'=>$source['feedIds']??[],
            'publishedAt'=>$source['publishedAt'],'fetchedAt'=>$source['fetchedAt'],
            'channel'=>$channel,'reason'=>trim($reason),'actor'=>$user['name']??'Medarbeider',
            'actorId'=>$user['id']??null,'at'=>gmdate('c')];
        if(in_array($action,['draft_update','keep_published'],true)){
            $record['updateDecision']=$action==='draft_update'?'draft':'keep';
            if($action==='draft_update')$record['updateDraft']=trim($draft);
            $record['updateBaseVersion']=($old['sourceVersion']??'')===$record['sourceVersion']&&($old['updateDecision']??'')==='draft'
                ?$old['updateBaseVersion']:$old['sourceVersion'];
        }elseif($action==='review'&&$channel!=='skip'&&($old['sourceVersion']??'')===$record['sourceVersion']&&($old['updateDecision']??'')==='draft'){
            foreach(['updateDecision','updateDraft','updateBaseVersion'] as $field)$record[$field]=$old[$field];
        }
        $record['history']=$old['history']??[];
        if($old){unset($old['history']);$record['history'][]=$old;}
        $board['rssIntakeReviews'][$key]=$record;
        return $record;
    },$path);
}
