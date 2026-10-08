<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true)) {
    http_response_code(405); header('Allow: GET, POST'); exit;
}
require dirname(__DIR__) . '/app/editorial-memory.php';
$canPrepare = studio_can($user, 'produce');
$isAdmin = ($user['role'] ?? '') === 'admin';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Ugyldig forespørsel. Last siden på nytt.');
    }
    if (!$canPrepare || (($_POST['action'] ?? '') !== 'propose' && !$isAdmin)) {
        http_response_code(403); exit('Rollen har ikke tilgang til denne handlingen.');
    }
    try {
        studio_memory_change((string)($_POST['action'] ?? ''), $_POST, $user);
        $action=(string)($_POST['action']??'');
        $_SESSION['learning_message'] = match($action){
            'propose'=>'Instruksene er lagret som forslag og venter på administrator. De brukes ikke ennå.',
            'approve'=>'Regelen er aktiv og følger med i neste radiomanus for God morgen Vestland.',
            'disable'=>'Regelen er deaktivert. Tidligere manus og regelhistorikken er bevart.',
            default=>'Forslaget er avvist og ligger i historikken.',
        };
        redirect('/learning.php?view='.($action==='approve'?'approved':($action==='propose'?'pending':'history')).'#learning-rules');
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log('Studio learning write failed'); $error = 'Kunne ikke lagre. Prøv igjen senere.'; }
}
try { $board = studio_board_read(); }
catch (Throwable $e) { http_response_code(503); exit('Læringsregisteret er utilgjengelig. Ingen regler kan endres nå.'); }
$selectedId = (string)($_POST['item'] ?? $_GET['item'] ?? '');
$selected = null;
foreach ($board['items'] as $item) if ($item['id'] === $selectedId) $selected = $item;
$corrected=[];
foreach(studio_board_active($board) as $item){
    if(($item['program']??'')==='god-morgen-vestland' && $item['status']==='ready' && !empty($item['verified']) && !empty($item['generatedOriginal']) && $item['generatedOriginal']!==$item['script'])$corrected[]=$item;
}
$eligible = $selected && ($selected['program'] ?? '') === 'god-morgen-vestland'
    && $selected['status'] === 'ready' && !empty($selected['verified'])
    && !empty($selected['generatedOriginal']) && $selected['generatedOriginal'] !== $selected['script'];
