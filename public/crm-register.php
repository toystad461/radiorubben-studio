<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user = current_user();
require_once dirname(__DIR__).'/app/crm.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
if (!studio_crm_allowed($user)) { http_response_code(403); echo json_encode(['error'=>'Ingen tilgang.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!is_string($_POST['csrf']??null) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])) { http_response_code(403); exit; }
try {

    if (($_POST['action']??'')==='search') {
        $query=studio_crm_text($_POST,'query',180);
        if(mb_strlen($query)<2) throw new InvalidArgumentException('Skriv minst to tegn.');
        if(microtime(true)-(float)($_SESSION['crm_search_at']??0)<0.5) throw new InvalidArgumentException('Vent litt før neste søk.');
        $_SESSION['crm_search_at']=microtime(true);
        $records=studio_crm_read()['records'];
        $warning=''; $remote=['results'=>[],'more'=>false];
        try { $remote=studio_crm_brreg_search($query); }
        catch(Throwable $e) { $warning='Brønnøysundregistrene er utilgjengelig. Viser bare treff i CRM. Prøv igjen senere.'; }
        echo json_encode(['results'=>studio_crm_search_results($records,$query,$remote['results']),'more'=>$remote['more'],'warning'=>$warning],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (time()-(int)($_SESSION['crm_lookup_at']??0)<2) throw new InvalidArgumentException('Vent litt før neste oppslag.');
    $_SESSION['crm_lookup_at']=time();
    $result=studio_crm_brreg(studio_crm_text($_POST,'orgNumber',20));
    $_SESSION['crm_lookup']=$result;
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['error'=>$e->getMessage()]); }
catch (Throwable $e) { http_response_code(503); echo json_encode(['error'=>'Oppslaget er utilgjengelig. Skjemaet ditt er beholdt.']); }
