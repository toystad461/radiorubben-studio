<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/crm.php';
function search_check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$payload=['page'=>['totalElements'=>12],'_embedded'=>['enheter'=>[
 ['organisasjonsnummer'=>'999999999','navn'=>'Fiktiv & Test AS','forretningsadresse'=>['poststed'=>'TESTBY']],
 ['organisasjonsnummer'=>'974760673','navn'=>'Avviklet eksempel','underAvvikling'=>true]
]]];
$hits=studio_crm_brreg_search('Fiktiv & Test',static function($url)use($payload){
 search_check(str_contains($url,'navn=Fiktiv+%26+Test')&&str_contains($url,'size=10')&&str_contains($url,'navnMetodeForSoek=FORTLOEPENDE'),'Name encoded and search bounded');
 return [200,json_encode($payload)];
});
search_check(count($hits['results'])===1 && $hits['more'],'Inactive businesses excluded, more results indicated');
search_check(studio_crm_brreg_search('Ukjent',fn()=>[200,'{"page":{"totalElements":0}}'])['results']===[],'Empty search');
foreach(['A',str_repeat('x',181),"Bad\nName"] as $name) {
 try {studio_crm_brreg_search($name,fn()=>throw new RuntimeException('Network must not run'));throw new RuntimeException('Accepted invalid input');}
 catch(InvalidArgumentException $e){}
}
foreach([[503,'{}'],[200,'{'],[200,'{}'],[200,str_repeat('x',200001)]] as $reply) {
 try {studio_crm_brreg_search('Test',fn()=>$reply);throw new LogicException('Accepted invalid response');}
 catch(RuntimeException|JsonException $e){}
}
$records=[
 ['id'=>'aaaaaaaaaaaaaaaa','company'=>'Tidligere navn','orgNumber'=>'999999999','stage'=>'archived'],
 ['id'=>'bbbbbbbbbbbbbbbb','company'=>'Fiktiv & Test AS','stage'=>'candidate'],
];
$matches=studio_crm_matches($records,$hits['results'][0]);
search_check(count($matches)===2,'Matches organisation and legacy name, including archive');
$results=studio_crm_search_results($records,'Tidligere',$hits['results']);
search_check(str_contains($results[0]['label'],'Arkivert') && str_contains($results[1]['label'],'Allerede registrert'),'Existing and archived cards labelled');
$single=[['id'=>'cccccccccccccccc','company'=>'Fiktiv & Test AS','orgNumber'=>'999999999','businessAddress'=>'Testvegen 1, Bømlo','stage'=>'active']];
$merged=studio_crm_search_results($single,'Fiktiv',$hits['results']);
search_check(count($merged)===1 && $merged[0]['value']==='card:cccccccccccccccc','Registered remote match opens existing card without duplicate result');
search_check(str_contains($merged[0]['label'],'999999999') && str_contains($merged[0]['label'],'Bømlo'),'Local hit retains organisation and location');
$conflicting=$single;$conflicting[0]['orgNumber']='974760673';
$conflict=studio_crm_search_results($conflicting,'Fiktiv',$hits['results']);
search_check($conflict[1]['value']==='org:999999999','Conflicting organisation keeps existing registration conflict check');
$new=studio_crm_search_results([],'Fiktiv',$hits['results']);
search_check($new[0]['value']==='org:999999999','New company uses existing registration selector');
$path=sys_get_temp_dir().'/crm-search-'.bin2hex(random_bytes(5)).'.json';$admin=['role'=>'admin','name'=>'Test'];
try {
 $fields=['company'=>'Fiktiv & Test AS','orgNumber'=>'999999999','stage'=>'candidate','priority'=>'2','contact'=>'Kari','nextStep'=>'Ring'];
 $id=studio_crm_apply('create',$fields,$admin,$path);
 try {studio_crm_apply('create',$fields,$admin,$path);throw new LogicException('Duplicate accepted');}catch(InvalidArgumentException $e){}
 $saved=studio_crm_read($path)['records'][0];
 studio_crm_apply('save',array_replace($saved,['company'=>'Nytt registrert navn','businessAddress'=>'Testvegen 2']),$admin,$path);
 $saved=studio_crm_read($path)['records'][0];
 search_check($saved['id']===$id && $saved['contact']==='Kari' && $saved['nextStep']==='Ring' && count($saved['history'])===2,'Update retains card identity, contact and history');
 try {studio_crm_apply('save',array_replace($saved,['revision'=>1]),$admin,$path);throw new LogicException('Stale update accepted');}catch(InvalidArgumentException $e){}
 search_check(count(studio_crm_read($path)['records'])===1,'Concurrent duplicate guard remains enforced');
} finally {foreach([$path,$path.'.lock'] as $file)if(is_file($file))unlink($file);}
echo "CRM company search OK\n";
