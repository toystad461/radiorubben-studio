<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user=current_user();if(!$user)redirect('/login.php');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
require_once dirname(__DIR__).'/app/newsroom.php';
require_once dirname(__DIR__).'/app/newsroom-view.php';
require_once dirname(__DIR__).'/app/newsroom-wordpress.php';
require_once dirname(__DIR__).'/app/integrations/NewsDesk.php';
require_once dirname(__DIR__).'/app/producer.php';
$can=studio_can($user,'produce');$admin=($user['role']??'')==='admin';$error=null;$notice=null;
$selected=(string)($_POST['item']??$_GET['item']??'');
$advance=false;$wpAfterDecision=null;
$fragment=($_SERVER['REQUEST_METHOD']==='POST'?($_POST['response']??'')==='json':($_GET['view']??'')==='fragment');
if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_SESSION['newsroom_notice'])){$notice=$_SESSION['newsroom_notice'];unset($_SESSION['newsroom_notice']);}
$feeds=newsdesk_all(dirname(__DIR__).'/config');$sections=newsdesk_sections($feeds);
$sources=[];foreach($sections as $section)foreach($section['items'] as $source)$sources[$source['id']]=$source;
$board=studio_board_read();
if($can&&is_array($_SESSION['newsdesk_rundown']??null)){
    foreach($_SESSION['newsdesk_rundown'] as $source)if(is_array($source)&&isset($source['id']))studio_board_add_source($source,$user);
    unset($_SESSION['newsdesk_rundown']);$board=studio_board_read();
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$can||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('Ingen tilgang. Last siden på nytt.');}
    try{
        $action=(string)($_POST['action']??'');$revision=(int)($_POST['revision']??0);
        if($action==='settings'){
            if(!$admin)throw new InvalidArgumentException('Bare administrator kan endre automatisk klargjøring.');
            $enabled=($_POST['enabled']??'')==='1';
            studio_board_change(static function(array &$b)use($enabled){$b['newsroomSettings']=['enabled'=>$enabled,'activatedAt'=>gmdate('c'),'dailyLimit'=>8];});
            $notice=$enabled?'Automatisk klargjøring er slått på. Du godkjenner publisering.':'Automatisk klargjøring er satt på pause.';
        }elseif($action==='open_source'){
            $source=$sources[(string)($_POST['source']??'')]??null;if(!$source)throw new InvalidArgumentException('Saken er ikke lenger i RSS-innboksen.');
            if(studio_board_has_source($board['items'],$source)){
                $item=studio_case_source_item($board['items'],$source);
                if($item['status']==='archived')throw new InvalidArgumentException('Denne saken er allerede forkastet eller arkivert.');
            }else{studio_board_add_source($source,$user);$item=studio_case_source_item(studio_board_active(studio_board_read()),$source);}
            $selected='studio:'.$item['id'];
            if(empty($item['web']['body']))studio_newsroom_prepare($item['id'],$item['revision'],$user,$config);
        }elseif(preg_match('/^wp:([1-9][0-9]*)$/D',$selected,$match)){
            if(!$admin)throw new InvalidArgumentException('Bare administrator kan behandle WordPress-saker.');
            if(!in_array($action,['approve','reject','revise'],true))throw new InvalidArgumentException('Ukjent handling.');
            if($action==='approve'&&($_POST['confirmed']??'')!=='1')throw new InvalidArgumentException('Bekreft at du har lest saken.');
            $wpAfterDecision=studio_newsroom_wp('POST',['id'=>(int)$match[1],'token'=>(string)($_POST['token']??''),'operation'=>$action,'comment'=>(string)($_POST['comment']??''),'editorial_facts'=>(string)($_POST['editorial_facts']??''),'actor'=>$user['name']??'Studio-redaktør']);
            $advance=in_array($action,['approve','reject'],true);
            $notice=$action==='approve'?'Godkjenningen er lagret og saken er publisert.':($action==='reject'?'Forslaget er forkastet.':'Saken er bearbeidet og krever ny godkjenning.');
        }elseif(preg_match('/^studio:([a-f0-9]{16})$/D',$selected,$match)){
            $id=$match[1];$item=studio_case_get($id);
            if($item['revision']!==$revision)throw new InvalidArgumentException('Saken er endret. Last siden på nytt og les siste versjon.');
            if($action==='approve'){
                if(!$admin||($_POST['confirmed']??'')!=='1')throw new InvalidArgumentException('Administrator må lese og bekrefte saken.');
                studio_web_save($id,$revision,'approve',['confirmed'=>'1'],$user);
                studio_web_publish($id,$revision+1,'publish',$user,studio_wp_config());$notice='Saken er publisert på radiorubben.no.';$advance=true;
            }elseif($action==='reject'){
                if(in_array($item['web']['delivery']['state']??'',['pending','unknown'],true)||($item['web']['delivery']['status']??'')==='publish')throw new InvalidArgumentException('Publisering må avklares før saken kan forkastes.');
                studio_board_update($id,$revision,'archive',[],$user);$notice='Saken er forkastet og historikken er bevart.';$advance=true;
            }elseif($action==='revise'){
                $comment=trim((string)($_POST['comment']??''));if($comment==='')throw new InvalidArgumentException('Skriv hva du ønsker endret.');
                studio_newsroom_prepare($id,$revision,$user,$config,$comment);$notice='Nytt utkast og ny kontroll er klare.';
            }elseif($action==='prepare'||$action==='check')studio_newsroom_prepare($id,$revision,$user,$config,'',null,null,null,$action==='check');
            elseif($action==='save'){
                studio_web_save($id,$revision,'save',$_POST,$user);
                studio_newsroom_prepare($id,$revision+1,$user,$config,'',null,null,null,true);$notice='Rettelsene er lagret og kontrollert.';
            }else throw new InvalidArgumentException('Ukjent handling.');
        }else throw new InvalidArgumentException('Velg en gyldig sak.');
    }catch(InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){$error='Handlingen kunne ikke bekreftes. Last siden på nytt før et nytt forsøk.';error_log('Studio newsroom operation failed.');}
    $board=studio_board_read();
}
$settings=studio_newsroom_settings($board);$cards=[];$studioItems=[];
foreach(studio_board_active($board) as $item)if(studio_web_is_news($item)){$key='studio:'.$item['id'];$cards[$key]=studio_newsroom_card($item);$studioItems[$key]=$item;}
$wpError=null;$wpStatus=[];
try{$wpStatus=$wpAfterDecision??studio_newsroom_wp();foreach($wpStatus['items'] as $card)$cards['wp:'.$card['id']]=$card;}
catch(Throwable $e){$wpError=$e instanceof InvalidArgumentException?$e->getMessage():'WordPress-køen er utilgjengelig.';}
$labels=['ready'=>'Til godkjenning','attention'=>'Trenger avklaring','working'=>'Under arbeid','published'=>'Publisert'];$counts=array_fill_keys(array_keys($labels),0);
foreach($cards as $card)if(isset($counts[$card['status']]))$counts[$card['status']]++;
$selection=studio_newsroom_selection($cards,$selected,(string)($_POST['filter']??$_GET['filter']??''),$advance&&!$error);
extract($selection);
foreach($cards as &$row){if(!empty($row['image']['url'])&&studio_newsroom_image_url($row['image']['url'])){$imageKey=hash('sha256',$row['image']['url']);$_SESSION['newsroom_images'][$imageKey]=$row['image']['url'];$row['image']['previewUrl']='/newsdesk-image.php?key='.$imageKey;}}unset($row);
$_SESSION['newsroom_images']=array_slice($_SESSION['newsroom_images']??[], -150, null, true);
$card=$cards[$selected]??null;$item=$studioItems[$selected]??null;
function newsroom_fields(string $key,array $card):void{global $filter;?><input type="hidden" name="filter" value="<?=escape($filter)?>"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="item" value="<?=escape($key)?>"><input type="hidden" name="revision" value="<?=(int)($card['revision']??0)?>"><input type="hidden" name="token" value="<?=escape($card['token']??'')?>"><?php }
function newsroom_time(?string $value):string{try{return $value?(new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Europe/Oslo'))->format('d.m. H:i'):'Ikke kontrollert';}catch(Throwable $e){return 'Ukjent tidspunkt';}}
$keys=array_keys($visible);$position=array_search($selected,$keys,true);$nextKey=$position!==false?($keys[$position+1]??''):'';
ob_start();require dirname(__DIR__).'/app/views/newsroom.php';$html=ob_get_clean();
$url=studio_newsroom_url($selected,$filter);
if($fragment){header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');if($error)http_response_code(409);echo json_encode(['ok'=>!$error,'error'=>$error,'html'=>$html,'url'=>$url,'advance'=>$advance&&!$error,'selected'=>$selected,'notice'=>$notice],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
if($_SERVER['REQUEST_METHOD']==='POST'&&!$error){$_SESSION['newsroom_notice']=$notice;redirect($url);}
$extraStylesheet='/assets/newsroom.css?v=20261005-mobile-1';require dirname(__DIR__).'/app/views/head.php';
?>
<div class="shell newsroom-shell"><?php $activePage='newsdesk';require dirname(__DIR__).'/app/views/sidebar.php';?><div class="workspace">
<header class="topbar"><span>Radio Rubben / <strong>Nyhetsdesk</strong></span><details class="nr-site-menu"><summary>Studio-meny</summary><nav><a href="/control.php">Kontrollsenter</a><a href="/sending.php">Sendeliste</a></nav><?php require dirname(__DIR__).'/app/views/account.php';?></details></header>
<?php echo $html; ?></div></div><p id="newsroom-progress" role="status" aria-live="polite" hidden></p><script src="/assets/newsroom.js?v=20261005-mobile-1" defer></script></body></html>
