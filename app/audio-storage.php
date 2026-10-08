<?php
declare(strict_types=1);
/** Private immutable assets. No supplied filesystem paths or public upload directory. */
function rr_audio_directory(?string $boardPath=null):string {
    $dir=dirname($boardPath??studio_board_path()).'/audio';
    if(is_link($dir)||(!is_dir($dir)&&!mkdir($dir,0700)))throw new RuntimeException('Lydlageret er utilgjengelig.');
    return $dir;
}
function rr_audio_store(string $bytes,string $extension,?string $boardPath=null):array {
    if(!in_array($extension,['wav','mp3'],true)||strlen($bytes)>24000000||$bytes==='')throw new RuntimeException('Ugyldig lydfil.');
    $name=bin2hex(random_bytes(16)).'.'.$extension;$path=rr_audio_directory($boardPath).'/'.$name;
    $h=fopen($path,'xb');if(!$h)throw new RuntimeException('Kunne ikke lagre lyd.');
    try {chmod($path,0600);if(fwrite($h,$bytes)!==strlen($bytes))throw new RuntimeException('Ufullstendig lydfil.');}finally{fclose($h);}
    return ['name'=>$name,'sha256'=>hash('sha256',$bytes),'bytes'=>strlen($bytes),'type'=>$extension==='wav'?'audio/wav':'audio/mpeg'];
}
function rr_audio_asset(array $asset,?string $boardPath=null):string {
    if(!preg_match('/^[a-f0-9]{32}\.(wav|mp3)$/D',(string)($asset['name']??'')))throw new RuntimeException('Ugyldig lydreferanse.');
    $p=rr_audio_directory($boardPath).'/'.$asset['name'];
    if(is_link($p)||!is_file($p)||filesize($p)!==($asset['bytes']??0)||!hash_equals((string)($asset['sha256']??''),hash_file('sha256',$p)))throw new RuntimeException('Lydfilen mangler eller er endret.');
    return $p;
}
function rr_audio_wav(string $pcm,int $rate=24000):string {
    if($pcm===''||strlen($pcm)%2||strlen($pcm)>12000000)throw new RuntimeException('Ugyldig PCM-lyd.');
    return 'RIFF'.pack('V',36+strlen($pcm)).'WAVEfmt '.pack('VvvVVvv',16,1,1,$rate,$rate*2,2,16).'data'.pack('V',strlen($pcm)).$pcm;
}
/** Accept only the PCM16 mono format produced by this pipeline. */
function rr_audio_measure(string $wav):array {
    if(strlen($wav)<44||substr($wav,0,4)!=='RIFF'||substr($wav,8,4)!=='WAVE')throw new RuntimeException('Ugyldig WAV-format.');
    $fmt=null;$pcm=null;
    for($offset=12;$offset+8<=strlen($wav);){
        $kind=substr($wav,$offset,4);$length=unpack('V',substr($wav,$offset+4,4))[1];$offset+=8;
        if($offset+$length>strlen($wav))throw new RuntimeException('Avkortet WAV-fil.');
        if($kind==='fmt '&&$length>=16)$fmt=unpack('vcodec/vchannels/Vrate/Vbytes/vblock/vbits',substr($wav,$offset,16));
        if($kind==='data')$pcm=substr($wav,$offset,$length);
        $offset+=$length+($length%2);
    }
    if(!$fmt||$fmt['codec']!==1||$fmt['channels']!==1||$fmt['bits']!==16||!in_array($fmt['rate'],[24000,44100,48000],true)||!is_string($pcm)||$pcm===''||strlen($pcm)%2)throw new RuntimeException('Krever PCM16 mono WAV.');
    $peak=0;$silent=0;$longest=0;$clipped=0;$energy=0;$count=strlen($pcm)/2;
    // Frame-based silence: individual zero crossings are not silence.
    $frame=max(1,(int)($fmt['rate']/100));$inFrame=0;$square=0;
    for($n=0;$n<strlen($pcm);$n+=2){$s=unpack('v',substr($pcm,$n,2))[1];if($s>=32768)$s-=65536;$a=abs($s);$peak=max($peak,$a);if($a>=32767)$clipped++;$square+=$s*$s;$energy+=$s*$s;$inFrame++;
        if($inFrame===$frame){$silent=sqrt($square/$frame)<104?$silent+$frame:0;$longest=max($longest,$silent);$square=0;$inFrame=0;}
    }
    return ['duration'=>$count/$fmt['rate'],'sampleRate'=>$fmt['rate'],'samplePeakDb'=>$peak?20*log10($peak/32768):-120.0,'silenceSeconds'=>$longest/$fmt['rate'],'clippedSamples'=>$clipped,'rmsDb'=>$energy?10*log10($energy/$count/(32768**2)):-120.0,'format'=>'pcm_s16le','channels'=>1];
}
