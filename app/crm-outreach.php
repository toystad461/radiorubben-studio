<?php
declare(strict_types=1);
require_once __DIR__.'/crm.php';
require_once __DIR__.'/crm-website.php';
require_once __DIR__.'/audio-storage.php';
require_once __DIR__.'/audio-profiles.php';
require_once __DIR__.'/integrations/ElevenLabs.php';

function studio_crm_digest(mixed $value): string { return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)); }
function studio_crm_business_hash(array $row): string {
    $facts=[]; foreach(['company','orgNumber','businessAddress','industry','website','email','contact','stage','registryEvidence'] as $key) $facts[$key]=$row[$key]??'';
    return studio_crm_digest($facts);
}
function studio_crm_proposal_hash(array $row): string {
    $p=$row['proposal']??[];
    $parts=[studio_crm_business_hash($row),$p['version']??0,$p['subject']??'',$p['intro']??'', $p['script']??'', $p['sponsor']??'',rr_audio_disclosure()];
    if(isset($p['generation']))$parts[]=$p['generation'];
    return studio_crm_digest($parts);
}
function studio_crm_proposal_current(array $row): bool {
    return isset($row['proposal']) && studio_crm_website_source_current($row) && ($row['proposal']['businessHash']??'')===studio_crm_business_hash($row)
        && !in_array($row['stage'],['declined','archived','paused'],true);
}
function studio_crm_demo_current(array $row): bool {
    return studio_crm_proposal_current($row) && ($row['proposal']['scriptApproval']['hash']??'')===studio_crm_proposal_hash($row) && ($row['proposal']['demo']['status']??'')==='ready'
        && ($row['proposal']['demo']['scriptHash']??'')===studio_crm_proposal_hash($row);
}
function studio_crm_proposal_defaults(array $row): array {
    $name=$row['company'];
    $site=$row['website']??'';
    return ['subject'=>'Et uforpliktende reklameforslag fra Radio Rubben',
        'intro'=>"Hei!\n\nVi i Radio Rubben ønsker å bli kjent med dere i {$name}. Vi har laget et lite forslag til hvordan dere kan presenteres på radio. Dette er en uforpliktende skisse, og vi tilpasser gjerne budskap og uttale sammen med dere.\n\nKunne en kort prat om reklame eller programsamarbeid være interessant?\n\nVennlig hilsen\nRadio Rubben",
        'script'=>"Bli bedre kjent med {$name}.".($site!==''?' Besøk hjemmesiden deres for å finne ut mer.':' Ta kontakt med bedriften for å finne ut mer.')." Dette er et eksempel på hvordan en kort presentasjon kan høres ut på Radio Rubben. Budskapet tilpasses i samarbeid med dere.",
        'sponsor'=>"Forslag til programsamarbeid: {$name} kan få en kort, tydelig merket sponsoromtale rundt en avtalt programflate. Program, periode og omfang avtales nærmere. Ingen sponsoravtale er inngått."];
}
/** All decisions happen under the existing CRM lock, using the card's revision. */
function studio_crm_outreach_apply(string $action,array $input,array $user,?string $path=null,?array $prepared=null): void {
    if(!studio_crm_allowed($user))throw new InvalidArgumentException('Ingen tilgang.');
    $id=studio_crm_text($input,'id',16); $revision=studio_crm_text($input,'revision',12);
    if(!preg_match('/^[1-9][0-9]*$/D',$revision))throw new InvalidArgumentException('Last siden på nytt.');
    studio_crm_change(static function(&$data)use($action,$input,$user,$id,$revision,$path,$prepared){
        foreach($data['records'] as &$row){
            if($row['id']!==$id)continue;
            if($row['revision']!==(int)$revision)throw new InvalidArgumentException('Kortet er endret. Last siden på nytt før du fortsetter.');
            if(count($row['history'])>=5000)throw new InvalidArgumentException('Historikken er full.');
            if(in_array($row['stage'],['declined','archived','paused'],true))throw new InvalidArgumentException('Kortet er på vent, avslått eller arkivert. Ingen forslag klargjøres.');
            $p=$row['proposal']??[]; $job=$p['demo']??[];
            if(in_array($job['status']??'',['queued','generating','unknown'],true)&&$action!=='resolve')throw new InvalidArgumentException('Lydjobben må fullføres eller avklares først.');
            if(in_array($action,['draft','save','generated'],true)){
                if($action==='draft'&&$p)throw new InvalidArgumentException('Et utkast finnes allerede. Rediger det nedenfor.');
                $fields=in_array($action,['draft','generated'],true)?studio_crm_proposal_defaults($row):[
                    'subject'=>studio_crm_text($input,'subject',180),'intro'=>studio_crm_text($input,'intro',4000,true),
                    'script'=>studio_crm_text($input,'script',1500,true),'sponsor'=>studio_crm_text($input,'sponsor',2000,true)];
                if($action==='generated'){
                    if(!$prepared||empty($prepared['generation'])||empty($prepared['script']))throw new InvalidArgumentException('Utkastet må lages fra hjemmesiden.');
                    $fields['script']=$prepared['script'];
                    if($p)foreach(['subject','intro','sponsor']as$key)$fields[$key]=$p[$key];
                }
                foreach($fields as $v)if(trim($v)==='')throw new InvalidArgumentException('Alle tekstfeltene må fylles ut.');
                if($p)$row['proposalHistory'][]=$p;
                $row['proposal']=$fields+['version'=>($p['version']??0)+1,'businessHash'=>studio_crm_business_hash($row),'at'=>gmdate('c'),'templateVersion'=>'1.0','scriptApproval'=>null,'approval'=>null];
                if($action==='generated')$row['proposal']['generation']=$prepared['generation'];
                elseif($action==='save'&&isset($p['generation']))$row['proposal']['generation']=$p['generation'];
            }elseif($action==='approve_text'){
                if(!studio_crm_proposal_current($row)||($input['confirmed']??'')!=='1'||($input['recipient']??'')!=='1'||!filter_var($row['email'],FILTER_VALIDATE_EMAIL))
                    throw new InvalidArgumentException('Kontroller gjeldende tekst, kildegrunnlag og mottaker før teksteksport.');
                $row['proposal']['textApproval']=['hash'=>studio_crm_proposal_hash($row),'actor'=>$user['name']??'Administrator','at'=>gmdate('c')];
            }elseif($action==='approve_script'){
                if(!studio_crm_proposal_current($row)||($input['confirmed']??'')!=='1')throw new InvalidArgumentException('Kontroller fakta, nettside og hele teksten før manusgodkjenning.');
                $row['proposal']['scriptApproval']=['hash'=>studio_crm_proposal_hash($row),'actor'=>$user['name']??'Administrator','at'=>gmdate('c')];
            }elseif($action==='approve_package'){
                if(!studio_crm_demo_current($row)||($input['heard']??'')!=='1'||($input['recipient']??'')!=='1'||($input['disclosure']??'')!=='1')throw new InvalidArgumentException('Kontroller mottakeren, lytt til hele prøven og bekreft KI-merkingen.');
                if(!filter_var($row['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Legg inn riktig mottaker på bedriftskortet.');
                rr_audio_asset($job['asset'],$path??studio_crm_path());
                $row['proposal']['approval']=['hash'=>studio_crm_digest([studio_crm_proposal_hash($row),$job['asset']]),'actor'=>$user['name']??'Administrator','at'=>gmdate('c')];
            }elseif($action==='resolve'){
                if(!in_array($job['status']??'',['queued','generating','unknown'],true))throw new InvalidArgumentException('Ingen uavklart jobb.');
                if(($job['status']??'')==='generating'&&(strtotime($job['startedAt']??'')?:0)>time()-180)throw new InvalidArgumentException('Vent til lydforespørselen er ferdig.');
                $note=studio_crm_text($input,'resolution',500,true);
                if(($input['confirmed']??'')!=='1'||strlen($note)<10)throw new InvalidArgumentException('Dokumenter kontroll av leverandørhistorikken først.');
                $row['proposal']['demo']['status']='resolved';$row['proposal']['demo']['resolution']=$note;
            }else throw new InvalidArgumentException('Ukjent handling.');
            $row['history'][]=['kind'=>'outreach','text'=>'Forslagspakke: '.$action,'at'=>gmdate('c'),'actor'=>$user['name']??'Administrator'];
            $row['revision']++;$row['updatedAt']=gmdate('c');return;
        }
        throw new InvalidArgumentException('Bedriften finnes ikke.');
    },$path);
}
function studio_crm_demo_config(array $config,string $voice): void {
    if(($config['crm_demo_enabled']??false)!==true||empty($config['api_key'])||!in_array($config['model_id']??'',['eleven_v4','eleven_multilingual_v2'],true))throw new InvalidArgumentException('CRM-lydprøver må konfigureres privat med eget budsjett før bruk.');
    $v=$config['voices'][$voice]??[];
    if(!preg_match('/^[a-zA-Z0-9]{10,80}$/D',$voice)||!in_array($voice,$config['crm_demo_voice_ids']??[],true)||($v['approved']??false)!==true||empty($v['rights_reference']))throw new InvalidArgumentException('Velg en stemme som er godkjent for kommersielle CRM-demoer.');
}
function studio_crm_demo_queue(array $input,array $user,array $config,?string $path=null): void {
    if(!studio_crm_allowed($user))throw new InvalidArgumentException('Ingen tilgang.');
    $voice=studio_crm_text($input,'voice',80);studio_crm_demo_config($config,$voice);
    $id=studio_crm_text($input,'id',16);$revision=studio_crm_text($input,'revision',12);
    studio_crm_change(static function(&$data)use($id,$revision,$voice,$config,$user){
        // One outstanding CRM call globally. Ambiguous results never auto-retry.
        foreach($data['records'] as $other)if(in_array($other['proposal']['demo']['status']??'',['queued','generating','unknown'],true))throw new InvalidArgumentException('En CRM-lydjobb venter, arbeider eller må avklares.');
        foreach($data['records'] as &$row){
            if($row['id']!==$id)continue;
            if((string)$row['revision']!==$revision||!studio_crm_proposal_current($row)||($row['proposal']['scriptApproval']['hash']??'')!==studio_crm_proposal_hash($row))throw new InvalidArgumentException('Godkjenn gjeldende manus før lydprøven bestilles.');
            $p=&$row['proposal'];$text=rr_audio_disclosure()['text']."\n\n".$p['script'];
            $characters=mb_strlen($text);$day=gmdate('Y-m-d');$used=$data['demoUsage'][$day]??0;
            if($characters>2000||$characters+$used>(int)($config['crm_demo_daily_character_limit']??0))throw new InvalidArgumentException('CRM-budsjettet for lydprøver er ikke angitt eller er brukt opp.');
            if(isset($p['demo']))$p['demoHistory'][]=$p['demo'];
            $data['demoUsage'][$day]=$used+$characters;
            $p['demo']=['status'=>'queued','token'=>bin2hex(random_bytes(16)),'at'=>gmdate('c'),'scriptHash'=>studio_crm_proposal_hash($row),
                'text'=>$text,'characters'=>$characters,'voice'=>$voice,'voiceHash'=>studio_crm_digest($config['voices'][$voice]),'model'=>$config['model_id'],
                'synthetic'=>true,'disclosure'=>rr_audio_disclosure(),'actor'=>$user['name']??'Administrator'];
            $p['approval']=null;$row['revision']++;return;
        }throw new InvalidArgumentException('Bedriften finnes ikke.');
    },$path);
}
/** Separate job from card saving; claim under lock, then one bounded provider call outside it. */
function studio_crm_demo_run(string $id,string $token,array $user,array $config,?string $path=null,?callable $transport=null):void {
    if(!studio_crm_allowed($user))throw new InvalidArgumentException('Ingen tilgang.');
    $job=studio_crm_change(static function(&$data)use($id,$token,$config){
        foreach($data['records']as &$row)if($row['id']===$id){
            $job=&$row['proposal']['demo'];
            if(($job['status']??'')!=='queued'||!hash_equals($job['token']??'',$token))throw new InvalidArgumentException('Jobben er allerede startet eller ikke tilgjengelig.');
            studio_crm_demo_config($config,$job['voice']);
            if(!studio_crm_proposal_current($row)||$job['scriptHash']!==studio_crm_proposal_hash($row)||($row['proposal']['scriptApproval']['hash']??'')!==$job['scriptHash']
                ||$job['voiceHash']!==studio_crm_digest($config['voices'][$job['voice']])||$job['model']!==$config['model_id'])throw new InvalidArgumentException('Tekst, bedrift eller stemme er endret. Avklar jobben og godkjenn på nytt.');
            $job['status']='generating';$job['startedAt']=gmdate('c');$row['revision']++;return $job;
        }throw new InvalidArgumentException('Jobben finnes ikke.');
    },$path);
    try {
        $payload=['text'=>$job['text'],'model_id'=>$job['model'],'voice_settings'=>['stability'=>0.5,'similarity_boost'=>0.75,'speed'=>1.0]];
        $pcm=($transport??'rr_elevenlabs_request')($config,$job['voice'],$payload);
        $wav=rr_audio_wav($pcm);$measure=rr_audio_measure($wav);$asset=rr_audio_store($wav,'wav',$path??studio_crm_path());
        $ok=$measure['duration']>=15&&$measure['duration']<=20&&$measure['clippedSamples']===0&&$measure['silenceSeconds']<2;
        $result=['status'=>$ok?'ready':'needs_revision','asset'=>$asset,'measure'=>$measure,'completedAt'=>gmdate('c')];
    }catch(RRElevenLabsRejectedException $e){$result=['status'=>'failed','error'=>$e->getMessage()];}
    catch(Throwable $e){$result=['status'=>'unknown','error'=>'Resultatet må avklares i leverandørhistorikken før nytt forsøk.'];}
    studio_crm_change(static function(&$data)use($id,$token,$result){
        foreach($data['records']as &$row)if($row['id']===$id){
            $job=&$row['proposal']['demo'];
            if(($job['status']??'')!=='generating'||($job['token']??'')!==$token)return;
            $job=array_replace($job,$result);
            if(!studio_crm_proposal_current($row)||$job['scriptHash']!==studio_crm_proposal_hash($row)||($row['proposal']['scriptApproval']['hash']??'')!==$job['scriptHash'])$job['status']='stale';
            $row['revision']++;return;
        }
    },$path);
}
function studio_crm_eml(array $row,?string $path=null,?array $config=null):string {
    $p=$row['proposal']??[];$job=$p['demo']??[];
    if(!studio_crm_demo_current($row)||($p['approval']['hash']??'')!==studio_crm_digest([studio_crm_proposal_hash($row),$job['asset']??[]]))throw new InvalidArgumentException('Godkjenn gjeldende tekst, mottaker og lyd før eksport.');
    if(!filter_var($row['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$row['email']))throw new InvalidArgumentException('Ugyldig mottaker.');
    if ($config !== null) {
        studio_crm_demo_config($config, $job['voice']);
        if ($job['voiceHash'] !== studio_crm_digest($config['voices'][$job['voice']])) throw new InvalidArgumentException('Stemmegodkjenningen er endret. Lag og godkjenn en ny prøve.');
    }
    $wav=file_get_contents(rr_audio_asset($job['asset'],$path??studio_crm_path()));$boundary='rr-'.bin2hex(random_bytes(16));
    $text=studio_crm_message($p,true);
    $b64=static fn(string $s):string=>rtrim(chunk_split(base64_encode($s),76,"\r\n"));
    return "X-Unsent: 1\r\nTo: ".$row['email']."\r\nSubject: ".mb_encode_mimeheader($p['subject'],'UTF-8','B',"\r\n",9)."\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n"
        ."--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".$b64($text)."\r\n"
        ."--{$boundary}\r\nContent-Type: audio/wav\r\nContent-Disposition: attachment; filename=\"Radio-Rubben-demoforslag.wav\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".$b64($wav)."\r\n--{$boundary}--\r\n";
}

/** One canonical script feeds both the email body and the existing TTS job. */
function studio_crm_message(array $p,bool $audio=false):string
{
    return $p['intro']."\n\nFORSLAG TIL REKLAMEMANUS\n".$p['script']."\n\n".$p['sponsor']
        .(!empty($p['generation'])?"\n\nReklameutkastet er laget med KI-støtte og er et uforpliktende forslag.":"")
        .($audio?"\n\nVedlegg: uforpliktende demonstrasjonsutkast med KI-generert stemme. Ingen avtale eller sending er bestilt.":"");
}
function studio_crm_text_eml(array $row):string
{
    $p=$row['proposal']??[];
    if(!studio_crm_proposal_current($row)||($p['textApproval']['hash']??'')!==studio_crm_proposal_hash($row))
        throw new InvalidArgumentException('Godkjenn gjeldende melding og mottaker før eksport.');
    if(!filter_var($row['email'],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$row['email']))throw new InvalidArgumentException('Ugyldig mottaker.');
    return "X-Unsent: 1\r\nTo: ".$row['email']."\r\nSubject: ".mb_encode_mimeheader($p['subject'],'UTF-8','B',"\r\n",9)
        ."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        .chunk_split(base64_encode(studio_crm_message($p)),76,"\r\n");
}
