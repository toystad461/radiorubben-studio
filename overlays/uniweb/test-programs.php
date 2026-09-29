<?php
declare(strict_types=1);
require __DIR__ . '/studio-private/app/editorial-memory.php';
require __DIR__ . '/studio-private/app/story-script.php';
function check_program(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "OK $label\n";
}
function reject_program(callable $fn, string $label): void {
    try { $fn(); } catch (InvalidArgumentException $e) { check_program(true, $label); return; }
    throw new RuntimeException($label);
}
$registry = studio_program_registry();
$morning = studio_program_default();
check_program(count($registry['programs']) === 1 && $morning === 'god-morgen-vestland', 'only existing real program ships');
// Test-only dependency: no environment switch or production profile is installed.
$registry['registryVersion'] = 2;
$registry['programs']['test-profile'] = ['id'=>'test-profile', 'name'=>'Testprofil',
    'profileVersion'=>7, 'style'=>'TEST_STYLE: korte testsetninger.', 'learningEnabled'=>true];
$admin = ['name'=>'Test admin', 'role'=>'admin'];
$presenter = ['name'=>'Test presenter', 'role'=>'presenter'];
$dir = sys_get_temp_dir() . '/programs-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
$path = $dir . '/board.json';
$source = ['id'=>'source', 'title'=>'Kommunen inviterer til møte', 'sourceName'=>'Bømlo kommune',
    'publishedAt'=>'2026-09-28T10:00:00Z', 'url'=>'https://example.org/source', 'summary'=>'Kommunen inviterer pårørende til et åpent møte i kulturhuset torsdag klokka 18. Møtet er gratis.'];
