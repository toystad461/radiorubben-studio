<?php
declare(strict_types=1);

/** Code-owned register. Never loaded from a request or the mutable sending board. */
function studio_program_registry(): array
{
    return ['registryVersion'=>1, 'defaultProgram'=>'god-morgen-vestland', 'programs'=>[
        'god-morgen-vestland'=>[
            'id'=>'god-morgen-vestland', 'name'=>'God morgen Vestland', 'profileVersion'=>1,
            'style'=>'Varm, tydelig og muntlig morgenradio for Bømlo og Vestland. Rolig og respektfull tone i alvorlige saker. Ingen påfunnet lokal tilknytning eller humor i nyhetsfakta.',
            'learningEnabled'=>true,
        ],
    ]];
}

/** Optional register is a server-side dependency for tests, never user input. */
function studio_program_profile(string $id, ?array $registry = null): ?array
{
    if ($id === '') return null; // Legacy/unassigned is not the default program.
    $registry ??= studio_program_registry();
    $profile = $registry['programs'][$id] ?? null;
    if (!$profile) throw new InvalidArgumentException('Velg et gyldig program.');
    return $profile;
}

function studio_program_default(?array $registry = null): string
{
    $registry ??= studio_program_registry();
    $id = $registry['defaultProgram'];
    if (!studio_program_profile($id, $registry)) throw new LogicException('Programregisteret mangler standardprogram.');
    return $id;
}

function studio_program_label(string $id): string
{
    if ($id === '') return 'Ikke tilordnet';
    return studio_program_registry()['programs'][$id]['name'] ?? 'Ukjent program: ' . $id;
}
