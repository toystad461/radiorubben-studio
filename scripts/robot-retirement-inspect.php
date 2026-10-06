<?php
declare(strict_types=1);
$base='/customers/9/3/1/cptk37ymg/webroots/r1417157';
if(realpath('/run/webroots/r1417157')!==$base)throw new RuntimeException('Unexpected root');
$files=['public/robot.php'=>'studio-public/robot.php','app/views/sidebar.php'=>'studio-private/app/views/sidebar.php','public/newsdesk.php'=>'studio-public/newsdesk.php','public/control.php'=>'studio-public/control.php'];
$out=['files'=>[]];
foreach($files as $repo=>$path){$full=$base.'/'.$path;if(is_link($full)||!is_file($full))throw new RuntimeException('Missing or linked source');$out['files'][$repo]=['sha256'=>hash_file('sha256',$full),'content'=>file_get_contents($full)];}
$statePath=getenv('HOME').'/.radiorubben-studio-deploy/selective-state.json';
$state=json_decode(file_get_contents($statePath),true,512,JSON_THROW_ON_ERROR);
$out['state']=['commit'=>$state['commit'],'run'=>$state['run'],'files'=>array_intersect_key($state['files'],array_flip(array_values($files)))];
require_once $base.'/studio-private/app/integrations/RobotInbox.php';
$out['legacyExports']=[];
foreach(['config/robot-news.json','config/robot-inbox.json','studio-private/config/robot-news.json','studio-private/config/robot-inbox.json'] as $p)$out['legacyExports'][$p]=['exists'=>is_file($base.'/'.$p),'validItems'=>count(robot_inbox_items($base.'/'.$p))];
require_once $base.'/studio-private/app/newsroom.php';
$board=studio_board_read();$out['activeCases']=[];
foreach(studio_board_active($board) as $i)$out['activeCases'][]=['id'=>$i['id'],'sourceUrl'=>$i['sourceUrl']??'','hasOrigin'=>!empty($i['originId']),'isNews'=>studio_web_is_news($i),'hasWeb'=>!empty($i['web']['body']),'hasRadio'=>trim((string)($i['script']??''))!==''];
echo 'ROBOT_PREFLIGHT_JSON '.json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
