<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/music-library-tags.php';
function check(bool $v):void{if(!$v)throw new RuntimeException('Tag assertion failed');}
$hash=str_repeat('a',64);$m=['sourceSha256'=>$hash,'fields'=>['genre'=>'Pop; Dance','year'=>'2016','title'=>'Test','artist'=>'Artist']];
$a=music_library_file_tags($m);check(array_column($a['tags'],'label')===['Pop','Dance','2010-tallet']);check(!$a['reviewRequired']);
$t=['sha256'=>$hash,'tags'=>['Manuelt program','Pop'],'manualOverrides'=>['title'=>'Min tittel'],'autoTags'=>$a];
check(music_library_tag_labels($t)===['Manuelt program','Pop','Dance','2010-tallet']);check($t['manualOverrides']['title']==='Min tittel');
$t['sha256']=str_repeat('b',64);check(music_library_tag_labels($t)===['Manuelt program','Pop']);
foreach(['ukjent','1800','9999','2016 eller 2017']as$year){$m['fields']['year']=$year;check(count(music_library_file_tags($m)['tags'])===2);}
$m['fields']=['genre'=>str_repeat('x',81),'year'=>null];$a=music_library_file_tags($m);check($a['tags']===[]&&$a['reviewRequired']);check(in_array('onlineIdentification',$a['pending'],true));
check(music_library_tag_labels(['sha256'=>$hash,'tags'=>'invalid'])===[]);
echo "PASS source-bound automatic tags, unknown years, review status and preserved manual labels\n";
