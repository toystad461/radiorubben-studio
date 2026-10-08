<?php
declare(strict_types=1);
require_once __DIR__.'/case-workflow.php';
require_once __DIR__.'/editorial-memory.php';

const STUDIO_NEWSROOM_VERSION='2026-10-04.1';

/** Intake reuses existing stories without changing their channel, text or approvals. */
function studio_newsroom_intake(array $source, string $channel, array $user, ?string $path=null): array {
    if(!in_array($user['role']??'', ['admin','producer','presenter'],true))throw new InvalidArgumentException('Ingen skrivetilgang.');
    if(!in_array($channel,['radio','web','both'],true))throw new InvalidArgumentException('Velg Radio, Nett eller Begge.');
    $board=studio_board_read($path);
    if(studio_board_has_source($board['items'],$source)){
        $item=studio_case_source_item($board['items'],$source);
        if($item['status']==='archived')throw new InvalidArgumentException('Denne saken er allerede forkastet eller arkivert.');
        return $item;
    }
    studio_board_add_source($source,$user,$path,$channel);
    return studio_case_source_item(studio_board_active(studio_board_read($path)),$source);
}

/** Read-only channel guidance. This never grants editorial or audio approval. */
function studio_newsroom_flow(array $item): array {
    $channel=studio_board_channel($item);$radio='Ikke valgt';$web='Ikke valgt';
    $radioChecked=trim((string)($item['script']??''))!==''&&studio_news_check_current($item)&&studio_news_radio_credit($item);
    $radioApproved=$radioChecked&&($item['status']??'')==='ready'&&!empty($item['verified'])&&!empty($item['approvedBy']);
    if($channel!=='web')$radio=empty($item['script'])?'Manus mangler':(!$radioChecked?'Manus trenger kontroll':($radioApproved?'Manus godkjent':'Manus til godkjenning'));
    if($channel!=='radio')$web=empty($item['web']['body'])?'Artikkel mangler':studio_web_status_label($item);
    $url='/case.php?item='.rawurlencode($item['id']);
    if($channel!=='web'&&!$radioApproved){$next=['label'=>empty($item['script'])?'Lag radiomanus':($radioChecked?'Godkjenn radiomanus':'Kontroller radiomanus'),'url'=>$url.'#radio-material'];}
    elseif($channel!=='radio'){$next=['label'=>'Åpne nettartikkel','url'=>$url.'#web-material'];}
    else{$next=['label'=>'Velg sak til nyhetssending','url'=>'/sending.php?item='.rawurlencode($item['id'])];}
    return ['channel'=>$channel,'radio'=>$radio,'web'=>$web,'radioApproved'=>$radioApproved,'radioChecked'=>$radioChecked,'next'=>$next];
}

/** All badges and buttons derive from the same server-side gates as publication. */
function studio_newsroom_card(array $item): array {
    $w=$item['web']??[];$d=$w['delivery']??[];$check=$w['check']??[];$reasons=[];
    $flow=studio_newsroom_flow($item);
    if($flow['channel']==='radio')return [
        'id'=>$item['id'],'type'=>'studio','revision'=>$item['revision'],'title'=>$item['title'],
        'intro'=>'','body'=>$item['script']??'','sourceName'=>$item['sourceName'],'sourceUrl'=>$item['sourceUrl'],
        'status'=>$flow['radioChecked']?'working':'attention','reasons'=>[$flow['radio']],
        'originalRead'=>studio_news_original_read($item,$item['sourceCheck']??[]),
        'sourceFetchedAt'=>$item['sourceCheck']['source']['fetchedAt']??null,
        'checkedAt'=>$item['sourceCheck']['checkedAt']??null,'publishedUrl'=>null,'canApprove'=>false,'flow'=>$flow];

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
        if(($item['newsroom']['state']??'')==='working'){
            if((strtotime($item['newsroom']['startedAt']??'')?:0)>time()-300){$status='working';$reasons=['Robåt leser originalen og klargjør saken.'];}
            else{$status='attention';$reasons=['Klargjøringen ble avbrutt. Kontroller saken før et nytt forsøk.'];}
        }
    }
    return ['id'=>$item['id'],'type'=>'studio','revision'=>$item['revision'],'title'=>$w['title']??$item['title'],
        'intro'=>$w['intro']??'','body'=>$w['body']??'','sourceName'=>$item['sourceName'],'sourceUrl'=>$item['sourceUrl'],
        'status'=>$status,'reasons'=>array_values(array_unique($reasons)),'originalRead'=>studio_news_original_read($item,$check),
        'sourceFetchedAt'=>$check['source']['fetchedAt']??null,'checkedAt'=>$check['checkedAt']??null,
        'publishedUrl'=>$status==='published'?($d['link']??null):null,'canApprove'=>$status==='ready','flow'=>$flow];
}

