<?php
// Test VPS only. Keep rr-test-isolation.php unchanged.
if (wp_get_environment_type() !== 'staging' || home_url('/') !== 'https://5.249.252.78/wordpress/') return;
function rr_e2e_wp_local(): bool {return PHP_SAPI==='cli'||($_SERVER['REMOTE_ADDR']??'')==='127.0.0.1';}
add_filter('wp_is_application_passwords_available',fn($v)=>rr_e2e_wp_local(),100);
add_filter('wp_is_application_passwords_available_for_user',fn($v,$u)=>rr_e2e_wp_local()&&$u->user_login==='rr-red-e2e-001',100,2);
add_filter('rest_pre_dispatch',function($result,$server,$request){
    if(wp_get_current_user()->user_login!=='rr-red-e2e-001')return $result;
    $deny=fn()=>new WP_Error('rr_e2e_scope','Kun syntetisk testsak er tillatt.',['status'=>403]);
    if(!rr_e2e_wp_local()||!preg_match('~^/wp/v2/posts(?:/([1-9][0-9]*))?$~D',$request->get_route(),$m)||!in_array($request->get_method(),['GET','POST'],true))return $deny();
    if(isset($m[1])){$p=get_post((int)$m[1]);if(!$p||(int)$p->post_author!==get_current_user_id())return $deny();}
    elseif($request->get_method()==='GET')$request->set_param('author',get_current_user_id());
    if($request->get_method()==='POST'){
        if(!str_starts_with((string)$request['title'],'TEST – ')||!str_contains((string)$request['content'],'https://5.249.252.78/e2e-source.php')||!in_array($request['status'],['draft','publish'],true)||isset($request['author'])||isset($request['featured_media']))return $deny();
        if(!isset($m[1])&&!preg_match('/^studio-[a-f0-9]{16}$/D',(string)$request['slug']))return $deny();
    }
    return $result;
},10,3);
