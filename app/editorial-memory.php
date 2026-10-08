<?php
declare(strict_types=1);
require_once __DIR__ . '/board.php';

/** First profile. Rules are editorial preferences, never a source of facts. */
function studio_memory_context(array $board, string $program): array
{
    if ($program !== 'god-morgen-vestland') return [];
    $rules = [];
    foreach ($board['editorialRules'] ?? [] as $rule) {
        if (($rule['status'] ?? '') === 'approved' && ($rule['program'] ?? '') === $program) {
            $rules[] = ['id'=>$rule['id'], 'version'=>$rule['revision'], 'text'=>$rule['text']];
        }
    }
    return ['profileVersion'=>1, 'program'=>$program, 'name'=>'God morgen Vestland',
        'style'=>'Varm, tydelig og muntlig morgenradio for Bømlo og Vestland. Rolig og respektfull tone i alvorlige saker. Ingen påfunnet lokal tilknytning eller humor i nyhetsfakta.',
        'rules'=>$rules];
}

function studio_memory_change(string $action, array $input, array $user, ?string $path = null): void
{
    $role = $user['role'] ?? '';
    if (!in_array($role, ['admin', 'producer', 'presenter'], true)
        || ($action !== 'propose' && $role !== 'admin')) {
        throw new InvalidArgumentException('Rollen har ikke tilgang til denne handlingen.');
    }
    studio_board_change(static function (array &$board) use ($action, $input, $user): void {
        $actor = $user['name'] ?? 'Medarbeider';
        $board['editorialRules'] ??= [];
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
                    if (($item['program'] ?? '') !== 'god-morgen-vestland' || $item['status'] !== 'ready'
                        || empty($item['verified']) || empty($item['generatedOriginal'])
                        || $item['generatedOriginal'] === $item['script'])
                        throw new InvalidArgumentException('Læring fra rettelser krever et rettet AI-utkast for God morgen Vestland som er kontrollert og merket klart.');
                    $evidence = ['itemId'=>$itemId, 'revision'=>$item['revision'], 'title'=>$item['title'],
                        'before'=>$item['generatedOriginal'], 'after'=>$item['script'],
                        'sourceUrl'=>$item['sourceUrl'], 'sourceAt'=>$item['sourceAt'],
                        'sourceExcerpt'=>$item['summary'], 'approvedBy'=>$item['approvedBy']];
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
