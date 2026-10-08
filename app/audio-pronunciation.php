<?php
declare(strict_types=1);
require_once __DIR__.'/board.php';
function rr_pronunciation_seed():array {return ['version'=>1,'entries'=>array_map(static fn($name)=>['id'=>hash('sha256',$name),'term'=>$name,'alias'=>'','status'=>'unverified','revision'=>1,'history'=>[]],['Rubbestadneset','Mosterhamn','Svortland','Bremnes'])];}
function rr_pronunciation_context(array $board):array {
    $d=$board['pronunciation']??rr_pronunciation_seed();$entries=[];
    foreach($d['entries'] as $e)if($e['status']==='approved')$entries[]=['id'=>$e['id'],'term'=>$e['term'],'alias'=>$e['alias'],'revision'=>$e['revision']];
    return ['version'=>$d['version'],'entries'=>$entries];
}
function rr_pronunciation_text(string $script,array $dictionary):string {
    $map=[];foreach($dictionary['entries'] as $e)$map[$e['term']]=$e['alias'];
    if(!$map)return $script;
    uksort($map,static fn($a,$b)=>strlen($b)<=>strlen($a));
    $pattern='/(?<![\p{L}\p{N}])(?:'.implode('|',array_map(static fn($s)=>preg_quote($s,'/'),array_keys($map))).')(?![\p{L}\p{N}])/u';
    return preg_replace_callback($pattern,static fn($m)=>$map[$m[0]],$script)??throw new RuntimeException('Uttalereglene kunne ikke brukes.');
}
function rr_pronunciation_change(string $action,array $input,array $user,?string $path=null):void {
    if(!in_array($user['role']??'',['admin','producer','presenter'],true)||($action!=='propose'&&($user['role']??'')!=='admin'))throw new InvalidArgumentException('Ingen tilgang til uttaleendringen.');
    studio_board_change(static function(&$b)use($action,$input,$user){
        $b['pronunciation']??=rr_pronunciation_seed();$d=&$b['pronunciation'];
        if($action==='propose'){
            $term=trim((string)($input['term']??''));$alias=trim((string)($input['alias']??''));
            foreach([$term,$alias]as$s)if(mb_strlen($s)<2||mb_strlen($s)>100||!preg_match('/^[\p{L}\p{M} .’\x27-]+$/uD',$s))throw new InvalidArgumentException('Bruk et navn og en uttalemåte på 2–100 tegn, uten tall eller markering.');
            if(count($d['entries'])>=100)throw new InvalidArgumentException('Uttaleregisteret er fullt.');
            foreach($d['entries']as$e)if($e['term']===$term&&$e['alias']===$alias&&in_array($e['status'],['pending','approved'],true))throw new InvalidArgumentException('Uttalen finnes allerede.');
            $d['entries'][]=['id'=>bin2hex(random_bytes(16)),'term'=>$term,'alias'=>$alias,'status'=>'pending','revision'=>1,'createdBy'=>$user['name']??'Medarbeider','history'=>[['action'=>'propose','at'=>gmdate('c'),'actor'=>$user['name']??'Medarbeider']]];return;
        }
        foreach($d['entries']as&$e)if($e['id']===($input['id']??'')){
            if($e['revision']!==(int)($input['revision']??0))throw new InvalidArgumentException('Uttalen ble endret. Last siden på nytt.');
            $next=match($action){'approve'=>$e['status']==='pending'&&($input['confirmed']??'')==='1'?'approved':null,'disable'=>$e['status']==='approved'?'disabled':null,'reject'=>$e['status']==='pending'?'rejected':null,default=>null};
            if(!$next)throw new InvalidArgumentException('Bekreft en gyldig uttaleendring.');
            if($next==='approved')foreach($d['entries']as$other)if($other['id']!==$e['id']&&$other['term']===$e['term']&&$other['status']==='approved')throw new InvalidArgumentException('Deaktiver den gamle uttalen av navnet først.');
            $e['history'][]=['action'=>$action,'at'=>gmdate('c'),'actor'=>$user['name']??'Administrator','previousStatus'=>$e['status']];$e['status']=$next;$e['revision']++;
            if(in_array($action,['approve','disable'],true))$d['version']++;return;
        }
        throw new InvalidArgumentException('Uttalen finnes ikke.');
    },$path);
}
