# Studio – felles status og arbeidsliste

Oppdatert 04.10.2026 etter selektiv aktivering, API-kontroll og innlogget UI-kontroll.
Eier: Thomas Magne Sellevold-Øystad.
Repo: https://github.com/toystad461/radiorubben-studio


## Aktiv nyhetsdesk – 04.10.2026

Samlet desk er **selektivt aktivert**. Runtime følger Studio-commit
`5fdee8efd3648b9cfb32f20f2113cb5a1b771d55` (PR #24), og Fotballrobot
0.10.1 / web-runtime `a253cfa3c41aa175cdceebc6ce46e8c5cd4d7b76` (PR #50).
Første aktivering: web-release `ef7808506f3c31837b8717198751ceac91eee385`,
Actions 37236445470. Oppfølging som bevarer utkast ved kontrollfeil og
begrenser køens høyde: release `9b92a8808fb1738da981717d27b4c2804e7bf264`,
Actions 37237084802. Avsnittsreferanser til urørt kilde ble deretter aktivert i
`74ea347da4b7e6483875dbb802a88bc5a39a0ad1`, Actions 37237550629.
Alle før-/etter-hasher ble kontrollert på serveren.
Privat backup og nøyaktig tilbakeføring er dokumentert i web-repoets
`NEWSROOM-RELEASE.md` og `scripts/newsroom-release.json`.

Studio CI på PHP 8.2/8.4 er grønn for denne runtime-committen (37237498827 og
37237495149). Fotballrobotens fullstendige tester og release-kontroller er
grønne. Ny dekning: kildeidentitet, manglende original, endrede revisjoner,
CSRF/roller, dubletter, avviste kilder, ingen automatiske feilrepetisjoner,
bevart utkast ved ufullstendig kontroll og samlevarslets transporttilstander.

WordPress-forbindelsen er reparert med egen Studio-programnøkkel for samme
allerede konfigurerte konto. Andre nøkler, inkludert WPVibe, er bevart.
Hemmeligheten finnes bare i privat serverkonfigurasjon. API og worker er
bekreftet i drift. Automatisk klargjøring (maks åtte nye saker per Oslo-døgn)
og samlevarsler (ti minutter, maks én per time ved nye revisjoner) er på.
Native WordPress-jobber ble aktivert i release
`05eca254c7ad3d071f021925bf94de5fd8e1342b`, Actions 37237964444.
Fersk autentisert HTTP-kontroll bekrefter at WordPress kan lese Studio-køen
uten å starte en separat PHP-prosess. Versjon 0.10.1 er bekreftet via API.

Live Studio er kontrollert med Thomas sin Microsoft-innlogging. Køen viser
Studio- og WordPress-saker, mangler, kilde, bilde og manuelle avgjørelser.
En ekte NRK-sak er hentet fra originalartikkelen og klargjort. Kontrolløren
fanget opp en udokumentert påstand og hindret godkjenning; utkastet ble
rettet gjennom den nye flaten. Kildelenken er «Les hele saken hos NRK».
NRK-utkastet `8140cd54c5b6e21d` står nå som «Til godkjenning», med
originalen lest, kontrollert korttekst og godkjenningsboksen urørt.
Ingen ekte artikkel er publisert og ingen test-e-post er sendt i arbeidet.
Første faktiske e-postlevering er ikke verifisert her.

Den tidligere uavklarte kladdoverføringen for `37115a841ef21a07` er løst
etter autentisert søk i alle WordPress-statuser: ingen innlegg med reservert
slug fantes. Overføringen er merket `not_delivered`, tidligere status er
bevart i historikken, og ingen ny overføring er gjort. Andre eldre saker
som trenger kontroll er ikke automatisk godkjent, slettet eller overskrevet.

Neste steg: bruk desken til redaksjonell godkjenning og vurder kvaliteten på
nye utkast før eventuell videre automatisering. Mobilvisning er kodet
responsivt, men ikke separat kontrollert på fysisk mobil i denne runden.

## Start her
Følg [AGENTS.md](../AGENTS.md). Det ferske
[Uniweb-kodegrunnlaget](../snapshots/uniweb-20260930/README.md) erstatter den
tidligere antakelsen om at produksjonsfiler ikke var tilgjengelige.
[Manifestet](../snapshots/uniweb-20260930/manifest.json) inneholder 80 filhasher
og sammenligning med 20 låste GitHub branch-tips.

## Konsolidering i PR #19

Servergrunnlaget er samlet i repoets app/ og public/, med rettet Uniweb-pakking
og logo fra brukerens logopakke. Se [avstemming og tester](CONSOLIDATION-20260930.md).
Snapshotet beholdes uendret. Ingen produksjonsdeploy eller merge er utført.
Aktuell kildecommit og CI-status finnes på PR #19.

## Bekreftet ved kildeinnsamling
- Privat serverbackup av app og offentlig kode er opprettet; config, vendor,
  docs og rotfiler er også kopiert. Backupen er tatt i flere trinn.
- 80 tekstfiler er bevart i Git-snapshotet uten private innstillinger/data.
- Mot main: 10 identiske, 12 ulike og 58 manglende filer.
- Mot alle undersøkte branch-tips: 41 identiske, 11 ulike og 28 manglende.
- 54 PHP-filer passerer syntakskontroll på PHP WASM 8.2/8.4; 15 JS-moduler
  passerer Node-syntakskontroll. Ingen ende-til-ende-godkjenning er gitt.
- Nyhetsmanus og nettpubliseringsmoduler finnes på serveren og matcher deler
  av gjeldende PR #18. Den gamle teksten «ikke deployet» er ikke pålitelig
  som beskrivelse av hele dagens produksjonskode.
- programs.php finnes ikke i den kopierte app-katalogen. board/sending/story-script
  avviker fra gjeldende GitHub-grener. Ingen eksisterende patch skal lastes opp
  uten ny avstemming.
- Ingen aktiv kode er endret. Hovedgrenen og standardpakken er fortsatt ikke
  en bekreftet kopi av produksjonen.

## Arbeidsliste
| Prioritet | Status | Oppgave / ferdigkriterium |
| --- | --- | --- |
| 1 | Utført for tekstkode | Serverbackup, kildeinnsamling og filkart med teksthasher. Råbyte-hasher, binærlogo og avhengighetslås må fortsatt avstemmes før reinstallasjon. |
| 2 | Samlet i PR #19 | Lag konsolidert runtime-gren fra kjent servergrunnlag. Avstem 11 ulike filer og nødvendige eksempelinnstillinger/avhengigheter uten å miste aktive funksjoner. |
| 3 | Funksjonstester innført; UI gjenstår | Kjør funksjonstester og produksjonsnær UI; avklar PR #13-konflikt og integreringsrekkefølge for programregister, sendeforslag og vær. |
| 4 | Produktmål | Samle automatisk klargjøring, kilder, kontroll, redigering og manuell sluttgodkjenning i én Studio-flyt. |
| 5 | Senere | Verifiser faktiske OneDrive-mapper/filreferanser og bygg ElevenLabs-lydsporet; playout behandles separat. |

## Beslutninger
Codex er fast utviklingsverktøy. Tekniske regler og status skal ligge i repoet.
Kodeendringer går via GitHub før publisering; eventuelle nødrettelser på
serveren må tilbakeføres straks med dokumentert avvik.
Bruk én avgrenset hovedoppgave om gangen og gjenbruk eksisterende PR når det
er samme arbeid. Ikke opprett flere parallelle oversikter.
Manuell redaksjonell sluttgodkjenning beholdes. OneDrive-lenker skal være
verifiserte filreferanser; ingen synk eller lydintegrasjon er aktivert her.

## Oppdatering ved hver levering
Noter oppgave/PR og eksakt head, endrede filer, tester, uprøvde integrasjoner,
publiseringsbevis eller «ikke publisert», og neste steg.
Skill alltid mellom innsamlet kode, testet kode, integrert kode og deployet kode.
Samtalehistorikk, grønn CI og PR-tekst alene er ikke serverbevis.

## Kontroll 01.10.2026

Kontrollert mot runtime-commit f7ce90ce393a625ba4b9ffc4cf494457dbb04244:
Uniwebs aktive vendor/composer/installed.json oppgir samme versjon og source.reference
som GitHub composer.lock for alle fire pakker: jumbojett/openid-connect-php v1.0.2,
paragonie/constant_time_encoding v3.1.3, paragonie/random_compat v9.99.100 og
phpseclib/phpseclib 3.0.57. Dette bekrefter pakkemetadata, ikke råbyte-likhet for
composer.lock eller integriteten til hver vendor-fil.

Nedlasting av composer.lock ga tidsavbrudd. En separat kopi av aktiv låsefil
ligger i studio-private/reconciliation-backup-20260930-2002/dependency-check-20261001/.
Originalen og de tidligere backupfilene er urørt. Foreslått endring av filtype
ble avbrutt uten å godta filbehandlerens advarsel.

Studio åpner innloggingssiden. Forespørselen om innloggingsmetode ble avbrutt;
innlogget nettlesergjennomgang er derfor fortsatt ikke utført. Ingen kode,
konfigurasjon eller redaksjonelle data er endret på produksjonsserveren.
Ingen merge eller deploy. Neste steg er innlogget UI-kontroll og avstemming av
ferske serverfiler før eventuell publisering.

## NRK Vestland RSS – 03.10.2026

Ny hovedoppgave: RSS-utvikling med brukerens to NRK Vestland-feeder, Toppsaker
og Siste nytt. Arbeidet bygger på PR #19-head
`049b42591e525055232503e4710787ad09b62139`, på egen gren `feat/nrk-vestland-rss`.
Render- og Entra-grenene er ikke endret.

Fire runtime-filer: ny `app/source-identity.php`, `app/integrations/NewsDesk.php`,
`app/board.php` og `public/newsdesk.php`. Felles Vestland-seksjon, toppsakmerking,
identitet på tvers av feedene og vern av eksisterende manus/godkjenninger.
Nasjonal NRK-feed erstattes; Bømlo kommune, trafikk og vær beholdes.
Egne syntetiske tester dekker dubletter, kildefeil, cache og eksisterende sendepunkter.

Ikke publisert. NRKs feedinnhold kunne ikke leses gjennom søkeverktøyet på grunn
av tilgangsblokkering; ingen omgåelse er forsøkt. Neste steg før selektiv
aktivering er fersk serveravstemming av berørte filer og bekreftet
Studio-deploytilgang, etterfulgt av faktisk kilde- og innlogget UI-kontroll.
Se [NRK Vestland RSS](NRK-VESTLAND-RSS.md) for avhengigheter og avgrensning.

Leveranse: [PR #22](https://github.com/toystad461/radiorubben-studio/pull/22).
Testet kode-head: `f39e10997205232b894ee4d51b7c00fdb69c6efd` (påfølgende commit
oppdaterer bare denne statusen). Hele PHP-suiten, JavaScript-kontroller,
Composer-validering/audit og pakking besto i
[CI-kjøring 37156663637](https://github.com/toystad461/radiorubben-studio/actions/runs/37156663637)
på PHP 8.2 og 8.4; pakking ble kjørt på 8.4. De 28 nye Vestland-kontrollene
og alle seks nyhetsdesk-tilgangstestene besto. Lokalt besto også RSS-parseren
og de 28 nye kontrollene i PHP-WASM. Testene bruker syntetiske RSS-data,
ikke NRK-innhold. Live kildehenting, innlogget produksjons-UI og deploy er
ikke utført. Ingen redaksjonelle data, brukerinnstillinger eller andre
greners kode er endret.


## Nyhetssaker til nett – 04.10.2026

Avgrenset neste trinn bygger på PR #22-head
`c96b27af7ab184f2e407fc5b3e2b83b68671ac11`, på egen gren
`feat/news-publication-profile`. Sakssiden får forhåndsvisning av nyhetsbildet
(media 1079), kategorivalg og korrekt leveringsstatus. Nye nyhetssaker bruker
Nyheter (8), kommunesaker også Lokale Nyheter (27). Objektene er bekreftet med
lesende WordPress-kall; ingen WordPress-innstillinger eller innlegg er endret.

Fire runtime-filer: `app/news-publication.php` (ny), `app/web-publish.php`,
`app/case-workflow.php` og `public/case.php`. Bildet/kategoriene følger første
opprettelse; senere oppdatering bevarer WordPress-redaktørens valg. Godkjenning
bindes til tekst, kildeattribusjon og nyhetsprofil. Gjenåpning fra en annen
NRK-feed gjenbruker eksisterende redigert sak. Kilde-/språkkontroll og manuell
sluttgodkjenning beholdes. Se [nyhetspublisering](NEWS-PUBLICATION.md).

Lokalt besto 33 nye publiseringskontroller, åtte sakssidekontroller og 10
regresjonskontroller i PHP-WASM, pluss JavaScript-simuleringer. Full native CI
kjøres ved levering; testet kodecommit og resultater dokumenteres på PR-en.
Ikke produksjonsaktivert. Neste steg er fersk serveravstemming av disse fire
filene og PR #22-avhengighetene, så innlogget Studio-test og kontrollert ekte
WordPress-kladd. Live originalhenting og ende-til-ende-overføring er uprøvd.
Ingen artikkel, automatisk jobb, OneDrive-overføring, merge eller deploy er gjort.


Verifisering: [PR #23](https://github.com/toystad461/radiorubben-studio/pull/23),
testet kodecommit `9795a3735c9d147e263f5deb66777c2103262275`.
[CI 37220266577](https://github.com/toystad461/radiorubben-studio/actions/runs/37220266577)
besto på native PHP 8.2 og 8.4: hele suiten, JavaScript, Composer-validering/audit
og pakking. Denne etterfølgende oppdateringen endrer bare dokumentert status.
PR #23 er åpen som draft og mergebar; ingen merge eller deploy er utført.


## Produksjonsaktivering og reell prøve – 04.10.2026

Etter brukerens «Aktiver» ble PR #22-avhengighetene og PR #23 aktivert selektivt på Uniweb. Endelig runtime-kilde er `f0cbc59f348978daf1532fed93ad8deee8b15979`, med grønn full [CI 37229833327](https://github.com/toystad461/radiorubben-studio/actions/runs/37229833327). Alle 11 endrede/opprettede runtime-filer er kontrollert mot ferske etter-hasher. Privat backup og før-hasher finnes. Ingen helgren-deploy eller merge.

Live UI-test avdekket blokkering fra eksisterende CSP. Sakssiden er rettet til samme-origin JavaScript, CSS og bilde, uten å endre sikkerhetsheadere. Innlogget Studio viser fungerende feeder, bilde og kategorier, og lager både radio- og nettutkast fra originalkilden. Prøvesak: `37115a841ef21a07`. Kontrollmerknader krever redaksjonell gjennomgang; manuell sluttgodkjenning er bevart.

**Gjenstående blokkering:** WordPress-kladdoverføringen feilet. Studios lesende tilkoblingskontroll bekrefter HTTP 401 / `incorrect_password`; den separate autentiserte WordPress-tilkoblingen finner ingen kladd med prøvens slug. Ingen ny levering ble forsøkt og ingen artikkel er publisert. Gyldig WordPress-applikasjonspassord, kontrollert avklaring av leveringsstatus og deretter en bekreftet kladdoverføring er neste nødvendige steg. Se [aktiveringsbevis, avvik og tilbakeføring](NEWS-ACTIVATION-20261004.md). Historiske «ikke aktivert»-avsnitt over beskriver tidligere leveranser.

## Nyhetsdesk – samlet kø (04.10.2026, under verifisering)

Arbeidsgren: `feat/unified-newsroom`, avhengig av PR #23. Den nye desken
samler Studio-utkast og Fotballrobotens godkjenning gjennom en autentisert
bro til WordPress. Fotballrobot-endringen ligger i `feat/newsroom-digest`
og bygger på aktiv 0.9.9 / web-PR #49. Ingen merge er utført.

- NRK Vestland- og Bømlo-RSS brukes til å finne saker. Originalen må hentes,
  identiteten bekreftes og teksten kildekontrolleres før klar-status.
- Korte selvstendige bokmålsutkast, eget nyhetsbilde og synlig lenke til
  originalen. NRK-lenken heter «Les hele saken hos NRK».
- Automatisk klargjøring er av som standard, maks åtte nye saker per døgn
  og én per kjøring. Avviste, uendrede og mislykkede saker gjenskapes ikke.
- Til godkjenning / Trenger avklaring / Under arbeid / Publisert.
  Serverroller, CSRF, revisjoner, historikk og manuell godkjenning beholdes.
- WordPress samler klare saker i ett e-postvarsel: ti minutters samleperiode,
  høyst én sending per time ved nye revisjoner. Ingen artikkeltekst i e-post.
  Uklar transportstatus stopper automatisk gjentakelse.

Historisk funn før aktivering: Studio sitt lagrede WordPress-passord ble
avvist med HTTP 401. Forbindelsen er nå reparert, se aktiv status øverst. Eksisterende WPVibe-nøkkel
skal bevares. Samlet godkjenning er nå verifisert i den innloggede flaten.
Ingen ekte sak skal publiseres som test, og tidligere uklar overføring skal
ikke gjentas uten oppslag etter eksisterende WordPress-innlegg.
