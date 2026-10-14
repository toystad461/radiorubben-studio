<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-queue.php';
function music_audio_manual_save(string $id,string $sha,int $revision,array $input,?array $user,string $csrf,string $expected,?string $root=null):array {
    music_audio_authorize($user,$csrf,$expected);$root??=music_library_root();$track=music_audio_find($root,$id);if($track['sha256']!==$sha||($track['revision']??0)!==$revision)throw new InvalidArgumentException('Låten er endret.');$fields=[];
    $bpm=$input['bpm']??'';if(!is_string($bpm)||strlen($bpm)>12||($bpm!==''&&(!preg_match('/^[0-9]+(?:\.[0-9]{1,3})?$/D',$bpm)||(float)$bpm<40||(float)$bpm>208)))throw new InvalidArgumentException('BPM må være40–208.');if($bpm!=='')$fields['bpm']=(float)$bpm;
    $key=$input['key']??'';$scale=$input['scale']??'';if(!is_string($key)||!is_string($scale)||(($key==='')!==($scale===''))||($key!==''&&(!in_array($key,['A','A#','Bb','B','C','C#','Db','D','D#','Eb','E','F','F#','Gb','G','G#','Ab'],true)||!in_array($scale,['major','minor'],true))))throw new InvalidArgumentException('Velg både toneart og dur/moll.');if($key!==''){$fields['key']=$key;$fields['scale']=$scale;}
    return music_worker_commit($root,$track,['audioManualOverrides'=>['sourceSha256'=>$sha,'fields'=>$fields,'updatedAt'=>gmdate('c'),'updatedBy'=>(string)$user['id']]]);
}
function music_audio_accept_cues(string $id,string $sha,int $revision,string $proposalSha,?array $user,string $csrf,string $expected,?string $root=null):array {
    music_audio_authorize($user,$csrf,$expected);$root??=music_library_root();$t=music_audio_find($root,$id);$p=music_audio_profile_current($t);if(!$p||!hash_equals(hash('sha256',json_encode($p['cueSuggestion'],JSON_THROW_ON_ERROR)),$proposalSha))throw new InvalidArgumentException('Forslaget er endret.');
    return music_library_cues_save($id,$sha,$revision,$p['cueSuggestion'],$user,$csrf,$expected,$root);
}
