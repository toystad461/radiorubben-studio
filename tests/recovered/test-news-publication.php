<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/app/case-workflow.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "OK $label\n";}
function reject($f,$label){try{$f();}catch(InvalidArgumentException $e){echo "OK $label\n";return;}throw new RuntimeException($label);}
$dir=sys_get_temp_dir().'/news-web-'.bin2hex(random_bytes(4));mkdir($dir);$path=$dir.'/board.json';
$admin=['name'=>'Test editor','role'=>'admin'];$c=['username'=>'test','application_password'=>'mock','category_id'=>99];
$w=['title'=>'Et åpent møte','intro'=>'Kommunen inviterer til møte.','body'=>'Møtet arrangeres 8. oktober.'];
$calls=[];
$request=function($config,$method,$route,$payload)use(&$calls){
    $calls[]=[$route,$payload];
    return ['id'=>str_starts_with($route,'/posts/')?(int)substr($route,7):100+count($calls),'status'=>$payload['status'],'link'=>'https://www.radiorubben.no/test/'];
};
$new=function($origin,$url)use($admin,$path){
    $source=['id'=>$origin,'url'=>$url,'title'=>'Kildetittel','sourceName'=>'Kilde','publishedAt'=>gmdate('c')];
    studio_board_add_source($source,$admin,$path);
    return studio_case_source_item(studio_board_active(studio_board_read($path)),$source);
};
$save=function($item,$data)use($admin,$path){studio_web_save($item['id'],$item['revision'],'save',$data,$admin,$path);return studio_case_get($item['id'],$path);};
$approve=function($item)use($admin,$path){
    $review=['source'=>['url'=>$item['sourceUrl'],'text'=>str_repeat('Et kontrollert kildebelegg. ',8),'sha256'=>hash('sha256',str_repeat('Et kontrollert kildebelegg. ',8)),'fetchedAt'=>gmdate('c')],'policy'=>STUDIO_NEWS_POLICY,'status'=>'passed','checkedAt'=>gmdate('c'),'fingerprint'=>studio_news_fingerprint($item,studio_web_text($item['web']))];
    studio_web_save($item['id'],$item['revision'],'check',['check'=>$review],$admin,$path);
    $item=studio_case_get($item['id'],$path);
    studio_web_save($item['id'],$item['revision'],'approve',['confirmed'=>'1'],$admin,$path);
    return studio_case_get($item['id'],$path);
};
$publish=function($item,$status='publish')use($admin,$path,$c,$request){studio_web_publish($item['id'],$item['revision'],$status,$admin,$c,$request,$path);return studio_case_get($item['id'],$path);};
try {
    $local=$save($new('municipality','https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/test.123.aspx'),$w+['featured_media'=>999,'categories'=>[16],'publication'=>['featured_media'=>999]]);
    check($local['web']['publication']['featured_media']===1079,'new news uses the verified image; posted image IDs ignored');
    check($local['web']['publication']['categories']===[8,27],'municipality gets news and local news');
    check($calls===[],'preparing and saving make no WordPress call');
    reject(fn()=>$publish($local),'source check and manual approval required');
    $local=$publish($local,'draft');
    check($calls[0][1]['featured_media']===1079 && $calls[0][1]['categories']===[8,27],'initial WordPress draft receives news metadata, not generic category override');
    check($calls[0][1]['status']==='draft' && str_contains($calls[0][1]['content'],'<a href="https://www.bomlo.kommune.no/'),'draft keeps original source');
    check(studio_web_status_label($local)==='WordPress-kladd lagret','confirmed draft status');
    $local=$publish($local,'draft');check(count($calls)===1,'draft retry reuses delivery');
    $local=$approve($local);$local=$publish($local);
    check($calls[1][0]==='/posts/101','approval publishes the existing draft');
    check(!isset($calls[1][1]['featured_media'])&&!isset($calls[1][1]['categories']),'publishing preserves WordPress image and category choices');
    check(studio_web_status_label($local)==='Publisert på radiorubben.no','published story is not labelled waiting for approval');
    $local=$publish($local);check(count($calls)===2,'repeated publication does not send again');
    reject(fn()=>$publish($local,'draft'),'published story cannot be reverted to draft');
    $local=$save($local,array_replace($w,['body'=>'Ny redaksjonell tekst.','news_scope'=>'news']));
    check($local['web']['publication']['categories']===[8,27],'saved delivery presentation is stable on editor updates');
    check(studio_web_status_label($local)==='Trenger kontroll / rettelser','changed published text needs review');
    reject(fn()=>$publish($local),'editing clears approval');

    $nrk=$save($new('nrk-old','https://www.nrk.no/vestland/test-1.12345678'),$w);
    check($nrk['web']['publication']['categories']===[8],'regional source is not automatically local to Bømlo');
    $before=studio_board_read($path);
    $newSource=['id'=>'nrk-new-feed','url'=>'https://www.nrk.no/vestland/ny-tittel-1.12345678?utm_source=rss','title'=>'Endret tittel'];
    studio_board_add_source($newSource,$admin,$path);
    $same=studio_case_source_item(studio_board_active(studio_board_read($path)),$newSource);
    check($same['id']===$nrk['id'] && $same['revision']===$nrk['revision'] && $same['web']===$nrk['web'],'open source reuses edited story across NRK feed IDs and URL changes');
    check(count(studio_board_read($path)['items'])===count($before['items']),'no duplicate board item');
    reject(fn()=>studio_case_source_item([$nrk],['id'=>'different','url'=>'https://www.nrk.no/vestland/test-1.98765432']),'different story cannot match by title');
    $nrk=$approve($nrk);$nrk=$save($nrk,$w+['news_scope'=>'local']);
    check($nrk['web']['publication']['categories']===[8,27] && $nrk['web']['approvedHash']===null,'editor can select local, requiring new approval');
    reject(fn()=>$save($nrk,$w+['news_scope'=>['local']]),'non-string category rejected');
    reject(fn()=>$save($nrk,$w+['news_scope'=>'sport']),'arbitrary category rejected');
    $nrk=$approve($nrk);
    studio_board_change(function(&$b)use($nrk){foreach($b['items']as&$i)if($i['id']===$nrk['id'])$i['sourceName']='Annen kilde';},$path);
    reject(fn()=>$publish(studio_case_get($nrk['id'],$path)),'changed attribution invalidates approval');
    $nrk=$approve(studio_case_get($nrk['id'],$path));
    studio_board_change(function(&$b)use($nrk){foreach($b['items']as&$i)if($i['id']===$nrk['id'])$i['web']['publication']['featured_media']=999;},$path);
    reject(fn()=>$publish(studio_case_get($nrk['id'],$path)),'changed image cannot reuse an approval');
    $nrk=$save(studio_case_get($nrk['id'],$path),$w);$nrk=$approve($nrk);
    studio_board_change(function(&$b)use($nrk){foreach($b['items']as&$i)if($i['id']===$nrk['id'])$i['web']['check']['checkedAt']=gmdate('c',time()-3601);},$path);
    reject(fn()=>$publish(studio_case_get($nrk['id'],$path)),'expired source check blocks publishing');
    $nrk=$approve(studio_case_get($nrk['id'],$path));
    reject(fn()=>studio_web_publish($nrk['id'],$nrk['revision'],'publish',['role'=>'presenter'],$c,$request,$path),'presenter cannot publish a checked article');
    reject(fn()=>studio_web_publish($nrk['id'],$nrk['revision']-1,'publish',$admin,$c,$request,$path),'stale revision cannot publish');

    // Pre-feature drafts need a deliberate save; older WordPress posts keep their metadata.
    $legacy=$save($new('legacy','https://www.nrk.no/vestland/legacy-1.33333333'),$w);
    studio_board_change(function(&$b)use($legacy){foreach($b['items']as&$i)if($i['id']===$legacy['id'])unset($i['web']['publication']);},$path);
    $legacy=studio_case_get($legacy['id'],$path);
    reject(fn()=>$publish($legacy,'draft'),'legacy unsent news cannot silently skip image and categories');
    studio_board_change(function(&$b)use($legacy){foreach($b['items']as&$i)if($i['id']===$legacy['id'])$i['web']['delivery']=['id'=>777,'status'=>'draft','state'=>'confirmed'];},$path);
    $legacy=$approve(studio_case_get($legacy['id'],$path));$legacy=$publish($legacy);
    $last=end($calls);check($last[0]==='/posts/777'&&!isset($last[1]['featured_media'])&&!isset($last[1]['categories']),'legacy WordPress image/categories are not replaced');
    check($legacy['script']==='','radio script preserved');
    check(!studio_web_is_news(['originId'=>'football','sourceUrl'=>'https://www.fotball.no/test']),'other sources are outside the news profile');
    check(!studio_web_is_news(['originId'=>'fake','sourceUrl'=>'https://www.nrk.no.evil.test/vestland/test-1.123']),'lookalike source host rejected');
} finally {foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
