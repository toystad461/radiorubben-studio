<?php
declare(strict_types=1);

/** Select the facts for this action before handing data to the model. */
function producer_action_context(array $input, ?callable $weather = null): array {
    $action = $input['action'];
    if ($action === 'shorter') {
        if (trim($input['draft']) === '') throw new RuntimeException('Skriv eller sett inn et manus før du velger Kortere.');
        $input['nowPlaying'] = $input['next'] = $input['notes'] = '';
    } elseif (in_array($action, ['weather', 'local', 'traffic', 'sponsor'], true)) {
        // A previously generated music break must never become the factual basis.
        $input['nowPlaying'] = $input['next'] = $input['draft'] = '';
        if ($action === 'weather') {
            if ($weather === null) {
                require_once __DIR__.'/weather-script.php';
                $weather = 'studio_weather_script_generate';
            }
            try { $source = $weather(); }
            catch (RuntimeException $e) { throw new RuntimeException('Ferske værdata er utilgjengelige. Prøv Vær igjen senere, eller bruk lokal mal.', 0, $e); }
            if (!is_array($source) || trim($source['text'] ?? '') === ''
                || strtotime($source['expiresAt'] ?? '') <= time()) {
                throw new RuntimeException('Værgrunnlaget er utløpt eller mangler. Hent et nytt varsel.');
            }
            $input['weatherSource'] = $source;
            $input['notes'] = ''; // Weather facts come from the server, never an old operator note.
        } elseif (trim($input['notes']) === '') {
            $label = ['local'=>'Lokal sak: legg inn kontrollerte fakta, kilde og dato',
                'traffic'=>'Trafikk: legg inn en fersk trafikkmelding med sted, kilde og tidspunkt',
                'sponsor'=>'Sponsor: legg inn godkjent sponsortekst og reklamemerking'][$action];
            throw new RuntimeException($label.' under «Fakta og ønsker til stikket».');
        }
    } else {
        $input['draft'] = '';
    }
    return $input;
}

function producer_action_instruction(string $action): string {
    return match ($action) {
        'weather' => 'Skriv en værmelding utelukkende fra weatherSource. Oppgi Meteorologisk institutt som kilde. Beskriv varselet som en prognose, ikke en observasjon. Ikke omtal musikk eller legg til vind, nedbørsmengder eller andre fakta som ikke finnes i grunnlaget.',
        'traffic' => 'Skriv en trafikkoppdatering fra notes. Ta med sted og tidspunkt når oppgitt. Ikke omtal musikk. Fravær av meldinger betyr ikke at trafikken går normalt. Ikke legg til råd eller hendelser uten belegg.',
        'local' => 'Skriv et lokalt nyhetsstikk fra notes, med oppgitt kilde og dato. Ikke omtal musikk. Ikke finn på lokal tilknytning, konsekvenser eller sitater.',
        'sponsor' => 'Skriv et tydelig merket sponsorstikk fra godkjent tekst i notes. Bevar reklamemerking. Ikke omtal musikk eller finn på tilbud, priser eller produktpåstander.',
        'shorter' => 'Forkort bare draft. Bevar tema, fakta, kildehenvisninger og reklamemerking. Ikke erstatt manuset med et musikkstikk.',
        'next' => 'Presenter bare neste bekreftede innslag i next. Ikke presenter den pågående sangen som avsluttet.',
        'new', 'refresh' => 'Skriv et kort musikkstikk fra den bekreftede program- og musikkonteksten. nowPlaying spiller nå og er ikke dokumentasjon på forrige sang.',
        default => throw new InvalidArgumentException('Ugyldig handling.'),
    };
}
