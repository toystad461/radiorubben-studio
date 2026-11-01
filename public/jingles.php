<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/jingles.php';
$user=current_user();if(!$user)redirect('/login.php');
if(!studio_can($user,'produce')){http_response_code(403);exit('Rollen gir ikke tilgang til jingler.');}
$method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
header('Cache-Control: no-store');$error='';$message=$_SESSION['jingle_message']??'';unset($_SESSION['jingle_message']);
if($method==='POST'){
    $csrf=$_POST['csrf']??'';$expected=$_SESSION['csrf']??'';
    if(!is_string($csrf)||$expected===''||!hash_equals($expected,$csrf)){http_response_code(403);exit('Last siden på nytt før du fortsetter.');}
    try{
        $action=$_POST['action']??'';
        if($action==='upload'){
            $f=$_FILES['jingles']??null;
            if(!is_array($f)||!is_array($f['name']??null)||count($f['name'])<1||count($f['name'])>10||array_sum($f['size']??[])>64*1024*1024)throw new InvalidArgumentException('Velg 1–10 MP3-filer, maksimalt 64 MiB samlet.');
            $results=[];
            foreach($f['name'] as $i=>$name){
                try{
                    $result=music_library_upload(['name'=>$name,'tmp_name'=>$f['tmp_name'][$i],'size'=>$f['size'][$i],'error'=>$f['error'][$i]],$user,$csrf,$expected,null,null,null,'jingle');
                    if(!$result['duplicate'])music_library_prepare($result['track']['id'],$result['track']['sha256'],0,$user,$csrf,$expected);
                    $results[]=$name.($result['duplicate']?' finnes allerede.':' er lastet opp og klargjort.');
                }catch(InvalidArgumentException $e){$results[]=(string)$name.': '.$e->getMessage();}
                catch(Throwable){$results[]=(string)$name.': Klargjøringen kunne ikke fullføres. Kontroller listen før nytt forsøk.';}
            }
            $message=implode(' ',$results);
        }elseif($action==='edit'){
            if(!is_string($_POST['revision']??null)||!ctype_digit($_POST['revision']))throw new InvalidArgumentException('Ugyldig revisjon.');
            jingle_edit(['id'=>$_POST['id']??null,'sha256'=>$_POST['sha256']??null,'revision'=>(int)$_POST['revision'],'name'=>$_POST['name']??null,'category'=>$_POST['category']??null,'disabled'=>isset($_POST['disabled'])],$user,$csrf,$expected);
            $message='Navn, kategori og tilgjengelighet er lagret. Originalfilen er uendret. Endringen brukes ved neste køsynkronisering; aktive innslag beholdes.';
        }elseif($action==='add'){
            if(!is_string($_POST['queueRevision']??null)||!ctype_digit($_POST['queueRevision']))throw new InvalidArgumentException('Ugyldig køversjon.');
            $found=false;foreach(music_library_read(null,'jingle') as $t)if($t['id']===($_POST['id']??null)&&$t['sha256']===($_POST['sha256']??null))$found=true;
            if(!$found)throw new InvalidArgumentException('Jinglen finnes ikke.');
            music_studio_change(['action'=>'add','trackId'=>$_POST['id'],'sha256'=>$_POST['sha256'],'revision'=>(int)$_POST['queueRevision']],$user,$csrf,$expected,null,null,music_playout_config());
            $message='Jinglen er lagt sist i sendekøen. Flytt den til ønsket plass i Musikkstudio.';
        }else throw new InvalidArgumentException('Ukjent handling.');
        $_SESSION['jingle_message']=$message;redirect('/jingles.php');
    }catch(DomainException $e){http_response_code(409);$error=$e->getMessage();}
    catch(InvalidArgumentException $e){http_response_code(400);$error=$e->getMessage();}
    catch(Throwable){http_response_code(503);$error='Endringen kunne ikke bekreftes. Oppdater før du prøver igjen.';}
}
$tracks=music_library_read(null,'jingle');$queue=music_play_queue_read();
$extraStylesheet='/assets/jingles.css?v=20261101';require dirname(__DIR__).'/app/views/head.php';
?>
<div class="shell"><?php $activePage='jingles';require dirname(__DIR__).'/app/views/sidebar.php';?><div class="workspace">
<header class="topbar"><span>Studio / <strong>Jingler</strong></span><?php require dirname(__DIR__).'/app/views/account.php';?></header>
<main id="main" class="jingle-page"><header><p class="eyebrow">RADIO RUBBEN / STASJONSLYD</p><h1>Jingler</h1><p>Små innslag som binder sendingen sammen. Forhåndslytt privat og legg dem i sendekøen.</p><a href="/music-studio.php">Åpne sendekøen →</a> · <a href="/music-library.php">Musikkbibliotek</a></header>
<?php if($message):?><p class="jingle-notice" role="status"><?=escape($message)?></p><?php endif;?>
<?php if($error):?><p role="alert"><?=escape($error)?></p><?php endif;?>
<details class="jingle-upload"><summary>Last opp jingler</summary><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="upload"><label>Velg MP3-filer<input type="file" name="jingles[]" accept=".mp3,audio/mpeg" multiple required></label><p>Inntil 10 filer, 20 MiB per fil og 64 MiB samlet. Originalene lagres privat. Jingler inngår ikke i musikkforslag eller rotasjon.</p><button type="submit">Last opp og klargjør jingler</button></form></details>
<section aria-labelledby="jingle-list-title"><h2 id="jingle-list-title">Jinglebibliotek <small><?=count($tracks)?> innslag</small></h2><label>Vis kategori<select id="jingle-category"><option value="all">Alle jingler</option><?php foreach(JINGLE_CATEGORIES as $value=>$label):?><option value="<?=escape($value)?>"><?=escape($label)?></option><?php endforeach;?></select></label>
<p>Knappen «Forhåndslytt» spiller bare i denne nettleseren. Nyhets- og sesongjingler velges manuelt; navn og kategori gir ingen automatisk tidsstyring.</p>
<div class="jingle-grid"><?php foreach($tracks as $t):$j=$t['jingle']??[];$name=$j['name']??$t['originalName'];$category=$j['category']??'station';$meta=music_library_metadata($t);$ready=$t['available']&&$meta&&!($j['disabled']??false);?>
<article class="jingle-card" id="jingle-<?=escape($t['id'])?>" data-category="<?=escape($category)?>"><p class="eyebrow"><?=escape(JINGLE_CATEGORIES[$category]??'Stasjonsjingle')?></p><h3><?=escape($name)?></h3><p><?=escape(music_library_duration($meta))?> · <?=($j['disabled']??false)?'Deaktivert':($ready?'Klar til kø':'Mangler klargjøring eller lydfil')?></p>
<audio preload="none" aria-label="<?=escape('Privat forhåndslytting: '.$name)?>" src="/music-library-audio.php?id=<?=escape($t['id'])?>&amp;version=<?=escape($t['sha256'])?>"></audio>
<div class="jingle-actions"><button type="button" data-preview <?=$t['available']?'':'disabled'?>>Forhåndslytt</button><form method="post"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?=escape($t['id'])?>"><input type="hidden" name="sha256" value="<?=escape($t['sha256'])?>"><input type="hidden" name="queueRevision" value="<?=$queue['revision']?>"><button type="submit" <?=$ready?'':'disabled'?>>Legg i sendekø</button></form></div>
<details><summary>Navn og innstillinger</summary><form method="post"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?=escape($t['id'])?>"><input type="hidden" name="sha256" value="<?=escape($t['sha256'])?>"><input type="hidden" name="revision" value="<?=$t['revision']??0?>"><label>Navn<input name="name" maxlength="160" value="<?=escape($name)?>" required></label><label>Kategori<select name="category"><?php foreach(JINGLE_CATEGORIES as $value=>$label):?><option value="<?=escape($value)?>" <?=$category===$value?'selected':''?>><?=escape($label)?></option><?php endforeach;?></select></label><label><input type="checkbox" name="disabled" <?=($j['disabled']??false)?'checked':''?>> Deaktivert for nye køinnslag</label><button type="submit">Lagre innstillinger</button><p class="small">Original: <?=escape($t['originalName'])?></p></form></details></article>
<?php endforeach;?></div><?php if(!$tracks):?><p>Ingen jingler ennå. Last opp den første jinglen over.</p><?php endif;?></section><p id="jingle-listen-status" role="status" aria-live="polite">Privat lytting er av.</p></main></div></div><script type="module" src="/assets/jingles.mjs?v=20261101"></script></body></html>
