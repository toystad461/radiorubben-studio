<?php
declare(strict_types=1);
require_once __DIR__ . '/editorial-memory.php';
require_once __DIR__ . '/story-script.php';

/** Code-owned templates: a request never supplies a path or an instruction file. */
function studio_broadcast_template(array $profile): array
{
    $files = ['morning-v1'=>__DIR__ . '/templates/morning-v1.json'];
    $file = $files[$profile['templateId'] ?? ''] ?? null;
    if (!$file) throw new InvalidArgumentException('Programmet har ingen sendemal ennå.');
    $template = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($template) || empty($template['blocks'])) throw new RuntimeException('Sendemalen kan ikke leses.');
    return $template;
}

function studio_broadcast_sources(array $board, string $program, array $ids): array
{
    if (count($ids) > 5 || count(array_unique($ids, SORT_REGULAR)) !== count($ids))
        throw new InvalidArgumentException('Velg inntil fem forskjellige kildesaker.');
    $selected = [];
    foreach ($ids as $id) {
        if (!is_string($id)) throw new InvalidArgumentException('Ugyldig kildevalg.');
        $found = null;
        foreach (studio_board_active($board) as $item) {
            if (($item['id'] ?? '') === $id) { $found = $item; break; }
        }
        if (!$found || ($found['program'] ?? '') !== $program)
            throw new InvalidArgumentException('Kilden finnes ikke i valgt program. Last siden på nytt.');
        studio_story_script_context($found); // Same factual minimum as Sending.
        $selected[] = $found;
    }
    return $selected;
}

/** Build a draft outside the lock; persist only if source/profile snapshots still match. */
function studio_broadcast_create(array $input, array $user, array $config, ?string $path = null,
    ?callable $request = null, ?array $registry = null): string
{
    if (!in_array($user['role'] ?? '', ['admin','producer','presenter'], true))
        throw new InvalidArgumentException('Rollen kan bare lese sendeforslag.');
    $program = $input['program'] ?? null;
    $date = $input['date'] ?? null;
    $presenter = $input['presenter'] ?? null;
    $token = $input['token'] ?? null;
    if (!is_string($program) || !is_string($date) || !is_string($presenter)
        || !is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)
        || !is_array($input['sources'] ?? [])) throw new InvalidArgumentException('Ugyldig sendeforslag.');
    $profile = studio_program_profile($program, $registry);
    if (!$profile) throw new InvalidArgumentException('Velg et program med sendemal.');
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Oslo'));
    if (!$day || $day->format('Y-m-d') !== $date) throw new InvalidArgumentException('Velg en gyldig sendedato.');
    $presenter = trim($presenter);
    if ($presenter === '' || studio_board_length($presenter) > 80 || preg_match('/[\x00-\x1f\x7f]/', $presenter))
        throw new InvalidArgumentException('Skriv programledernavn på inntil 80 tegn.');
    $board = studio_board_read($path);
    foreach ($board['broadcastDrafts'] ?? [] as $old) if ($old['requestToken'] === $token) return $old['id'];
    if (count($board['broadcastDrafts'] ?? []) >= 50) throw new InvalidArgumentException('Arkivet har 50 sendeforslag. Kontakt administrator før nye opprettes.');
    $sources = studio_broadcast_sources($board, $program, $input['sources'] ?? []);
    $editorial = studio_memory_context($board, $program, $registry);
    $template = studio_broadcast_template($profile);
    $stories = [];
    foreach ($sources as $source) {
        $script = trim((string)($source['script'] ?? ''));
        $generated = false;
        if ($script === '' && ($input['generateNews'] ?? '') === '1') {
            $script = studio_story_script_generate($source, $config, $request, $editorial);
            $generated = true;
        }
        $stories[] = ['id'=>$source['id'], 'sourceRevision'=>$source['revision'],
            'sourceSnapshot'=>$source, 'script'=>$script, 'status'=>'draft', 'verified'=>false,
            'generation'=>$generated ? ['model'=>$config['openai_model'], 'editorial'=>$editorial] : null,
            'scriptOrigin'=>$generated ? 'ai' : ($script !== '' ? 'sending-snapshot' : 'missing')];
    }
    $blocks = $template['blocks'];
    foreach ($blocks as &$block) {
        $block['script'] = str_replace('{{presenter}}', $presenter, $block['script']);
        $block['storyIds'] = !empty($block['news']) ? array_column($stories, 'id') : [];
    }
    unset($block);
    $draft = ['id'=>bin2hex(random_bytes(8)), 'requestToken'=>$token, 'program'=>$program,
        'date'=>$date, 'presenter'=>$presenter, 'name'=>$profile['name'], 'status'=>'draft',
        'createdAt'=>gmdate('c'), 'createdBy'=>$user['name'] ?? 'Medarbeider',
        'editorial'=>$editorial, 'templateVersion'=>$template['version'], 'templateId'=>$template['id'],
        'theme'=>$template['theme'], 'timezone'=>$template['timezone'],
        'blocks'=>$blocks, 'stories'=>$stories, 'reserves'=>$template['reserves'],
        'timingStatus'=>'unmeasured', 'archiveStatus'=>'unknown',
        'reviewNotes'=>['Alle nyheter må kontrolleres for sendedatoen; utvalget hentes fra Sending, ikke et nytt nettsøk.',
            'Nyhetsplassene viser samme utvalg som utgangspunkt. Prioriter og varier før hver bulletin.',
            'Vær og trafikk er tomme serviceplasser inntil ferske opplysninger er kontrollert.',
            'Musikken er forslag. Filtilgang, versjon, spilletid og reservespill må kontrolleres i Musikkontroll.',
            'Programlederstikk kommer fra den faste malen. Godkjente læringsregler brukes ved AI-generering av nyhetsmanus.',
            'Ingen automatisk godkjenning, publisering eller avspilling.']];
    return studio_board_change(static function (array &$current) use ($draft, $sources, $input, $program, $editorial, $registry): string {
        foreach ($current['broadcastDrafts'] ?? [] as $old) if ($old['requestToken'] === $draft['requestToken']) return $old['id'];
        if (count($current['broadcastDrafts'] ?? []) >= 50) throw new InvalidArgumentException('Sendeforslagsarkivet er fullt.');
        if (studio_broadcast_sources($current, $program, $input['sources'] ?? []) !== $sources
            || studio_memory_context($current, $program, $registry) !== $editorial)
            throw new InvalidArgumentException('Kildene eller programreglene er endret. Lag et nytt forslag fra oppdatert grunnlag.');
        // Keep AI drafts editable in Sending and eligible for the existing correction workflow.
        // This runs under the same lock as the plan: failure never leaves a partial batch.
        foreach ($draft['stories'] as &$story) {
            if ($story['scriptOrigin'] !== 'ai') continue;
            foreach ($current['items'] as &$item) {
                if ($item['id'] !== $story['id']) continue;
                $before = $item; unset($before['history']);
                $item['script'] = $story['script'];
                $item['generatedOriginal'] = $story['script'];
                $item['generation'] = $story['generation'];
                $item['generatedAt'] = gmdate('c');
                $item['status'] = 'draft'; $item['verified'] = false; $item['approvedBy'] = null;
                $item['history'][] = ['action'=>'generated', 'at'=>gmdate('c'), 'actor'=>$draft['createdBy'], 'before'=>$before];
                $item['updatedAt'] = gmdate('c'); $item['revision']++;
                $story['sourceSnapshot'] = $item; $story['sourceRevision'] = $item['revision'];
                break;
            }
            unset($item);
        }
        unset($story);
        $current['broadcastDrafts'][] = $draft;
        return $draft['id'];
    }, $path);
}

