# Nytt musikkutvalg uten country

Arbeidsområde: Musikk & Tagging / Teknisk Produksjon.
Utført 02.11.2026 ca. 00:00 Europe/Oslo på uttrykkelig bestilling.

Fersk gjennomgang viste 100 musikkspor, hvorav 75 var merket Country.
Alle 100 er fjernet fra aktivt VPS-bibliotek og sendekø. De er erstattet med
96 andre Radio Edit-filer fra brukerens eksisterende OneDrive-musikkarkiv.
Ingen av de gamle musikkhashene finnes blant de nye sporene.
Seks jingler, deres metadata og originalhasher er bevart uendret.

Utvalget bygger på filenes sjangertagger, med Country, ukjent sjanger og
country-tilknyttede tvilstilfeller utelatt. Dette er ingen ekstern musikkologisk
klassifisering. Nye kildebaserte tagger: 70 Top 40, 10 Rock, 4 Mainstream,
3 Pop, 3 Hip Hop, 3 Dance, 1 Alternative, 1 Reggae og 1 Dance Commercial.
Samlet musikkvarighet ca. 5,51 timer. Komplette originale filer er uendret.

97 nye kandidater ble importert til et separat privat klargjøringsbibliotek.
96 bestod full FFmpeg-kontroll og fikk kildebundne automatiske cue-punkter.
Kygo / Ellie Goulding – First Time ble utelatt fordi coverbildets PNG-merking
inneholdt JPEG-data og FFmpeg avviste bildet. Dette beviser ikke skadet lyd.
Originalen i brukerens arkiv ble ikke endret.

Ny kø ble validert før omkobling: 23 innslag, tre sanger / stasjonsjingle,
crossfade 3 sekunder, opprinnelig planlagt til 01:02:03 Oslo. Bakgrunnstimeren
fortsetter å fylle en time fram uten nettleser. Første rekkefølge:
DNCE – Cake By The Ocean, Demi Lovato – Cool For The Summer,
Vance Joy – Fire and the Flood, Jingle 1.

## Verifisert drift

Bibliotek-/købyttet brukte normal validert Stopp/Start med bekreftet motorstatus,
buss-, kø- og biblioteklåser. Kontrollert omkobling tok 9,14 sekunder.
Alle 102 aktive originalhasher verifisert; 96 musikkspor og 6 jingler.
Ingen gammel musikk i køen, ingen rotasjonsmangel. Hele tidligere journalprefix
(1 134 263 bytes) er bevart. Stream, Icecast og begge berørte timere er aktive.
Offentlig status viste DNCE og Demi Lovato som nå/neste.
45 sekunder offentlig HTTPS-MP3 dekodet uten feil etter byttet.

Ingen programkode ble endret eller installert. PR #115 forblir draft, ikke merget.
Bibliotekdata og mediefiler er ikke lagt i Git.

## Privat backup og tilbakeføring

Arbeidsområde på VPS: `/srv/radiorubben-test/noncountry-replacement`.
Backup: `backup-20261101-225956` (tidsstempel UTC).
`music/` inneholder det gamle biblioteket; `queue-before.json` og
`control-before.json` dokumenterer førtilstand. `events-before.jsonl` er en
privat journalkopi. `switch-receipt.json` og `verification.json` i arbeidsområdet
inneholder manifest og etterkontroll. Backuplagringen er utenfor aktivt bibliotek.

Tilbakeføring: stans berørte bibliotektimere, send validert Stopp og vent på
bekreftelse. Under buss-/kølås bevares dagens bibliotek separat, backupens
music-mappe gjenopprettes, og førkøen får ny revisjon. Publiser gjennom vanlig
Studio-start, som tar hensyn til eksisterende journal. Start timerne igjen.
Ikke erstatt journalen eller kopier gammel control.json direkte over aktiv motor.
OneDrive-originalene er bevart.
