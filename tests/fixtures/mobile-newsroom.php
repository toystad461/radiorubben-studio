<?php
$_SESSION=['csrf'=>'fixture'];
// Isolated visual fixture: no authentication, network, database, email or real story mutations.
require dirname(__DIR__,2).'/app/newsroom-view.php';
function newsroom_time($v){return $v?:'Ikke kontrollert';}
function escape($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function newsroom_fields($key,$card){echo '<input type="hidden" name="item" value="'.escape($key).'"><input type="hidden" name="token" value="fixture-token"><input type="hidden" name="csrf" value="fixture-csrf">';}
$second=($argv[1]??'')==='next';$selected=$second?'wp:2':'wp:1';$filter='ready';$admin=$can=true;$error=$notice=$wpError=null;$item=null;$settings=['enabled'=>true];$wpStatus=[];$sources=[];$board=['items'=>[]];
$labels=['ready'=>'Til godkjenning','attention'=>'Trenger avklaring','working'=>'Under arbeid','published'=>'Publisert'];$counts=['ready'=>$second?1:2,'attention'=>0,'working'=>0,'published'=>$second?1:0];
$card=['id'=>1,'title'=>$second?'Neste eksempelsak er klar':'Eksempelsak: En enklere nyhetsdesk på mobil','sourceName'=>'Fotball · Spillersak','sourceUrl'=>'','status'=>'ready','reasons'=>[],'intro'=>'Dette er en oppdiktet sak for å teste lesing og godkjenning på små skjermer.','body'=>"Du kan lese saken, se bildet og be om endringer i samme visning.\n".str_repeat("Eksempeltekst for å kontrollere plassering av knapper og lesbarhet på mobil.\n",5),'image'=>['previewUrl'=>'/fixture-image.svg','alt'=>'Eksempelbilde','caption'=>'Illustrasjon for test'],'canApprove'=>true,'canRevise'=>true,'canAddFacts'=>true,'links'=>[['url'=>'https://example.test/source','label'=>'Eksempelkilde']]];
$cards=[$selected=>$card];if(!$second)$cards['wp:2']=array_replace($card,['title'=>'Neste eksempelsak er klar']);$visible=$cards;$position=0;$nextKey=$second?'':'wp:2';
$mode=$argv[1]??'';
if($mode==='rejected'){$card=null;$cards=$visible=[];$selected='';$nextKey='';$counts=array_fill_keys(array_keys($counts),0);$notice='Forslaget er forkastet. Historikken er bevart.';}
if($mode==='published')$card['status']='published';
if($mode==='observer')$admin=$can=false;
if(in_array($mode,['radio','both'],true)){
 require_once dirname(__DIR__,2).'/app/newsroom.php';
 $item=['id'=>'1234567890abcdef','originId'=>'fixture','title'=>'Eksempelsak for radio og nett','sourceName'=>'NRK','sourceUrl'=>'https://www.nrk.no/vestland/test-1.12345678','revision'=>1,'channel'=>$mode,'status'=>'draft','script'=>'Et radiomanus til kontroll.'];
 $card=studio_newsroom_card($item);$counts=['ready'=>0,'attention'=>1,'working'=>0,'published'=>0];$selected='studio:'.$item['id'];$cards=$visible=[$selected=>$card];$nextKey='';$board=['items'=>[$item]];
 $sources=[['id'=>'incoming','title'=>'Ny eksempelsak','sourceName'=>'NRK','url'=>'https://www.nrk.no/vestland/test-1.87654321','summary'=>'Kildeomtale','publishedAt'=>gmdate('c')]];
}
$extraStylesheet='/assets/newsroom.css';require dirname(__DIR__,2).'/app/views/head.php';
?>
<div class="shell newsroom-shell"><aside class="sidebar">Studio-meny</aside><div class="workspace"><header class="topbar"><span>Radio Rubben / <strong>Nyhetsdesk</strong></span><details class="nr-site-menu"><summary>Studio-meny</summary><nav><a href="/control.php">Kontrollsenter</a></nav></details></header>
<?php require dirname(__DIR__,2).'/app/views/newsroom.php';?>
</div></div><p id="newsroom-progress" role="status" aria-live="polite" hidden></p><script src="/assets/newsroom.js" defer></script></body></html>
