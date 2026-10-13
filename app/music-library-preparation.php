<?php
declare(strict_types=1);
require_once __DIR__.'/music-library.php';
require_once __DIR__.'/music-library-tags.php';
function music_library_verified_handle(array $track,string $root){
    music_library_directory($root,false);$p=$root.'/originals/'.$track['id'].'.mp3';
    if(is_link($p)||!is_file($p))throw new RuntimeException('Originalfilen må kontrolleres.');
    $h=fopen($p,'rb');if(!$h)throw new RuntimeException('Originalfilen kan ikke leses.');
    try{$s=fstat($h);if($s['size']!==$track['bytes'])throw new RuntimeException('Originalfilen er endret.');$hash=hash_init('sha256');hash_update_stream($hash,$h);if(!hash_equals($track['sha256'],hash_final($hash)))throw new RuntimeException('Originalfilen er endret.');rewind($h);return $h;}catch(Throwable $e){fclose($h);throw $e;}
}
function music_library_analyze(array $track,string $root):array {
    $h=music_library_verified_handle($track,$root);fclose($h);
    require_once dirname(__DIR__).'/vendor/autoload.php';
    $reader=new getID3();$reader->encoding='UTF-8';$reader->option_save_attachments=false;$reader->option_tags_html=false;$reader->option_tag_lyrics3=false;
    $info=$reader->analyze($root.'/originals/'.$track['id'].'.mp3');
    if(!empty($info['error'])||($info['fileformat']??'')!=='mp3'||!is_numeric($info['playtime_seconds']??null)||!is_finite((float)$info['playtime_seconds'])||$info['playtime_seconds']<=0||$info['playtime_seconds']>86400)throw new InvalidArgumentException('Filen kunne ikke klargjøres som MP3 med gyldig varighet.');
    $fields=[];foreach(['artist','title','album','genre','year']as$key){$fields[$key]=null;foreach(['id3v2','id3v1','ape']as$type){$value=$info['tags'][$type][$key][0]??null;if(!is_string($value)||!preg_match('//u',$value))continue;$value=trim(preg_replace('/[\x00-\x1f\x7f]/u',' ',$value));if($value!==''){$fields[$key]=preg_match('/^.{0,240}/us',$value,$cap)?$cap[0]:null;break;}}}
    $h=music_library_verified_handle($track,$root);fclose($h);
    return ['source'=>'file','sourceSha256'=>$track['sha256'],'parser'=>'getID3','parserVersion'=>getID3::VERSION,'fields'=>$fields,'durationSeconds'=>round((float)$info['playtime_seconds'],3),'warnings'=>count($info['warning']??[]),'analyzedAt'=>gmdate('c')];
}
function music_library_prepare(string $id,string $version,int $revision,?array $user,string $csrf,string $expected,?string $root=null):array {
    if(!music_library_can_upload($user))throw new InvalidArgumentException('Rollen kan ikke klargjøre filer.');if($expected===''||!hash_equals($expected,$csrf))throw new InvalidArgumentException('Last siden på nytt.');
    if(!preg_match('/^[a-f0-9]{32}$/D',$id)||!preg_match('/^[a-f0-9]{64}$/D',$version)||$revision<0)throw new InvalidArgumentException('Ugyldig låtreferanse.');
    $root??=music_library_root();if(!music_library_directory($root,false)||is_link($root.'/library.lock'))throw new RuntimeException('Biblioteket kan ikke leses.');
    $lock=fopen($root.'/library.lock','rb');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Biblioteket er opptatt.');$staging=null;
    try{$index=music_library_index($root);$at=null;foreach($index['tracks']as$i=>$t)if($t['id']===$id){$at=$i;break;}if($at===null)throw new InvalidArgumentException('Låten finnes ikke.');$t=$index['tracks'][$at];if(!hash_equals($t['sha256'],$version)||($t['revision']??0)!==$revision)throw new InvalidArgumentException('Låten er endret. Last siden på nytt før klargjøring.');
      $metadata=music_library_analyze($t,$root);$index['tracks'][$at]['metadata']=$metadata;$index['tracks'][$at]['autoTags']=music_library_file_tags($metadata);$index['tracks'][$at]['revision']=$revision+1;$index['tracks'][$at]['status']='file_prepared';$index['tracks'][$at]['preparedBy']=(string)($user['id']??$user['name']??$user['role']);
      $staging=$root.'/library-'.bin2hex(random_bytes(8)).'.tmp';$h=fopen($staging,'xb');if(!$h)throw new RuntimeException('Kunne ikke lagre opplysningene.');try{$json=json_encode($index,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if(!chmod($staging,0600)||fwrite($h,$json)!==strlen($json)||!fflush($h))throw new RuntimeException('Kunne ikke lagre opplysningene.');}finally{fclose($h);}if(!rename($staging,$root.'/library.json'))throw new RuntimeException('Kunne ikke bekrefte klargjøringen.');$staging=null;return $index['tracks'][$at];
    }finally{if($staging!==null&&is_file($staging))unlink($staging);flock($lock,LOCK_UN);fclose($lock);}
}
function music_library_metadata(array $track):?array {
    $m=$track['metadata']??null;if(!is_array($m)||($m['source']??null)!=='file'||($m['sourceSha256']??null)!==$track['sha256']||!is_array($m['fields']??null)||!is_numeric($m['durationSeconds']??null)||!is_finite((float)$m['durationSeconds'])||$m['durationSeconds']<=0||$m['durationSeconds']>86400)return null;
    foreach(['artist','title','album','genre','year']as$k){$v=$m['fields'][$k]??null;if($v!==null&&(!is_string($v)||strlen($v)>960||!preg_match('//u',$v)))return null;}return $m;
}
function music_library_duration(?array $metadata):string {if(!$metadata)return '—';$s=(int)round($metadata['durationSeconds']);return sprintf('%d:%02d',intdiv($s,60),$s%60);}
