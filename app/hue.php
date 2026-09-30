<?php
declare(strict_types=1);
function hue_config(): array {
    $path=dirname(__DIR__).'/config/hue.private.json';
    $c=is_file($path) ? json_decode(file_get_contents($path),true) : null;
    if (!is_array($c) || !filter_var($c['host']??'',FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)
        || !preg_match('/^[a-f0-9]{16}$/D',$c['bridge_id']??'') || !is_string($c['key']??null)
        || !preg_match('/^[a-zA-Z0-9_-]+$/D',$c['key']) || !is_array($c['lights']??null) || !$c['lights']) throw new RuntimeException('Hue er ikke konfigurert.');
    foreach ($c['lights'] as $id) if (!is_string($id) || !ctype_digit($id)) throw new RuntimeException('Ugyldig lampevalg.');
    return $c;
}
function hue_request(array $c,string $method,string $path,?array $body=null): array {
    $h=curl_init('https://'.$c['bridge_id'].'/api/'.$c['key'].$path);
    curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_CAINFO=>dirname(__DIR__).'/config/hue-bridge.pem',
        CURLOPT_RESOLVE=>[$c['bridge_id'].':443:'.$c['host']],CURLOPT_NOPROXY=>'*',CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>3,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
    if ($body!==null) curl_setopt($h,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));
    $raw=curl_exec($h); $status=curl_getinfo($h,CURLINFO_HTTP_CODE); curl_close($h);
    $data=is_string($raw)?json_decode($raw,true):null;
    if ($status!==200 || !is_array($data)) throw new RuntimeException('Hue Bridge svarer ikke.');
    foreach ($data as $row) if (is_array($row) && isset($row['error'])) throw new RuntimeException('Hue kunne ikke utføre lysendringen.');
    return $data;
}
function hue_snapshot(array $state): array {
    $out=['on'=>(bool)($state['on']??false)];
    if(isset($state['bri'])) $out['bri']=$state['bri'];
    foreach (match($state['colormode']??'') {'xy'=>['xy'],'ct'=>['ct'],'hs'=>['hue','sat'],default=>[]} as $key) if(isset($state[$key])) $out[$key]=$state[$key];
    if(isset($state['effect'])) $out['effect']=$state['effect'];
    return $out;
}
function hue_save_state(string $path,array $state): void {
    if(file_put_contents($path,json_encode($state,JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Kan ikke lagre opprinnelig lysstatus.');
    chmod($path,0600);
}
// Lock spans snapshot + writes, so a second tab never snapshots the red warning as the original scene.
function hue_sync(array $c,string $owner,?bool $live,bool $watchdog=false,?callable $transport=null,?string $dir=null,string $action='sync'): array {
    $dir??=dirname(__DIR__).'/config'; $path=$dir.'/hue-state.json';
    $lock=fopen($dir.'/hue.lock','c'); if(!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Hue er opptatt.');
    chmod($dir.'/hue.lock',0600);
    $request=$transport??fn($method,$url,$body=null)=>hue_request($c,$method,$url,$body);
    try {
        $state=is_file($path)?json_decode(file_get_contents($path),true):[];
        if(!is_array($state)) throw new RuntimeException('Hue-gjenoppretting må kontrolleres.');
        if($action==='off') {
            // Persist inhibition before the bridge call: watchdogs and other tabs must not restore lights.
            hue_save_state($path,['manual_off'=>true]);
            $request('PUT','/groups/0/action',['on'=>false]);
            return ['active'=>false,'manualOff'=>true,'message'=>'Slå av-kommando sendt til alle Hue-lys. MIC LIVE-lys er pauset.'];
        }
        if($action==='resume' && !$watchdog && ($state['manual_off']??false)) {
            $state=[];hue_save_state($path,$state);
        }
        if($state['manual_off']??false) return ['active'=>false,'manualOff'=>true,'message'=>'MIC LIVE-lys er pauset etter Slå av alle lys. Slå på lysfølging for å fortsette.'];
        $restore=function()use(&$state,$path,$request){
            $failed=false;
            foreach($state['previous']??[] as $id=>$previous){
                try {
                    $lamp=$request('GET','/lights/'.$id);
                    if(($lamp['state']['reachable']??false)!==true) throw new RuntimeException('Lampe utilgjengelig.');
                    $request('PUT','/lights/'.$id.'/state',$previous+['transitiontime'=>2]);
                    unset($state['previous'][$id]); hue_save_state($path,$state);
                } catch(RuntimeException $e){ $failed=true; }
            }
            if(!$failed){$state=[];hue_save_state($path,$state);}
            else throw new RuntimeException('Venter på lampene for å gjenopprette tidligere lys.');
        };
        if(($state['previous']??[]) && ($state['expires']??0)<=time()) $restore();
        if($watchdog) return ['active'=>(bool)($state['previous']??[])];
        if(($state['previous']??[]) && ($state['owner']??'')!==$owner) throw new RuntimeException('Hue styres av en annen Studio-fane/økt.');
        if($live!==true){$restore();return ['active'=>false,'message'=>'Tidligere lys er gjenopprettet.'];}
        if($state['previous']??[]){$state['expires']=time()+15;hue_save_state($path,$state);return ['active'=>true,'message'=>'Rødt studiolys · følger MIC LIVE'];}
        $previous=[];
        foreach($c['lights'] as $id){
            $lamp=$request('GET','/lights/'.$id);$s=$lamp['state']??[];
            if(($s['reachable']??false)!==true || !isset($s['xy'])) throw new RuntimeException('Studiolampene må være tilgjengelige og støtte farger.');
            $previous[$id]=hue_snapshot($s);
        }
        $state=['owner'=>$owner,'expires'=>time()+15,'previous'=>$previous];hue_save_state($path,$state);
        try {foreach($c['lights'] as $id) $request('PUT','/lights/'.$id.'/state',['on'=>true,'bri'=>180,'xy'=>[0.675,0.322],'effect'=>'none','transitiontime'=>2]);}
        catch(RuntimeException $e){$state['expires']=0;hue_save_state($path,$state);$restore();throw $e;}
        return ['active'=>true,'message'=>'Rødt studiolys · følger MIC LIVE'];
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
