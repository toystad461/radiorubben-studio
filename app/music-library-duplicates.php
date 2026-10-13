<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-edit.php';
function music_library_possible_duplicates(array $track,array $tracks):array {
    $m=music_library_metadata($track);$fields=music_library_effective_fields($track);if(!$m||empty($fields['title'])||empty($fields['artist']))return [];
    $normalize=static fn(string $v)=>mb_strtolower(trim(preg_replace('/\s+/u',' ',$v)),'UTF-8');$matches=[];
    foreach($tracks as$other){if($other['id']===$track['id'])continue;$om=music_library_metadata($other);$of=music_library_effective_fields($other);
      if(!$om||empty($of['title'])||empty($of['artist'])||$normalize($of['title'])!==$normalize($fields['title'])||$normalize($of['artist'])!==$normalize($fields['artist'])||abs($om['durationSeconds']-$m['durationSeconds'])>3)continue;
      $matches[]=['id'=>$other['id'],'title'=>$of['title'],'artist'=>$of['artist'],'durationSeconds'=>$om['durationSeconds'],'sameOriginal'=>$other['sha256']===$track['sha256']];
    }return $matches;
}
