<?php
declare(strict_types=1);
// Isolated route fixture: real CRM controller/storage/views; no real accounts or partner data.
$mode = $argv[1] ?? 'get'; $root = dirname(__DIR__);
$tmp = sys_get_temp_dir().'/crm-page-'.bin2hex(random_bytes(5));
foreach (['app/views', 'public', 'config'] as $dir) mkdir($tmp.'/'.$dir, 0700, true);
foreach (['crm.php','crm-brreg.php','crm-candidates.php','helpers.php','users.php'] as $file) copy($root.'/app/'.$file, $tmp.'/app/'.$file);
foreach (['head.php','sidebar.php','account.php'] as $file) copy($root.'/app/views/'.$file, $tmp.'/app/views/'.$file);
copy($root.'/public/crm.php', $tmp.'/public/crm.php');
file_put_contents($tmp.'/app/bootstrap.php', '<?php require __DIR__."/helpers.php"; require __DIR__."/users.php"; function current_user(){return $GLOBALS["mode"]==="guest"?null:["name"=>"Testbruker","role"=>in_array($GLOBALS["mode"],["observer","producer","presenter"],true)?$GLOBALS["mode"]:"admin"];} $config=[];');
if($mode==='select-new') {
 $brreg=$tmp.'/app/crm-brreg.php';
 $code=file_get_contents($brreg);
 $code=str_replace('function studio_crm_brreg(', 'function fixture_unused_brreg(', $code);
 $code.='\nfunction studio_crm_brreg(string $org): array { if($org!=="974760673")throw new RuntimeException("Wrong selection"); return ["fields"=>["orgNumber"=>$org,"company"=>"Ny testbedrift AS"],"fetchedAt"=>gmdate("c")]; }';
 file_put_contents($brreg,str_replace('\\nfunction', "\nfunction", $code));
}
require $tmp.'/app/crm.php';
$path = $tmp.'/config/crm.private.json';
$admin = ['role'=>'admin', 'name'=>'Testadministrator'];
$fields = ['company'=>'<script>alert(1)</script>', 'stage'=>'candidate', 'priority'=>'1', 'nextStep'=>'Avtal møte', 'opportunity'=>'Test', 'oneDriveUrl'=>'https://1drv.ms/f/test'];
$id = studio_crm_apply('create', $fields, $admin, $path);
$before = file_get_contents($path);
$_SESSION = ['csrf'=>'fixture-token']; $_GET = ['id'=>$id]; $_POST = [];
$_SERVER['REQUEST_METHOD'] = in_array($mode, ['csrf','save','activity','invalid','conflict','forged'], true) ? 'POST' : ($mode === 'method' ? 'DELETE' : 'GET');
if ($mode === 'list') $_GET = [];
if ($mode === 'new') $_GET = ['new'=>'1'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') $_POST = $fields + ['id'=>$id, 'revision'=>1, 'csrf'=>$mode === 'csrf' ? 'wrong' : 'fixture-token', 'action'=>'save'];
if ($mode === 'save') $_POST['contact'] = 'Kari Test';
if ($mode === 'activity') $_POST = ['csrf'=>'fixture-token','action'=>'activity','id'=>$id,'revision'=>1,'channel'=>'phone','date'=>'2026-01-26','text'=>'Avtalt et møte.'];
if ($mode === 'invalid') { $_POST['email']='invalid'; $_POST['opportunity']='Behold dette utkastet'; }
if ($mode === 'conflict') { $_POST['revision']=9; $_POST['nextStep']='Behold neste steg'; }
if ($mode === 'forged') $_POST['action']='send_email';

if(in_array($mode,['lookup-new','lookup-existing','lookup-expired'],true)) {
 $_SESSION['crm_lookup']=['fields'=>['orgNumber'=>'999999999','company'=>$fields['company'],'website'=>'https://example.test','businessAddress'=>'Testvegen 2'],'fetchedAt'=>$mode==='lookup-expired'?'2000-01-01T00:00:00Z':gmdate('c')];
 $_GET=($mode==='lookup-new'?['new'=>'1']:['id'=>$id])+['from_lookup'=>'999999999'];
}
if($mode==='select-existing') {
 $_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf'=>'fixture-token','action'=>'select_company','choice'=>'card:'.$id];
}
if($mode==='select-new') { $_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf'=>'fixture-token','action'=>'select_company','choice'=>'org:974760673']; }
if($mode==='select-invalid') {
 $_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf'=>'fixture-token','action'=>'select_company','choice'=>'card:../../file'];
}
ob_start();
register_shutdown_function(static function () use ($mode,$tmp,$path,$before,$id): void {
    $html = (string)ob_get_clean(); $after = studio_crm_read($path); $ok = true;
    if (in_array($mode,['observer','producer','presenter','csrf'],true)) $ok = http_response_code()===403 && !str_contains($html,'alert(1)');
    elseif ($mode==='method') $ok = http_response_code()===405;
    elseif ($mode==='guest') $ok = http_response_code()===303 && !str_contains($html,'alert(1)');
    elseif ($mode==='select-existing') $ok=http_response_code()===303 && file_get_contents($path)===$before;
    elseif ($mode==='select-new') $ok=http_response_code()===303 && ($_SESSION['crm_lookup']['fields']['orgNumber']??'')==='974760673' && file_get_contents($path)===$before;
    elseif ($mode==='save') $ok = http_response_code()===303 && $after['records'][0]['contact']==='Kari Test';
    elseif ($mode==='activity') $ok = http_response_code()===303 && $after['records'][0]['lastContact']==='2026-01-26';
    else {
        $ok = !str_contains($html,'<script>alert(1)</script>') && str_contains($html,'Samarbeidspartnere');
        if ($mode==='get') $ok = $ok && str_contains($html,'&lt;script&gt;') && str_contains($html,'Logg en samtale') && str_contains($html,'OneDrive');
        if ($mode==='invalid') $ok = $ok && http_response_code()===422 && str_contains($html,'Behold dette utkastet');
        if ($mode==='conflict') $ok = $ok && http_response_code()===422 && str_contains($html,'value="9"') && str_contains($html,'Behold neste steg');
        if(in_array($mode,['lookup-new','lookup-existing'],true)) $ok=$ok && str_contains($html,'value="999999999"') && str_contains($html,'value="Testvegen 2"') && str_contains($html,$mode==='lookup-new'?'Ny potensiell kunde.':'Bedriften er allerede registrert.');
        if($mode==='lookup-expired') $ok=$ok && !str_contains($html,'value="Testvegen 2"');
        if($mode==='select-invalid') $ok=$ok && http_response_code()===422;
        if ($mode==='forged') $ok = $ok && http_response_code()===422;
    }
    if (!in_array($mode,['save','activity'],true)) $ok = $ok && file_get_contents($path)===$before;
    $preview = getenv('CRM_PREVIEW_FILE'); if ($preview && in_array($mode,['get','list','new'],true)) file_put_contents($preview,$html);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); rmdir($tmp);
    if (!$ok) { fwrite(STDERR,"FAIL CRM page $mode\n".substr($html,0,1000)); exit(1); }
    echo "OK CRM page $mode\n";
});
require $tmp.'/public/crm.php';
