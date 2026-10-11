<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user=current_user();if(!$user)redirect('/login.php');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
require_once dirname(__DIR__).'/app/news-input.php';
$can=studio_can($user,'produce');$error=null;$notice=null;
if($_SERVER['REQUEST_METHOD']==='POST'&&(!$can||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))){http_response_code(403);exit('Ingen tilgang. Last siden på nytt.');}
$feeds=newsdesk_all(dirname(__DIR__).'/config');
$filter=is_string($_GET['feed']??null)?$_GET['feed']:'all';
if($filter!=='all'&&!isset(newsdesk_sources()[$filter]))$filter='all';
$sources=studio_input_sources($feeds,$filter);
$selected=is_string($_POST['source']??$_GET['source']??null)?($_POST['source']??$_GET['source']):'';
$source=$sources[$selected]??null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    try {
        if(!$source)throw new InvalidArgumentException('Kilden er ikke lenger i denne innboksen.');
        studio_input_save($source,$_POST,$user,$_SESSION['csrf']);
        $_SESSION['rss_input_notice']='Vurderingen er lagret. Innhold og lyd er ikke godkjent eller publisert.';
        redirect('/news-input.php?'.http_build_query(['feed'=>$filter,'source'=>$source['id']]).'#selected-news');
    }catch(InvalidArgumentException $e){http_response_code($can?409:403);$error=$e->getMessage();}
    catch(Throwable $e){http_response_code(503);$error='Vurderingen kunne ikke lagres. Last siden på nytt.';error_log('Studio RSS intake save failed.');}
}
if(!$source)$source=reset($sources)?:null;
if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_SESSION['rss_input_notice'])){$notice=$_SESSION['rss_input_notice'];unset($_SESSION['rss_input_notice']);}
$board=studio_board_read(studio_input_path());$review=$source?studio_input_review($board,$source):null;
$old=$source?($board['rssIntakeReviews'][studio_source_identity($source['url'])]??null):null;
function input_url(string $feed,string $source=''):string{return '/news-input.php?'.http_build_query(['feed'=>$feed,'source'=>$source]);}
function input_time(?string $at):string{try{return $at?(new DateTimeImmutable($at))->setTimezone(new DateTimeZone('Europe/Oslo'))->format('d.m. H:i'):'Ukjent';}catch(Throwable $e){return 'Ukjent';}}
$extraStylesheet='/assets/news-input.css?v=20261011';require dirname(__DIR__).'/app/views/head.php';
?>
<div class="shell"><?php $activePage='newsdesk';require dirname(__DIR__).'/app/views/sidebar.php';?><div class="workspace">
<header class="topbar"><span>Radio Rubben / <strong>Nyhetsinngang</strong></span><a href="/newsdesk.php">Til behandling</a></header>
<?php require dirname(__DIR__).'/app/views/news-input.php';?>
</div></div></body></html>
