<?php
$fixture=__DIR__.'/crm-outreach-page.php';
foreach(['website-csrf','website-denied','website-missing-config','text-approve','text-export','view','guest','denied','csrf','save','invalid','lookup-denied','lookup-csrf','lookup-method','job-denied','job-csrf','job-method','audio-denied','audio-missing']as$mode){
    $p=proc_open([PHP_BINARY,$fixture,$mode],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes);
    if(!is_resource($p))exit(1);fclose($pipes[0]);if(proc_close($p)!==0)exit(1);
}