/** Durable preparation job, not an approval. Human edits invalidate the reserved revision. */
function studio_newsroom_prepare(string $id,int $revision,array $user,array $config,string $comment='',?string $path=null,?callable $request=null,?callable $fetch=null,bool $recheck=false): void {
    if(!in_array($user['role']??'',['admin','producer','presenter'],true))throw new InvalidArgumentException('Ingen skrivetilgang.');
    $comment=trim($comment);if(strlen($comment)>3000||$comment!==strip_tags($comment))throw new InvalidArgumentException('Skriv et kort endringsønske uten HTML.');
    $item=studio_case_get($id,$path);if($item['revision']!==$revision)throw new InvalidArgumentException('Saken er endret. Last siden på nytt.');
    if(($item['web']['delivery']['status']??'')==='publish')throw new InvalidArgumentException('En publisert sak må redigeres manuelt.');
    studio_web_save($id,$revision,'invalidate',[],$user,$path);
    $token=bin2hex(random_bytes(12));
    $editorial=studio_memory_context(studio_board_read($path),(string)($item['program']??''));
    studio_board_change(static function(array &$b)use($id,$token,$comment,$revision,$editorial){foreach($b['items'] as &$i)if($i['id']===$id){if($i['revision']!==$revision+1)throw new InvalidArgumentException('Saken ble endret.');$i['newsroom']=['state'=>'working','token'=>$token,'startedAt'=>gmdate('c'),'request'=>$comment,'editorial'=>$editorial];return;}},$path);
    try{
        $item['revisionRequest']=$comment;
        $channel=studio_board_channel($item); $nextRevision=$revision+1;
        $source=studio_news_source($item,$fetch);
        if($channel!=='radio'){
            if($recheck)$result=['check'=>studio_news_review($item,studio_web_text($item['web']),$source,$config,$request??'producer_request')];
            else $result=studio_web_prepare($item,$config,$editorial,$request,$fetch,$source);
            studio_web_save($id,$nextRevision,$recheck?'check':'generated',$result,$user,$path);
            $nextRevision++;
        }
        // Both uses share one original; existing radio edits are never rewritten.
        if($channel!=='web' && (trim((string)($item['script']??''))==='' || $channel==='radio')){
            $existing=trim((string)($item['script']??''))===''?null:(string)$item['script'];
            $radio=studio_news_prepare($item,$config,$editorial,$existing,$request,$fetch,$source);
            studio_board_update($id,$nextRevision,$existing===null?'generated':'source_checked',
                ['script'=>$radio['script'],'sourceCheck'=>$radio['check'],'generation'=>['model'=>$config['openai_model'],
                'sourceSha256'=>$source['sha256'],'editorial'=>$editorial,'journalist'=>$radio['generation']??null]],$user,$path);
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
    $productionPath=$path===null;
    $path??=studio_board_path();$lock=fopen($path.'.newsroom.lock','c');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))return ['state'=>'busy'];
    chmod($path.'.newsroom.lock',0600);
    try{
        $board=studio_board_read($path);$settings=studio_newsroom_settings($board);
        if(!$settings['enabled'])return ['state'=>'paused'];
        if($productionPath){
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
