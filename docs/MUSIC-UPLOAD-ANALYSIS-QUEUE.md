# Automatisk lydanalyse og manuell cue-redigering

## Endring 21.10.2026

Thomas ønsker automatisk måling og bruk av cue-punkter, uten separat godkjenning
av hvert analyseresultat. Dette gjelder musikkteknisk klargjøring.

Opplasting har analyse valgt som standard. Den eksisterende nettleserkøen måler
BPM, toneart, signalnivå, stillhet, vokal og lydbølge. Når analysen er ferdig,
lagres automatiske cue-punkter atomisk med målingene. Nye analyser oppdaterer
automatiske punkter, men bevarer manuelle punkter (også tomme vokal-/overgangsfelt).
Tidligere gyldige kildebundne profiler brukes uten nytt godkjenningsklikk eller
reanalysering dersom manuelle punkter mangler. Originalfilen endres ikke.

Manuelle justeringer lagres med «Lagre endringene mine». En egen handling kan
erstatte manuelle punkter med analysepunkt; historikk og revisjonskontroll beholdes.
Ny opplasting eller eksplisitt nytt analyseforsøk gjenopptar en tidligere pauset
kø. Vanlig åpning respekterer fortsatt operatørens pause.

Dette er nettleserbasert analyse: musikkbiblioteket må fortsatt være åpent mens
køen arbeider. Ingen serverbasert bakgrunnsarbeider er lagt til. Ikke forveksle
lydanalyse med MusicBrainz/nettidentifisering eller betalt tekst-AI-vurdering.

Ukjente vokal-/overgangspunkter forblir null og vises som ukjent. BPM, toneart og
vokal er estimater. Nivå måles i dBFS, ikke LUFS. Ingen automatisk avspilling,
rotasjonsgodkjenning, sending eller fading aktiveres.

Kontroll: målrettet PHP-test utvidet for automatisk lagring, gamle profiler,
ny analyse, ukjente punkter og manuell forrang. Eksisterende nettlesertest oppdatert
for eksplisitt erstatning av manuelle punkter. JavaScript-syntaks kontrollert lokalt.
PHP-runtime mangler lokalt; PHP og nettleserintegrasjon må bekreftes i CI.
Ingen VPS-utrulling er utført for denne endringen.

## Motor og opprinnelse

YAMNet: Google TFJS-modell v1, TensorFlow.js 4.22.0, Apache 2.0.
https://www.tensorflow.org/hub/tutorials/yamnet
Modell og lisens leveres med Studio. Essentia.js er AGPL-lisensiert.
Originalmusikken sendes ikke til ekstern analysetjeneste.
