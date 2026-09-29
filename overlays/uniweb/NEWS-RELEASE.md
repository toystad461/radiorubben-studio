# Separat PR #18-patch – ikke deployet

## Avgrensning og status

Denne leveransen er en **patch for kodegjennomgang og offline avstemming**, ikke en
opplastingspakke som er bekreftet kompatibel med aktive Uniweb-filer.
Utgangspunkt: `d753535963d9d1dbf20d104a8b23478547f75f36`.
Nyhetsfunksjon: `4f498188e443bcc6065261dd768be9adbf607a61`.
GitHub Actions run 210 var grønn på denne funksjonsversjonen.
Det er ikke dokumentasjon på produksjonens nåværende filinnhold.

ZIP-en inneholder bare `news-scripts.patch`, `manifest.json` og `README.txt`.
Patchen endrer bare følgende stier, relative til Uniwebs felles overmappe:

| Fil | Endring | Eksisterende avhengighet |
| --- | --- | --- |
| `studio-private/app/board.php` | Krever news-script; ugyldiggjør utløpt klar-status; lagrer kildekontroll i historikk; sperrer godkjenning uten gyldig kontroll | Programregisterets default/profile-funksjoner, eksisterende låst JSON-lagring, revisjoner og historikk |
| `studio-private/app/news-script.php` | Ny, uendret modul fra PR #18 | Programkontekst fra Sending, `producer_request`, PHP cURL/DOM/libxml, eksisterende privat OpenAI-konfigurasjon |
| `studio-public/sending.php` | Generering, ny kontroll, kontrollrapport, intern handlingssperre | bootstrap/innlogging/roller/CSRF, board, story-script, editorial-memory, producer og felles visningsmaler |

`board.php` og `sending.php` pakkes som differanser, **ikke hele erstatningsfiler**.
Programregister, sendeforslag, vær, menyer, kontrollsenter, CSS/JS, tester og privat
konfigurasjon følger ikke med. De øvrige fem filene i opprinnelig PR #18 er
CI/dokumentasjon/testfiler, ikke driftsfiler for denne utrullingen.

## Konkret uløst avhengighet

`board.php` forventer `programs.php`. Sending forventer programvelger,
`studio_memory_context()` og historie fra læringsarbeidet. `news-script.php`
kontrollerer at mottatt redaksjonell kontekst tilhører punktets program.
Profilen hentes via `studio_memory_context()` i Sending, ikke direkte i nyhetsmodulen.
Referansen har register/profil v2 fra PR #15 og utvidet editorial-memory-kontekst
med `format`/`styleExamples`. Disse er **forutsetninger i referansekoden, ikke
skjulte leveranser i patchen**. Preflight krever eksakt referanseinnhold for
programs.php, editorial-memory.php og story-script.php; alternative versjoner
må vurderes separat. Ikke kopier inn PR #15 for å få kontrollen grønn.

GitHub-kildene lest 29.09.2026 gir dette grunnlaget:

