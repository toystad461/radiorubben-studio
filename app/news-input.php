<?php
declare(strict_types=1);
require_once __DIR__.'/board.php';
require_once __DIR__.'/users.php';
require_once __DIR__.'/integrations/NewsDesk.php';

function studio_input_labels(): array {
    return ['both'=>'Radio og nett','web'=>'Nett','radio'=>'Radio','review'=>'Vurder senere','skip'=>'Ikke aktuell'];
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

/** Intake judgement only: no manuscript approval, generation, delivery or editorial rules. */
function studio_input_save(array $source,array $post,array $user,string $csrf,?string $path=null): array {
    $path ??= studio_input_path();
    if(!studio_can($user,'produce')||!is_string($post['csrf']??null)||!hash_equals($csrf,$post['csrf']))
        throw new InvalidArgumentException('Ingen tilgang. Last siden på nytt.');
    if(($post['action']??'')!=='review'||($post['source']??'')!==$source['id']||($post['sourceVersion']??'')!==studio_input_version($source))
        throw new InvalidArgumentException('Kilden er endret. Les siste versjon før du lagrer.');
    $channel=$post['channel']??null;$reason=$post['reason']??null;
    if(!is_string($channel)||!isset(studio_input_labels()[$channel])||!is_string($reason)
        ||mb_strlen(trim($reason))<15||mb_strlen($reason)>1000||strip_tags($reason)!==$reason)
        throw new InvalidArgumentException('Velg bruksområde og begrunn vurderingen med 15–1000 tegn.');
    $revision=filter_var($post['reviewRevision']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if($revision===false)throw new InvalidArgumentException('Ugyldig vurderingsversjon.');
    return studio_board_change(static function(array &$board)use($source,$channel,$reason,$user,$revision):array {
        $key=studio_source_identity($source['url']);$old=$board['rssIntakeReviews'][$key]??null;
        if(($old['revision']??0)!==$revision)throw new InvalidArgumentException('Vurderingen er endret i et annet vindu. Last siden på nytt.');
        $record=['revision'=>$revision+1,'sourceId'=>$source['id'],'sourceUrl'=>$source['url'],
            'sourceVersion'=>studio_input_version($source),'sourceTitle'=>$source['rssTitle']??$source['title'],
            'rssText'=>$source['rssText']??$source['summary'],'rssComplete'=>array_key_exists('rssText',$source),
            'rssFeed'=>$source['rssFeed']??null,'feedIds'=>$source['feedIds']??[],
            'publishedAt'=>$source['publishedAt'],'fetchedAt'=>$source['fetchedAt'],
            'channel'=>$channel,'reason'=>trim($reason),'actor'=>$user['name']??'Medarbeider',
            'actorId'=>$user['id']??null,'at'=>gmdate('c')];
        $record['history']=$old['history']??[];
        if($old){unset($old['history']);$record['history'][]=$old;}
        $board['rssIntakeReviews'][$key]=$record;
        return $record;
    },$path);
}
