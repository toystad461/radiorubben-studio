<?php
declare(strict_types=1);
function studio_weather(?array $place = null): array {
    $lat=round((float)($place['lat'] ?? (getenv('STUDIO_WEATHER_LAT') ?: '59.793')),4);
    $lon=round((float)($place['lon'] ?? (getenv('STUDIO_WEATHER_LON') ?: '5.172')),4);
    if(abs($lat)>90 || abs($lon)>180) throw new RuntimeException('Ugyldig værsted.');
    $file=sys_get_temp_dir().'/rubben-weather-'.hash('sha256',__DIR__."$lat,$lon").'.json';
    $handle=fopen($file,'c+');if(!$handle || !flock($handle,LOCK_EX))throw new RuntimeException('Værdata er utilgjengelige.');
    chmod($file,0600);
    try {
        $cache=json_decode(stream_get_contents($handle),true) ?: [];
        if(($cache['expires']??0)<=time()) {
            $headers=[];$h=curl_init("https://api.met.no/weatherapi/locationforecast/2.0/compact?lat=$lat&lon=$lon");
            curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_ENCODING=>'',CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'RadioRubbenStudio/1.0 https://www.radiorubben.no/kontakt/',CURLOPT_HTTPHEADER=>isset($cache['modified'])?['If-Modified-Since: '.$cache['modified']]:[],CURLOPT_HEADERFUNCTION=>function($h,$line)use(&$headers){$parts=explode(':',$line,2);if(count($parts)===2)$headers[strtolower(trim($parts[0]))]=trim($parts[1]);return strlen($line);}]);
            $raw=curl_exec($h);$status=curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
            $data=is_string($raw)?json_decode($raw,true):null;
            if($status===200 && isset($data['properties']['timeseries'])) {
                $cache['data']=$data;$cache['fetched']=time();
                if(isset($headers['last-modified']))$cache['modified']=$headers['last-modified'];
            } elseif($status!==304) {$cache['failed']=true;}
            if($status===200 || $status===304)unset($cache['failed']);
            $cache['expires']=max(time()+600,strtotime($headers['expires']??'')?:0,strtotime($headers['retry-after']??'')?:0);
            rewind($handle);ftruncate($handle,0);fwrite($handle,json_encode($cache));
        }
        if($cache['failed']??false)throw new RuntimeException('Været kunne ikke oppdateres.');
        return studio_weather_summary($cache['data']??[],time())+['place'=>$place['name'] ?? (getenv('STUDIO_WEATHER_NAME')?:'Bømlo · Bremnes')];
    } finally {flock($handle,LOCK_UN);fclose($handle);}
}
function studio_weather_summary(array $data,int $now): array {
    $best=null;$distance=PHP_INT_MAX;
    foreach($data['properties']['timeseries']??[] as $row){$time=strtotime($row['time']??'');if($time===false)continue;$diff=abs($time-$now);if($diff<$distance){$best=$row;$distance=$diff;}}
    $temp=$best['data']['instant']['details']['air_temperature']??null;
    if($distance>5400 || !is_numeric($temp))throw new RuntimeException('Ingen fersk værprognose.');
    $updated=strtotime($data['properties']['meta']['updated_at']??'');
    if($updated===false || $updated>$now+300 || $updated<$now-21600)throw new RuntimeException('Værgrunnlaget er for gammelt.');
    return ['temperature'=>(float)$temp,'symbol'=>$best['data']['next_1_hours']['summary']['symbol_code']??$best['data']['next_6_hours']['summary']['symbol_code']??'unknown','time'=>$best['time'],'updated'=>$data['properties']['meta']['updated_at']??null];
}
