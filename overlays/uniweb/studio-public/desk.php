<?php
declare(strict_types=1);
require dirname(__DIR__).'/studio-private/app/bootstrap.php';
$user=current_user();if(!$user)redirect('/login.php');
require dirname(__DIR__).'/studio-private/app/board.php';
require dirname(__DIR__).'/studio-private/app/integrations/NewsDesk.php';
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);exit;}
$feeds=newsdesk_all(dirname(__DIR__).'/studio-private/config');$can=studio_can($user,'produce');$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!$can||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('Ingen tilgang.');}
 try{
  $source=null;foreach($feeds as $feed)foreach($feed['items'] as $row)if($row['id']===($_POST['source']??''))$source=$row;
  if(!$source)throw new InvalidArgumentException('Saken finnes ikke i innboksen lenger.');
  studio_board_add_source($source,$user);
  foreach(studio_board_active(studio_board_read()) as $item)if($item['originId']===$source['id'])redirect('/case.php?item='.$item['id']);
 }catch(Throwable $e){$error='Kunne ikke åpne saken. Sendelisten kan være full eller utilgjengelig.';}
}
$extraStylesheet='/assets/control.css?v=2';require dirname(__DIR__).'/studio-private/app/views/head.php';?>
<div class="shell"><?php $activePage='newsdesk';require dirname(__DIR__).'/studio-private/app/views/sidebar.php';?><div class="workspace"><main id="main" class="control-page"><div class="control-heading"><div><p class="eyebrow">REDAKSJON</p><h1>Innkomne saker</h1><p>Åpne en sak, klargjør utkast og velg radio eller nettside.</p></div><a href="/sending.php">Sendeliste</a></div><?php if($error):?><p role="alert"><?=escape($error)?></p><?php endif;?>
<?php foreach($feeds as $feed):?><section class="control-panel"><?php foreach($feed['items'] as $source):?><article><h2><?=escape($source['title'])?></h2><p><?=escape($source['sourceName'])?> · <?=escape($source['publishedAt'])?></p><p><?=escape($source['summary'])?></p><?php if($can):?><form method="post"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="source" value="<?=escape($source['id'])?>"><button>Åpne sak – radio og nett</button></form><?php endif;?></article><?php endforeach;?><?php if(!$feed['items']):?><p>Ingen saker tilgjengelig fra denne kilden.</p><?php endif;?></section><?php endforeach;?><p><a href="/newsdesk.php">Trafikk og øvrige kilder</a></p></main></div></div></body></html>
