<?php
declare(strict_types=1);

function weather_overview_places(): array
{
    return [
        'bomlo'=>['name'=>'Bømlo', 'detail'=>'Bremnes / Svortland', 'lat'=>'59.7933', 'lon'=>'5.1728'],
        'stord'=>['name'=>'Stord', 'detail'=>'Leirvik', 'lat'=>'59.7798', 'lon'=>'5.5005'],
        'haugesund'=>['name'=>'Haugesund', 'detail'=>'Sentrum', 'lat'=>'59.4138', 'lon'=>'5.2680'],
        'bergen'=>['name'=>'Bergen', 'detail'=>'Sentrum', 'lat'=>'60.3913', 'lon'=>'5.3221'],
        'stavanger'=>['name'=>'Stavanger', 'detail'=>'Sentrum', 'lat'=>'58.9700', 'lon'=>'5.7331'],
    ];
}

function weather_overview_time(int $time, string $format = 'd.m H:i'): string
{
    return (new DateTimeImmutable('@'.$time))->setTimezone(new DateTimeZone('Europe/Oslo'))->format($format);
}

/** Temperature is instantaneous; symbols and precipitation describe the following interval. */
function weather_overview_parse(string $body, int $now): ?array
{
    if (strlen($body) > 2000000) return null;
    $json = json_decode($body, true);
    $meta = $json['properties']['meta'] ?? [];
    $updated = is_string($meta['updated_at'] ?? null) ? strtotime($meta['updated_at']) : false;
    $series = $json['properties']['timeseries'] ?? null;
    if ($updated === false || $updated > $now + 300 || !is_array($series)
        || ($meta['units']['air_temperature'] ?? '') !== 'celsius'
        || ($meta['units']['wind_speed'] ?? '') !== 'm/s'
        || ($meta['units']['precipitation_amount'] ?? '') !== 'mm') return null;
    $points = [];
    foreach ($series as $row) {
        $time = is_string($row['time'] ?? null) ? strtotime($row['time']) : false;
        $data = $row['data'] ?? [];
        $instant = $data['instant']['details'] ?? [];
        $temperature = $instant['air_temperature'] ?? null;
        if ($time === false || !is_numeric($temperature) || !is_finite((float)$temperature)
            || $temperature < -100 || $temperature > 65) continue;
        $hours = isset($data['next_1_hours']) ? 1 : (isset($data['next_6_hours']) ? 6 : 0);
        $period = $data['next_'.$hours.'_hours'] ?? [];
        $symbol = $period['summary']['symbol_code'] ?? '';
        $rain = $period['details']['precipitation_amount'] ?? null;
        $wind = $instant['wind_speed'] ?? null;
        $points[$time] = ['time'=>$time, 'temperature'=>(float)$temperature,
            'wind'=>is_numeric($wind) && $wind >= 0 && $wind <= 150 ? (float)$wind : null,
            'rain'=>is_numeric($rain) && $rain >= 0 && $rain <= 2000 ? (float)$rain : null,
            'hours'=>$hours, 'symbol'=>is_string($symbol) && preg_match('/^[a-z_]{1,60}$/D', $symbol) ? $symbol : ''];
    }
    if (!$points) return null;
    ksort($points, SORT_NUMERIC);
    return ['updatedAt'=>$updated, 'points'=>array_values($points)];
}

function weather_overview_symbol(string $symbol): array
{
    $base = preg_replace('/_(day|night|polartwilight)$/', '', $symbol);
    $known = ['clearsky'=>['☀️','klarvær'], 'fair'=>['🌤️','lettskyet'],
        'partlycloudy'=>['⛅','delvis skyet'], 'cloudy'=>['☁️','skyet'], 'fog'=>['🌫️','tåke']];
    if (isset($known[$base])) {
        if (str_ends_with($symbol, '_night') && in_array($base, ['clearsky','fair'], true)) return ['🌙',$known[$base][1]];
        return $known[$base];
    }
    if (preg_match('/^(light|heavy)?(rain|sleet|snow)(showers)?(andthunder)?$/D', (string)$base)
        || in_array($base, ['lightssleetshowersandthunder','lightssnowshowersandthunder'], true)) {
        if (str_contains($base, 'thunder')) return ['⛈️','nedbør og torden'];
        if (str_contains($base, 'snow')) return ['🌨️',str_contains($base,'showers') ? 'snøbyger' : 'snø'];
        if (str_contains($base, 'sleet')) return ['🌨️',str_contains($base,'showers') ? 'sluddbyger' : 'sludd'];
        return ['🌧️',str_contains($base,'showers') ? 'regnbyger' : 'regn'];
    }
    return ['—','Værtype ikke tilgjengelig'];
}

