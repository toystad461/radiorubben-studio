<?php
declare(strict_types=1);
// Exercise the actual page controller against temporary fixture data.
foreach (['propose','edit','approve','csrf','stale','duplicate','observer'] as $mode) {
    $command=[PHP_BINARY, dirname(__DIR__).'/fixtures/learning-page.php', 'approved', $mode==='observer'?'observer':'admin', 'general', 'rules', $mode];
    $process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($process);
    if($code!==0 || $error!=='')throw new RuntimeException($mode.': '.$error.$output);
    $parts=explode("\nRESULT ",$output);
    $result=json_decode($parts[1]??'',true,512,JSON_THROW_ON_ERROR);
    $rules=$result['rules'];
    $ok=match($mode){
        'propose'=>str_contains($parts[0],'REDIRECT /learning-rules.php?view=pending#learning-rules') && count($rules)===5 && $rules[4]['status']==='pending',
        'edit'=>str_contains($parts[0],'REDIRECT /learning-rules.php?view=pending#learning-rules') && $rules[1]['status']==='pending' && $rules[1]['revision']===2,
        'approve'=>str_contains($parts[0],'REDIRECT /learning-rules.php?view=approved#learning-rules') && $rules[0]['status']==='approved',
        'csrf','observer'=>$result['code']===403 && $rules[1]['status']==='approved' && $rules[1]['revision']===1,
        'stale'=>str_contains($parts[0],'Regelen ble endret') && $rules[1]['revision']===1 && str_contains($parts[0],'Bruk konkrete verb i hver setning.'),
        'duplicate'=>str_contains($parts[0],'Denne regelen finnes allerede') && count($rules)===4,
    };
    if(!$ok)throw new RuntimeException('Failed learning route '.$mode.': '.$output);
    echo "OK learning route $mode\n";
}
