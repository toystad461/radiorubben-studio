<?php
require '/srv/radiorubben-test/studio/app/case-workflow.php';
$c=rr_e2e_config();$i=studio_case_get($c['case_id']);$d=$i['web']['delivery'];
$p=rr_e2e_http($c,'GET','/posts/'.$d['id'].'?context=edit');
$list=rr_e2e_http($c,'GET','/posts?context=edit&slug=studio-'.$i['id']);
$checks=['published'=>$p['status']==='publish','one_post'=>count($list)===1&&$list[0]['id']===$p['id'],'same_id'=>$d['id']===$p['id'],'same_title'=>$p['title']['raw']===$i['web']['title'],'same_body'=>trim($p['content']['raw'])===trim(studio_web_html($i)),'same_intro'=>trim($p['excerpt']['raw'])===trim($i['web']['intro']),'ai_disclosure'=>str_contains($p['content']['rendered'],'KI-støtte'),'source_link'=>str_contains($p['content']['rendered'],rr_e2e_url()),'confirmed'=>$d['state']==='confirmed'&&$d['lookup']['state']==='matched'];
$changed=$i;$changed['sourceUrl']='https://example.test/changed';$checks['changed_source_rejected']=!studio_web_checked($changed);
$changed=$i;$changed['web']['check']['source']['sha256']=str_repeat('0',64);$checks['tampered_source_rejected']=!studio_web_checked($changed);
$changed=$i;$changed['web']['check']['checkedAt']=gmdate('c',time()-7200);$checks['expired_check_rejected']=!studio_web_checked($changed);
$wrong=$c;$wrong['target']='https://www.radiorubben.no/';try{rr_e2e_http($wrong,'GET','/posts');$checks['production_target_rejected']=false;}catch(RuntimeException){$checks['production_target_rejected']=true;}
try{studio_web_publish($i['id'],$i['revision'],'publish',['role'=>'producer'],$c);$checks['non_editor_rejected']=false;}catch(InvalidArgumentException){$checks['non_editor_rejected']=true;}
$other=[];foreach(studio_board_read()['items'] as $row)if($row['id']!==$i['id'])$other[]=['id'=>$row['id'],'hash'=>hash('sha256',json_encode($row))];
echo json_encode(['case_id'=>$i['id'],'post_id'=>$p['id'],'post_link'=>$p['link'],'revision'=>$i['revision'],'checks'=>$checks,'other_items'=>$other],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
if(in_array(false,$checks,true))exit(1);
