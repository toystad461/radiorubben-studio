<?php
declare(strict_types=1);
require_once __DIR__.'/crm-brreg.php';

/** Private partner registry. The entry point uses Studio's verified current_user(). */
function studio_crm_allowed(?array $user): bool { return ($user['role'] ?? '') === 'admin'; }
function studio_crm_stages(): array
{
    return ['candidate'=>'Kandidat', 'contacted'=>'Kontaktet', 'meeting'=>'Møte avtalt',
        'offer'=>'Tilbud sendt', 'active'=>'Aktiv partner', 'paused'=>'På vent',
        'declined'=>'Avslått', 'archived'=>'Arkivert'];
}
function studio_crm_channels(): array
{
    return ['phone'=>'Telefon', 'meeting'=>'Møte', 'email'=>'E-post', 'message'=>'Melding', 'note'=>'Notat'];
}
function studio_crm_path(): string { return dirname(__DIR__).'/config/crm.private.json'; }
function studio_crm_today(): string { return (new DateTimeImmutable('now', new DateTimeZone('Europe/Oslo')))->format('Y-m-d'); }
function studio_crm_read(?string $path = null): array
{
    $path ??= studio_crm_path();
    if (!is_file($path)) return ['schemaVersion'=>1, 'nextNumber'=>1, 'records'=>[], 'updatedAt'=>null];
    $data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || ($data['schemaVersion'] ?? null) !== 1
        || !is_int($data['nextNumber'] ?? null) || $data['nextNumber'] < 1
        || !is_array($data['records'] ?? null) || !array_is_list($data['records']))
        throw new RuntimeException('Ugyldig CRM-register.');
    foreach ($data['records'] as $row) {
        if (!is_array($row) || !is_string($row['id'] ?? null)
            || !preg_match('/^[a-f0-9]{16}$/D', $row['id']) || !is_int($row['revision'] ?? null)
            || !is_string($row['company'] ?? null) || !isset(studio_crm_stages()[$row['stage'] ?? ''])
            || !is_array($row['history'] ?? null)) throw new RuntimeException('Ugyldig CRM-kort.');
    }
    return $data;
}
/** Replace under the CRM lock, preserving the previous register on failure. */
function studio_crm_replace(string $temp, string $path): bool
{
    if (PHP_OS_FAMILY !== 'Windows') return rename($temp, $path);
    if (is_file($path) && !chmod($path, 0600)) return false;
    for ($attempt = 0; $attempt < 10; $attempt++) {
        if (@rename($temp, $path)) return true;
        if ($attempt < 9) usleep(20000);
    }
    return false;
}
function studio_crm_change(callable $change, ?string $path = null): mixed
{
    $path ??= studio_crm_path();
    $lock = fopen($path.'.lock', 'c');
    if (!$lock) throw new RuntimeException('CRM er midlertidig utilgjengelig.');
    $temp = null;
    try {
        if (!chmod($path.'.lock', 0600) || !flock($lock, LOCK_EX)) throw new RuntimeException('CRM kunne ikke låses.');
        $data = studio_crm_read($path);
        $result = $change($data);
        $data['updatedAt'] = gmdate('c');
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $temp = tempnam(dirname($path), '.crm-');
        if (!$temp || !chmod($temp, 0600) || file_put_contents($temp, $json) !== strlen($json)
            || !studio_crm_replace($temp, $path)) throw new RuntimeException('CRM kunne ikke lagres.');
        return $result;
    } finally {
        if ($temp && is_file($temp)) unlink($temp);
        flock($lock, LOCK_UN); fclose($lock);
    }
}
function studio_crm_text(array $input, string $key, int $max, bool $multiline = false): string
{
    $value = $input[$key] ?? '';
    if (!is_string($value)) throw new InvalidArgumentException('Ugyldig felt: '.$key.'.');
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    $length = preg_match_all('/./us', $value);
    if ($length === false || $length > $max || preg_match($multiline ? '/[\x00-\x08\x0b-\x1f\x7f]/' : '/[\x00-\x1f\x7f]/', $value))
        throw new InvalidArgumentException('Kontroller lengde og tegn i feltet '.$key.'.');
    return $value;
}
function studio_crm_date(string $value): string
{
    if ($value === '') return '';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Oslo'));
    if (!$date || $date->format('Y-m-d') !== $value || $value < '2000-01-01' || $value > '2100-12-31')
        throw new InvalidArgumentException('Velg en gyldig dato.');
    return $value;
}
function studio_crm_url(string $value, bool $oneDrive = false): string
{
    if ($value === '') return '';
    $url = parse_url($value);
    if (!filter_var($value, FILTER_VALIDATE_URL) || !is_array($url) || isset($url['user']) || isset($url['pass'])
        || !in_array($url['scheme'] ?? '', $oneDrive ? ['https'] : ['https', 'http'], true))
        throw new InvalidArgumentException('Bruk en gyldig nettadresse.');
    if ($oneDrive) {
        $host = strtolower($url['host'] ?? '');
        if (!in_array($host, ['1drv.ms', 'onedrive.live.com', 'onedrive.com'], true)
            && !str_ends_with($host, '.sharepoint.com') && !str_ends_with($host, '.onedrive.com'))
            throw new InvalidArgumentException('Lim inn en OneDrive- eller SharePoint-lenke.');
    }
    return $value;
}
function studio_crm_fields(array $input): array
{
    $fields = [];
    foreach (['company'=>180, 'contact'=>140, 'phone'=>40, 'email'=>200, 'website'=>1000,
        'owner'=>140, 'nextStep'=>500, 'followUp'=>10, 'opportunity'=>2000, 'oneDriveUrl'=>2000,
        'stage'=>20, 'priority'=>1, 'orgNumber'=>20, 'businessAddress'=>500, 'industry'=>500, 'organizationForm'=>200] as $key=>$max) $fields[$key] = studio_crm_text($input, $key, $max, $key === 'opportunity');
    if ($fields['company'] === '') throw new InvalidArgumentException('Skriv et bedriftsnavn.');
    if (!isset(studio_crm_stages()[$fields['stage']]) || !in_array($fields['priority'], ['1', '2', '3'], true))
        throw new InvalidArgumentException('Velg status og prioritet.');
    if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Kontroller e-postadressen.');
    if ($fields['phone'] !== '' && !preg_match('/^\+?[0-9 ()-]{5,40}$/D', $fields['phone'])) throw new InvalidArgumentException('Kontroller telefonnummeret.');
    $fields['website'] = studio_crm_url($fields['website']);
    $fields['oneDriveUrl'] = studio_crm_url($fields['oneDriveUrl'], true);
    $fields['followUp'] = studio_crm_date($fields['followUp']);
    if ($fields['followUp'] !== '' && $fields['nextStep'] === '') throw new InvalidArgumentException('Beskriv neste steg når du setter en oppfølgingsdato.');
    $fields['orgNumber'] = studio_crm_org_number($fields['orgNumber']);
    return $fields;
}
function studio_crm_normalize(string $name): string
{
    $name = preg_replace('/\s+/u', ' ', trim($name));
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower(strtr($name, ['Æ'=>'æ', 'Ø'=>'ø', 'Å'=>'å']));
}
function studio_crm_duplicate(array $records, array $fields, string $except = ''): bool
{
    foreach ($records as $row) if ($row['id'] !== $except && (studio_crm_normalize($row['company']) === studio_crm_normalize($fields['company']) || (!empty($fields['orgNumber']) && ($row['orgNumber']??'') === $fields['orgNumber']))) return true;
    return false;
}
function studio_crm_insert(array &$data, array $fields, array $user, string $reason = 'Bedrift opprettet'): string
{
    if (count($data['records']) >= 1000) throw new InvalidArgumentException('CRM har nådd grensen på 1000 bedriftskort.');
    if (studio_crm_duplicate($data['records'], $fields)) throw new InvalidArgumentException('Bedriften finnes allerede. Søk også i arkivet.');
    $id = bin2hex(random_bytes(8)); $now = gmdate('c');
    $data['records'][] = $fields + ['id'=>$id, 'number'=>$data['nextNumber']++, 'revision'=>1,
        'createdAt'=>$now, 'updatedAt'=>$now, 'lastContact'=>'',
        'history'=>[['kind'=>'created', 'at'=>$now, 'actor'=>$user['name'] ?? 'Administrator', 'text'=>$reason]]];
    return $id;
}
/** No email, network calls or editorial changes. Every mutation requires an administrator. */
function studio_crm_apply(string $action, array $input, array $user, ?string $path = null, ?array $registryEvidence = null): string
{
    if (!studio_crm_allowed($user)) throw new InvalidArgumentException('CRM er bare tilgjengelig for administrator.');
    if ($action === 'create') {
        $fields = studio_crm_fields($input);
        if ($registryEvidence && ($registryEvidence['fields']['orgNumber']??'') === $fields['orgNumber']) $fields['registryEvidence'] = $registryEvidence;
        return studio_crm_change(static fn(array &$data): string => studio_crm_insert($data, $fields, $user), $path);
    }
    if ($action === 'seed') {
        require_once __DIR__.'/crm-candidates.php';
        return studio_crm_change(static function (array &$data) use ($user): string {
            foreach (studio_crm_candidates() as $candidate) {
                $fields = studio_crm_fields($candidate + ['stage'=>'candidate', 'owner'=>$user['name'] ?? '', 'followUp'=>'', 'oneDriveUrl'=>'']);
                if (!studio_crm_duplicate($data['records'], $fields)) studio_crm_insert($data, $fields, $user,
                    'Kandidat fra oversikten 07.10.2026. Tidligere kontakt, interesse og budsjett er uavklart. Kilde: '.$fields['website']);
            }
            return '';
        }, $path);
    }
    if (!in_array($action, ['save', 'activity'], true)) throw new InvalidArgumentException('Ukjent handling.');
    $id = studio_crm_text($input, 'id', 16);
    $revision = $input['revision'] ?? '';
    if ((!is_int($revision) && !is_string($revision)) || !preg_match('/^[1-9][0-9]*$/D', (string)$revision)) throw new InvalidArgumentException('Ugyldig revisjon. Last siden på nytt.');
    return studio_crm_change(static function (array &$data) use ($action, $input, $user, $id, $revision, $registryEvidence): string {
        foreach ($data['records'] as &$row) {
            if ($row['id'] !== $id) continue;
            if ($row['revision'] !== (int)$revision) throw new InvalidArgumentException('Bedriftskortet er endret i en annen fane. Kopier teksten din og last siden på nytt.');
            $now = gmdate('c');
            if (count($row['history']) >= 5000) throw new InvalidArgumentException('Historikken er full. Kontakt administrator.');
            if ($action === 'save') {
                $fields = studio_crm_fields($input);
                if (studio_crm_duplicate($data['records'], $fields, $id)) throw new InvalidArgumentException('Et annet kort har samme bedriftsnavn.');
                $changes = [];
                foreach ($fields as $key=>$value) if (($row[$key] ?? '') !== $value) $changes[$key] = ['before'=>$row[$key] ?? '', 'after'=>$value];
                if (!$changes) return $id;
                if (isset($row['proposal'])) {
                    $row['proposal']['textApproval']=null; $row['proposal']['scriptApproval']=null; $row['proposal']['approval']=null;
                    if (isset($row['proposal']['demo']) && !in_array($row['proposal']['demo']['status']??'', ['queued','generating','unknown'], true)) $row['proposal']['demo']['status']='stale';
                }
                if (($row['orgNumber']??'') !== $fields['orgNumber']) unset($row['registryEvidence']);
                $row = array_replace($row, $fields);
                if ($registryEvidence && ($registryEvidence['fields']['orgNumber']??'') === $fields['orgNumber']) $row['registryEvidence'] = $registryEvidence;
                $event = ['kind'=>'updated', 'text'=>'Bedriftskort oppdatert', 'changes'=>$changes];
            } else {
                $channel = studio_crm_text($input, 'channel', 20);
                $text = studio_crm_text($input, 'text', 5000, true);
                $date = studio_crm_date(studio_crm_text($input, 'date', 10));
                if (!isset(studio_crm_channels()[$channel]) || $text === '' || $date === '' || $date > studio_crm_today())
                    throw new InvalidArgumentException('Velg kontakttype, faktisk dato og et notat. Fremtidige avtaler legges som neste oppfølging.');
                $event = ['kind'=>'activity', 'channel'=>$channel, 'date'=>$date, 'text'=>$text];
                if ($channel !== 'note' && $date > $row['lastContact']) $row['lastContact'] = $date;
            }
            $row['history'][] = $event + ['at'=>$now, 'actor'=>$user['name'] ?? 'Administrator'];
            $row['updatedAt'] = $now; $row['revision']++;
            return $id;
        }
        unset($row);
        throw new InvalidArgumentException('Bedriftskortet finnes ikke.');
    }, $path);
}
function studio_crm_due(array $row, ?string $today = null): bool
{
    return !in_array($row['stage'], ['paused', 'declined', 'archived'], true)
        && ($row['followUp'] ?? '') !== '' && $row['followUp'] <= ($today ?? studio_crm_today());
}
function studio_crm_list(array $records, string $query = '', string $stage = '', bool $due = false): array
{
    $query = studio_crm_normalize($query);
    $rows = array_values(array_filter($records, static function ($row) use ($query, $stage, $due): bool {
        if ($stage !== '' ? $row['stage'] !== $stage : $row['stage'] === 'archived') return false;
        if ($due && !studio_crm_due($row)) return false;
        return $query === '' || str_contains(studio_crm_normalize($row['company'].' '.$row['contact'].' '.$row['email'].' '.$row['phone'].' '.($row['orgNumber']??'')), $query);
    }));
    usort($rows, static fn($a, $b) => [!studio_crm_due($a), $a['followUp'] ?: '9999', $a['priority'], studio_crm_normalize($a['company'])]
        <=> [!studio_crm_due($b), $b['followUp'] ?: '9999', $b['priority'], studio_crm_normalize($b['company'])]);
    return $rows;
}

