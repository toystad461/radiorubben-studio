<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!current_user()){http_response_code(401);exit;}
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
require_once dirname(__DIR__).'/app/case-workflow.php';
try {
    $item=studio_case_get((string)($_GET['item']??''));
    if(($item['web']['approvedHash']??'')!==studio_web_approval_hash($item)||studio_images_issue($item))throw new RuntimeException();
    $v=$item['web']['imageSelection']['variants'][$_GET['placement']??'']??null;
    if(!$v||!rrni_url_valid($v['url']))throw new RuntimeException();
    session_write_close();
    $body='';$curl=curl_init($v['url']);
    curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_WRITEFUNCTION=>static function($h,$part)use(&$body){if(strlen($body)+strlen($part)>4000000)return 0;$body.=$part;return strlen($part);}]);
    $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    if(!$ok||$status!==200)throw new RuntimeException();
    rrni_check_bytes($body,rrni_assets()[$v['asset_id']]);
    header('Content-Type: image/png');header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="'.$v['asset_id'].'.png"');header('X-Content-Type-Options: nosniff');echo $body;
}catch(Throwable){http_response_code(409);echo 'Bildet er ikke tilgjengelig eller trenger ny godkjenning.';}
