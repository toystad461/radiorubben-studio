<?php
declare(strict_types=1);

/** Reviewed code, never editable through learning or source input. */
const RR_STYLEBOOK_VERSION = '1.0.0';
const RR_JOURNALIST_VERSION = '2.0.0';

function rr_stylebook_rules(): array
{
    return [
        'RR-L01'=>'Skriv naturlig bokmål med aktive verb og konkrete ord. Vær lokal, inkluderende, verdig og engasjerende. Alvorlige saker krever rolig tone.',
        'RR-S01'=>'Velg én dokumentert hovedvinkel etter vesentlighet, aktualitet og nærhet. Start med det viktigste. Ingressen tilfører informasjon; brødteksten gjentar den ikke.',
        'RR-S02'=>'Fortell hendelser i forståelig rekkefølge når kilden dokumenterer forløpet. Ingen oppdiktede scener, stemninger, reaksjoner, dialog eller dramaturgisk årsakssammenheng.',
        'RR-L02'=>'Lokal vinkel krever uttrykkelig belegg for tilknytning til Bømlo eller Vestland. Ikke konstruer en lokal konsekvens. Ingen tvungen lokal vinkel eller lengde.',
        'RR-F01'=>'Bare den aktuelle originalkilden er faktagrunnlag. Bevar navn, tall, tidspunkt, negasjon, usikkerhet og attribusjon. Påstander fra myndigheter er ikke automatisk uavhengig bekreftet.',
        'RR-F02'=>'Ikke dikt eller språkvask ordrette sitater. Bruk indirekte tale ved omskriving. Stilregler, gamle artikler og godkjente rettelser er aldri nye faktakilder.',
        'RR-E01'=>'Unngå unødvendig identifisering, særlig av barn, ofre og sårbare personer. Skill anklage, siktelse og dom. Ikke bruk reklamespråk eller diskriminerende generaliseringer.',
        'RR-T01'=>'Synliggjør originalkilden og vesentlig AI-medvirkning. Ikke påstå eget intervju, tilstedeværelse eller menneskelig kontroll som ikke er utført.',
    ];
}

function rr_writer_instructions(string $channel): string
{
    if (!in_array($channel, ['radio','web'], true)) throw new InvalidArgumentException('Ukjent journalistkanal.');
    return "\nRadio Rubben Stylebook ".RR_STYLEBOOK_VERSION.". RR Writer, RR Storytelling og RR Editor: "
        .implode(' ', rr_stylebook_rules())
        .' RR Editor: Les eget utkast før retur; fjern gjentakelser, tomme superlativer og alle formuleringer som går lenger enn kilden. Dette er egenkontroll, ikke faktagodkjenning.'
        .($channel === 'radio' ? ' Bruk korte setninger som er lette å lese høyt.' : ' Bruk konkret overskrift uten klikkagn. Mellomtitler bare når tekstlengden trenger dem.');
}

/** Independent review gets fixed policy only, never learned preferences. */
function rr_review_instructions(): string
{
    return '\nRR FactCheck og RR Ethics, Stylebook '.RR_STYLEBOOK_VERSION.': '
        .'Kildebelegg betyr ikke at en parts påstand er uavhengig bekreftet. Bevar attribusjon og forbehold. '
        .'Legg presseetiske hindringer i issues med prefiks RR Ethics: unødvendig identifisering, barn eller sårbare personer, private helseopplysninger, forhåndsdømming, diskriminering, skjult reklame eller sterke beskyldninger uten dokumentert samtidig imøtegåelse. '
        .'Ved tvil om et slikt forhold: krev redaksjonell avklaring i issues. Ikke anta at imøtegåelse er innhentet. '
        .'Flagg tittel/ingress uten dekning, fabrikkerte scener, misvisende sitater og påfunnet lokal tilknytning. '
        .'Ikke krev mer bakgrunn enn kilden gir. Ikke gi godkjenning på grunnlag av stilønsker. Menneskelig sluttgodkjenning er alltid nødvendig.';
}

/** Trace what actually ran; these are responsibilities, not seven model calls. */
function rr_generation_record(array $source, array $config, array $editorial, string $channel): array
{
    return ['journalistVersion'=>RR_JOURNALIST_VERSION, 'stylebookVersion'=>RR_STYLEBOOK_VERSION,
        'stylebookSha256'=>hash('sha256', json_encode(rr_stylebook_rules(), JSON_THROW_ON_ERROR)),
        'channel'=>$channel, 'model'=>(string)($config['openai_model'] ?? ''), 'generatedAt'=>gmdate('c'),
        'sourceSha256'=>$source['sha256'], 'sourceUrl'=>$source['url'], 'sourceFetchedAt'=>$source['fetchedAt'],
        'editorial'=>$editorial, 'aiAssisted'=>true,
        'stages'=>['RR Research'=>'original_snapshot','RR Writer'=>'generated',
            'RR Storytelling'=>'writer_instructions','RR Editor'=>'writer_self_review',
            'RR Transparency'=>'provenance_recorded']];
}

/** No automatic quality score: counts are descriptive, not factual verification. */
function rr_text_metrics(string $text): array
{
    $words=preg_split('/\s+/u',trim($text),-1,PREG_SPLIT_NO_EMPTY) ?: [];
    $sentences=preg_split('/[.!?]+(?:\s|$)/u',trim($text),-1,PREG_SPLIT_NO_EMPTY) ?: [];
    $long=0; foreach($words as $word) if(mb_strlen(preg_replace('/[^\p{L}]/u','',$word) ?? '')>6)$long++;
    return ['words'=>count($words),'sentences'=>count($sentences),
        'lixApprox'=>count($words)&&count($sentences)?round(count($words)/count($sentences)+100*$long/count($words),1):null];
}
