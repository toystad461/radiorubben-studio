<?php
declare(strict_types=1);
require_once __DIR__.'/case-workflow.php';
require_once __DIR__.'/audio-pronunciation.php';
require_once __DIR__.'/audio-storage.php';
require_once __DIR__.'/integrations/ElevenLabs.php';
require_once __DIR__.'/bulletin.php';

function rr_audio_role(array $user):void {
    if(!in_array($user['role']??'',['admin','producer','presenter'],true))throw new InvalidArgumentException('Ingen tilgang til lydproduksjon.');
}
function rr_audio_hash(mixed $value):string {return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));}
function rr_audio_projection(array $item,array $variant):array {
    $item['script']=$variant['script']??'';$item['sourceCheck']=$variant['check']??[];return $item;
}
function rr_audio_script_hash(array $item,array $v):string {
    return rr_audio_hash([studio_news_fingerprint($item,(string)($v['script']??'')),$v['profile']??'', $v['check']['source']['sha256']??'',RR_AUDIO_VERSION,RR_STYLEBOOK_VERSION,
        // Editing the primary radio manuscript invalidates dependent audio too.
        studio_board_audio_identity($item),$item['bulletin']??null,rr_audio_disclosure()]);
}
function rr_audio_checked(array $item,array $v):bool {
    if(isset($item['bulletin']))return rr_bulletin_checked($item,$v);
    $p=rr_audio_projection($item,$v);
    return trim((string)($v['script']??''))!==''&&studio_news_check_current($p)&&studio_news_original_read($p,$v['check']??[])&&studio_news_radio_credit($p)
        && (strtotime((string)($v['check']['source']['fetchedAt']??''))?:0)>=time()-3600;
}
function rr_audio_script_approved(array $item,array $v):bool {
    return rr_audio_checked($item,$v)&&hash_equals(rr_audio_script_hash($item,$v),(string)($v['scriptApproval']['hash']??''));
}
function rr_audio_voice(array $config,string $voice,string $program):array {
    $v=$config['voices'][$voice]??[];
    if($program!=='')studio_program_profile($program); // Reject unknown IDs; do not assign legacy items.
    $allowed=($v['scope']??'')==='all'||($program!==''&&in_array($program,$v['programs']??[],true));
    if(($v['approved']??false)!==true||trim((string)($v['rights_reference']??''))===''||!$allowed)throw new InvalidArgumentException('Stemmen er ikke godkjent for dette programmet.');
    return $v;
}
function rr_audio_spoken_text(array $variant,array $dictionary):string {
    return rr_audio_disclosure()['text']."\n\n".rr_pronunciation_text((string)($variant['script']??''),$dictionary);
}
function rr_audio_approval_hash(array $audio):string {
    return rr_audio_hash([$audio['raw']??[],$audio['master']??[],$audio['send']??[],$audio['token']??'',
        $audio['disclosure']??[],$audio['spokenTextSha256']??'']);
}
function rr_audio_current(array $item,array $v,array $board,array $config):bool {
    if(isset($item['bulletin'])&&!rr_bulletin_checked($item,$v,$board))return false;
    $a=$v['audio']??[];
    try{$voice=rr_audio_voice($config,(string)($a['voice']??''),(string)($item['program']??''));}catch(Throwable){return false;}
    return ($a['aiPolicyVersion']??'')===RR_AI_POLICY_VERSION&&($a['synthetic']??null)===true
        &&($a['disclosure']??null)===rr_audio_disclosure()
        &&($a['spokenTextSha256']??'')===hash('sha256',rr_audio_spoken_text($v,rr_pronunciation_context($board)))
        &&rr_audio_script_approved($item,$v)&&hash_equals(rr_audio_script_hash($item,$v),(string)($a['scriptHash']??''))
        &&($a['dictionaryHash']??'')===rr_audio_hash(rr_pronunciation_context($board))
        &&($a['voiceHash']??'')===rr_audio_hash($voice)&&($a['model']??'')===($config['model_id']??'');
}
function rr_audio_ready(array $item,array $v,array $board,array $config,?string $path=null):bool {
    $a=$v['audio']??[];
    if(($item['status']??'')==='archived'||studio_board_channel($item)==='web'||!rr_audio_current($item,$v,$board,$config)||($a['status']??'')!=='processed'||($a['qa']['passed']??false)!==true
        ||($a['profileHash']??'')!==rr_audio_hash($config['audio_profile']??[])||($a['approval']['heardDisclosure']??false)!==true||($a['approval']['hash']??'')!==rr_audio_approval_hash($a))return false;
    try {rr_audio_asset($a['master'],$path);rr_audio_asset($a['send'],$path);}catch(Throwable){return false;}
    return true;
}
/** All durable mutations use the existing board lock and optimistic revision. */
function rr_audio_change(string $id,int $revision,string $action,array $input,array $user,?string $path=null):void {
    rr_audio_role($user);
    studio_board_change(static function(&$b)use($id,$revision,$action,$input,$user){
        foreach($b['items'] as &$i)if(($i['id']??'')===$id&&($i['status']??'')!=='archived'){
            if(($i['revision']??0)!==$revision)throw new InvalidArgumentException('Saken ble endret. Last siden på nytt.');
            $profile=(string)($input['profile']??'');rr_audio_profile($profile);
            if(isset($i['bulletin'])&&$action!=='approve_script')throw new InvalidArgumentException('Rett enkeltsakene og lag en ny samlet sending.');
            $before=$i;unset($before['history']);$v=&$i['audioScripts'][$profile];
            if($action==='save'||$action==='generated'){
                $script=trim((string)($input['script']??''));
                if($script===''||mb_strlen($script)>2200||preg_match('/[<>\x00-\x08\x0b\x0c\x0e-\x1f]/',$script))throw new InvalidArgumentException('Skriv et rent radiomanus på inntil 2200 tegn.');
                // Retain in-flight token on edit: completion detects a stale script, never resurrects it.
                $v=array_replace($v??[],['profile'=>$profile,'script'=>$script,'check'=>$action==='generated'?($input['check']??[]):[], 'scriptApproval'=>null]);
                if($action==='generated'){$v['generatedOriginal']=$script;$v['generation']=$input['generation']??[];}
                if(isset($v['audio']))$v['audio']['approval']=null;
                unset($i['audioQueue']);
            }elseif($action==='checked'){
                if(!$v)throw new InvalidArgumentException('Lagre manus først.');
                $v['check']=$input['check']??[];$v['scriptApproval']=null;
                if(isset($v['audio']))$v['audio']['approval']=null;
                unset($i['audioQueue']);
            }elseif($action==='approve_script'){
                if(($input['confirmed']??'')!=='1'||!rr_audio_checked($i,$v??[])||(isset($i['bulletin'])&&!rr_bulletin_checked($i,$v??[],$b)))throw new InvalidArgumentException('Les manuset og kjør gyldig kildekontroll før godkjenning.');
                $v['scriptApproval']=['hash'=>rr_audio_script_hash($i,$v),'actor'=>$user['name']??'Medarbeider','at'=>gmdate('c')];
            }else throw new InvalidArgumentException('Ukjent manushandling.');
            $i['history'][]=['action'=>'audio_'.$action,'at'=>gmdate('c'),'actor'=>$user['name']??'Medarbeider','before'=>$before];$i['revision']++;return;
        }
        throw new InvalidArgumentException('Saken finnes ikke.');
    },$path);
}
/** Both channels consume the same validated source snapshot; article prose is never input. */
function rr_audio_prepare(array $item,string $profile,array $config,array $editorial=[],?string $script=null,?callable $request=null):array {
    rr_audio_profile($profile);$source=null;
    foreach([$item['sourceCheck']??[], $item['web']['check']??[]]as$check){
        if(($check['status']??'')==='passed'&&studio_news_original_read($item,$check)&&(strtotime((string)($check['source']['fetchedAt']??''))?:0)>=time()-3600){$source=$check['source'];break;}
    }
    if(!$source)throw new InvalidArgumentException('Klargjør radio og nett med et ferskt, kontrollert originalgrunnlag først.');
    $result=studio_news_prepare($item,$config,$editorial,$script,$request,null,$source,$profile);
    $result['generation']['audioProfile']=rr_audio_profile($profile);
    $result['generation']['stages']['RR Audio']='standalone_manuscript';
    return $result;
}
/** Reserve before the external call. Unknown provider outcomes require explicit resolution. */
function rr_audio_generate(string $id,int $revision,string $profile,string $voice,array $settings,array $user,array $config,?string $path=null,?callable $transport=null):void {
    rr_audio_role($user);rr_audio_profile($profile);
    if(($config['enabled']??false)!==true||empty($config['api_key'])||!in_array($config['model_id']??'',['eleven_multilingual_v2','eleven_v4'],true))throw new InvalidArgumentException('Ekte TTS er deaktivert eller ikke konfigurert.');
    $safe=[];
    foreach(['speed'=>[0.8,1.2,1.0],'stability'=>[0,1,0.5],'similarity_boost'=>[0,1,0.75],'style'=>[0,1,0.0]]as$key=>$bounds){$x=$settings[$key]??$bounds[2];if(!is_numeric($x)||!is_finite((float)$x)||$x<$bounds[0]||$x>$bounds[1])throw new InvalidArgumentException('Ugyldig stemmeinnstilling.');$safe[$key]=(float)$x;}
    $token=bin2hex(random_bytes(16));
    $payload=studio_board_change(static function(&$b)use($id,$revision,$profile,$voice,$safe,$config,$token,$user){
        foreach($b['items']as$other)foreach($other['audioScripts']??[]as$ov)if(in_array($ov['audio']['status']??'', ['generating','unknown'],true))throw new InvalidArgumentException('En TTS-forespørsel pågår eller må avklares før nytt forsøk.');
        foreach($b['items']as&$i)if(($i['id']??'')===$id){
            if(($i['revision']??0)!==$revision||($i['status']??'')==='archived'||studio_board_channel($i)==='web')throw new InvalidArgumentException('Saken ble endret eller er ikke valgt for radio.');
            $v=&$i['audioScripts'][$profile];if(!rr_audio_script_approved($i,$v??[])||(isset($i['bulletin'])&&!rr_bulletin_checked($i,$v??[],$b)))throw new InvalidArgumentException('Manuset må kildekontrolleres og sluttgodkjennes før TTS.');
            $vp=rr_audio_voice($config,$voice,(string)($i['program']??''));$d=rr_pronunciation_context($b);$text=rr_audio_spoken_text($v,$d);$count=mb_strlen($text);
            $today=gmdate('Y-m-d');$used=(int)($b['audioUsage'][$today]??0);$limit=(int)($config['daily_character_limit']??0);
            if($count>3000||$count+$used>$limit)throw new InvalidArgumentException('TTS-budsjettet er brukt opp eller ikke angitt.');
            $b['audioUsage'][$today]=$used+$count;
            if(isset($v['audio']))$v['audioHistory'][]=$v['audio'];
            $v['audio']=['aiPolicyVersion'=>RR_AI_POLICY_VERSION,'synthetic'=>true,'disclosure'=>rr_audio_disclosure(),'spokenTextSha256'=>hash('sha256',$text),'token'=>$token,'status'=>'generating','version'=>RR_AUDIO_VERSION,'stylebookVersion'=>RR_STYLEBOOK_VERSION,'at'=>gmdate('c'),'actor'=>$user['name']??'Medarbeider','scriptHash'=>rr_audio_script_hash($i,$v),'dictionaryHash'=>rr_audio_hash($d),'dictionary'=>$d,'voice'=>$voice,'voiceHash'=>rr_audio_hash($vp),'model'=>$config['model_id'],'settings'=>$safe,'characters'=>$count,'approval'=>null];
            unset($i['audioQueue']);$i['revision']++;
            return ['text'=>$text,'model_id'=>$config['model_id'],'voice_settings'=>$safe];
        }
        throw new InvalidArgumentException('Saken finnes ikke.');
    },$path);
    $result=[];
    try{$pcm=($transport??'rr_elevenlabs_request')($config,$voice,$payload);$wav=rr_audio_wav($pcm);$result=['status'=>'needs_processing','raw'=>rr_audio_store($wav,'wav',$path),'measurements'=>rr_audio_measure($wav)];}
    catch(RRElevenLabsRejectedException $e){$result=['status'=>'failed','httpStatus'=>$e->httpStatus,'providerCode'=>$e->providerCode,'error'=>$e->getMessage()];}
    catch(InvalidArgumentException){$result=['status'=>'failed','error'=>'Leverandøren avviste forespørselen. Kontroller tilgang og bruksgrense.'];}
    catch(Throwable){$result=['status'=>'unknown','error'=>'Resultatet er uavklart. Administrator må kontrollere leverandørhistorikken.'];}
    studio_board_change(static function(&$b)use($id,$profile,$token,$result,$config){
        foreach($b['items']as&$i)if(($i['id']??'')===$id){$v=&$i['audioScripts'][$profile];if(($v['audio']['token']??'')!==$token||($v['audio']['status']??'')!=='generating')return;
            $v['audio']=array_replace($v['audio'],$result);if($result['status']==='needs_processing'&&!rr_audio_current($i,$v,$b,$config))$v['audio']['status']='stale';$i['revision']++;return;}
    },$path);
}
function rr_audio_resolve(string $id,int $revision,string $profile,array $input,array $user,?string $path=null):void {
    if(($user['role']??'')!=='admin'||($input['confirmed']??'')!=='1'||mb_strlen(trim((string)($input['resolution']??'')))<10)throw new InvalidArgumentException('Administrator må dokumentere kontroll av leverandørhistorikken.');
    studio_board_change(static function(&$b)use($id,$revision,$profile,$input,$user){foreach($b['items']as&$i)if(($i['id']??'')===$id){$a=&$i['audioScripts'][$profile]['audio'];
        if(($i['revision']??0)!==$revision||!in_array($a['status']??'',['unknown','generating'],true))throw new InvalidArgumentException('Last siden på nytt.');
        if(($a['status']??'')==='generating'&&(strtotime($a['at'])?:0)>time()-120)throw new InvalidArgumentException('Vent til forespørselen er avsluttet.');
        $a['status']='resolved';$a['resolution']=['note'=>mb_substr(trim($input['resolution']),0,500),'actor'=>$user['name']??'Administrator','at'=>gmdate('c')];$i['revision']++;return;}
        throw new InvalidArgumentException('Saken finnes ikke.');},$path);
}