/** Fixed allowlist; bounded HTTPS requests, no browser-to-MET calls or user URLs. */
function weather_overview_request(array $place, string $modified): array
{
    if (!function_exists('curl_init')) return ['status'=>0];
    $url = 'https://api.met.no/weatherapi/locationforecast/2.0/compact?'.http_build_query(['lat'=>$place['lat'],'lon'=>$place['lon']]);
    $body = ''; $headers = [];
    $curl = curl_init($url);
    $requestHeaders = ['Accept: application/json'];
    if ($modified !== '' && !preg_match('/[\r\n]/', $modified)) $requestHeaders[] = 'If-Modified-Since: '.$modified;
    curl_setopt_array($curl, [CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>6, CURLOPT_ENCODING=>'',
        CURLOPT_HTTPHEADER=>$requestHeaders,
        CURLOPT_USERAGENT=>'RadioRubben-Studio-Weather/1.0 (+https://www.radiorubben.no/kontakt/)',
        CURLOPT_HEADERFUNCTION=>static function ($curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) { [$key,$value] = explode(':',$line,2); $headers[strtolower(trim($key))] = trim($value); }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION=>static function ($curl, string $chunk) use (&$body): int {
            if (strlen($body)+strlen($chunk)>2000000) return 0;
            $body .= $chunk; return strlen($chunk);
        }]);
    $ok = curl_exec($curl); $status = (int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    if (!$ok || ($status === 200 && !preg_match('~^application/(?:[\w.-]+\+)?json\b~i',$headers['content-type'] ?? ''))) return ['status'=>0];
    return ['status'=>$status,'body'=>$body,'headers'=>$headers];
}

/** Private cache, per-location locking, upstream expiry and conditional revalidation. */
function weather_overview_load(string $id, string $dir, int $now, ?callable $request = null): array
{
    $places = weather_overview_places();
    if (!isset($places[$id])) throw new InvalidArgumentException('Ukjent værsted.');
    $empty = ['status'=>'unavailable','forecast'=>null,'fetchedAt'=>null];
    $path = rtrim($dir,'/').'/weather-overview-'.$id.'.json';
    $handle = @fopen($path,'c+');
    if (!$handle) return $empty;
    if (!flock($handle,LOCK_EX | LOCK_NB)) { fclose($handle); return $empty; }
    @chmod($path,0600);
    try {
        $cache = json_decode(stream_get_contents($handle) ?: '',true);
        if (!is_array($cache)) $cache = [];
        if (($cache['nextCheck'] ?? 0) <= $now) {
            try { $response = ($request ?? 'weather_overview_request')($places[$id],$cache['modified'] ?? ''); }
            catch (Throwable $e) { $response = ['status'=>0]; }
            $headers = $response['headers'] ?? [];
            $forecast = ($response['status'] ?? 0) === 200 ? weather_overview_parse($response['body'] ?? '',$now) : null;
            $good = $forecast !== null || (($response['status'] ?? 0) === 304 && isset($cache['forecast']));
            if ($good) {
                if ($forecast !== null) { $cache['forecast']=$forecast; $cache['fetchedAt']=$now; }
                $cache['checkedAt']=$now; $cache['failed']=false;
                $expires = strtotime($headers['expires'] ?? '') ?: 0;
                $cache['nextCheck']=max($now+600,$expires)+random_int(5,45);
                if (isset($headers['last-modified'])) $cache['modified']=$headers['last-modified'];
            } else {
                $cache['failed']=true;
                $retry = $headers['retry-after'] ?? '';
                $retryAt = ctype_digit((string)$retry) ? $now+(int)$retry : (strtotime((string)$retry) ?: 0);
                $cache['nextCheck']=max($now+600,$retryAt)+random_int(5,45);
            }
            rewind($handle); ftruncate($handle,0);
            fwrite($handle,json_encode($cache,JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)); fflush($handle);
        }
        if (!isset($cache['forecast']['updatedAt'])) return $empty;
        $age = $now - (int)$cache['forecast']['updatedAt'];
        if ($age > 86400 || $age < -300) return $empty;
        $status = empty($cache['failed']) && $age <= 43200 && $now-($cache['checkedAt'] ?? 0) <= 43200 ? 'updated' : 'stale';
        return ['status'=>$status,'forecast'=>$cache['forecast'],'fetchedAt'=>$cache['fetchedAt'] ?? null];
    } finally { flock($handle,LOCK_UN); fclose($handle); }
}

function weather_overview_slots(?array $forecast, int $now): array
{
    $local = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Europe/Oslo'));
    $slots = ['now'=>null,'today'=>null,'tomorrow'=>null];
    $targets = ['today'=>$local->setTime(12,0)->getTimestamp(), 'tomorrow'=>$local->modify('+1 day')->setTime(12,0)->getTimestamp()];
    foreach ($forecast['points'] ?? [] as $point) {
        $time = $point['time'];
        // Nearest model hour, at most 30 minutes away; do not call old observations "now".
        if (abs($time-$now) <= 1800 && ($slots['now'] === null || abs($time-$now) < abs($slots['now']['time']-$now))) $slots['now']=$point;
        foreach ($targets as $key=>$target) if ($time === $target) $slots[$key]=$point;
    }
    return $slots;
}

function weather_overview_script(array $rows, int $now): string
{
    $sentences = ['Værutkast – '.weather_overview_time($now).' norsk tid. Kontroller før opplesing.'];
    foreach ($rows as $row) {
        $place = $row['place']['name'];
        if ($row['status'] !== 'updated') { $sentences[] = $place.': mangler ferskt værgrunnlag.'; continue; }
        $parts = [];
        foreach (['now'=>'nå','today'=>'i dag klokken 12','tomorrow'=>'i morgen klokken 12'] as $key=>$label) {
            $point = $row['slots'][$key];
            // Do not describe a passed noon forecast as a future forecast.
            if (!$point || ($key === 'today' && $point['time'] < $now)) continue;
            $parts[] = $label.' '.str_replace('.',',',(string)round($point['temperature'])).' grader';
        }
        $sentences[] = $place.': '.($parts ? implode(', ',$parts).'.' : 'prognose for valgt tidspunkt mangler.');
    }
    $sentences[] = 'Kilde: Meteorologisk institutt. Temperaturene er prognoser, ikke målinger.';
    return implode("\n\n",$sentences);
}
