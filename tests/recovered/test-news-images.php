<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/case-workflow.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "OK $label\n";}
function reject($fn,$label){try{$fn();}catch(Throwable){echo "OK $label\n";return;}throw new RuntimeException($label);}
$catalog=['package'=>RRNI_PACKAGE,'manifest_sha256'=>RRNI_MANIFEST_SHA,'assets'=>[]];$n=2000;
foreach(rrni_assets() as $id=>$a)$catalog['assets'][$id]=['media_id'=>++$n,'url'=>'https://www.radiorubben.no/wp-content/uploads/synthetic/'.$id.'.png','sha256'=>$a['sha256'],'width_px'=>$a['width_px'],'height_px'=>$a['height_px']];
$rows=[
 ['Bremnes IL vant fotballkampen','Bremnes IL vant fotballkampen 3–1.','bremnes-fotball','bremnes_football','Bremnes IL'],
 ['Brann vant med spiller fra Bømlo','Brann vant kampen. Spilleren har tidligere spilt for Bremnes IL.','sport','sport_event','Brann'],
 ['Kommunestyret diskuterer budsjett','Flertallet prioriterer skolen i en politisk budsjettdebatt.','politikk','political_debate',null],
 ['Nye åpningstider på servicetorget','Kommunen informerer om åpningstidene og tjenestene.','kommunesaker','municipal_service',null],
 ['Reguleringsplan på høring','Administrasjonen ber om innspill til reguleringsplanen.','kommunesaker','planning',null],
 ['Politikerne vedtok reguleringsplanen','Politikerne vedtok reguleringsplanen etter debatt.','politikk','political_decision',null],
 ['Konsert på kulturhuset','En akustisk konsert med musikk fra scenen.','kultur-arrangement','music',null],
 ['Idrettsarrangement i svømmehallen','Svømmere og løpere møtes til et idrettsarrangement.','sport','sport_event',null],
 ['Nytt maritimt verksted','Et maritimt verksted ansetter flere fagarbeidere.','naeringsliv','maritime',null],
 ['Frisørsalong åpner','En frisørsalong åpner med tilbud om hårklipp.',null,'',null],
 ['Nye tilbud i nabolaget','Kystsamfunnet får et nytt møtested ved havnen.','lokalnyheter','local_community',null],
];
$choices=[];
foreach($rows as [$title,$body,$theme,$angle,$club]){
 $ctx=['title'=>$title,'intro'=>$body,'body'=>$body,'category'=>'local','entities'=>[]];
 $answer=['theme_id'=>$theme,'reason'=>$theme?'Hovedvinklingen og motivet passer den beskrevne saken.':'Frisørsalongen passer ikke verkstedmotivet eller de andre motivene.',
  'evidence'=>[$body],'motif_fits'=>$theme!==null,'confidence'=>0.95,'main_angle'=>$angle,'primary_club'=>$club,'bremnes_focus'=>$club==='Bremnes IL'];
 $s=rrni_bind(rrni_assessment($ctx,$answer),$catalog)+['context_hash'=>rrni_context_hash($ctx)];$choices[]=$s;
 check(($s['theme_id']??null)===$theme&&($theme?rrni_selection_valid($s):$s['status']==='INGEN_PASSENDE_BILDE'),'semantic contract: '.$title);
 if($theme)check(array_keys($s['variants'])===['article_landscape','feed_square','story_vertical'],'same theme, three explicit placements: '.$theme);
 $candidate=['category'=>'local','entities'=>[],'web'=>['title'=>$title,'intro'=>$body,'body'=>$body]];
 $prepared=studio_images_prepare($candidate,['news_images_enabled'=>true,'openai_model'=>'synthetic'],static function($config,$payload)use($ctx,$answer){
  $input=json_decode($payload['input'],true);if($input['article']!==$ctx||count($input['themes'])!==7)throw new RuntimeException('Incomplete semantic context');
  check(($payload['text']['format']['strict']??false)===true,'image assessment requires structured JSON');
  foreach($payload['text']['format']['schema']['properties']['evidence']['items']['enum'] as $quote)
   check(str_contains(implode("\n",[$ctx['title'],$ctx['intro'],$ctx['body']]),$quote),'schema evidence is verbatim article text');
  return json_encode($answer);
 },fn()=>$catalog);
 check(($prepared['theme_id']??null)===$theme&&($prepared['status']??'')===($theme?'selected':'INGEN_PASSENDE_BILDE'),'full context selection pipeline: '.$title);
}
$bad=['theme_id'=>'bremnes-fotball','reason'=>'Tidligere klubb finnes i teksten, men er ikke hovedklubben.','motif_fits'=>true,'confidence'=>0.95,'main_angle'=>'bremnes_football','primary_club'=>'Brann','bremnes_focus'=>true,'evidence'=>[$rows[1][1]]];
check(rrni_assessment(['body'=>$rows[1][1]],$bad)['status']==='INGEN_PASSENDE_BILDE','Brann with former Bremnes history cannot get Bremnes logo');
$bad['primary_club']='Bremnes';$bad['evidence']=['Dette sitatet finnes ikke.'];
check(rrni_assessment(['body'=>$rows[1][1]],$bad)['status']==='INGEN_PASSENDE_BILDE','invented semantic evidence rejected');
$broken=$catalog;unset($broken['assets'][array_key_first($broken['assets'])]);reject(fn()=>rrni_catalog_validate($broken),'missing upload rejected');
$broken=$catalog;$key=array_key_first($broken['assets']);$broken['assets'][$key]['sha256']=str_repeat('0',64);reject(fn()=>rrni_catalog_validate($broken),'changed checksum rejected');
check(!rrni_url_valid('https://www.radiorubben.no.evil.test/wp-content/uploads/a.png'),'lookalike media host rejected');

