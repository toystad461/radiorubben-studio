<?php
declare(strict_types=1);
require_once __DIR__.'/news-publication.php';

/** Credentials stay outside the public tree and are never sent to the browser. */
function studio_wp_config(): array {
    $file = dirname(__DIR__) . '/config/wordpress.php';
    $c = is_file($file) ? require $file : [];
    if (!is_array($c)) return [];
    return $c;
}
function studio_wp_ready(array $c): bool {
    return !empty($c['username']) && !empty($c['application_password']);
}
function studio_wp_request(array $c, string $method, string $route, ?array $payload = null): array {
    if (!studio_wp_ready($c)) throw new InvalidArgumentException('WordPress-koblingen må konfigureres på serveren.');
    if (!preg_match('~^/posts(?:/[1-9][0-9]*)?$~D', $route) || $method !== 'POST') throw new InvalidArgumentException('Ugyldig WordPress-operasjon.');
    $curl = curl_init('https://www.radiorubben.no/wp-json/wp/v2' . $route);
    $body = '';
    curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_TIMEOUT=>35, CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,
        CURLOPT_USERPWD=>$c['username'] . ':' . $c['application_password'],
        CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS=>json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        CURLOPT_WRITEFUNCTION=>static function($h, string $chunk) use (&$body): int {
            if (strlen($body)+strlen($chunk)>1000000) return 0;
            $body.=$chunk; return strlen($chunk);
        }]);
    $ok=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    if (!$ok || $status < 200 || $status >= 300) throw new RuntimeException('WordPress svarte ikke med bekreftet lagring.');
    $r=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    if (!is_array($r) || !is_int($r['id']??null) || $r['id']<1 || !in_array($r['status']??'', ['draft','publish'],true)) throw new RuntimeException('Uventet WordPress-svar.');
    $url=parse_url((string)($r['link']??''));
    if (($url['scheme']??'')!=='https' || !in_array($url['host']??'', ['radiorubben.no','www.radiorubben.no'],true)) throw new RuntimeException('Uventet WordPress-lenke.');
    return ['id'=>$r['id'],'status'=>$r['status'],'link'=>$r['link']];
}
function studio_web_text(array $web): string {
    return trim(($web['title']??'') . "\n" . ($web['intro']??'') . "\n" . ($web['body']??''));
}
function studio_web_checked(array $item): bool {
    $w=$item['web']??[];
    return studio_news_original_read($item,$w['check']??[]) && studio_news_check_current(array_replace($item,['script'=>studio_web_text($w),'sourceCheck'=>$w['check']??[]]));
}
function studio_web_html(array $item): string {
    $esc=static fn($s)=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $w=$item['web']; $html='';
    foreach (studio_news_reading_paragraphs($w['body']) as $p) $html.='<p>'.$esc($p).'</p>';
    $nrk=parse_url($item['sourceUrl']??'',PHP_URL_HOST)==='www.nrk.no';
    $label=$nrk?'Les hele saken hos NRK':'Les mer hos '.$item['sourceName'];
    $disclosure=!empty($w['generation']['aiAssisted']) ? '<p class="rr-transparency">Denne teksten er utarbeidet med KI-støtte. Radio Rubben har redaksjonelt ansvar.</p>' : '';
    return $disclosure.'<p>Basert på opplysninger fra '.($nrk?'NRK':$esc($item['sourceName'])).'. <a href="'.$esc($item['sourceUrl']).'" rel="noopener">'.$esc($label).'</a></p><p><strong>'.$esc($w['intro']).'</strong></p>'.$html;
}
/** Reserve durably before network I/O. An unknown outcome is never retried automatically. */
function studio_web_publish(string $id,int $revision,string $status,array $user,array $config,?callable $request=null,?string $path=null): array {
    if (($user['role']??'')!=='admin') throw new InvalidArgumentException('Bare administrator kan sende til WordPress.');
    if (!in_array($status,['draft','publish'],true) || !studio_wp_ready($config)) throw new InvalidArgumentException('WordPress-koblingen er ikke konfigurert.');
    $reserved=studio_board_change(static function(array &$board) use($id,$revision,$status,$user): array {
        foreach($board['items'] as &$item) if($item['id']===$id && $item['status']!=='archived') {
            $w=$item['web']??[];
            if (studio_board_channel($item) === 'radio') throw new InvalidArgumentException('Saken er valgt som radiomateriale. Velg nett før overføring.');
            if ($item['revision']!==$revision) throw new InvalidArgumentException('Saken er endret. Last siden på nytt.');
            if (in_array($w['delivery']['state']??'', ['pending','unknown'],true)) throw new InvalidArgumentException('Forrige overføring er uavklart. Kontroller WordPress før ny overføring.');
            if (empty($w['title']) || empty($w['intro']) || empty($w['body'])) throw new InvalidArgumentException('Lagre en komplett nettsak først.');
            if (!studio_web_presentation_ready($item)) throw new InvalidArgumentException('Lagre nettsaken med nyhetsbilde og kategori før overføring.');
            studio_news_publication_metadata($item);
            if ($status==='publish' && (!studio_web_checked($item) || ($w['approvedHash']??'')!==studio_web_approval_hash($item))) throw new InvalidArgumentException('Nettsaken må kildekontrolleres og godkjennes før publisering.');
            $hash=studio_web_approval_hash($item);
            if (($w['delivery']['hash']??'')===$hash && ($w['delivery']['status']??'')===$status && ($w['delivery']['state']??'')==='confirmed') return ['done'=>$w['delivery']];
            // Do not unpublish an already published post through the draft action.
            if (($w['delivery']['status']??'')==='publish' && $status==='draft') throw new InvalidArgumentException('Saken er publisert. Bruk publisering for å oppdatere den.');
            $token=bin2hex(random_bytes(16));
            $prior=$w['delivery']??[];
            $item['web']['delivery']=array_replace($prior,['state'=>'pending','token'=>$token,'hash'=>$hash,'requestedStatus'=>$status,'at'=>gmdate('c'),'actor'=>$user['name']??'Administrator']);
            $item['revision']++; $item['updatedAt']=gmdate('c');
            return ['item'=>$item,'token'=>$token,'postId'=>(int)($prior['id']??0)];
        }
        throw new InvalidArgumentException('Saken finnes ikke.');
    },$path);
    if(isset($reserved['done'])) return $reserved['done'];
    $request??='studio_wp_request'; $item=$reserved['item']; $postId=$reserved['postId'];
    $payload=['title'=>$item['web']['title'],'excerpt'=>$item['web']['intro'],'content'=>studio_web_html($item),'status'=>$status];
    if(!$postId) $payload['slug']='studio-'.$id;
    // Assign the news image/categories only on creation. Keep WordPress editor choices on updates.
    $newsMetadata=studio_news_publication_metadata($item);
    if(!$postId && $newsMetadata) $payload=array_merge($payload,$newsMetadata);
    elseif(!$newsMetadata && !studio_web_is_news($item) && !empty($config['category_id'])) $payload['categories']=[(int)$config['category_id']];
    try {
        $result=$request($config,'POST','/posts'.($postId?'/'.$postId:''),$payload);
        if (!is_int($result['id']??null) || $result['id']<1 || ($result['status']??'')!==$status || ($postId && $result['id']!==$postId)) throw new RuntimeException('WordPress-lagringen er ikke bekreftet.');
    } catch(Throwable $e) {
        studio_web_finish_delivery($id,$reserved['token'],['state'=>'unknown'],$path);
        throw new InvalidArgumentException('Overføringen er uavklart. Sjekk WordPress før du prøver igjen; Studio hindrer dobbeltpublisering.');
    }
    studio_web_finish_delivery($id,$reserved['token'],array_merge($result,['state'=>'confirmed']),$path);
    return $result;
}
function studio_web_finish_delivery(string $id,string $token,array $result,?string $path): void {
    studio_board_change(static function(array &$board) use($id,$token,$result): void {
        foreach($board['items'] as &$item) if($item['id']===$id && ($item['web']['delivery']['token']??'')===$token) {
            $item['web']['delivery']=array_replace($item['web']['delivery'],$result); $item['revision']++; return;
        }
        throw new RuntimeException('Overføringsstatus kunne ikke lagres.');
    },$path);
}
