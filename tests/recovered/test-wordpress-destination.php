<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/wordpress-status.php';
require dirname(__DIR__,2).'/app/newsroom-wordpress.php';
require dirname(__DIR__,2).'/app/newsroom-view.php';
require_once dirname(__DIR__,2).'/app/news-images.php';
function destination_ok(bool $ok,string $label):void { if(!$ok)throw new RuntimeException($label);echo "PASS WP destination: $label\n"; }
function destination_reject(callable $fn,string $label):void {
    try{$fn();}catch(InvalidArgumentException|RuntimeException){destination_ok(true,$label);return;}
    throw new RuntimeException($label);
}
$previous=getenv('STUDIO_WORDPRESS_ENVIRONMENT');
$production=['username'=>'synthetic','application_password'=>'synthetic'];
$test=$production+['environment'=>'test','site_url'=>'https://5.249.252.78/wordpress'];
$readRoutes=['/wp/v2/posts?context=edit','/wp/v2/posts/123?context=edit','/wp/v2/users/me?context=edit',
    '/rr-fotballrobot/v1/newsroom','/rr-news-images/v1/catalog'];
try{
    putenv('STUDIO_WORDPRESS_ENVIRONMENT=production');
    destination_ok(studio_wp_ready($production),'legacy production credentials remain compatible');
    foreach($readRoutes as$route)destination_ok(studio_wp_endpoint($production,'GET',$route)==='https://www.radiorubben.no/wp-json'.$route,'production GET destination');
    destination_ok(studio_wp_endpoint($production,'POST','/wp/v2/posts/123')==='https://www.radiorubben.no/wp-json/wp/v2/posts/123','production write destination unchanged');
    destination_ok(!studio_wp_ready($test),'test configuration cannot be used in production');
    destination_ok(studio_wp_link_valid('https://radiorubben.no/story/'),'existing canonical alias accepted');
    destination_ok(!studio_wp_link_valid('https://5.249.252.78/wordpress/story/'),'production rejects a test receipt');
    putenv('STUDIO_WORDPRESS_ENVIRONMENT=test');
    destination_ok(studio_wp_ready($test),'test requires matching explicit profile');
    foreach([$production,$production+['environment'=>'test'],$production+['site_url'=>$test['site_url']],array_replace($test,['site_url'=>'https://www.radiorubben.no'])]as$bad){
        destination_ok(!studio_wp_ready($bad),'test has no production fallback');
        destination_reject(fn()=>studio_wp_status_request($bad,'GET','/posts?context=edit'),'wrong destination fails before status transport');
        destination_reject(fn()=>studio_newsroom_wp('GET',null,$bad),'wrong destination fails before newsroom transport');
        destination_reject(fn()=>studio_images_catalog($bad),'wrong destination fails before catalog transport');
    }
    foreach($readRoutes as$route)destination_ok(studio_wp_endpoint($test,'GET',$route)===$test['site_url'].'/wp-json'.$route,'test GET stays below /wordpress');
    foreach(['http://5.249.252.78/wordpress','https://5.249.252.78','https://5.249.252.78:443/wordpress',
        'https://5.249.252.78/wordpress/','https://5.249.252.78.evil.invalid/wordpress','https://user@5.249.252.78/wordpress',
        'https://5.249.252.78/wordpress?next=production','https://5.249.252.78/wordpress#other']as$site){
        destination_reject(fn()=>studio_wp_endpoint(array_replace($test,['site_url'=>$site]),'GET',$readRoutes[0]),'unapproved destination rejected');
    }
    foreach(['/wp/v2/posts/../users','//evil.invalid/wp/v2/posts','https://evil.invalid/wp/v2/posts','/wp/v2/posts#fragment',
        '/wp/v2/posts?x=1\r\nInjected: yes','/wp/v2/posts/0','/wp/v2/posts%2f..','/wp/v2/posts\\other','/wp/v2/plugins',
        '/wp/v2/posts?rest_route=/wp/v2/users','/wp/v2/posts?_method=POST']as$route){
        destination_reject(fn()=>studio_wp_endpoint($test,'GET',$route),'unexpected API route rejected');
    }
    destination_reject(fn()=>studio_wp_request($test,'POST','/posts',['title'=>'Synthetic']),'test draft transport blocked');
    destination_reject(fn()=>studio_newsroom_wp('POST',['action'=>'approve'],$test),'test newsroom mutation blocked');
    $calls=0;
    $request=function()use(&$calls){$calls++;return [];};
    foreach(['draft','publish']as$status)destination_reject(fn()=>studio_web_publish('0123456789abcdef',1,$status,['role'=>'admin'],$test,$request,'/must-not-be-opened',$request),'test write blocked before board/image/transport');
    destination_ok($calls===0,'blocked test writes perform no callbacks');
    destination_ok(studio_wp_link_valid($test['site_url'].'/?p=123'),'query-style WordPress post accepted inside test prefix');
    foreach(['https://www.radiorubben.no/story/','https://5.249.252.78/story/','https://5.249.252.78/wordpress-other/story/',
        'https://user@5.249.252.78/wordpress/story/','https://5.249.252.78:443/wordpress/story/',
        'https://5.249.252.78/wordpress/../story/','https://5.249.252.78/wordpress/%2e%2e/story/',
        'https://5.249.252.78/wordpress/%252e%252e/story/','https://5.249.252.78/wordpress/%5cstory/',
        'http://5.249.252.78/wordpress/story/']as$url)destination_ok(!studio_wp_link_valid($url),'cross-environment or ambiguous receipt rejected');
    $media=$test['site_url'].'/wp-content/uploads/synthetic/news.png';
    destination_ok(rrni_url_valid($media)&&studio_newsroom_image_url($media),'test image uses the same target and uploads prefix');
    foreach(['https://www.radiorubben.no/wp-content/uploads/test.png',$media.'?token=other',$test['site_url'].'/wp-admin/test.png']as$url){
        destination_ok(!rrni_url_valid($url)&&!studio_newsroom_image_url($url),'unapproved media rejected');
    }
    $receipt=['web'=>[]];$receipt['web']['delivery']=['state'=>'confirmed','status'=>'publish','hash'=>studio_web_approval_hash($receipt)];
    destination_ok(studio_web_status_label($receipt)==='Publisert i WordPress-test','test receipt never claims production publication');
    $path=tempnam(sys_get_temp_dir(),'rr-destination-');
    try{
        $item=['id'=>'0123456789abcdef','status'=>'draft','revision'=>1,'sourceName'=>'Syntetisk kilde','sourceUrl'=>'https://example.test/source',
            'web'=>['title'=>'Syntetisk kamp','intro'=>'Bare testdata.','body'=>'Manuelt bevart testtekst.']];
        $item['web']['approvedHash']=studio_web_approval_hash($item);
        $item['web']['delivery']=['state'=>'unknown','requestedStatus'=>'draft','hash'=>studio_web_approval_hash($item)];
        $bytes=json_encode(['items'=>[$item]]);file_put_contents($path,$bytes);
        $remote=['id'=>123,'slug'=>'studio-'.$item['id'],'status'=>'draft','link'=>$test['site_url'].'/?p=123',
            'title'=>['raw'=>$item['web']['title']],'excerpt'=>['raw'=>$item['web']['intro']],'content'=>['raw'=>studio_web_html($item)]];
        $admin=['role'=>'admin','name'=>'Syntetisk redaktør'];
        destination_reject(fn()=>studio_web_status_check($item['id'],1,$admin,$test,$path,fn()=>[array_replace($remote,['link'=>'https://www.radiorubben.no/test/'])]),'production response cannot confirm test receipt');
        destination_ok(file_get_contents($path)===$bytes,'rejected cross-environment response leaves board untouched');
        $result=studio_web_status_check($item['id'],1,$admin,$test,$path,function($c,$method,$route)use($test,$remote){
            destination_ok(studio_wp_endpoint($c,$method,'/wp/v2'.$route)===$test['site_url'].'/wp-json/wp/v2'.$route,'real reconciliation selects test GET endpoint');
            return [$remote];
        });
        $saved=studio_case_get($item['id'],$path);
        destination_ok($result['state']==='matched'&&$saved['web']['delivery']['state']==='confirmed','matching test response reconciles existing draft');
        destination_ok($saved['web']['body']===$item['web']['body']&&$saved['web']['approvedHash']===$item['web']['approvedHash'],'read-only reconciliation preserves manual text and human approval');
    }finally{unlink($path);if(is_file($path.'.lock'))unlink($path.'.lock');}
    foreach(['','staging','TEST']as$env){putenv('STUDIO_WORDPRESS_ENVIRONMENT='.$env);destination_ok(!studio_wp_ready($production)&&!studio_wp_ready($test),'unknown runtime profile fails closed');}
}finally{putenv($previous===false?'STUDIO_WORDPRESS_ENVIRONMENT':'STUDIO_WORDPRESS_ENVIRONMENT='.$previous);}
