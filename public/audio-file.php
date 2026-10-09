<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user=current_user();if(!$user)redirect('/login.php');
require_once dirname(__DIR__).'/app/audio-processing.php';
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
try {
    $id=(string)($_GET['item']??'');$profile=(string)($_GET['profile']??'');$kind=(string)($_GET['kind']??'');
    if(!preg_match('/^[a-f0-9]{16}$/D',$id)||!in_array($kind,['raw','master','send'],true))throw new RuntimeException();
    rr_audio_profile($profile);$board=studio_board_read();$i=studio_case_get($id);$v=$i['audioScripts'][$profile]??[];
    if(($i['status']??'')==='archived')throw new RuntimeException();
    // Send-file access is a consumer of the approval gate, even with a copied URL.
    if($kind==='send'&&!rr_audio_queued($i,$board,$config['rr_audio']??[]))throw new RuntimeException();
    if($kind==='send'&&($i['audioQueue']['profile']??'')!==$profile)throw new RuntimeException();
    $asset=$v['audio'][$kind]??[];$p=rr_audio_asset($asset);$bytes=(string)file_get_contents($p);
    // Check the bytes actually served (avoid a check/read race).
    if(!hash_equals($asset['sha256'],hash('sha256',$bytes)))throw new RuntimeException();
    $size=strlen($bytes);$start=0;$end=$size-1;
    header('Accept-Ranges: bytes');
    if(isset($_SERVER['HTTP_RANGE'])) {
        if(!preg_match('/^bytes=(\d*)-(\d*)$/D',$_SERVER['HTTP_RANGE'],$m)||($m[1]===''&&$m[2]==='')){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}
        if($m[1]===''){$start=max(0,$size-(int)$m[2]);}else{$start=(int)$m[1];if($m[2]!=='')$end=min($end,(int)$m[2]);}
        if($start>$end||$start>=$size){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}
        http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);$bytes=substr($bytes,$start,$end-$start+1);
    }
    header('Content-Type: '.$asset['type']);header('Content-Length: '.strlen($bytes));header('Cache-Control: private, no-store');
    header('Content-Disposition: '.($kind==='send'?'attachment':'inline').'; filename="radio-rubben-'.$kind.'.'.pathinfo($asset['name'],PATHINFO_EXTENSION).'"');
    echo $bytes;
}catch(Throwable){http_response_code(404);echo 'Lydfilen er ikke tilgjengelig eller mangler godkjenning.';}