function studio_broadcast_export(array $draft): string
{
    $text = $draft['name'] . ' · ' . $draft['date'] . " · UTKAST\n";
    $text .= 'Tema: ' . $draft['theme'] . "\nTidssone: " . $draft['timezone'] . "\n";
    $text .= 'Profilversjon ' . $draft['editorial']['profileVersion'] . ' / malversjon ' . $draft['templateVersion'] . "\n";
    foreach ($draft['reviewNotes'] as $note) $text .= 'MERK: ' . $note . "\n";
    $stories = array_column($draft['stories'], null, 'id');
    foreach ($draft['blocks'] as $block) {
        $text .= "\n" . $block['start'] . '–' . $block['end'] . ' ' . $block['title'] . "\n" . $block['script'] . "\n";
        if ($block['news'] && !$block['storyIds']) $text .= "NYHETSPLASS: Ingen kilder valgt.\n";
        foreach ($block['storyIds'] as $id) {
            $story = $stories[$id]; $source = $story['sourceSnapshot'];
            $text .= 'NYHETSUTKAST: ' . $source['title'] . "\n" . ($story['script'] ?: '[Manus mangler]') . "\n";
            $text .= $source['sourceName'] . ' | ' . $source['sourceUrl'] . ' | publisert ' . $source['sourceAt'] . "\n";
        }
        if ($block['service']) $text .= "SERVICEPLASS: Krever ferske kontrollerte vær-/trafikkdata.\n";
        foreach ($block['music'] as $song) $text .= 'MUSIKKFORSLAG: ' . $song['artist'] . ' – ' . $song['title'] . " [fil/spilletid ukjent]\n";
        $text .= $block['note'] . "\n";
    }
    $text .= "\nRESERVER – FILTILGANG UKJENT\n";
    foreach ($draft['reserves'] as $song) $text .= $song['artist'] . ' – ' . $song['title'] . "\n";
    return $text;
}
