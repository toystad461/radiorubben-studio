<?php
declare(strict_types=1);
// Test overlay: deliberately unusable outside this VPS release tree.
function rr_e2e_config(): array {
    if(!str_starts_with((string)realpath(dirname(__DIR__)), '/srv/radiorubben-test/releases/')) throw new RuntimeException('Testmiljø kreves.');
    $c=require dirname(__DIR__).'/config/wordpress.php';
    if(($c['rr_test']??'')!=='RR-RED-E2E-001'||($c['target']??'')!=='https://5.249.252.78/wordpress/')throw new RuntimeException('Ugyldig testmål.');
    return $c;
}
function rr_e2e_url():string {return 'https://5.249.252.78/e2e-source.php';}
function rr_e2e_item(array $item):bool {
    return ($item['id']??'')===(rr_e2e_config()['case_id']??null)&&($item['sourceUrl']??'')===rr_e2e_url();
}
function rr_e2e_lines():array {return ['TEST – syntetisk redaksjonell prøve','Dette er oppdiktede testdata for Radio Rubbens isolerte testmiljø.','Testprotokollen inneholder tre kontrollpunkter og beskriver ingen virkelig hendelse.'];}
function rr_e2e_source(array $item):array {
    if(!rr_e2e_item($item))throw new RuntimeException('Ugyldig testsak.');
    $text=studio_news_extract('<article>'.implode('',array_map(fn($s)=>'<p>'.htmlspecialchars($s).'</p>',rr_e2e_lines())).'</article>');
    return ['url'=>rr_e2e_url(),'text'=>$text,'sha256'=>hash('sha256',$text),'fetchedAt'=>gmdate('c'),'kind'=>'synthetic_test_fixture'];
}
function rr_e2e_review(array $config,array $payload):string {
    // Deterministic fixture transport, not a model or a general approval bypass.
    rr_e2e_config();$input=json_decode($payload['input'],true,64,JSON_THROW_ON_ERROR);
    if(($input['source']['url']??'')!==rr_e2e_url()||($input['source']['text']??'')!==implode("\n",rr_e2e_lines()))throw new RuntimeException('Ukjent testgrunnlag.');
    $rows=[];foreach($input['segments'] as $seg){$i=array_search($seg['text'],rr_e2e_lines(),true);$rows[]=['index'=>$seg['index'],'verdict'=>$i===false?'unsupported':'supported','evidence_indexes'=>$i===false?[]:[$i],'reason'=>'Deterministisk sammenligning med syntetisk testgrunnlag.'];}
    return json_encode(['segments'=>$rows,'issues'=>[]],JSON_THROW_ON_ERROR);
}
function rr_e2e_http(array $c,string $method,string $route,?array $payload=null):array {
    $expected=rr_e2e_config();
    if($c!==$expected||!in_array($method,['GET','POST'],true)||!preg_match('~^/posts(?:/[1-9][0-9]*)?(?:\?[^#]*)?$~D',$route))throw new RuntimeException('Testkoblingen avviser målet.');
    if($method==='POST'&&(!str_starts_with($payload['title']??'','TEST – ')||!str_contains($payload['content']??'',rr_e2e_url())||isset($payload['rr_news_image'])||isset($payload['categories'])))throw new RuntimeException('Bare syntetisk testsak er tillatt.');
    $parts=explode('?',$route,2);$url='https://5.249.252.78/wordpress/index.php?rest_route=/wp/v2'.$parts[0].(isset($parts[1])?'&'.$parts[1]:'');
    $h=curl_init($url);curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_PROXY=>'',CURLOPT_UNIX_SOCKET_PATH=>'/srv/radiorubben-test/studio-data/rr-red-e2e/proxy.sock',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$c['username'].':'.$c['application_password'],CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json']]);
    if($payload!==null)curl_setopt($h,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR));
    $body=curl_exec($h);$status=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);
    if(!is_string($body)||strlen($body)>1000000||$status<200||$status>=300)throw new RuntimeException('Test-WordPress svarte ikke med bekreftet lagring (HTTP '.$status.').');
    $r=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    foreach(array_is_list($r)?$r:[$r] as $post){$u=parse_url($post['link']??'');if(($u['scheme']??'')!=='https'||($u['host']??'')!=='5.249.252.78'||!str_starts_with($u['path']??'','/wordpress/')||!is_int($post['id']??null))throw new RuntimeException('Uventet testinnlegg.');}
    return $r;
}
