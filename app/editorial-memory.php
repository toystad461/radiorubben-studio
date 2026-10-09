<?php
declare(strict_types=1);
require_once __DIR__ . '/board.php';
require_once __DIR__ . '/web-publish.php';

/** First profile. Rules are editorial preferences, never a source of facts. */
function studio_memory_context(array $board, string $program): array
{
    $profile=studio_program_registry()['programs'][$program]??null;
    if (!$profile || empty($profile['learningEnabled'])) return [];
    $rules = [];
    foreach ($board['editorialRules'] ?? [] as $rule) {
        if (($rule['status'] ?? '') === 'approved' && ($rule['program'] ?? '') === $program) {
            $rules[] = ['id'=>$rule['id'], 'version'=>$rule['revision'], 'text'=>$rule['text']];
        }
    }
    return ['profileVersion'=>$profile['profileVersion'], 'program'=>$program, 'name'=>$profile['name'],
        'style'=>$profile['style'], 'rules'=>$rules];
}

function studio_memory_change(string $action, array $input, array $user, ?string $path = null): void
{
    $role = $user['role'] ?? '';
    if (!in_array($role, ['admin', 'producer', 'presenter'], true)
        || (!in_array($action, ['propose','feedback'], true) && $role !== 'admin')) {
        throw new InvalidArgumentException('Rollen har ikke tilgang til denne handlingen.');
    }
    studio_board_change(static function (array &$board) use ($action, $input, $user): void {
        $actor = $user['name'] ?? 'Medarbeider';
        $board['editorialRules'] ??= [];
        if ($action === 'feedback') {
            $text = trim((string)($input['text'] ?? ''));
            if (studio_board_length($text) < 10 || studio_board_length($text) > 500
                || $text !== strip_tags($text) || preg_match('/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f\\x7f]/', $text))
                throw new InvalidArgumentException('Skriv en kommentar på 10–500 tegn uten HTML.');
            $itemId = (string)($input['item'] ?? '');
            $item = null;
            foreach ($board['items'] as $candidate) if (($candidate['id'] ?? '') === $itemId) $item = $candidate;
            if (!$item || ($item['status'] ?? '') === 'archived')
                throw new InvalidArgumentException('Saken finnes ikke i den aktive listen.');
            if (($item['revision'] ?? 0) !== (int)($input['itemRevision'] ?? 0))
                throw new InvalidArgumentException('Saken ble endret. Les siste versjon før du kommenterer.');
            if (($item['program'] ?? '') !== 'god-morgen-vestland')
                throw new InvalidArgumentException('Kommentarbasert læring støtter foreløpig God morgen Vestland.');
            $activate = ($input['apply'] ?? '') === '1';
            if ($activate && ($user['role'] ?? '') !== 'admin')
                throw new InvalidArgumentException('Bare administrator kan aktivere et skriveråd.');
            if ($activate && ($input['styleOnly'] ?? '') !== '1')
                throw new InvalidArgumentException('Bekreft at skriverådet gjelder språk og kildebruk, aldri nye fakta eller unntak fra kontroll.');
            foreach ($board['editorialRules'] as $rule)
                if (($rule['evidence']['kind'] ?? '') === 'feedback'
                    && ($rule['evidence']['itemId'] ?? '') === $itemId
                    && ($rule['evidence']['revision'] ?? 0) === $item['revision']
                    && $rule['text'] === $text)
                    throw new InvalidArgumentException('Denne kommentaren er allerede lagret.');
            if (count($board['editorialRules']) >= 300)
                throw new InvalidArgumentException('Læringsregisteret er fullt.');
            if ($activate && count(array_filter($board['editorialRules'], static fn($r) => ($r['status'] ?? '') === 'approved')) >= 20)
                throw new InvalidArgumentException('Maksimalt 20 aktive skriveråd. Deaktiver et gammelt råd i Robåt – læring.');
            $source = $item['web']['check']['source'] ?? $item['sourceCheck']['source'] ?? [];
            $board['editorialRules'][] = [
                'id'=>bin2hex(random_bytes(8)), 'program'=>$item['program'], 'text'=>$text,
                'status'=>$activate ? 'approved' : 'pending', 'revision'=>1,
                'evidence'=>['kind'=>'feedback', 'itemId'=>$itemId, 'revision'=>$item['revision'],
                    'title'=>$item['title'], 'before'=>studio_memory_feedback_text($item),
                    'after'=>$text, 'radio'=>(string)($item['script'] ?? ''),
                    'sourceUrl'=>$item['sourceUrl'] ?? '', 'sourceAt'=>$item['sourceAt'] ?? null,
                    'source'=>$source, 'webCheckStatus'=>$item['web']['check']['status'] ?? null,
                    'radioCheckStatus'=>$item['sourceCheck']['status'] ?? null],
                'createdAt'=>gmdate('c'), 'createdBy'=>$actor,
                'history'=>[['action'=>$activate ? 'feedback_activate' : 'feedback', 'at'=>gmdate('c'), 'actor'=>$actor]],
            ];
            return;
        }
        if ($action === 'propose') {
            $raw = $input['text'] ?? '';
            if (!is_string($raw) || strlen($raw) > 40000)
                throw new InvalidArgumentException('Skriv høyst 20 instrukser, én per linje.');
            $texts = [];
            foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
                $text = trim(preg_replace('/^\s*[-*•]\s+/u', '', $line));
                if ($text === '') continue;
                if (studio_board_length($text) < 10 || studio_board_length($text) > 500 || preg_match('/[\x00-\x1f\x7f]/', $text))
                    throw new InvalidArgumentException('Hver instruks må ha 10–500 tegn. Skriv én instruks per linje.');
                if (in_array($text, $texts, true))
                    throw new InvalidArgumentException('Samme instruks står flere ganger i listen.');
                $texts[] = $text;
            }
            if (!$texts || count($texts) > 20)
                throw new InvalidArgumentException('Skriv 1–20 instrukser, én per linje.');
            if (($input['styleOnly'] ?? '') !== '1')
                throw new InvalidArgumentException('Bekreft at regelen gjelder språk eller form, ikke fakta eller unntak fra kildekontroll.');
            $evidence = null;
            $itemId = (string)($input['item'] ?? '');
            if ($itemId !== '') {
                foreach ($board['items'] as $item) {
                    if ($item['id'] !== $itemId) continue;
                    if (($item['revision'] ?? 0) !== (int)($input['itemRevision'] ?? 0))
                        throw new InvalidArgumentException('Manuset ble endret. Last siden på nytt før du foreslår læring.');
                    $evidence=studio_memory_correction($item,(string)($input['channel']??'radio'));
                    if (!$evidence) throw new InvalidArgumentException('Læring krever en rettet AI-tekst med gjeldende kontroll og manuell godkjenning.');
                }
                if (!$evidence) throw new InvalidArgumentException('Fant ikke manuset.');
            }
            foreach ($board['editorialRules'] as $rule) {
                if (in_array($rule['status'], ['pending','approved'], true) && in_array($rule['text'], $texts, true))
                    throw new InvalidArgumentException('Denne regelen finnes allerede.');
            }
            if (count($board['editorialRules']) + count($texts) > 300)
                throw new InvalidArgumentException('Regelregisteret er fullt. Kontakt administrator.');
            foreach ($texts as $text) $board['editorialRules'][] = ['id'=>bin2hex(random_bytes(8)), 'program'=>'god-morgen-vestland',
                'text'=>$text, 'status'=>'pending', 'revision'=>1, 'evidence'=>$evidence,
                'createdAt'=>gmdate('c'), 'createdBy'=>$actor,
                'history'=>[['action'=>'propose', 'at'=>gmdate('c'), 'actor'=>$actor]]];
            return;
        }
        foreach ($board['editorialRules'] as &$rule) {
            if ($rule['id'] !== ($input['id'] ?? '')) continue;
            if ($rule['revision'] !== (int)($input['revision'] ?? 0))
                throw new InvalidArgumentException('Regelen ble endret. Last siden på nytt.');
            $next = match ($action) {
                'approve' => $rule['status'] === 'pending' ? 'approved' : null,
                'reject' => $rule['status'] === 'pending' ? 'rejected' : null,
                'disable' => $rule['status'] === 'approved' ? 'disabled' : null,
                default => null,
            };
            if (!$next) throw new InvalidArgumentException('Ugyldig regelendring.');
            if ($next === 'approved') {
                $active = array_filter($board['editorialRules'], static fn($row) => $row['status'] === 'approved');
                if (count($active) >= 20) throw new InvalidArgumentException('Maksimalt 20 aktive regler. Deaktiver en gammel regel først.');
            }
            $rule['history'][] = ['action'=>$action, 'at'=>gmdate('c'), 'actor'=>$actor,
                'previousStatus'=>$rule['status'], 'previousRevision'=>$rule['revision']];
            $rule['status'] = $next;
            $rule['revision']++;
            return;
        }
        throw new InvalidArgumentException('Regelen finnes ikke.');
    }, $path);
}

