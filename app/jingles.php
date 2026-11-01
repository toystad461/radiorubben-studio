<?php
declare(strict_types=1);
require_once __DIR__.'/music-studio.php';
const JINGLE_CATEGORIES=['station'=>'Stasjonsjingle','news'=>'Nyhetsjingle','season'=>'Sesongjingle'];

function jingle_edit(array $input,?array $user,string $csrf,string $expected,?string $root=null):array {
    if(!studio_can($user,'produce') || $expected==='' || !hash_equals($expected,$csrf))throw new InvalidArgumentException('Handlingen er ikke autorisert.');
    $name=$input['name']??null;$category=$input['category']??null;
    if(!is_string($name)||trim($name)===''||mb_strlen($name)>160||preg_match('/[\x00-\x1f]/u',$name)||!is_string($category)||!isset(JINGLE_CATEGORIES[$category])||!is_bool($input['disabled']??null)||!is_int($input['revision']??null))throw new InvalidArgumentException('Kontroller navn og kategori.');
    $root??=music_library_root();music_library_directory($root,false);
    if(is_link($root.'/library.lock'))throw new RuntimeException('Ugyldig biblioteklås.');
    $lock=fopen($root.'/library.lock','rb');if(!$lock)throw new RuntimeException('Biblioteket kunne ikke låses.');
    try {
        if(!flock($lock,LOCK_EX))throw new RuntimeException('Biblioteket er opptatt.');
        $index=music_library_index($root);$at=array_search($input['id']??null,array_column($index['tracks'],'id'),true);
        if($at===false)throw new InvalidArgumentException('Jinglen finnes ikke.');
        $t=$index['tracks'][$at];
        if(($t['kind']??'music')!=='jingle')throw new InvalidArgumentException('Dette er ikke en jingle.');
        if($t['sha256']!==($input['sha256']??null)||($t['revision']??0)!==$input['revision'])throw new DomainException('Jinglen er endret. Oppdater siden først.');
        $t['jingle']=['name'=>trim($name),'category'=>$category,'disabled'=>$input['disabled'],'updatedAt'=>gmdate('c'),'updatedBy'=>(string)($user['id']??$user['role'])];
        $t['revision']=($t['revision']??0)+1;$index['tracks'][$at]=$t;
        music_playout_atomic($root.'/library.json',$index,0600);return $t;
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
