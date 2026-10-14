<?php
declare(strict_types=1);
require_once __DIR__.'/case-workflow.php';
require_once __DIR__.'/web-publish.php';

/** Fixed WordPress destination; GET only, no redirect and no credentials in output. */
function studio_wp_status_request(array $config,string $method,string $route):array {
    if(!studio_wp_ready($config)||$method!=='GET'||!preg_match('~^/posts(?:/[1-9][0-9]*)?\?~D',$route))throw new InvalidArgumentException('WordPress-lesetilgang mangler.');
    $body='';$curl=curl_init('https://www.radiorubben.no/wp-json/wp/v2'.$route);
    curl_setopt_array($curl,[CURLOPT_HTTPGET=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,
        CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$config['username'].':'.$config['application_password'],
        CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_WRITEFUNCTION=>static function($h,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>1000000)return 0;$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    if(!$ok||$status!==200)throw new RuntimeException('WordPress-status kunne ikke kontrolleres. Prøv statuskontrollen igjen senere; ikke publiser på nytt.');
    $result=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($result))throw new RuntimeException('Ugyldig statusrespons.');
    return $result;
}

/** Reconcile an existing attempt. Neither publication nor editorial approval is performed. */
function studio_web_status_check(string $id,int $revision,array $user,array $config,?string $path=null,?callable $request=null):array {
    if(($user['role']??'')!=='admin')throw new InvalidArgumentException('Bare administrator kan avklare WordPress-overføringen.');
    if(!preg_match('/^[a-f0-9]{16}$/D',$id)||!studio_wp_ready($config))throw new InvalidArgumentException('WordPress-lesetilgang mangler.');
    $item=studio_case_get($id,$path);$d=$item['web']['delivery']??[];
    if($item['revision']!==$revision||($item['status']??'')==='archived')throw new InvalidArgumentException('Saken er endret. Last siden på nytt.');
    if(!in_array($d['state']??'',['unknown','pending','confirmed'],true))throw new InvalidArgumentException('Saken har ingen overføring å kontrollere.');
    if(($d['state']??'')==='pending'&&(strtotime($d['at']??'')?:0)>time()-120)throw new InvalidArgumentException('Overføringen pågår. Vent litt før statuskontroll.');
    $postId=$d['id']??null;
    if($postId!==null&&(!is_int($postId)||$postId<1))throw new InvalidArgumentException('Innleggsidentiteten må avklares.');
    $fields='id,status,link,slug,title,content,excerpt,featured_media';
    $query=['context'=>'edit','_fields'=>$fields];
    if(!$postId)$query+=['slug'=>'studio-'.$id,'status'=>['publish','draft','pending','future','private'],'per_page'=>10];
    $route='/posts'.($postId?'/'.$postId:'').'?'.http_build_query($query);
    try{$raw=($request??'studio_wp_status_request')($config,'GET',$route);}
    catch(Throwable){throw new InvalidArgumentException('WordPress svarte ikke med en gyldig status. Sist lagrede overføring er beholdt. Prøv statuskontrollen igjen, ikke publiser på nytt.');}
    if(!is_array($raw))throw new RuntimeException('Ugyldig WordPress-status.');
    $posts=$postId?[$raw]:$raw;
    if(!array_is_list($posts))throw new RuntimeException('Ugyldig WordPress-liste.');
    $lookup=['checkedHash'=>studio_web_approval_hash($item),'state'=>'not_found','checkedAt'=>gmdate('c'),'actor'=>$user['name']??'Administrator'];$confirmed=false;
    if(count($posts)>1)$lookup['state']='multiple';
    elseif(count($posts)===1){
        $p=$posts[0];$url=parse_url($p['link']??'');
        if(!is_int($p['id']??null)||$p['id']<1||!in_array($p['status']??'',['publish','draft','pending','future','private'],true)
            ||($url['scheme']??'')!=='https'||!in_array($url['host']??'',['radiorubben.no','www.radiorubben.no'],true)||isset($url['user'])||isset($url['pass'])
            ||($postId?$p['id']!==$postId:($p['slug']??'')!=='studio-'.$id))throw new RuntimeException('WordPress-innleggets identitet kunne ikke bekreftes.');
        $lookup+=['id'=>$p['id'],'status'=>$p['status'],'link'=>$p['link']];
        $hash=studio_web_approval_hash($item);
        $same=($p['title']['raw']??null)===($item['web']['title']??null)
            && is_string($p['content']['raw']??null)&&trim($p['content']['raw'])===trim(studio_web_html($item))
            && is_string($p['excerpt']['raw']??null)&&trim($p['excerpt']['raw'])===trim($item['web']['intro']??'')
            && ($d['hash']??'')===$hash;
        if(isset($item['web']['imageSelection']))$same=$same&&($p['featured_media']??null)===($item['web']['imageSelection']['variants']['article_landscape']['media_id']??0);
        $wanted=$d['requestedStatus']??$d['status']??null;
        $confirmed=$same&&in_array($p['status'],['publish','draft'],true)&&$wanted===$p['status'];
        $lookup['state']=$confirmed?'matched':'different';
    }
    return studio_board_change(static function(array &$b)use($id,$revision,$d,$lookup,$confirmed){
        foreach($b['items']as&$row)if($row['id']===$id){
            if($row['revision']!==$revision||($row['web']['delivery']??[])!==$d||($row['status']??'')==='archived')throw new InvalidArgumentException('Saken eller overføringen ble endret under kontrollen. Last siden på nytt.');
            $row['web']['deliveryHistory'][]=['delivery'=>$d,'checkedAt'=>$lookup['checkedAt'],'actor'=>$lookup['actor']];
            $row['web']['delivery']['lookup']=$lookup;
            if(!$confirmed)$row['web']['delivery']['state']='unknown';
            if($confirmed)$row['web']['delivery']=array_replace($row['web']['delivery'],['state'=>'confirmed','id'=>$lookup['id'],'status'=>$lookup['status'],'link'=>$lookup['link']]);
            $row['revision']++;$row['updatedAt']=gmdate('c');
            return $lookup;
        }
        throw new InvalidArgumentException('Saken finnes ikke.');
    },$path);
}
function studio_wp_status_message(array $lookup):string {
    return match($lookup['state']??''){
        'matched'=>($lookup['status']??'')==='publish'?'Publisert på radiorubben.no. Overføringen er bekreftet; ingen ny publisering er gjort.':'WordPress-kladden er bekreftet. Den er ikke publisert.',
        'different'=>'Innlegget finnes i WordPress, men innhold eller status avviker. Åpne innlegget og avklar før nytt forsøk.',
        'multiple'=>'Flere innlegg ble funnet. Identiteten må avklares før nytt forsøk.',
        default=>'Ingen samsvarende innlegg ble funnet. Dette bekrefter ikke at overføringen feilet. Avklar før nytt forsøk.'};
}
