<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-worker.php';
function music_library_effective_fields(array $track):array {
    $fields=music_library_metadata($track)['fields']??[];
    foreach(['title','artist','album','genre','year']as$key){$v=$track['manualOverrides'][$key]??null;if(is_string($v)&&trim($v)!==''&&mb_strlen($v)<=240)$fields[$key]=$v;}return $fields;
}
function music_library_edit(string $id,string $sha,int $revision,array $input,?array $user,string $csrf,string $expected,?string $root=null):array {
    if(!music_library_can_upload($user)||$expected===''||!hash_equals($expected,$csrf))throw new InvalidArgumentException('Redigeringen er ikke autorisert.');
    $overrides=[];foreach(['title','artist','album','genre','year']as$key){$v=$input[$key]??'';if(!is_string($v)||!preg_match('//u',$v)||mb_strlen($v)>240||preg_match('/[\x00-\x1f\x7f]/',$v))throw new InvalidArgumentException('Ugyldig eller for langt tekstfelt.');$v=trim($v);if($v!=='')$overrides[$key]=$v;}
    $raw=$input['tags']??'';if(!is_string($raw)||strlen($raw)>5000||!preg_match('//u',$raw))throw new InvalidArgumentException('Ugyldige merkelapper.');$tags=[];foreach(preg_split('/[,\r\n]+/u',$raw)?:[]as$v){$v=trim($v);if($v==='')continue;if(mb_strlen($v)>80||preg_match('/[\x00-\x1f\x7f]/',$v))throw new InvalidArgumentException('En merkelapp kan ha maksimalt 80 tegn.');$tags[]=$v;}$tags=array_values(array_unique($tags));if(count($tags)>20)throw new InvalidArgumentException('Bruk maksimalt 20 egne merkelapper.');
    $root??=music_library_root();$track=null;foreach(music_library_read($root)as$t)if($t['id']===$id)$track=$t;if(!$track||$track['sha256']!==$sha||($track['revision']??0)!==$revision)throw new InvalidArgumentException('Låten er endret. Last siden på nytt.');
    $previous=is_array($track['manualOverrides']??null)?$track['manualOverrides']:[];foreach(['title','artist','album','genre','year']as$key)unset($previous[$key]);
    return music_worker_commit($root,$track,['manualOverrides'=>array_replace($previous,$overrides),'tags'=>$tags,'editedAt'=>gmdate('c'),'editedBy'=>(string)($user['id']??$user['role'])]);
}
