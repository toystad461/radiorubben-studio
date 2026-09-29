<?php
declare(strict_types=1);
require_once __DIR__ . '/board.php';

/** Rules are editorial preferences, never a source of facts. */
function studio_memory_context(array $board, string $program, ?array $registry = null): array
{
    $registry ??= studio_program_registry();
    $profile = studio_program_profile($program, $registry);
    if (!$profile) return [];
    $rules = [];
    foreach ($board['editorialRules'] ?? [] as $rule) {
        if ($profile['learningEnabled'] && ($rule['status'] ?? '') === 'approved' && ($rule['program'] ?? '') === $program) {
            $rules[] = ['id'=>$rule['id'], 'version'=>$rule['revision'], 'text'=>$rule['text']];
        }
    }
    return ['registryVersion'=>$registry['registryVersion'], 'profileVersion'=>$profile['profileVersion'],
        'program'=>$profile['id'], 'name'=>$profile['name'], 'style'=>$profile['style'],
        'learningEnabled'=>$profile['learningEnabled'], 'rules'=>$rules];
}

/** Correction evidence belongs to the program that generated the original draft. */
function studio_memory_eligible(array $item, ?array $registry = null): bool
{
    $program = (string)($item['program'] ?? '');
    $profile = studio_program_profile($program, $registry);
    return $profile && $profile['learningEnabled'] && ($item['status'] ?? '') === 'ready'
        && !empty($item['verified']) && !empty($item['generatedOriginal'])
        && $item['generatedOriginal'] !== ($item['script'] ?? '')
        && ($item['generation']['editorial']['program'] ?? '') === $program;
}

function studio_memory_change(string $action, array $input, array $user, ?string $path = null, ?array $registry = null): void
{
    $role = $user['role'] ?? '';
    if (!in_array($role, ['admin', 'producer', 'presenter'], true)
        || ($action !== 'propose' && $role !== 'admin')) {
        throw new InvalidArgumentException('Rollen har ikke tilgang til denne handlingen.');
    }
    studio_board_change(static function (array &$board) use ($action, $input, $user, $registry): void {
        $actor = $user['name'] ?? 'Medarbeider';
        $board['editorialRules'] ??= [];
        if ($action === 'propose') {
            $text = trim((string)($input['text'] ?? ''));
            if (strlen($text) < 10 || studio_board_length($text) > 500 || preg_match('/[\x00-\x1f\x7f]/', $text))
                throw new InvalidArgumentException('Skriv en språk- eller stilregel på 10–500 tegn, uten linjeskift.');
            if (($input['styleOnly'] ?? '') !== '1')
                throw new InvalidArgumentException('Bekreft at regelen gjelder språk eller form, ikke fakta eller unntak fra kildekontroll.');
            $program = (string)($input['program'] ?? studio_program_default($registry));
            $profile = studio_program_profile($program, $registry);
            if (!$profile || !$profile['learningEnabled'])
                throw new InvalidArgumentException('Redaksjonell læring er ikke aktivert for programmet.');
            $evidence = null;
            $itemId = (string)($input['item'] ?? '');
            if ($itemId !== '') {
                foreach ($board['items'] as $item) {
                    if ($item['id'] !== $itemId) continue;
                    if (($item['revision'] ?? 0) !== (int)($input['itemRevision'] ?? 0))
                        throw new InvalidArgumentException('Manuset ble endret. Last siden på nytt før du foreslår læring.');
                    if (($item['program'] ?? '') !== $program || !studio_memory_eligible($item, $registry))
                        throw new InvalidArgumentException('Læring krever et rettet AI-utkast fra samme program som er kontrollert og merket klart.');
                    $evidence = ['program'=>$program, 'itemId'=>$itemId, 'revision'=>$item['revision'], 'title'=>$item['title'],
                        'before'=>$item['generatedOriginal'], 'after'=>$item['script'],
                        'sourceUrl'=>$item['sourceUrl'], 'sourceAt'=>$item['sourceAt'],
                        'sourceExcerpt'=>$item['summary'], 'approvedBy'=>$item['approvedBy']];
                }
                if (!$evidence) throw new InvalidArgumentException('Fant ikke manuset.');
            }
            foreach ($board['editorialRules'] as $rule) {
                if (($rule['program'] ?? '') === $program && in_array($rule['status'], ['pending','approved'], true) && $rule['text'] === $text)
                    throw new InvalidArgumentException('Denne regelen finnes allerede.');
            }
            if (count($board['editorialRules']) >= 300)
                throw new InvalidArgumentException('Regelregisteret er fullt. Kontakt administrator.');
            $board['editorialRules'][] = ['id'=>bin2hex(random_bytes(8)), 'program'=>$program,
                'text'=>$text, 'status'=>'pending', 'revision'=>1, 'evidence'=>$evidence,
                'createdAt'=>gmdate('c'), 'createdBy'=>$actor,
                'history'=>[['action'=>'propose', 'at'=>gmdate('c'), 'actor'=>$actor]]];
            return;
        }
        foreach ($board['editorialRules'] as &$rule) {
            if ($rule['id'] !== ($input['id'] ?? '')) continue;
            if (isset($input['program']) && $input['program'] !== ($rule['program'] ?? ''))
                throw new InvalidArgumentException('Regelen tilhører et annet program.');
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
                $profile = studio_program_profile((string)($rule['program'] ?? ''), $registry);
                if (!$profile || !$profile['learningEnabled'])
                    throw new InvalidArgumentException('Redaksjonell læring er ikke aktivert for programmet.');
                $active = array_filter($board['editorialRules'], static fn($row) => $row['status'] === 'approved' && ($row['program'] ?? '') === $rule['program']);
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

