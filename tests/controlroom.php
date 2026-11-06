<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/controlroom.php';
require dirname(__DIR__).'/app/users.php';
function check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$entry=static fn($id)=>['id'=>$id,'title'=>'Title '.$id,'artist'=>'Artist','duration'=>100,'blocked'=>[],'path'=>'secret-path','sha256'=>'private-hash'];
$s=['engine'=>'liquidsoap','mode'=>'playing','current'=>$entry('a')+['evidence'=>'liquidsoap-pcm','observedWallStart'=>990],'active'=>['a'=>$entry('a')],'reserved'=>['b'=>$entry('b')],'outcomes'=>['old'=>[]]];
$beat=['engine'=>'liquidsoap','timestamp'=>999,'connected'=>true,'position'=>9];
$control=['ready'=>true,'entries'=>[$entry('old'),$entry('a'),$entry('b'),$entry('c'),$entry('d'),$entry('e')]];
$view=studio_controlroom_project($s,$beat,$control,1000);
check($view['status']==='playing'&&$view['position']===9.0,'fresh PCM playing and measured position');
check(array_column($view['next'],'title')===['Title b','Title c','Title d'],'reserved next first, no duplicates or completed entries');
check(!str_contains(json_encode($view),'private-hash')&&!str_contains(json_encode($view),'secret-path'),'no private paths or hashes');
foreach(['stopped','paused','blocked','starting']as $mode){$v=studio_controlroom_project(array_replace($s,['mode'=>$mode]),$beat,$control,1000);check($v['status']===$mode&&$v['now']===null&&$v['position']===null,"$mode clears current/progress");}
foreach([['timestamp'=>995],['timestamp'=>1001],['engine'=>'other']]as $bad){$v=studio_controlroom_project($s,array_replace($beat,$bad),$control,1000);check($v['status']==='unknown'&&$v['now']===null&&$v['next']===[],'expired/future/wrong heartbeat fails closed');}
$v=studio_controlroom_project($s,array_replace($beat,['connected'=>false]),$control,1000);check($v['status']==='disconnected'&&$v['now']===null,'disconnected never claims playing');
foreach([null,-1,INF,200]as $position){$v=studio_controlroom_project($s,array_replace($beat,['position'=>$position]),$control,1000);check($v['position']===null,'invalid progress remains unknown');}
$v=studio_controlroom_project(array_replace($s,['current'=>$entry('a')]),$beat,$control,1000);check($v['position']===null,'unverified timing remains unknown');
$v=studio_controlroom_project($s,$beat,array_replace($control,['ready'=>false]),1000);check(!$v['queueKnown']&&$v['next']===[],'unready manifest cannot claim a next track');
$c=$control;$c['entries'][3]['blocked']=['file missing'];$v=studio_controlroom_project($s,$beat,$c,1000);check(count($v['next'])===1,'blocked queue boundary is not skipped');
check(studio_controlroom_read(null)['status']==='unconfigured','unconfigured state');
function escape($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function icon(...$args){return '';}
foreach(['admin','producer','presenter','observer']as $role){$user=['role'=>$role];$activePage='ai-studio';ob_start();require dirname(__DIR__).'/app/views/navigation.php';$html=ob_get_clean();
check(str_contains($html,'/music-studio.php')&&str_contains($html,'/desk.php'),"$role sees implemented read views");
check(str_contains($html,'/users.php')===($role==='admin'),"$role users restriction");
check(str_contains($html,'/crm.php')===($role==='admin'),"$role CRM restriction");
check(str_contains($html,'#innstillinger')===in_array($role,['admin','producer'],true),"$role settings restriction");
check(!str_contains($html,'href="/workspace.php'),'planned pages are not links');}

// The menu cleanup must preserve every existing destination and role boundary.
foreach (['admin','producer','presenter','observer'] as $role) {
 $user=['role'=>$role];$activePage='sending';
 ob_start();require dirname(__DIR__).'/app/views/navigation.php';$html=ob_get_clean();
 preg_match_all('/href="([^"]+)"/',$html,$matches);
 $expected=['/ai-studio.php','/control.php','/music-library.php','/jingles.php',
  '/music-studio.php','/music-queue.php','/music-plan.php','/newsdesk.php',
  '/sending.php','/desk.php','/ai-studio.php#producer-title','/ai-studio.php#music-title','/learning.php'];
 if(in_array($role,['admin','producer'],true))array_push($expected,'/ai-studio.php#innstillinger','/ai-studio.php#rode');
 if($role==='admin')array_push($expected,'/users.php','/crm.php');
 sort($expected);sort($matches[1]);
 check($matches[1]===$expected,"$role retains all original destinations exactly once");
 check(str_contains($html,'data-nav-group="editorial" open'),"$role editorial group opens on its current page");
 check(!str_contains($html,'data-nav-group="library" open'),"$role unrelated library stays folded");
 check(str_contains($html,'Redaksjonell kjøreplan')&&str_contains($html,'Sendekø'),'editorial list and playout queue have distinct names');
 check(str_contains($html,'WordPress-tilkoblingen på VPS er ikke klar'),'no false WordPress integration claim');
 check(str_contains($html,'data-nav-group="settings"')===in_array($role,['admin','producer'],true),"$role empty restricted settings group is omitted");
}
foreach(['case'=>'editorial','news-input'=>'editorial','learning-rules'=>'robot','crm-register'=>'cooperation'] as $page=>$group){
 $user=['role'=>'admin'];$activePage=$page;
 ob_start();require dirname(__DIR__).'/app/views/navigation.php';$html=ob_get_clean();
 check(str_contains($html,'data-nav-group="'.$group.'" open'),"$page keeps its parent workspace open");
}
