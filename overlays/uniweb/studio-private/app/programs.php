<?php
declare(strict_types=1);

/** Code-owned register. Never loaded from a request or the mutable sending board. */
function studio_program_registry(): array
{
    return ['registryVersion'=>2, 'defaultProgram'=>'god-morgen-vestland', 'programs'=>[
        'god-morgen-vestland'=>[
            'id'=>'god-morgen-vestland', 'name'=>'God morgen Vestland', 'profileVersion'=>2,
            'style'=>'Varm, tydelig og muntlig morgenradio for Bømlo og Vestland. Rolig og respektfull tone i alvorlige saker. Ingen påfunnet lokal tilknytning eller humor i nyhetsfakta.',
            'learningEnabled'=>true,
            'templateId'=>'morning-v1',
            'format'=>[
                'timezone'=>'Europe/Oslo', 'start'=>'05:00', 'end'=>'09:00',
                'voice'=>'Korte, naturlige setninger for Thomas. Lun humor i programlederstikk, tydelig skille fra nyhetene.',
                'music'=>'Variert norsk og internasjonal musikk. Varm start, økende energi mot arbeidsreisen og behagelig avslutning. Forslag må kobles til faktiske arkivfiler; ukjent spilletid må aldri oppgis som målt.',
                'editorial'=>'Lokale og regionale saker med navngitte kilder. Daterte eksempelnyheter gjenbrukes aldri som ferske fakta. Ikke dikt lyttermeldinger, intervjuer, vær eller trafikk. Manglende data betyr ikke normal drift.',
                'theme'=>'Et lett gjennomgående tema i korte drypp. Dokumenterte nettfunn merkes med dato og holdes utenfor nyhetsbulletinen når de er eldre.',
            ],
            'styleExamples'=>[
                'Til deg som er på vei hjem fra en nattevakt: Takk for jobben du har gjort.',
                'Hvilken helt vanlig ting er du uvanlig god på?',
                'Noen låter setter oss rett tilbake til et kjøkken, en biltur eller den første sommerjobben.',
            ],
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
