<?php
declare(strict_types=1);
const MUSIC_LIBRARY_FILE_LIMIT=20*1024*1024;
const MUSIC_LIBRARY_TOTAL_LIMIT=512*1024*1024;
function music_library_can_upload(?array $user):bool{return in_array($user['role']??'', ['admin','producer'],true);}
function music_library_root():string{
    // Deployment may keep media beside a versioned release. Resolve the server-owned parent link.
    $media=realpath(dirname(__DIR__).'/media') ?: dirname(__DIR__).'/media';
    return $media.'/music';
}
function music_library_directory(string $root,bool $create):bool {
    // Internal fixed path (or isolated test path); never supplied by HTTP.
    $parent=dirname(dirname($root));if(!is_dir($parent)||is_link($parent)||str_replace('\\','/',(string)realpath($parent))!==str_replace('\\','/',$parent))throw new RuntimeException('Privat lagringsområde er utilgjengelig.');
    foreach([dirname($root),$root,$root.'/originals']as$p){if(is_link($p))throw new RuntimeException('Ugyldig lagringsområde.');if(!is_dir($p)){if(!$create){if(is_file($root.'/library.json'))throw new RuntimeException('Originalmappen mangler.');return false;}if(!mkdir($p,0700)&&!is_dir($p))throw new RuntimeException('Kunne ikke opprette privat musikkmappe.');}if(str_replace('\\','/',(string)realpath($p))!==str_replace('\\','/',$p))throw new RuntimeException('Ugyldig lagringsområde.');}
    return true;
}
function music_library_index(string $root):array {
    $p=$root.'/library.json';if(is_link($p))throw new RuntimeException('Ugyldig bibliotekregister.');
    if(!is_file($p))return ['schemaVersion'=>1,'tracks'=>[]];
    $v=json_decode((string)file_get_contents($p),true,32,JSON_THROW_ON_ERROR);
    if(!is_array($v)||($v['schemaVersion']??null)!==1||!is_array($v['tracks']??null)||count($v['tracks'])>100)throw new RuntimeException('Bibliotekregisteret må kontrolleres.');
    $ids=[];$hashes=[];foreach($v['tracks']as$t){if(!is_array($t)||!preg_match('/^[a-f0-9]{32}$/D',(string)($t['id']??''))||!preg_match('/^[a-f0-9]{64}$/D',(string)($t['sha256']??''))||!is_int($t['bytes']??null)||$t['bytes']<1||$t['bytes']>MUSIC_LIBRARY_FILE_LIMIT||!is_string($t['originalName']??null)||strlen($t['originalName'])>240||isset($ids[$t['id']])||isset($hashes[$t['sha256']]))throw new RuntimeException('Bibliotekregisteret må kontrolleres.');$ids[$t['id']]=true;$hashes[$t['sha256']]=true;}
    return $v;
}
function music_library_read(?string $root=null):array {
    $root??=music_library_root();if(!music_library_directory($root,false))return [];
    $p=$root.'/library.lock';if(is_link($p))throw new RuntimeException('Ugyldig biblioteklås.');
    if(!is_file($p)){if(is_file($root.'/library.json'))throw new RuntimeException('Biblioteklåsen mangler.');return [];}
    $h=fopen($p,'rb');if(!$h||!flock($h,LOCK_SH))throw new RuntimeException('Biblioteket er opptatt.');
    try{$v=music_library_index($root);$expected=[];foreach($v['tracks']as&$t){$expected[]=$t['id'].'.mp3';$p=$root.'/originals/'.$t['id'].'.mp3';$t['available']=!is_link($p)&&is_file($p)&&filesize($p)===$t['bytes'];}unset($t);foreach(new DirectoryIterator($root.'/originals')as$f)if(!$f->isDot()&&($f->isLink()||!$f->isFile()||!in_array($f->getFilename(),$expected,true)))throw new RuntimeException('Uregistrert original må kontrolleres.');return array_reverse($v['tracks']);}finally{flock($h,LOCK_UN);fclose($h);}
}
function music_library_mp3(string $path):bool {
    // Bounded format screening, not full decoding or music identification.
    if(!class_exists('finfo'))throw new RuntimeException('Filtypekontroll er ikke tilgjengelig på serveren.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,['audio/mpeg','audio/mp3','audio/x-mpeg'],true))return false;
    $h=fopen($path,'rb');if(!$h)return false;
    try{$first=fread($h,10);$offset=0;if(substr($first,0,3)==='ID3'){if(strlen($first)!==10||!in_array(ord($first[3]),[2,3,4],true))return false;$tag=0;for($i=6;$i<10;$i++){if(ord($first[$i])>127)return false;$tag=($tag<<7)|ord($first[$i]);}$offset=10+$tag+((ord($first[3])===4&&(ord($first[5])&16))?10:0);}
      $size=filesize($path);$version=null;$rate=null;
      for($n=0;$n<3;$n++){if($offset+4>$size||fseek($h,$offset)!==0)return false;$raw=fread($h,4);if(strlen($raw)!==4)return false;$v=unpack('N',$raw)[1];$ver=($v>>19)&3;$layer=($v>>17)&3;$bi=($v>>12)&15;$si=($v>>10)&3;if(($v&0xffe00000)!==0xffe00000||$ver===1||$layer!==1||$bi===0||$bi===15||$si===3)return false;$rates=[44100,48000,32000];$sr=(int)($rates[$si]/($ver===3?1:($ver===2?2:4)));if($version!==null&&($version!==$ver||$rate!==$sr))return false;$version=$ver;$rate=$sr;$kbps=($ver===3?[0,32,40,48,56,64,80,96,112,128,160,192,224,256,320]:[0,8,16,24,32,40,48,56,64,80,96,112,128,144,160])[$bi];$len=(int)floor(($ver===3?144000:72000)*$kbps/$sr)+(($v>>9)&1);if($offset+$len>$size)return false;$offset+=$len;}
      return true;
    }finally{fclose($h);}
}
function music_library_upload(array $file,?array $user,string $csrf,string $expected,?string $root=null,?callable $uploaded=null,?callable $move=null):array {
    if(!music_library_can_upload($user))throw new InvalidArgumentException('Bare administrator og produsent kan laste opp musikk.');
    if($expected===''||!hash_equals($expected,$csrf))throw new InvalidArgumentException('Last siden på nytt før opplasting.');
    if(($file['error']??null)!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Filen ble ikke lastet opp. Kontroller størrelse og prøv igjen.');
    $name=$file['name']??null;$tmp=$file['tmp_name']??null;
    if(!is_string($name)||!is_string($tmp)||$name===''||strlen($name)>240||preg_match('/[\x00-\x1f\x7f\/\\\\]/',$name)||strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='mp3')throw new InvalidArgumentException('Velg en MP3-fil med et vanlig filnavn.');
    if(!(($uploaded??'is_uploaded_file')($tmp))||is_link($tmp)||!is_file($tmp))throw new InvalidArgumentException('Ugyldig opplastet fil.');
    $bytes=filesize($tmp);if($bytes<1||$bytes>MUSIC_LIBRARY_FILE_LIMIT||($file['size']??null)!==$bytes)throw new InvalidArgumentException('Hver MP3-fil kan være maksimalt 20 MiB.');
    if(!music_library_mp3($tmp))throw new InvalidArgumentException('Filen kunne ikke bekreftes som MP3.');
    $sha=hash_file('sha256',$tmp);$root??=music_library_root();music_library_directory($root,true);
    $lock=$root.'/library.lock';if(is_link($lock))throw new RuntimeException('Ugyldig biblioteklås.');$h=fopen($lock,'c+b');if(!$h||!flock($h,LOCK_EX))throw new RuntimeException('Biblioteket er opptatt.');chmod($lock,0600);
    $asset=null;$staging=null;
    try{$index=music_library_index($root);$expectedFiles=[];foreach($index['tracks']as$t){$expectedFiles[]=$t['id'].'.mp3';$p=$root.'/originals/'.$t['id'].'.mp3';if(is_link($p)||!is_file($p)||filesize($p)!==$t['bytes'])throw new RuntimeException('Eksisterende original må kontrolleres.');}foreach(new DirectoryIterator($root.'/originals')as$f)if(!$f->isDot()&&($f->isLink()||!$f->isFile()||!in_array($f->getFilename(),$expectedFiles,true)))throw new RuntimeException('Uregistrert original må kontrolleres før ny opplasting.');
      $total=0;foreach($index['tracks']as$t){$total+=$t['bytes'];if(hash_equals($t['sha256'],$sha)){$p=$root.'/originals/'.$t['id'].'.mp3';if(hash_file('sha256',$p)!==$sha)throw new RuntimeException('Eksisterende original må kontrolleres.');return ['duplicate'=>true,'track'=>$t];}}
      if(count($index['tracks'])>=100||$total+$bytes>MUSIC_LIBRARY_TOTAL_LIMIT)throw new InvalidArgumentException('Prøvebibliotekets grense er nådd: 100 filer eller 512 MiB.');
      $id=bin2hex(random_bytes(16));$asset=$root.'/originals/'.$id.'.mp3';if(file_exists($asset)||is_link($asset))throw new RuntimeException('Kunne ikke velge filidentitet.');
      if(!(($move??'move_uploaded_file')($tmp,$asset)))throw new RuntimeException('Kunne ikke lagre originalfilen.');
      if(!chmod($asset,0600)||filesize($asset)!==$bytes||hash_file('sha256',$asset)!==$sha)throw new RuntimeException('Originalfilen kunne ikke bekreftes.');
      $track=['id'=>$id,'originalName'=>$name,'sha256'=>$sha,'bytes'=>$bytes,'uploadedAt'=>gmdate('c'),'uploadedBy'=>(string)($user['id']??$user['name']??$user['role']),'status'=>'awaiting_preparation','metadata'=>null,'tags'=>[]];$index['tracks'][]=$track;
      $staging=$root.'/library-'.bin2hex(random_bytes(8)).'.tmp';$out=fopen($staging,'xb');if(!$out)throw new RuntimeException('Kunne ikke lagre registeret.');try{if(!chmod($staging,0600))throw new RuntimeException('Kunne ikke beskytte registeret.');$json=json_encode($index,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if(fwrite($out,$json)!==strlen($json)||!fflush($out))throw new RuntimeException('Ufullstendig bibliotekregister.');}finally{fclose($out);}
      if(!rename($staging,$root.'/library.json'))throw new RuntimeException('Kunne ikke bekrefte bibliotekregisteret.');$staging=null;$asset=null;return ['duplicate'=>false,'track'=>$track];
    }finally{if($staging!==null&&is_file($staging))unlink($staging);if($asset!==null&&is_file($asset))unlink($asset);flock($h,LOCK_UN);fclose($h);}
}
