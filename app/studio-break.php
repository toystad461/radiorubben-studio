<?php
declare(strict_types=1);
require_once __DIR__.'/audio-workflow.php';
/** One human approval, reusing the board and RR Audio's existing hash-bound approval. */
function studio_break_approve(array $input,array $user,string $csrf,?string $path=null):array {
    rr_audio_role($user);
    if(($input['action']??'')!=='approve'||($input['confirmed']??'')!=='1'||!is_string($input['csrf']??null)||!hash_equals($csrf,$input['csrf']))throw new InvalidArgumentException('Les stikket og godkjenn det manuelt.');
    if(!in_array($input['type']??'', ['new','shorter','next','refresh'],true))throw new InvalidArgumentException('Nyheter, vær og sponsor krever kildebehandling i sin eksisterende arbeidsflyt.');
    $script=$input['text']??null;$context=$input['context']??null;$notes=$input['notes']??'';
    if(!is_string($script)||mb_strlen(trim($script))<5||mb_strlen($script)>2200||preg_match('/[<>\x00-\x08\x0b\x0c\x0e-\x1f]/',$script)||preg_match('/\[[^\]]+\]/',$script))throw new InvalidArgumentException('Les og rett stikket. Fjern plassholdere og bruk rent manus på 5–2200 tegn.');
    if(count(preg_split('/\s+/u',trim($script)))>100)throw new InvalidArgumentException('Programlederstikket kan ha inntil 100 ord. Velg Kortere eller rediger teksten.');
    if(!is_array($context)||!in_array($context['source']??'', ['local','operator','unavailable'],true)||!is_string($notes)||mb_strlen($notes)>2000)throw new InvalidArgumentException('Bruk ekte eller manuell kontekst, ikke testdata.');
    $source=['kind'=>'operator_context','contextSource'=>$context['source'],'notes'=>$notes,'origin'=>($input['aiAssisted']??false)===true?'ai_assisted':'manual'];
    foreach(['program','nowPlaying','next']as$key){$value=$context[$key]??'';if($value===null)$value='';if(!is_string($value)||mb_strlen($value)>300||strip_tags($value)!==$value)throw new InvalidArgumentException('Ugyldig program-/musikkgrunnlag.');$source[$key]=$value;}
    $tracks=$context['nextTracks']??[];if(!is_array($tracks)||count($tracks)>3)throw new InvalidArgumentException('Køen kan ha tre låter.');
    foreach($tracks as$t)if(!is_string($t)||mb_strlen($t)>300||strip_tags($t)!==$t)throw new InvalidArgumentException('Ugyldig musikkø.');
    $source['nextTracks']=array_values($tracks);$script=trim($script);
    $identity=rr_audio_hash([$script,$source,$user['id']??$user['name']??'']);
    $saved=studio_board_change(static function(array &$board)use($script,$source,$user,$identity){
        foreach($board['items']as$i)if(($i['studioBreak']['identity']??'')===$identity&&($i['status']??'')!=='archived'&&($i['audioScripts']['host']['script']??'')===$script&&($i['studioBreak']['source']??[])===$source&&rr_audio_checked($i,$i['audioScripts']['host']??[]))return $i;
        if(count(studio_board_active($board))>=30)throw new InvalidArgumentException('Arbeidslisten er full. Arkiver et punkt først.');
        $at=gmdate('c');$item=['id'=>bin2hex(random_bytes(8)),'originId'=>null,'channel'=>'radio','title'=>'Studio-stikk','sourceName'=>'Operatørkontrollert Studio-grunnlag','sourceUrl'=>'','sourceAt'=>$at,'capturedAt'=>$at,'summary'=>'Programlederstikk; manuelt kontrollert musikk-/programgrunnlag.','program'=>'','script'=>$script,'notes'=>$source['notes'],'status'=>'draft','verified'=>false,'approvedBy'=>null,'revision'=>1,'createdAt'=>$at,'updatedAt'=>$at,'createdBy'=>$user['name']??'Medarbeider',
            'studioBreak'=>['kind'=>'operator-break-1','identity'=>$identity,'source'=>$source,'actor'=>$user['name']??'Medarbeider','actorId'=>$user['id']??null,'at'=>$at]];
        $v=['profile'=>'host','script'=>$script,'generatedOriginal'=>$script,'generation'=>['aiPolicyVersion'=>RR_AI_POLICY_VERSION,'aiAssisted'=>$source['origin']==='ai_assisted','origin'=>'studio_operator','at'=>$at],'check'=>rr_studio_break_check($item,$script),'scriptApproval'=>null];
        $item['audioScripts']=['host'=>$v];$board['items'][]=$item;return $item;
    },$path);
    if(!rr_audio_script_approved($saved,$saved['audioScripts']['host']))rr_audio_change($saved['id'],$saved['revision'],'approve_script',['profile'=>'host','confirmed'=>'1'],$user,$path);
    return studio_case_get($saved['id'],$path);
}
