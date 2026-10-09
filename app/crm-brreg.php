<?php
declare(strict_types=1);

/** Public business data only; no role/person endpoints. */
function studio_crm_org_number(string $value): string
{
    $value = preg_replace('/[ .-]/', '', trim($value));
    if ($value === '') return '';
    if (!preg_match('/^[0-9]{9}$/D', $value)) throw new InvalidArgumentException('Organisasjonsnummer må ha ni siffer.');
    $sum = 0;
    foreach ([3,2,7,6,5,4,3,2] as $i=>$weight) $sum += (int)$value[$i] * $weight;
    $check = (11 - $sum % 11) % 11;
    if ($check === 10 || $check !== (int)$value[8]) throw new InvalidArgumentException('Kontroller organisasjonsnummeret. Kontrollsifferet stemmer ikke.');
    return $value;
}
function studio_crm_brreg(string $org, ?callable $transport = null): array
{
    $org = studio_crm_org_number($org);
    if ($org === '') throw new InvalidArgumentException('Skriv organisasjonsnummeret først.');
    $url = 'https://data.brreg.no/enhetsregisteret/api/enheter/'.$org;
    [$status, $body] = studio_crm_brreg_request($url, $transport);
    if ($status === 404) throw new InvalidArgumentException('Ingen hovedenhet funnet. Bruk bedriftens organisasjonsnummer, ikke et avdelingsnummer.');
    if ($status !== 200 || strlen($body)>200000) throw new RuntimeException('Oppslaget er midlertidig utilgjengelig.');
    $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data) || ($data['organisasjonsnummer']??'') !== $org || !is_string($data['navn']??null)) throw new RuntimeException('Oppslaget returnerte uventede opplysninger.');
    if (!empty($data['slettedato']) || !empty($data['konkurs']) || !empty($data['underAvvikling']) || !empty($data['underTvangsavviklingEllerTvangsopplosning']))
        throw new InvalidArgumentException('Registeret viser sletting, konkurs eller avvikling. Kontroller bedriften manuelt.');
    $address = $data['forretningsadresse']??$data['postadresse']??[];
    $website = trim((string)($data['hjemmeside']??''));
    if ($website !== '' && !preg_match('~^https?://~i',$website)) $website = 'https://'.$website;
    if ($website !== '') {
        $parts = parse_url($website);
        if (!filter_var($website,FILTER_VALIDATE_URL) || !in_array($parts['scheme']??'', ['http','https'],true)
            || isset($parts['user']) || isset($parts['pass']) || strlen($website)>1000) $website = '';
    }
    // Keep a minimal, bounded source snapshot rather than directors, personal data or the entire response.
    $fields = ['orgNumber'=>$org, 'company'=>$data['navn'], 'website'=>$website,
        'businessAddress'=>implode(', ',array_merge($address['adresse']??[], [trim(($address['postnummer']??'').' '.($address['poststed']??''))])),
        'industry'=>(string)($data['naeringskode1']['beskrivelse']??''),
        'organizationForm'=>(string)($data['organisasjonsform']['beskrivelse']??'')];
    foreach ($fields as $key=>$value) {
        if (!is_string($value) || strlen($value)>1500 || preg_match('/[\x00-\x1f\x7f]/',$value)) throw new RuntimeException('Uventet registerfelt.');
    }
    return ['fields'=>$fields, 'source'=>$url, 'fetchedAt'=>gmdate('c'),
        'sha256'=>hash('sha256',json_encode($fields,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))];
}

function studio_crm_brreg_request(string $url, ?callable $transport = null): array
{
    if ($transport) { [$status, $body] = $transport($url); }
    else {
        $body = ''; $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_TIMEOUT=>12, CURLOPT_HTTPHEADER=>['Accept: application/json'],
            CURLOPT_USERAGENT=>'RadioRubbenStudio/1.0', CURLOPT_WRITEFUNCTION=>static function($c, string $chunk) use (&$body): int {
                if (strlen($body)+strlen($chunk)>200000) return 0;
                $body .= $chunk; return strlen($chunk);
            }]);
        $ok = curl_exec($c); $status = (int)curl_getinfo($c, CURLINFO_RESPONSE_CODE); curl_close($c);
        if ($ok === false) throw new RuntimeException('Brønnøysundregistrene svarte ikke. Prøv igjen senere; skjemaet ditt er beholdt.');
    }
    return [$status, $body];
}

function studio_crm_brreg_search(string $name, ?callable $transport = null): array
{
    $name = trim($name);
    if (mb_strlen($name)<2 || mb_strlen($name)>180 || preg_match('/[\x00-\x1f\x7f]/',$name)) throw new InvalidArgumentException('Skriv mellom 2 og 180 tegn.');
    $url='https://data.brreg.no/enhetsregisteret/api/enheter?'.http_build_query(['navn'=>$name,'size'=>10,'sort'=>'navn,asc']);
    [$status,$body]=studio_crm_brreg_request($url,$transport);
    if ($status!==200 || strlen($body)>200000) throw new RuntimeException('Navnesøket er utilgjengelig.');
    $data=json_decode($body,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($data) || !is_int($data['page']['totalElements']??null)) throw new RuntimeException('Uventet søkeresultat.');
    $rows=$data['_embedded']['enheter']??[];
    if (!is_array($rows) || !array_is_list($rows)) throw new RuntimeException('Uventet treffliste.');
    $results=[];
    foreach(array_slice($rows,0,10) as $row) {
        if (!is_array($row)) throw new RuntimeException('Uventet treff.');
        if (!empty($row['slettedato']) || !empty($row['konkurs']) || !empty($row['underAvvikling']) || !empty($row['underTvangsavviklingEllerTvangsopplosning'])) continue;
        $org=studio_crm_org_number((string)($row['organisasjonsnummer']??''));
        $company=$row['navn']??null; $place=$row['forretningsadresse']['poststed']??'';
        if ($org==='' || !is_string($company) || $company==='' || mb_strlen($company)>180 || !is_string($place) || mb_strlen($place)>100 || preg_match('/[\x00-\x1f\x7f]/',$company.$place)) throw new RuntimeException('Uventet treff.');
        $results[]=['orgNumber'=>$org,'company'=>$company,'place'=>$place];
    }
    return ['results'=>$results,'more'=>$data['page']['totalElements']>10];
}
