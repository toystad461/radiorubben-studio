<?php
declare(strict_types=1);
require_once __DIR__.'/case-workflow.php';

const STUDIO_NEWSROOM_VERSION='2026-10-04.1';

/** All badges and buttons derive from the same server-side gates as publication. */
function studio_newsroom_card(array $item): array {
    $w=$item['web']??[];$d=$w['delivery']??[];$check=$w['check']??[];$reasons=[];
    if(($d['state']??'')==='confirmed'&&($d['status']??'')==='publish'&&($d['hash']??'')===studio_web_approval_hash($item))$status='published';
    else {
        if(in_array($d['state']??'',['unknown','pending'],true))$reasons[]='WordPress-overføringen må avklares før nytt forsøk.';
        if(empty($w['title'])||empty($w['intro'])||empty($w['body']))$reasons[]='Nettsaken er ikke ferdig skrevet.';
        if(!studio_news_original_read($item,$check))$reasons[]='Originalartikkelen må hentes og leses. RSS-omtalen er ikke nok.';
        if(!studio_web_checked($item)){
            foreach($check['issues']??[] as $reason)$reasons[]=$reason;
            foreach($check['segments']??[] as $seg)if(($seg['verdict']??'')!=='supported')$reasons[]=($seg['text']??'').': '.($seg['reason']??'Mangler kildebelegg.');
            if(!$reasons)$reasons[]='Kilde- og språkkontrollen må fornyes.';
        }
        if(!studio_web_presentation_ready($item))$reasons[]='Bilde og kategorier må lagres.';
        if(!empty($item['newsroom']['error']))$reasons[]=$item['newsroom']['error'];
        $status=$reasons?'attention':'ready';
        if (studio_board_channel($item) === 'radio') { $status='attention'; $reasons[]='Valgt som radiomateriale. Behandle radiomanuset i Kontrollsenter.'; }
        if(($item['newsroom']['state']??'')==='working'){
            if((strtotime($item['newsroom']['startedAt']??'')?:0)>time()-300){$status='working';$reasons=['Robåt leser originalen og klargjør saken.'];}
            else{$status='attention';$reasons=['Klargjøringen ble avbrutt. Kontroller saken før et nytt forsøk.'];}
        }
    }
    return ['id'=>$item['id'],'type'=>'studio','revision'=>$item['revision'],'title'=>$w['title']??$item['title'],
        'intro'=>$w['intro']??'','body'=>$w['body']??'','sourceName'=>$item['sourceName'],'sourceUrl'=>$item['sourceUrl'],
        'status'=>$status,'reasons'=>array_values(array_unique($reasons)),'originalRead'=>studio_news_original_read($item,$check),
        'sourceFetchedAt'=>$check['source']['fetchedAt']??null,'checkedAt'=>$check['checkedAt']??null,
        'publishedUrl'=>$status==='published'?($d['link']??null):null,'canApprove'=>$status==='ready'];
}

/** Durable preparation job, not an approval. Human edits invalidate the reserved revision. */
function studio_newsroom_prepare(string $id,int $revision,array $user,array $config,string $comment='',?string $path=null,?callable $request=null,?callable $fetch=null,bool $recheck=false): void {
    if(!in_array($user['role']??'',['admin','producer','presenter'],true))throw new InvalidArgumentException('Ingen skrivetilgang.');
    $comment=trim($comment);if(strlen($comment)>3000||$comment!==strip_tags($comment))throw new InvalidArgumentException('Skriv et kort endringsønske uten HTML.');
    $item=studio_case_get($id,$path);if($item['revision']!==$revision)throw new InvalidArgumentException('Saken er endret. Last siden på nytt.');
    if(($item['web']['delivery']['status']??'')==='publish')throw new InvalidArgumentException('En publisert sak må redigeres manuelt.');
    studio_web_save($id,$revision,'invalidate',[],$user,$path);
    $token=bin2hex(random_bytes(12));
    studio_board_change(static function(array &$b)use($id,$token,$comment,$revision){foreach($b['items'] as &$i)if($i['id']===$id){if($i['revision']!==$revision+1)throw new InvalidArgumentException('Saken ble endret.');$i['newsroom']=['state'=>'working','token'=>$token,'startedAt'=>gmdate('c'),'request'=>$comment];return;}},$path);
    try{
        $item['revisionRequest']=$comment;
        $channel=studio_board_channel($item); $nextRevision=$revision+1;
        $source=studio_news_source($item,$fetch);
        if($channel!=='radio'){
            if($recheck)$result=['check'=>studio_news_review($item,studio_web_text($item['web']),$source,$config,$request??'producer_request')];
            else $result=studio_web_prepare($item,$config,[],$request,$fetch,$source);
            studio_web_save($id,$nextRevision,$recheck?'check':'generated',$result,$user,$path);
            $nextRevision++;
        }
        // Both uses share one original; existing radio edits are never rewritten.
        if($channel!=='web' && (trim((string)($item['script']??''))==='' || $channel==='radio')){
            $existing=trim((string)($item['script']??''))===''?null:(string)$item['script'];
            $radio=studio_news_prepare($item,$config,[],$existing,$request,$fetch,$source);
            studio_board_update($id,$nextRevision,$existing===null?'generated':'source_checked',
                ['script'=>$radio['script'],'sourceCheck'=>$radio['check'],'generation'=>['model'=>$config['openai_model'],
                'sourceSha256'=>$source['sha256']]],$user,$path);
        }
        studio_board_change(static function(array &$b)use($id,$token){foreach($b['items'] as &$i)if($i['id']===$id&&($i['newsroom']['token']??'')===$token){$i['newsroom']['state']='prepared';$i['newsroom']['finishedAt']=gmdate('c');return;}},$path);
    }catch(Throwable $e){
        $safe=$e instanceof StudioNewsPreparationException||$e instanceof InvalidArgumentException?$e->getMessage():'Klargjøringen ble avbrutt. Utkast og historikk er bevart.';
        studio_board_change(static function(array &$b)use($id,$token,$safe){foreach($b['items'] as &$i)if($i['id']===$id&&($i['newsroom']['token']??'')===$token){$i['newsroom']['state']='failed';$i['newsroom']['error']=$safe;return;}},$path);
        throw new InvalidArgumentException($safe);
    }
}

