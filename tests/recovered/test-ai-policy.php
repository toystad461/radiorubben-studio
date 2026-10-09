<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/case-workflow.php';
function policy_check(bool $ok,string $why): void {if(!$ok)throw new RuntimeException($why);echo "PASS $why\n";}
function policy_reject(callable $fn,string $why): void {
    try {$fn();} catch (InvalidArgumentException $e) {policy_check(true,$why);return;}
    throw new RuntimeException($why);
}
$dir=sys_get_temp_dir().'/rr-ai-policy-'.bin2hex(random_bytes(5));mkdir($dir,0700);$path=$dir.'/board.json';
$admin=['role'=>'admin','name'=>'Fixture editor'];$config=['username'=>'fixture','application_password'=>'fixture'];
$calls=0;$payload=null;
$request=static function($c,$method,$route,$body)use(&$calls,&$payload):array {
    $calls++;$payload=$body;return ['id'=>321,'status'=>$body['status'],'link'=>'https://www.radiorubben.no/fixture/'];
};
try {
    studio_board_add_source(['id'=>'policy-source','title'=>'Møte','sourceName'=>'Bømlo kommune',
        'url'=>'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/test.123.aspx','publishedAt'=>gmdate('c')],$admin,$path);
    $i=studio_board_active(studio_board_read($path))[0];$id=$i['id'];
    $text=str_repeat('Kommunen inviterer til et åpent møte på biblioteket. ',5);
    $source=['url'=>$i['sourceUrl'],'text'=>$text,'sha256'=>hash('sha256',$text),'fetchedAt'=>gmdate('c'),'kind'=>'original_article'];
    $generation=rr_generation_record($source,['openai_model'=>'fixture-model'],[],'web');
    policy_check($generation['aiPolicyVersion']==='1.0.0'&&$generation['aiAssisted']===true,'policy version and AI provenance recorded');
    $w=['title'=>'Åpent møte','intro'=>'Kommunen inviterer.','body'=>'Møtet holdes på biblioteket.','generation'=>$generation];
    studio_web_save($id,$i['revision'],'generated',$w,$admin,$path);
    $i=studio_case_get($id,$path);
    $review=['source'=>$source,'policy'=>STUDIO_NEWS_POLICY,'status'=>'passed','checkedAt'=>gmdate('c'),
        'fingerprint'=>studio_news_fingerprint($i,studio_web_text($i['web']))];
    studio_web_save($id,$i['revision'],'check',['check'=>$review],$admin,$path);
    $i=studio_case_get($id,$path);
    policy_reject(fn()=>studio_web_publish($id,$i['revision'],'publish',$admin,$config,$request,$path),'passed AI check alone cannot publish');
    policy_check($calls===0,'no transport before human approval');
    studio_web_save($id,$i['revision'],'approve',['confirmed'=>'1'],$admin,$path);
    $approved=file_get_contents($path);$i=studio_case_get($id,$path);
    foreach(['observer','presenter','producer'] as $role)
        policy_reject(fn()=>studio_web_publish($id,$i['revision'],'publish',['role'=>$role],$config,$request,$path),'role '.$role.' cannot publish');
    $mutations=[
        'source URL'=>static function(&$i){$i['sourceUrl']='https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/other.456.aspx';},
        'source snapshot'=>static function(&$i){$i['web']['generation']['sourceSha256']=str_repeat('0',64);},
        'source timestamp'=>static function(&$i){$i['web']['generation']['sourceFetchedAt']='2026-01-01T00:00:00Z';},
        'model'=>static function(&$i){$i['web']['generation']['model']='changed';},
        'policy version'=>static function(&$i){$i['web']['generation']['aiPolicyVersion']='0.0.0';},
        'missing policy'=>static function(&$i){unset($i['web']['generation']['aiPolicyVersion']);},
        'AI disclosure'=>static function(&$i){$i['web']['generation']['aiAssisted']=false;},
        'text'=>static function(&$i){$i['web']['body'].=' Ny opplysning.';},
        'expired review'=>static function(&$i){$i['web']['check']['checkedAt']=gmdate('c',time()-3601);},
    ];
    foreach($mutations as $label=>$mutate){
        $board=json_decode($approved,true,512,JSON_THROW_ON_ERROR);$mutate($board['items'][0]);
        file_put_contents($path,json_encode($board,JSON_THROW_ON_ERROR));
        policy_reject(fn()=>studio_web_publish($id,$i['revision'],'publish',$admin,$config,$request,$path),'changed '.$label.' blocks delivery');
        policy_check($calls===0,'no transport for changed '.$label);
    }
    file_put_contents($path,$approved);
    studio_web_publish($id,$i['revision'],'publish',$admin,$config,$request,$path);
    policy_check($calls===1&&$payload['status']==='publish','valid human-approved revision reaches mocked transport once');
    policy_check(str_contains($payload['content'],'KI-støtte')&&str_contains($payload['content'],$source['url']),'actual outgoing payload retains visible AI disclosure and original source');
    policy_check(studio_case_get($id,$path)['web']['generation']===$generation,'delivery preserves versioned provenance');
} finally {foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
