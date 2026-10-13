<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-producer.php';
function music_online_normalize(string $v):string{return mb_strtolower(trim(preg_replace('/\s+/u',' ',$v)),'UTF-8');}
function music_online_select(array $track,array $response):array {
    $input=music_library_producer_input($track);$fields=$input['effectiveFields'];$matches=[];
    if(!is_array($response['recordings']??null)||count($response['recordings'])>10)throw new RuntimeException('Ugyldig musikkoppslag.');
    foreach($response['recordings']as$r){
      if(!is_array($r)||!is_string($r['id']??null)||!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D',$r['id'])||!is_string($r['title']??null)||mb_strlen($r['title'])>240||!is_array($r['artist-credit']??null)||count($r['artist-credit'])>12)continue;
      $artist='';foreach($r['artist-credit']as$c){if(!is_array($c)||!is_string($c['name']??null)||mb_strlen($c['name'])>240||!is_string($c['joinphrase']??'')){$artist='';break;}$artist.=$c['name'].($c['joinphrase']??'');}
      if((int)($r['score']??0)<100||music_online_normalize($r['title'])!==music_online_normalize($fields['title'])||music_online_normalize($artist)!==music_online_normalize($fields['artist'])||!is_int($r['length']??null)||abs($r['length']/1000-$input['durationSeconds'])>3)continue;
      // A named edit/remix must agree exactly; never silently promote another edition.
      $disambiguation=$r['disambiguation']??'';if(!is_string($disambiguation)||$disambiguation!=='')continue;
      $matches[$r['id']]=['id'=>$r['id'],'title'=>$r['title'],'artist'=>$artist,'lengthMs'=>$r['length']];
    }
    return ['status'=>count($matches)===1?'matched':'needs_review','candidate'=>count($matches)===1?array_values($matches)[0]:null,'matchingCandidates'=>count($matches),'source'=>'MusicBrainz','sourceSha256'=>$track['sha256'],'queryInputSha256'=>hash('sha256',json_encode($input['effectiveFields'],JSON_THROW_ON_ERROR)),'retrievedAt'=>gmdate('c')];
}
function music_online_get(array $config,string $path,string $stateRoot,?callable $transport=null):array {
    if($transport)return $transport($path);
    if(($config['music_processing']['online_enabled']??false)!==true||($config['music_processing']['provider_terms_confirmed']??false)!==true)throw new RuntimeException('Nettoppslag er ikke aktivert.');
    if(!preg_match('~^recording(?:\?[a-zA-Z0-9%_.=&+-]+|/[a-f0-9-]{36}\?inc=genres&fmt=json)$~D',$path))throw new RuntimeException('Ugyldig oppslagsadresse.');
    $lockPath=$stateRoot.'/online-rate.lock';if(is_link($lockPath))throw new RuntimeException('Ugyldig oppslagslås.');$lock=fopen($lockPath,'c+b');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Oppslag er opptatt.');chmod($lockPath,0600);
    try{$last=(float)stream_get_contents($lock);$wait=1.1-(microtime(true)-$last);if($wait>0)usleep((int)($wait*1000000));rewind($lock);ftruncate($lock,0);fwrite($lock,(string)microtime(true));fflush($lock);
      $body='';$curl=curl_init('https://musicbrainz.org/ws/2/'.$path);curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_USERAGENT=>'RadioRubbenStudio/1.0 (+https://radiorubben.no)',CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_WRITEFUNCTION=>function($c,$b)use(&$body){if(strlen($body)+strlen($b)>262144)return 0;$body.=$b;return strlen($b);}]);$ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);if(!$ok||$status!==200)throw new RuntimeException('Musikkoppslaget er utilgjengelig.');$data=json_decode($body,true,32,JSON_THROW_ON_ERROR);if(!is_array($data))throw new RuntimeException('Ugyldig oppslagssvar.');return $data;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function music_online_lookup(array $config,array $track,string $root,?callable $transport=null):array {
    $fields=music_library_producer_input($track)['effectiveFields'];$quote=static fn($v)=>'"'.str_replace(['\\','"'],['\\\\','\\"'],$v).'"';
    $query='recording:'.$quote($fields['title']).' AND artist:'.$quote($fields['artist']);$path='recording?'.http_build_query(['query'=>$query,'limit'=>10,'fmt'=>'json'],'','&',PHP_QUERY_RFC3986);
    $result=music_online_select($track,music_online_get($config,$path,$root,$transport));$result['genres']=[];
    if($result['status']==='matched'){$id=$result['candidate']['id'];$detail=music_online_get($config,'recording/'.$id.'?inc=genres&fmt=json',$root,$transport);if(($detail['id']??null)!==$id||!is_array($detail['genres']??null))throw new RuntimeException('Oppslaget kunne ikke bekreftes.');
      foreach(array_slice($detail['genres'],0,20)as$g)if(is_array($g)&&is_string($g['name']??null)&&trim($g['name'])!==''&&mb_strlen($g['name'])<=80&&is_int($g['count']??null)&&$g['count']>0)$result['genres'][]=$g['name'];$result['genres']=array_slice(array_values(array_unique($result['genres'])),0,8);$result['url']='https://musicbrainz.org/recording/'.$id;}
    $result['evidenceSha256']=hash('sha256',json_encode($result,JSON_THROW_ON_ERROR));return $result;
}
