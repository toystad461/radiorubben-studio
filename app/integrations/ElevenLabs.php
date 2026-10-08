<?php
declare(strict_types=1);
class RRAudioUncertainException extends RuntimeException {}
/** Fixed transport: no client-selected host, redirects, credentials in URLs or provider error bodies in logs. */
function rr_elevenlabs_request(array $config,string $voice,array $payload):string {
    if(!preg_match('/^[a-zA-Z0-9]{10,80}$/D',$voice)||empty($config['api_key']))throw new InvalidArgumentException('TTS er ikke konfigurert.');
    $body='';$curl=curl_init('https://api.elevenlabs.io/v1/text-to-speech/'.$voice.'?output_format=pcm_24000');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_PROXY=>'',
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>60,
        CURLOPT_HTTPHEADER=>['xi-api-key: '.$config['api_key'],'Content-Type: application/json','Accept: application/octet-stream'],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
        CURLOPT_WRITEFUNCTION=>static function($h,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>12000000)return 0;$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($curl,CURLINFO_CONTENT_TYPE);curl_close($curl);
    return rr_elevenlabs_response($ok!==false,$status,$type,$body);
}
/** Classification is shared by the real transport and deterministic failure tests. */
function rr_elevenlabs_response(bool $ok,int $status,string $type,string $body):string {
    if(!$ok||$status>=500)throw new RRAudioUncertainException('TTS-resultatet er uavklart. Kontroller leverandørhistorikken før nytt forsøk.');
    if($status===429)throw new InvalidArgumentException('ElevenLabs avviser forespørselen på grunn av bruksgrense. Ingen automatisk gjentakelse.');
    if($status!==200)throw new InvalidArgumentException('ElevenLabs kunne ikke levere lyd. Kontroller tilgang og innstillinger.');
    if(!preg_match('~^(audio/|application/octet-stream)~i',$type)||strlen($body)<4800||strlen($body)%2!==0)throw new RRAudioUncertainException('TTS returnerte et uventet lydformat. Kontroller forespørselen før nytt forsøk.');
    return $body;
}
