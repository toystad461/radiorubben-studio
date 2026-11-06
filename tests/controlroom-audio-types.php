<?php
declare(strict_types=1);
require __DIR__.'/controlroom.php';
foreach(['stikk','jingle','reklame','lydteppe','program']as$type){
 $typed=$s;$typed['current']['eventType']=$type;$typed['reserved']['b']['eventType']=$type;
 $v=studio_controlroom_project($typed,$beat,$control,1000);
 check($v['now']['eventType']===$type&&$v['next'][0]['eventType']===$type,'now and reserved next retain audio type '.$type);
}
$typed=$s;$typed['current']['eventType']='<script>unknown';
check(studio_controlroom_project($typed,$beat,$control,1000)['now']['eventType']===null,'unknown audio type omitted');
