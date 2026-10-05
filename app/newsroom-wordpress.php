<?php
declare(strict_types=1);
require_once __DIR__.'/web-publish.php';

/** Fixed WordPress desk API; Studio roles and CSRF are checked before any decision. */
function studio_newsroom_wp(string $method='GET',?array $payload=null,?array $config=null): array {
    $config??=studio_wp_config();
    if(!studio_wp_ready($config))throw new InvalidArgumentException('WordPress-tilkoblingen mangler.');
    if(!in_array($method,['GET','POST'],true))throw new InvalidArgumentException('Ugyldig handling.');
    $curl=curl_init('https://www.radiorubben.no/wp-json/rr-fotballrobot/v1/newsroom');$body='';
    curl_setopt_array($curl,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>$method==='GET'?12:90,
        CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$config['username'].':'.$config['application_password'],
        CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json'],
        CURLOPT_WRITEFUNCTION=>static function($h,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>2000000)return 0;$body.=$chunk;return strlen($chunk);}]);
    if($payload!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    if(in_array($status,[401,403],true))throw new InvalidArgumentException('WordPress avviser Studio-tilkoblingen. Tilgangen må kontrolleres.');
    if(!$ok||$status<200||$status>=300)throw new InvalidArgumentException($method==='POST'?'Handlingen kunne ikke bekreftes. Last køen på nytt før du prøver igjen.':'WordPress-køen er utilgjengelig akkurat nå.');
    $result=json_decode($body,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($result)||($result['version']??'')!=='newsroom-1'||!is_array($result['items']??null))throw new InvalidArgumentException('WordPress-køen må oppdateres før den kan vises her.');
    return $result;
}
