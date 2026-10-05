<?php
declare(strict_types=1);
require dirname(__DIR__).'/studio-private/app/bootstrap.php';
$user=current_user();if(!$user)redirect('/login.php');
if(($user['role']??'')!=='admin'){http_response_code(403);exit('Ingen tilgang.');}
require dirname(__DIR__).'/studio-private/app/web-publish.php';
header('Cache-Control: no-store');
$results=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit;}
 $c=studio_wp_config();
 foreach(['/users/me?context=edit','/posts?slug=studio-7c172f6031fd87c2&status=any&context=edit'] as $route){
  $ch=curl_init('https://www.radiorubben.no/wp-json/wp/v2'.$route);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>($c['username']??'').':'.($c['application_password']??''),CURLOPT_HTTPHEADER=>['Accept: application/json']]);
  $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$errno=curl_errno($ch);$redirect=(string)curl_getinfo($ch,CURLINFO_REDIRECT_URL);curl_close($ch);
  $data=is_string($body)?json_decode($body,true):null;
  $row=['http'=>$code,'transport'=>$errno,'redirectHost'=>parse_url($redirect,PHP_URL_HOST)?:null,'restCode'=>is_array($data)&&is_string($data['code']??null)?substr($data['code'],0,80):null];
  if(str_starts_with($route,'/users')&&$code===200)$row['authenticated']=isset($data['id']);
  if(str_starts_with($route,'/posts')&&$code===200&&is_array($data))$row['posts']=array_map(static fn($p)=>['id'=>$p['id']??null,'status'=>$p['status']??null,'slug'=>$p['slug']??null],$data);
  $results[]=$row;
 }
}
?><!doctype html><html lang="nb"><meta charset="utf-8"><title>WordPress-kontroll</title><h1>WordPress-kontroll</h1><p>Leser tilkoblingsstatus og ser etter testkladden. Oppretter eller publiserer ingen innlegg.</p><form method="post"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><button>Kontroller tilkobling og testkladd</button></form><pre><?=escape(json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></pre><a href="/sending.php">Til sendelisten</a></html>