/** Persist the displayed draft as evidence, without introducing it into other stories. */
function studio_memory_feedback_text(array $item): string
{
    $w = $item['web'] ?? [];
    return implode("\n", array_filter([(string)($w['title'] ?? $item['title'] ?? ''),
        (string)($w['intro'] ?? ''), (string)($w['body'] ?? '')], static fn($v) => $v !== ''));
}

/** A saved correction is evidence only after the existing human approval gates. */
function studio_memory_correction(array $item, string $channel = 'radio'): ?array
{
    if (($item['program']??'') !== 'god-morgen-vestland' || ($item['status']??'') === 'archived') return null;
    if ($channel === 'web') {
        $w=$item['web']??[];
        if (empty($w['generatedOriginal']) || !studio_web_checked($item)
            || empty($w['approvedBy']) || ($w['approvedHash']??'')!==studio_web_approval_hash($item)) return null;
        $before=$w['generatedOriginal']; $after=studio_web_text($w); $check=$w['check']; $actor=$w['approvedBy'];
    } elseif ($channel === 'radio') {
        if (($item['status']??'')!=='ready' || empty($item['verified']) || empty($item['generatedOriginal'])
            || empty($item['approvedBy']) || !studio_news_radio_credit($item)
            || (isset($item['sourceCheck']) && !studio_news_check_current($item))) return null;
        $before=$item['generatedOriginal']; $after=$item['script']; $check=$item['sourceCheck']??[]; $actor=$item['approvedBy'];
    } else return null;
    if ($before===$after) return null;
    return ['kind'=>'approved_correction','channel'=>$channel,'itemId'=>$item['id'],'revision'=>$item['revision'],
        'title'=>$item['title'],'before'=>$before,'after'=>$after,'approvedBy'=>$actor,
        'sourceUrl'=>$item['sourceUrl']??'','sourceAt'=>$item['sourceAt']??null,
        'sourceExcerpt'=>$item['summary']??'','source'=>$check['source']??[],
        'checkPolicy'=>$check['policy']??null,'stylebookVersion'=>RR_STYLEBOOK_VERSION,
        'beforeSha256'=>hash('sha256',$before),'afterSha256'=>hash('sha256',$after)];
}