function studio_newsroom_settings(array $board): array {
    return array_replace(['enabled'=>false,'activatedAt'=>null,'dailyLimit'=>8],$board['newsroomSettings']??[]);
}

/** One new story per invocation; feed identity includes archived/rejected stories. */
function studio_newsroom_tick(array $feeds,array $config,?string $path=null,?callable $request=null,?callable $fetch=null): array {
    $path??=studio_board_path();$lock=fopen($path.'.newsroom.lock','c');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))return ['state'=>'busy'];
    chmod($path.'.newsroom.lock',0600);
    try{
        $board=studio_board_read($path);$settings=studio_newsroom_settings($board);
        if(!$settings['enabled'])return ['state'=>'paused'];
        if($path===null){
            require_once __DIR__.'/weather-script.php';
            try{studio_weather_script_tick();}catch(Throwable $e){error_log('Studio weather preparation unavailable.');}
        }
        $user=['role'=>'producer','name'=>'Robåt – automatisk klargjøring'];$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Oslo')))->format('Y-m-d');
        $count=0;foreach($board['items'] as $i)if(($i['newsroom']['automaticDay']??'')===$today)$count++;
        $active=studio_board_active($board);
        // Recheck unchanged pending text; never regenerate an editor's text automatically.
        foreach($active as $i)if(!empty($i['web']['body'])&&($i['web']['check']['status']??'')==='passed'&&!studio_web_checked($i)&&!in_array($i['web']['delivery']['state']??'',['unknown','pending'],true)&&($i['web']['delivery']['status']??'')!=='publish'&&($i['newsroom']['state']??'')!=='failed'){
            studio_newsroom_prepare($i['id'],$i['revision'],$user,$config,'',$path,$request,$fetch,true);return ['state'=>'rechecked'];
        }
        if($count>=min(8,(int)$settings['dailyLimit'])||count($active)>=30)return ['state'=>'limit'];
        $candidates=[];
        foreach($feeds as $feed)if(($feed['status']??'')==='updated')foreach($feed['items'] as $source){
            $at=strtotime($source['publishedAt']??'')?:0;
            if($at<time()-86400||$at>time()+300||!studio_news_allowed_url($source['url']??'')||studio_board_has_source($board['items'],$source))continue;
            $identity=studio_source_identity($source['url']);if($identity!==null)$candidates[$identity]=$source;
        }
        usort($candidates,static fn($a,$b)=>(strtotime($b['publishedAt'])<=>strtotime($a['publishedAt'])));
        if(!$candidates)return ['state'=>'idle'];
        $source=$candidates[0];studio_board_add_source($source,$user,$path);
        $i=studio_case_source_item(studio_board_active(studio_board_read($path)),$source);
        studio_newsroom_prepare($i['id'],$i['revision'],$user,$config,'',$path,$request,$fetch);
        return ['state'=>'prepared'];
    }finally{
        if(isset($i)&&isset($source))studio_board_change(static function(array &$b)use($i,$today){foreach($b['items'] as &$row)if($row['id']===$i['id'])$row['newsroom']['automaticDay']=$today;},$path);
        flock($lock,LOCK_UN);fclose($lock);
    }
}