$message = $_SESSION['learning_message'] ?? null; unset($_SESSION['learning_message']);
$labels=['pending'=>'Venter på godkjenning','approved'=>'Aktiv','rejected'=>'Avvist','disabled'=>'Deaktivert'];
$rules=array_values(array_filter($board['editorialRules']??[],static fn($rule)=>($rule['program']??'')==='god-morgen-vestland'));
$counts=array_fill_keys(array_keys($labels),0);foreach($rules as $rule)if(isset($counts[$rule['status']]))$counts[$rule['status']]++;
$view=is_string($_GET['view']??null)?$_GET['view']:($counts['pending']?'pending':'approved');
if(!in_array($view,['pending','approved','history'],true))$view='approved';
$visible=array_values(array_filter($rules,static fn($rule)=>$view==='history'?in_array($rule['status'],['disabled','rejected'],true):$rule['status']===$view));
$context=studio_memory_context($board,'god-morgen-vestland');
$extraStylesheet = '/assets/control.css?v=20261007';
require dirname(__DIR__) . '/app/views/head.php';
?>
<div class="shell">
<?php $activePage='learning'; require dirname(__DIR__) . '/app/views/sidebar.php'; ?>
<div class="workspace"><header class="topbar"><span>Robåt / <strong>Læring</strong></span><?php require dirname(__DIR__) . '/app/views/account.php'; ?></header>
<main id="main" class="control-page learning-page">
<div class="control-heading"><div><p class="eyebrow">ROBÅT / GOD MORGEN VESTLAND</p><h1>Gjør neste manus bedre</h1><p class="control-intro">Ta vare på gode rettelser og gjør dem til tydelige regler for språk og form.</p></div><a class="control-link" href="/sending.php">Til Sending</a></div>
<?php if ($message): ?><p class="control-alert" role="status"><?= escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="control-alert error" role="alert"><?= escape($error) ?></p><?php endif; ?>
<section class="control-status" aria-label="Status for læring"><div><strong><?=count($corrected)?> rettede manus</strong><small>Kan brukes som læringseksempler</small></div><div><strong><?=$counts['pending']?> forslag</strong><small>Venter på administrator</small></div><div><strong><?=$counts['approved']?> av 20 aktive regler</strong><small>Brukes i nye radiomanus</small></div></section>
<ol class="learning-steps" aria-label="Slik lærer Robåt"><li><strong>1. Finn en rettelse</strong><span>Velg et kontrollert manus eller foreslå en generell språkregel.</span></li><li><strong>2. Beskriv lærdommen</strong><span>Skriv hva som skal bli bedre i neste utkast.</span></li><li><strong>3. Godkjenn regelen</strong><span>Administrator vurderer forslaget før det tas i bruk.</span></li></ol>
<details class="control-panel learning-profile"><summary>Dette bruker Robåt i neste radiomanus</summary><p><?= escape($context['style']) ?></p><?php if($context['rules']):?><ul><?php foreach($context['rules'] as $rule):?><li><?=escape($rule['text'])?> <small>· versjon <?=(int)$rule['version']?></small></li><?php endforeach;?></ul><?php else:?><p>Ingen ekstra programregler er aktive ennå.</p><?php endif;?><p>Dette gjelder radiomanus for God morgen Vestland i Sending. Rettelser blir først en ny regel når et forslag er godkjent. Gamle manus endres ikke. Nettartikler og værmanus bruker foreløpig sine egne skriveråd.</p><p>Kildekravene gjelder alltid. Eksempeltekst gir hjelp med språk og form; den brukes ikke som faktakilde i andre saker.</p></details>
<div class="learning-grid">
<?php if ($canPrepare): ?>
<section class="control-panel"><h2>Gi Robåt skriveinstrukser</h2><p>Du kan gi instrukser direkte, uten å velge et manus. Skriv ett punkt per linje. Hvert punkt blir et eget forslag som kan godkjennes eller deaktiveres.</p>
<form method="get" class="editor-form"><label for="learning-item">Velg utgangspunkt</label><select id="learning-item" name="item"><option value="">En generell språkregel</option><?php foreach($corrected as $candidate):?><option value="<?=escape($candidate['id'])?>"<?=$selectedId===$candidate['id']?' selected':''?>><?=escape($candidate['title'])?></option><?php endforeach;?></select><button type="submit">Vis utgangspunkt</button></form>
<?php if(!$corrected):?><p class="control-muted">Ingen rettede og godkjente manus er klare som eksempler. Du kan foreslå en generell språkregel nå.</p><?php endif;?>
<?php if ($selected): ?><p><?= escape($selected['title']) ?> · <a href="/sending.php?item=<?= escape($selected['id']) ?>">Åpne manuset</a></p><?php endif; ?>
<?php if ($eligible): ?><details open><summary>Sammenlign rettelsen</summary><div class="learning-comparison"><div><h3>Originalutkast</h3><p class="script-view"><?= nl2br(escape($selected['generatedOriginal'])) ?></p></div><div><h3>Rettet og kontrollert</h3><p class="script-view"><?= nl2br(escape($selected['script'])) ?></p></div></div></details><?php endif; ?>
<?php if (!$selectedId || $eligible): ?>
<form method="post" class="editor-form"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="propose"><input type="hidden" name="item" value="<?= escape($selectedId) ?>"><input type="hidden" name="itemRevision" value="<?= (int)($selected['revision'] ?? 0) ?>"><label for="rule-text">Hva skal Robåt gjøre bedre neste gang?</label><p id="rule-help" class="control-muted">Skriv 1–20 instrukser, én per linje og 10–500 tegn per instruks. Beskriv ønsket språk eller form. LIX 40–50 er et skrivemål; siden måler ikke tekstens LIX.</p><textarea id="rule-text" name="text" rows="10" minlength="10" maxlength="10000" required aria-describedby="rule-help" placeholder="Del lange setninger i korte setninger som er lette å lese høyt."><?= escape(is_string($_POST['text'] ?? null) ? $_POST['text'] : (!$selectedId ? implode("\n", [
'Skriv i klarspråk. Teksten skal være lettlest.',
'Sikt mot en lesbarhetsindeks (LIX) på 40–50.',
'Bruk hovedsakelig korte og noen mellomlange setninger.',
'Bruk aktivt språk og unngå passive setninger.',
'Velg enkle, folkelige ord fremfor faguttrykk.',
'Unngå lange setninger, klisjeer og buzzord.',
'Vær nøktern. Unngå skryt av egen virksomhet.',
]) : '')) ?></textarea><label class="verify-row"><input type="checkbox" name="styleOnly" value="1" required<?=($_POST['styleOnly']??'')==='1'?' checked':''?>> Regelen gjelder språk eller form, ikke fakta eller unntak fra kildekontroll.</label><p>Forslaget må godkjennes av administrator før det brukes. Kontroller at det passer sammen med aktive regler.</p><button type="submit">Send instrukser til godkjenning</button></form>
<details><summary>Eksempler på gode læringsregler</summary><ul><li>Del lange setninger i korte setninger som er lette å lese høyt.</li><li>Bevar kildens forbehold når noe ikke er bekreftet.</li><li>Unngå å gjenta samme poeng i ingressen og avslutningen.</li></ul><p>Eksemplene er skrivehjelp og aktiveres ikke automatisk.</p></details>
<?php else: ?><p>Velg God morgen Vestland i Sending, rett et AI-utkast, lagre med kildekontroll og merk det klart. Deretter kan rettelsen brukes som grunnlag for læring.</p><a href="/learning.php">Foreslå en generell programregel</a><?php endif; ?>
</section><?php endif; ?>
<section id="learning-rules" class="control-panel"><h2>Regler og godkjenning</h2><nav class="learning-tabs" aria-label="Vis programregler"><?php foreach(['pending'=>'Forslag · '.$counts['pending'],'approved'=>'Aktive · '.$counts['approved'],'history'=>'Historikk · '.($counts['disabled']+$counts['rejected'])] as $key=>$label):?><a href="/learning.php?view=<?=escape($key)?>#learning-rules"<?=$view===$key?' aria-current="page"':''?>><?=escape($label)?></a><?php endforeach;?></nav>
<?php if($view==='pending'):?><p>Les regelen og eksemplet før godkjenning. Se etter motstrid med aktive regler.</p><?php elseif($view==='approved'):?><p>Disse reglene følger med i nye radiomanus. Vil du endre en regel, deaktiver den og legg inn et nytt forslag.</p><?php else:?><p>Avviste og deaktiverte regler beholdes med eksempler og historikk.</p><?php endif;?>
<?php if(!$visible):?><p class="control-empty"><?=$view==='pending'?'Ingen forslag venter på godkjenning.':($view==='approved'?'Ingen aktive regler ennå. Start med en konkret lærdom fra en rettelse.':'Ingen avviste eller deaktiverte regler.')?></p><?php endif;?>
<?php foreach (array_reverse($visible) as $rule): ?>
<article class="source-summary"><h3><?= escape($labels[$rule['status']]) ?> · versjon <?= (int)$rule['revision'] ?></h3><p><?= escape($rule['text']) ?></p><p class="control-muted">Foreslått av <?= escape($rule['createdBy']) ?></p>
<?php if ($rule['evidence']): ?><details><summary>Se rettelsen regelen bygger på</summary><p><?= escape($rule['evidence']['title']) ?></p><div class="learning-comparison"><div><h4>Før</h4><p class="script-view"><?= nl2br(escape($rule['evidence']['before'])) ?></p></div><div><h4>Etter</h4><p class="script-view"><?= nl2br(escape($rule['evidence']['after'])) ?></p></div></div></details><?php else:?><p class="control-muted">Generell språkregel · uten tilknyttet manus</p><?php endif; ?>
<details><summary>Regelhistorikk</summary><?php foreach ($rule['history'] as $event): ?><p><?= escape($event['at'].' · '.$event['actor'].' · '.$event['action']) ?></p><?php endforeach; ?></details>
<?php if ($isAdmin): ?><div class="item-controls"><?php foreach (($rule['status'] === 'pending' ? ['approve'=>'Godkjenn','reject'=>'Avvis'] : ($rule['status'] === 'approved' ? ['disable'=>'Deaktiver'] : [])) as $action=>$label): ?><form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="<?= escape($action) ?>"><input type="hidden" name="id" value="<?= escape($rule['id']) ?>"><input type="hidden" name="revision" value="<?= (int)$rule['revision'] ?>"><button type="submit"><?= escape($label) ?></button></form><?php endforeach; ?></div><?php endif; ?>
</article><?php endforeach; ?></section></div>
</main></div></div></body></html>