- [PR #13](https://github.com/toystad461/radiorubben-studio/pull/13) og
  [PR #14](https://github.com/toystad461/radiorubben-studio/pull/14) beskriver
  selektiv publisering 28.09, bevarte utkast og private sikkerhetskopier.
  `LEARNING.md` beskriver samtidig senere programregisterrefaktorering som
  **ikke deployet**. En oppdatert gren er derfor ikke en produksjonssnapshot.
- [PR #15](https://github.com/toystad461/radiorubben-studio/pull/15) beskriver
  programprofil v2/sendeforslag som ikke deployet. [PR #16](https://github.com/toystad461/radiorubben-studio/pull/16)
  beskriver været som ikke publisert. Ingen av modulene skal følge patchen.
- [PR #17](https://github.com/toystad461/radiorubben-studio/pull/17) dokumenterer
  selektiv publisering av bare control.php og control.css 29.09. Disse beholdes.
- Ingen av disse notatene gir ferske, eksakte hashverdier for aktiv board.php,
  sending.php, programs.php eller editorial-memory.php. PR #18 og NEWS-SCRIPTS.md
  advarer uttrykkelig mot full opplasting av grenen.
- Sending krever eksisterende `studio-private/app/producer.php` og
  `producer_request($config, $payload)`. Filen var ikke tilgjengelig på undersøkte
  GitHub-stier (`overlays/uniweb/studio-private/app/producer.php`,
  `studio-private/app/producer.php`, `app/producer.php`) ved funksjonens head.
  Eksakt aktiv implementasjon, API-kontrakt og privat modellkonfigurasjon må
  derfor avstemmes lokalt. Ingen credentials skal hentes inn i leveransen.
- bootstrap krever config/helpers/users/lokal autentisering og eksisterende
  sesjon/rolleoppsett; visningsmaler og kontroll-CSS er eksisterende runtime.
  Patchen endrer ikke disse. Preflight sertifiserer ikke dette avhengighetstreet.

**Stopp før utrulling:** Skaff en fersk lokal kodekopi fra Uniweb, avstem de to
berørte eksisterende filene og program-/producer-avhengighetene. Hvis referansen
ikke matcher, må en ny målrettet patch utarbeides mot den faktiske kodebasen med
samme sikkerhetstester. Ikke bruk fuzzy patching, ikke fjern kontrollkrav og ikke
anta at full gren/main/standardpakken er riktig.

## Reproduserbar bygging og kontroll

Krever Python 3 og Git med full historikk (`git fetch --unshallow` ved behov).
Kjør fra repoet:

```sh
python3 scripts/test-news-release.py
python3 scripts/news-release.py build
python3 scripts/news-release.py verify --archive dist/news-scripts-pr18.zip
python3 scripts/news-release.py preflight --target /lokal/kodekopi
```

Preflight er kun lesing av fem navngitte eksisterende kodefiler og sjekk av at
news-script.php ikke finnes. Den leser aldri privat config, sendeliste, kilder,
brukere, cache eller backup. `git apply --check` skriver ikke til kopien.
Eksakt samsvar betyr bare samsvar med GitHub-referansen, ikke godkjent utrulling.

Byggeren leser bare låste Git-blobs, aldri arbeidsmappen eller hele grenen.
Manifestet oppgir SHA-256 før/etter, kildecommits og `productionVerified: false`.
Verifieren regenererer det forventede arkivet fra kodeeide blob-ID-er og krever
byte-for-byte-likhet. En selvendret manifestfil kan derfor ikke godkjenne ekstra
innhold. Faste ZIP-tider/rekkefølge/metadata gir reproduserbar leveranse.
Dette kontrollerer kjent, gjennomgått kildeinnhold; det er ikke en generell
hemmelighetsdetektor for vilkårlige fremtidige kodeendringer.

Negative tester dekker PR #15/#16-filer, programavhengigheter, private data,
.env, backup, katalogtraversering, duplikater, ekstra ZIP-data og endret innhold
på tillatt sti. Tester dekker også manglende/endrede/symlinkede avhengigheter,
gjentatt patching, uendret privat historiesentinel og at anvendt patch gir
nøyaktig de tre originale PR #18-filene. Eksisterende PHP-tester beholdes.

CI publiserer **news-scripts-pr18-review-only** separat fra standardpakken.
Standardpakken `uniweb-package` er fortsatt uegnet som full erstatning for aktivt
Studio og skal ikke brukes til denne endringen.

## Bevarte sperrer og senere manuell kontroll

Ingen runtime-logikk er skrevet om i denne leveransen. Resultatet av patchen
skal være identisk med PR #18: godkjente HTTPS-kilder, DNS/IP/TLS-sperrer, ingen
redirect, begrenset HTML/tekst, separat kontrollpass med ordrette belegg,
fingeravtrykk/én times utløp, eksplisitt redaksjonell bekreftelse, CSRF/roller,
revisjonssperre, låsing og historikk. Kontrollfeil skal ugyldiggjøre tidligere
kontroll før nettverksarbeid. Teksteksport må ikke vise utløpt kontroll som klar.

Etter avstemming i isolert miljø: kjør eksisterende board-, story-script-,
editorial-memory-, program- og news-tester samt news-page-modusene
get/guest/method/observer/csrf/forged på PHP 8.2/8.4. Kontroller ekte kildemaler,
AI-kontrakt, cURL/DOM, ventetid, innlogging/roller, revidering, ny kontroll og
historikk. Mocktester alene godkjenner ikke live integrasjon.

Ved en senere, separat autorisert utrulling: privat sikkerhetskopi av berørt
kode og data, vedlikehold som hindrer samtidige endringer, og samlet installasjon
av de tre avstemte filene. Tilbakeføring må også være samlet og avstemt mot
nyere sourceCheck-data; eldre kode kan ellers behandle tidligere klar-status
uten utløpskontrollen. Ikke gjenopprett gammel sendeliste over nytt arbeid.
Ingen deploy, lydproduksjon, automatisk publisering eller OneDrive-overføring
utføres av denne patchen eller dens verktøy.
