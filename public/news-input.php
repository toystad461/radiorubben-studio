<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user=current_user();if(!$user)redirect('/login.php');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
require_once dirname(__DIR__).'/app/news-input.php';
$can=studio_can($user,'produce');$error=null;$notice=null;
$demo=($_GET['demo']??null)==='updates';
if($demo&&$_SERVER['REQUEST_METHOD']==='POST'){http_response_code(403);exit('Eksempelet er skrivebeskyttet.');}
if($_SERVER['REQUEST_METHOD']==='POST'&&(!$can||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf']))){http_response_code(403);exit('Ingen tilgang. Last siden på nytt.');}
$example=$demo?studio_input_demo():null;
$feeds=$demo?$example['feeds']:newsdesk_all(dirname(__DIR__).'/config');
$board=$demo?$example['board']:studio_board_read(studio_input_path());
$editorialError=null;
try{$editorial=$demo?$example['editorial']:studio_board_read();}
catch(Throwable $e){$editorial=['items'=>[]];$editorialError='Koblingen til eksisterende Studio-saker kunne ikke kontrolleres.';}
$filter=is_string($_GET['feed']??null)?$_GET['feed']:'all';
if($filter!=='all'&&!isset(newsdesk_sources()[$filter]))$filter='all';
$sources=studio_input_watch_sources(studio_input_sources($feeds,$filter),$board,$feeds,$filter);
$view=is_string($_GET['view']??null)?$_GET['view']:'active';
if(!in_array($view,['active','discarded','all','updates'],true))$view='active';
$selected=is_string($_POST['source']??$_GET['source']??null)?($_POST['source']??$_GET['source']):'';
$source=$sources[$selected]??null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    try {
        if(!$source)throw new InvalidArgumentException('Kilden er ikke lenger i denne innboksen.');
        $saved=studio_input_save($source,$_POST,$user,$_SESSION['csrf']);
        $_SESSION['rss_input_notice']=$saved['channel']==='skip'?'Saken er forkastet og finnes under Forkastet. Kilde og historikk er beholdt.':'Vurderingen er lagret. Innhold og lyd er ikke godkjent eller publisert.';
        if(($saved['updateDecision']??'')==='draft')$_SESSION['rss_input_notice']='Oppdateringsutkastet er lagret under Oppdateringer. Publisert tekst, manus og lyd er uendret.';
        if(($saved['updateDecision']??'')==='keep')$_SESSION['rss_input_notice']='Valget om å beholde eksisterende innhold er logget. Innhold og lyd er uendret.';
        if($saved['channel']==='skip'){
            $visible=studio_input_visible_sources($sources,studio_board_read(studio_input_path()));
            $ids=array_keys($sources);$position=array_search($source['id'],$ids,true);$next='';
            foreach(array_merge(array_slice($ids,$position+1),array_slice($ids,0,$position)) as $id)if(isset($visible[$id])){$next=$id;break;}
            redirect('/news-input.php?'.http_build_query(['feed'=>$filter,'source'=>$next,'view'=>'active']).'#selected-news');
        }
        redirect('/news-input.php?'.http_build_query(['feed'=>$filter,'source'=>$source['id'],'view'=>$view]).'#selected-news');
    }catch(InvalidArgumentException $e){http_response_code($can?409:403);$error=$e->getMessage();}
    catch(Throwable $e){http_response_code(503);$error='Vurderingen kunne ikke lagres. Last siden på nytt.';error_log('Studio RSS intake save failed.');}
}
if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_SESSION['rss_input_notice'])){$notice=$_SESSION['rss_input_notice'];unset($_SESSION['rss_input_notice']);}
$board=$demo?$example['board']:studio_board_read(studio_input_path());$sources=studio_input_visible_sources($sources,$board,$view);
if(!$error&&$source&&!isset($sources[$source['id']]))$source=null;
if(!$source)$source=reset($sources)?:null;
$review=$source?studio_input_review($board,$source):null;
$old=$source?($board['rssIntakeReviews'][studio_source_identity($source['url'])]??null):null;
$update=$source?studio_input_update($board,$source):null;
$suggestion=$source?studio_input_suggestion($source):null;
$related=$source?studio_input_related($source,$editorial):[];
function input_url(string $feed,string $source=''):string{global $view,$demo;$query=['feed'=>$feed,'source'=>$source,'view'=>$view];if($demo)$query['demo']='updates';return '/news-input.php?'.http_build_query($query);}
function input_time(?string $at):string{try{return $at?(new DateTimeImmutable($at))->setTimezone(new DateTimeZone('Europe/Oslo'))->format('d.m. H:i'):'Ukjent';}catch(Throwable $e){return 'Ukjent';}}
$extraStylesheet='/assets/news-input.css?v=20261011-updates1';require dirname(__DIR__).'/app/views/head.php';
?>
<div class="shell"><?php $activePage='newsdesk';require dirname(__DIR__).'/app/views/sidebar.php';?><div class="workspace">
<header class="topbar"><span>Radio Rubben / <strong>Nyhetsinngang</strong></span><a href="/newsdesk.php">Til behandling</a></header>
<?php require dirname(__DIR__).'/app/views/news-input.php';?>
</div></div></body></html>
