<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/integrations/NewsDesk.php';
function expiry_expect(bool $ok):void{if(!$ok)throw new RuntimeException('Inbox expiry assertion failed.');}
$now=strtotime('2026-10-07T12:00:00Z');
$items=[['id'=>'old','publishedAt'=>gmdate('c',$now-2592000),'fetchedAt'=>gmdate('c',$now)],['id'=>'fresh','publishedAt'=>gmdate('c',$now-2591999)],['id'=>'invalid','publishedAt'=>'bad'],['id'=>'future','publishedAt'=>gmdate('c',$now+301)]];
expiry_expect(array_column(newsdesk_recent_sources($items,$now),'id')===['fresh']);
$dir=sys_get_temp_dir().'/inbox-expiry-'.bin2hex(random_bytes(5));mkdir($dir,0700);
$path=$dir.'/newsdesk-bomlo.json';
try{
    file_put_contents($path,json_encode(['checkedAt'=>$now,'fetchedAt'=>gmdate('c',$now),'items'=>$items,'failed'=>false]));
    $fetch=static function(){throw new RuntimeException('Warm cache must not fetch');};
    $feed=newsdesk_feed('bomlo',newsdesk_sources()['bomlo'],$dir,$fetch,$now);
    expiry_expect($feed['status']==='updated'&&array_column($feed['items'],'id')===['fresh']);
    expiry_expect(array_column(json_decode(file_get_contents($path),true)['items'],'id')===['fresh']);
    $feed=newsdesk_feed('bomlo',newsdesk_sources()['bomlo'],$dir,fn()=>null,$now+1800);
    expiry_expect($feed['items']===[]);
    expiry_expect(json_decode(file_get_contents($path),true)['items']===[]);
}finally{if(is_file($path))unlink($path);rmdir($dir);}
echo "Inbox expiry tests passed.\n";