$dir=sys_get_temp_dir().'/rr-images-'.bin2hex(random_bytes(5));mkdir($dir);$path=$dir.'/board.json';
$admin=['name'=>'Synthetic editor','role'=>'admin'];$wp=['username'=>'synthetic','application_password'=>'synthetic'];
try{
 $source=['id'=>'test-image-source','url'=>'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/test.123.aspx','title'=>$rows[0][0],'sourceName'=>'Bømlo kommune','publishedAt'=>gmdate('c')];
 studio_board_add_source($source,$admin,$path,'web');$item=studio_case_source_item(studio_board_read($path)['items'],$source);
 $w=['title'=>$rows[0][0],'intro'=>$rows[0][1],'body'=>$rows[0][1],'imageSelection'=>$choices[0]];
 studio_web_save($item['id'],$item['revision'],'generated',$w,$admin,$path);$item=studio_case_get($item['id'],$path);
 check(studio_images_issue($item)===null,'new draft selection binds to saved article context');
 $calls=0;$request=function($c,$method,$route,$payload)use(&$calls){$calls++;check(isset($payload['rr_news_image'])&&$payload['rr_news_image_confirmed']===true,'WP receives confirmed image contract');return ['id'=>900,'status'=>$payload['status'],'link'=>'https://www.radiorubben.no/synthetic/'];};
 reject(fn()=>studio_web_publish($item['id'],$item['revision'],'publish',$admin,$wp,$request,$path,fn()=>$catalog),'image suggestion cannot bypass human publication approval');
 check($calls===0,'no publish transport without approval');
 reject(fn()=>studio_images_change($item['id'],$item['revision'],[],['role'=>'observer'],$path),'observer cannot change images');
 reject(fn()=>studio_images_change($item['id'],$item['revision']-1,[], $admin,$path),'stale image edit rejected');
 $manual=['image_theme'=>'sport','image_reason'=>'Redaktøren velger nøytralt treningsmotiv til denne saken.','image_locked'=>'1'];
 studio_images_change($item['id'],$item['revision'],$manual,$admin,$path,fn()=>$catalog);$item=studio_case_get($item['id'],$path);
 $locked=$item['web']['imageSelection'];check($locked['locked']&&$locked['mode']==='manual','manual lock is saved');
 $r=studio_images_prepare($item,['news_images_enabled'=>true,'openai_model'=>'synthetic'],function(){throw new RuntimeException('must not call model');});
 check($r===$locked,'retry returns exact manual choice without model call');
 studio_web_save($item['id'],$item['revision'],'generated',$w,$admin,$path);$item=studio_case_get($item['id'],$path);
 check($item['web']['imageSelection']===$locked,'regeneration preserves manual choice even when another candidate is supplied');
 $review=['source'=>['url'=>$item['sourceUrl'],'text'=>str_repeat('Kontrollert syntetisk kildebelegg. ',8),'sha256'=>hash('sha256',str_repeat('Kontrollert syntetisk kildebelegg. ',8)),'fetchedAt'=>gmdate('c')],
  'policy'=>STUDIO_NEWS_POLICY,'status'=>'passed','checkedAt'=>gmdate('c'),'fingerprint'=>studio_news_fingerprint($item,studio_web_text($item['web']))];
 studio_web_save($item['id'],$item['revision'],'check',['check'=>$review],$admin,$path);$item=studio_case_get($item['id'],$path);
 studio_web_save($item['id'],$item['revision'],'approve',['confirmed'=>'1'],$admin,$path);$item=studio_case_get($item['id'],$path);
 check($item['web']['approvedHash']===studio_web_approval_hash($item)&&!empty($item['web']['imageSelection']['reviewed_at']),'same final approval binds image and text with actor/time');
 reject(fn()=>studio_web_publish($item['id'],$item['revision'],'publish',$admin,$wp,$request,$path,fn()=>$broken),'missing media blocks before delivery reservation');
 check(empty(studio_case_get($item['id'],$path)['web']['delivery']),'upload failure does not create unknown publication');
 $modified=$item;$modified['web']['body'].=' Ny hovedvinkling.';check(studio_images_issue($modified)!==null,'changed text requires image revalidation');
 $changed=$item;$changed['web']['imageSelection']['variants']['article_landscape']['media_id']++;
 check(studio_web_approval_hash($changed)!==$item['web']['approvedHash'],'changed media identity invalidates approval');
 studio_web_publish($item['id'],$item['revision'],'publish',$admin,$wp,$request,$path,fn()=>$catalog);check($calls===1,'valid human approval reaches WordPress once');
 $item=studio_case_get($item['id'],$path);reject(fn()=>studio_images_change($item['id'],$item['revision'],$manual,$admin,$path,fn()=>$catalog),'delivered article cannot be silently re-imaged');
 reject(fn()=>studio_images_change($item['id'],$item['revision'],['image_operation'=>'confirm_context','image_reason'=>'Samme bilde passer fortsatt den nye teksten.'],$admin,$path,fn()=>$catalog),'published image cannot be re-confirmed through draft-only action');
 // A verified WP draft may confirm the SAME image after a text edit; no new media or approval.
 studio_board_change(static function(array &$board):void{$board['items'][0]['web']['delivery']['status']='draft';$board['items'][0]['web']['body'].=' Redaksjonell presisering.';},$path);
 $item=studio_case_get($item['id'],$path);$variants=$item['web']['imageSelection']['variants'];
 check(studio_images_issue($item)!==null,'delivered draft with edited text needs image review');
 studio_images_change($item['id'],$item['revision'],['image_operation'=>'confirm_context','image_reason'=>'Samme bilde passer fortsatt den presiserte teksten.'],$admin,$path,fn()=>$catalog);
 $item=studio_case_get($item['id'],$path);
 check(studio_images_issue($item)===null&&$item['web']['imageSelection']['variants']===$variants&&$item['web']['imageSelection']['locked'],'draft review retains exact media and manual lock');
 check($item['web']['approvedHash']===null&&!isset($item['web']['imageSelection']['reviewed_at']),'draft review requires fresh final approval');
 // Reconnecting an interrupted upload must not silently bless edited article text.
 studio_board_change(static function(array &$board):void{unset($board['items'][0]['web']['delivery']);$board['items'][0]['web']['imageSelection']['status']='MANGLER_MEDIEFIL';$board['items'][0]['web']['body'].=' En ny vinkling.';},$path);
 $item=studio_case_get($item['id'],$path);$oldContext=$item['web']['imageSelection']['context_hash'];
 studio_images_change($item['id'],$item['revision'],['image_operation'=>'retry_upload'],$admin,$path,fn()=>$catalog);
 $item=studio_case_get($item['id'],$path);
 check($item['web']['imageSelection']['context_hash']===$oldContext&&studio_images_issue($item)!==null,'upload retry preserves stale context and still requires human review');
 $empty=['title'=>'Frisørsalong åpner','web'=>['title'=>'Frisørsalong åpner','intro'=>'Ny frisørsalong.','body'=>'En frisørsalong åpner.']];
 $config=['news_images_enabled'=>true,'openai_model'=>'synthetic'];
 $invalid=studio_images_prepare($empty,$config,fn()=>"```json\n{}\n```");
 check($invalid['status']==='BILDEVURDERING_FEILET','malformed model response still fails closed');
 $none=studio_images_prepare($empty,$config,fn()=>json_encode(['theme_id'=>null,'reason'=>'Ingen av motivene passer en frisørsalong.']));
 check($none['status']==='INGEN_PASSENDE_BILDE','no suitable image stays visible, not random fallback');
 $legacy=$empty;$legacy['web']['publication']=['featured_media'=>999];check(studio_images_prepare($legacy,$config)===null,'existing editorial profile preserved');
 $legacy=$empty;$legacy['web']['delivery']=['id'=>999,'status'=>'publish'];check(studio_images_prepare($legacy,$config)===null,'published articles excluded');
 check(studio_images_prepare($empty,['news_images_enabled'=>false])===null,'rollout flag defaults closed');
}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
echo "Semantic responses above are synthetic contract tests, not a live model-quality evaluation.\n";
