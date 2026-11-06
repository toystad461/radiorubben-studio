# L02: automatisk cue og rullerende timekø

Avtalt 01.11.2026: utvid til omtrent 100 Radio Edit-filer før rotasjonen lages.
Privat test-VPS alene; ingen offentlig sending eller endring i Uniweb/WordPress/radio.co.

## Funksjon

Standardavspilling velger en time fram og lagrer hver forekomst. Tre musikkspor
følges av en stasjonsjingle. Nyhets-/sesongjingler holdes utenfor. Allerede valgte
framtidige innslag byttes ikke tilfeldig ved påfyll. Spilt historikk og eksisterende
programregler/godkjenning brukes; ingen stille svekkelse ved mangel på kandidater.
Køen fylles av en egen systemd-timer mens den spiller, uten åpen nettleser.

Crossfade kan settes til 0–10 sekunder; startverdi 3. Overlapp brukes bare mellom
to musikkspor og begrenses til en fjerdedel av hver kilde ved svært korte filer.
Jingler går uten overlapp. Endring krever stoppet motor og bygger ny timekø.
Restart krever fortsatt manuell Start; tjenesteomstart starter aldri lyd automatisk.

Automatisk cue-analyse dekoder hele originalen og måler ytterkantstillhet under
−50 dB i minst 100 ms, med 50 ms beholdt margin. Originalene endres ikke.
Minst ett sekund og 75 % av kildevarigheten må beholdes. Manuelle cue-punkter
bevares. Automatisk analyse gjetter ikke vokalstart eller musikalske taktgrenser.
Resultater er bundet til filhash og revisjon; feil/uavklarte filer velges ikke.
Nye filer kontrolleres enkeltvis av serverarbeideren, men musikkens godkjenning
for programmet er fortsatt nødvendig. Tekniske målinger er ikke innholdsgodkjenning.

## Motor og bevis

Liquidsoap 2.2.4 bruker cue-in/out i request-metadata og native cross med
numerisk varighet. Start/slutt knyttes til utgående PCM-klokke; overlappende
haler kvitteres når utgangen har forbrukt dem, ikke ved dekoderens forhåndslesing.
Stopp/feil håndterer alle aktive bidrag og reserverte innslag via eksisterende journal.

Tester: tests/music-rotation.php, tests/music-autocue.php og
 tests/test-studio-rotation-stream.py. Sistnevnte måler faktisk privat HTTP-MP3,
separate tonefrekvenser, cue-lengder og overlapp ved 0, 3 og 10 sekunder.
Eksisterende reell strømtest dekker stopp, tilkoblingsbrudd, frosset PCM og
endrede lydkopier. Driftskvittering og konkret installasjonscommit føres i status.

## Tilbakeføring

Stopp rotasjonstimeren og avspillingen først. Kodeinstallasjon tar privat backup
av berørte filer, bibliotek, kø, kontrollmanifest og journal. Nye cue-/kødata
krever samordnet restaurering dersom eldre kode gjeninnføres. Bevar dagens
originaler og avspillingsjournal som bevis; ikke slett importerte sanger som
skjult del av kode-rollback. Timeren har egen enhetsbackup og tilbakeføring.