/** Match using the same rule as the locked duplicate guard, including archived cards. */
function studio_crm_matches(array $records, array $fields): array
{
    return array_values(array_filter($records, static fn($row) => studio_crm_duplicate([$row], $fields)));
}
function studio_crm_search_results(array $records, string $query, array $remote): array
{
    $results=[];
    foreach($records as $row) {
        if (!str_contains(studio_crm_normalize($row['company'].' '.($row['orgNumber']??'')),studio_crm_normalize($query))) continue;
        $results[]=['value'=>'card:'.$row['id'],'label'=>$row['company'].' · Allerede registrert · '.studio_crm_stages()[$row['stage']].(!empty($row['orgNumber'])?' · '.$row['orgNumber']:'').(!empty($row['businessAddress'])?' · '.$row['businessAddress']:'')];
        if(count($results)===20) break;
    }
    foreach($remote as $hit) {
        $matches=studio_crm_matches($records,$hit);
        if(count($matches)===1 && (empty($matches[0]['orgNumber']) || $matches[0]['orgNumber']===$hit['orgNumber'])) {
            $row=$matches[0]; $value='card:'.$row['id'];
            $index=array_search($value,array_column($results,'value'),true);
            if($index===false)$results[]=['value'=>$value,'label'=>$row['company'].' · Allerede registrert · '.studio_crm_stages()[$row['stage']].' · '.$hit['orgNumber'].' · '.$hit['place']];
            else {
                if(empty($row['orgNumber']))$results[$index]['label'].=' · '.$hit['orgNumber'];
                if(empty($row['businessAddress']) && $hit['place']!=='')$results[$index]['label'].=' · '.$hit['place'];
            }
            continue;
        }
        $results[]=['value'=>'org:'.$hit['orgNumber'],'label'=>$hit['company'].' · '.$hit['orgNumber'].' · '.$hit['place'].' · '.($matches?'Allerede registrert – oppdater kort':'Ny potensiell kunde')];
    }
    return $results;
}