try {
    studio_board_add_source($source, $presenter, $path, $registry);
    studio_board_add_manual('Eget punkt', $presenter, $path, $registry);
    $board = studio_board_read($path); $item = $board['items'][0]; $id = $item['id'];
    check_program($item['program'] === $morning && $board['items'][1]['program'] === $morning, 'both new item paths use registry default');
    $otherDefault = $registry; $otherDefault['defaultProgram'] = 'test-profile';
    studio_board_add_manual('Annen standard', $presenter, $path, $otherDefault);
    check_program(studio_board_read($path)['items'][2]['program'] === 'test-profile', 'default is data-driven');
    $before = file_get_contents($path);
    reject_program(fn()=>studio_board_update($id, 1, 'save', ['program'=>'unknown'], $presenter, $path, $registry), 'unknown assignment rejected');
    reject_program(fn()=>studio_memory_context([], 'unknown', $registry), 'unknown context rejected');
    reject_program(fn()=>studio_memory_change('propose', ['program'=>'unknown', 'text'=>'En lang nok testregel.', 'styleOnly'=>'1'], $presenter, $path, $registry), 'unknown rule program rejected');
    check_program(file_get_contents($path) === $before, 'rejected writes leave storage unchanged');
    foreach ([$morning, 'test-profile'] as $program) {
        studio_memory_change('propose', ['program'=>$program, 'text'=>'Felles ordlyd kan brukes uavhengig.', 'styleOnly'=>'1'], $presenter, $path, $registry);
        $rules = studio_board_read($path)['editorialRules']; $rule = end($rules);
        studio_memory_change('approve', ['id'=>$rule['id'], 'revision'=>1, 'program'=>$program], $admin, $path, $registry);
    }
    $board = studio_board_read($path);
    $a = studio_memory_context($board, $morning, $registry);
    $b = studio_memory_context($board, 'test-profile', $registry);
    check_program(count($a['rules']) === 1 && count($b['rules']) === 1 && $a['rules'][0]['id'] !== $b['rules'][0]['id'], 'identical rule text remains isolated in both directions');
    check_program($b['profileVersion'] === 7 && $b['registryVersion'] === 2 && $b['style'] === $registry['programs']['test-profile']['style'], 'second profile and versions resolved from registry');
    reject_program(fn()=>studio_memory_change('disable', ['id'=>$board['editorialRules'][1]['id'], 'revision'=>2, 'program'=>$morning], $admin, $path, $registry), 'cross-program decision rejected');
    studio_board_update($id, 1, 'save', ['title'=>$item['title'], 'program'=>'test-profile'], $presenter, $path, $registry);
    $item = studio_board_read($path)['items'][0]; $payload = null;
    $config = ['openai_api_key'=>'mock', 'openai_model'=>'mock'];
    studio_story_script_generate($item, $config, static function ($config, $p) use (&$payload) { $payload=$p; return 'Et testutkast.'; }, $b);
    check_program(str_contains($payload['instructions'], 'TEST_STYLE') && !str_contains($payload['instructions'], $a['style']) && !str_contains($payload['instructions'], $a['rules'][0]['id']), 'second program prompt excludes first program profile and rules');
    check_program($payload['store'] === false && str_contains($payload['instructions'], 'Kildekravene over har alltid forrang'), 'source and storage controls retained');
    reject_program(fn()=>studio_story_script_generate($item, $config, static fn()=>throw new RuntimeException('must not call AI'), $a), 'wrong program context rejected before AI call');
    studio_board_update($id, 2, 'generated', ['script'=>'Original.', 'generation'=>['editorial'=>$b]], $presenter, $path, $registry);
    studio_board_update($id, 3, 'save', ['title'=>$item['title'], 'script'=>'Rettet.', 'verified'=>'1'], $presenter, $path, $registry);
    studio_board_update($id, 4, 'ready', [], $presenter, $path, $registry);
    $proposal = ['program'=>'test-profile', 'text'=>'En egen regel fra rettelsen.', 'styleOnly'=>'1', 'item'=>$id, 'itemRevision'=>5];
    studio_memory_change('propose', $proposal, $presenter, $path, $registry);
    $rules = studio_board_read($path)['editorialRules'];
    check_program($rules[2]['program'] === 'test-profile' && $rules[2]['evidence']['program'] === 'test-profile', 'second program correction evidence is scoped');
    reject_program(fn()=>studio_memory_change('propose', array_replace($proposal, ['program'=>$morning]), $presenter, $path, $registry), 'cannot attach another program correction');
    studio_board_update($id, 5, 'save', ['title'=>$item['title'], 'script'=>'Rettet igjen.', 'verified'=>'1', 'program'=>$morning], $presenter, $path, $registry);
    studio_board_update($id, 6, 'ready', [], $presenter, $path, $registry);
    reject_program(fn()=>studio_memory_change('propose', array_replace($proposal, ['program'=>$morning, 'itemRevision'=>7]), $presenter, $path, $registry), 'reassignment cannot relabel old generation evidence');
    $disabled = $registry; $disabled['programs']['test-profile']['learningEnabled'] = false;
    $ctx = studio_memory_context(studio_board_read($path), 'test-profile', $disabled);
    check_program($ctx['rules'] === [] && $ctx['style'] === $b['style'], 'disabled learning keeps profile but excludes all rules');
    reject_program(fn()=>studio_memory_change('propose', ['program'=>'test-profile', 'text'=>'Forslag uten aktiv læring.', 'styleOnly'=>'1'], $presenter, $path, $disabled), 'disabled learning rejects proposals');
    reject_program(fn()=>studio_memory_change('approve', ['id'=>$rules[2]['id'], 'revision'=>1], $admin, $path, $disabled), 'disabled learning rejects approval');
    // Reading and saving legacy items never silently assigns the default.
    $legacy = ['items'=>[['id'=>'legacy', 'title'=>'Gammelt', 'script'=>'Manus', 'status'=>'draft', 'revision'=>1]]];
    file_put_contents($dir.'/legacy.json', json_encode($legacy));
    check_program(studio_board_read($dir.'/legacy.json') === $legacy, 'legacy read unchanged');
    check_program(studio_memory_context($legacy, '', $registry) === [], 'legacy has no profile or learned rules');
    studio_board_update('legacy', 1, 'save', ['title'=>'Gammelt', 'script'=>'Manus', 'program'=>''], $presenter, $dir.'/legacy.json', $registry);
    check_program(!array_key_exists('program', studio_board_read($dir.'/legacy.json')['items'][0]), 'legacy form save keeps missing program field');
    echo "Program registry workflow passed\n";
} finally { foreach (glob($dir.'/*') as $file) unlink($file); rmdir($dir); }
