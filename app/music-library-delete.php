<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-preparation.php';
function music_library_delete(string $id,string $sha,int $revision,?array $user,string $csrf,string $expected,bool $confirmed,?string $root=null,?callable $purge=null):array {
    if(!music_library_can_upload($user)||$expected===''||!hash_equals($expected,$csrf)||!$confirmed)throw new InvalidArgumentException('Slettingen er ikke bekreftet eller autorisert.');
    if(!preg_match('/^[a-f0-9]{32}$/D',$id)||!preg_match('/^[a-f0-9]{64}$/D',$sha)||$revision<0)throw new InvalidArgumentException('Ugyldig låtreferanse.');
    $root??=music_library_root();if(!music_library_directory($root,false)||is_link($root.'/library.lock'))throw new RuntimeException('Biblioteket kan ikke kontrolleres.');$lock=fopen($root.'/library.lock','rb');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Biblioteket er opptatt.');$tmp=null;$moved=false;$committed=false;$stage=$root.'/.pending-delete-'.$id.'.mp3';
    try{$index=music_library_index($root);$at=null;foreach($index['tracks']as$i=>$t)if($t['id']===$id)$at=$i;if($at===null)throw new InvalidArgumentException('Låten finnes ikke.');$track=$index['tracks'][$at];if($track['sha256']!==$sha||($track['revision']??0)!==$revision)throw new InvalidArgumentException('Låten er endret. Last siden på nytt før sletting.');
      if(file_exists($stage)||is_link($stage))throw new RuntimeException('Tidligere sletting må avklares.');$h=music_library_verified_handle($track,$root);fclose($h);$original=$root.'/originals/'.$id.'.mp3';
      $index['tracks']=array_values(array_filter($index['tracks'],fn($t)=>$t['id']!==$id));$raw=json_encode($index,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);$tmp=$root.'/library-'.bin2hex(random_bytes(8)).'.tmp';$h=fopen($tmp,'xb');if(!$h)throw new RuntimeException('Slettingen kunne ikke klargjøres.');try{if(!chmod($tmp,0600)||fwrite($h,$raw)!==strlen($raw)||!fflush($h))throw new RuntimeException('Slettingen kunne ikke klargjøres.');}finally{fclose($h);}
      // Stage the private original so a failed index commit can restore it intact.
      if(!rename($original,$stage))throw new RuntimeException('Originalen kunne ikke klargjøres for sletting.');$moved=true;
      if(!rename($tmp,$root.'/library.json'))throw new RuntimeException('Biblioteket kunne ikke oppdateres.');$tmp=null;$committed=true;
      try{$deleted=($purge??'unlink')($stage);}catch(Throwable){$deleted=false;}return ['libraryRemoved'=>true,'fileDeleted'=>$deleted===true&&!file_exists($stage),'id'=>$id];
    }finally{if($moved&&!$committed&&!rename($stage,$root.'/originals/'.$id.'.mp3'))error_log('Studio music deletion needs recovery');if($tmp&&is_file($tmp))unlink($tmp);flock($lock,LOCK_UN);fclose($lock);}
}
