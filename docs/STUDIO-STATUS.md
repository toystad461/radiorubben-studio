# Studio – felles status og arbeidsliste

Oppdatert 07.10.2026 etter aktivering av #40 og oppfølging av artikkelkontroll.
Eier: Thomas Magne Sellevold-Øystad.
Repo: https://github.com/toystad461/radiorubben-studio




## Artikkelretting og læringsregler – 07.10.2026

PR #40 er merget som 959fb5cbe46e29762ce24e623cdac1754a4fcc9a og
selektivt deployet i Actions 37543203059. PHP 8.2/8.4, mobiltester,
pakke og deploy besto. 13 filer ble endret; produksjonshasher og HTTPS
ble bekreftet. Privat backup: selective-37543203059-1. Innlogget Studio
viser det nye kontrollsenteret og Radioliste.

Ved redigering av et faktisk utkast ble CRLF-avsnitt fra nettleserskjemaet
tolket som tomme kontrollsegmenter. Kildekontrollen deler nå på alle
linjeskift og hopper bare over blanke segmenter. Alle reelle påstander,
kildebelegg, tekstfingeravtrykk og manuell godkjenning beholder kravene.
Regresjonstesten dekker LF, CRLF, mellomrom på blank linje, CR og
at manglende kontroll av en faktisk påstand fortsatt avvises.

Læringen er lagt inn som faste skriveråd i nettgeneratoren: bevar
meldt/skal ha-forbehold; ikke utled umiddelbar utrykning fra at politiet
er på stedet; ikke finn på videre oppfølging; ikke gjør «ingen skadde
funnet/meldt» om til «ingen skadet». Korte kilder gir korte saker.
Dette er eksplisitte generatorregler, ikke automatisk modelltrening.
Kontrollkravene er ikke svekket og det utføres ingen automatisk publisering.

Seks publiserte Studio-artikler får naturlige avsnitt via WordPress-
revisjoner. Ordlyd og metadata kontrolleres mot førversjonen.
Det korrigerte utkastet skal kontrolleres på nytt etter utrulling og
bli liggende til Thomas sin godkjenning. Endelig CI-, deploy- og
innholdskontroll føres i oppfølgings-PR-en ved levering.

## Aktivering av PR #40 – 07.10.2026

Thomas har uttrykkelig bedt om å utføre #40 og om mulig rette allerede
publiserte artikler. PR-ens funksjoner og avsnittsretting aktiveres via den
etablerte selektive main-pipelinen, med nye tester etter denne oppfølgingen.
Før merge ble logoavviket i sidebar avstemt: main med dimensjoner 2172×724
matcher nøyaktig den registrerte produksjonshashen
69ecf254292a9d6b35c2249ac8991b811364888321bbcb5366afd7096bdaf1d2.
Disse dimensjonene bevares i #40. Eksisterende kildegrunnlag, godkjenninger,
privat konfigurasjon og innhold skal bevares av utrullingen.

Seks publiserte Studio-saker er identifisert med setningsvise brudd:
1244, 1243, 1242, 1241, 1109 og 1107. Formateringen kan rettes separat via
WordPress sine innholdsrevisjoner, uten å endre ordlyd, ingress, bilder,
kategorier eller kildelenker. Nye naturlige avsnitt velges etter tema.
Siste head, grønne tester, deployresultat og verifiserte innholdsendringer
føres i PR #40 ved levering. Dette notatet alene er ikke et deploybevis.

## Kontrollsenter: sak til venstre, behandling til høyre – 06.10.2026

Avgrenset gren fra main b73d6646e69723c79521f81ee6d7ef68d0ee4445.
Kontrollsenteret viser «Saker til behandling», alle aktive saker og markert valg.
Høyre kolonne viser kilden, lagret bruksområde (radio/nett/begge), status og
lenker til eksisterende manus/nettredigering. Forkasting krever bekreftelse,
CSRF, rolle og ferskt listesnapshot; manus/historikk beholdes og kan gjenopprettes.
Endret bruksområde krever ny godkjenning. Nettvalget sperrer radio-klarstatus,
radiovalget sperrer WordPress-levering. Eksisterende utkast bevares.
Eldre saker beholder begge bruksområder til et eksplisitt valg gjøres.
Robåt fra Nyhetsdesk er også tilgjengelig direkte i høyre behandlingskolonne.
Valgt bruksområde styrer hvilke utkast som klargjøres; begge bruker ett originalgrunnlag.
Forslag vises som sammenhengende lesetekst med naturlige avsnitt uten å endre
lagret tekst eller kontrollfingeravtrykk. NRK krediteres før nettteksten og lenkes
til originalen; radiogeneratoren legger kildeomtalen først. Se [NRK-kreditering](NRK-CREDITING.md)
for verifiserte regler og avgrensningen mot en særskilt RSS-avtale.
Ingen artikkel godkjennes eller publiseres av dette arbeidet. Ingen produksjonsdeploy.
Selektiv aktivering etter review og grønne kontroller gjenstår; ekte innlogget
produksjonsflyt og redaksjonell godkjenning utføres ikke i testen.

## Guardrail-oppfølging etter PR #25 – 05.10.2026

Første kartlegging viste PR #25 på `fix/mobile-newsroom` mot
`feat/unified-newsroom`, head `2330214ff7335d2f497965e5cbc8f979610c488d`,
konfliktfri draft og grønn PHP 8.2/8.4/mobil-CI 37243345398.
Under arbeidet ble PR-en retargetet og merget eksternt kl. 12:14:19 Oslo:
slutt-head `4d8ac6e4e477b0477592244c94598e74f6510815`,
merge `e434969bc5f62ff1b1eb4af4a3bc3d47c0d1e886`.
Ingen merge ble utført av denne oppgaven. GitHub avviste et ikke-fast-forward
oppdateringsforsøk; eksisterende historie ble bevart uten force-push.

Denne separate oppfølgingen bygger på fersk main
`19bbb18d7e3250406e2de5dada7e5194c40818e9`. Kun release-workflow,
deploysperrer, isolert sperretest og dokumentasjon endres.
Fullpakke-apply stoppes før transport eller serveroperasjoner, også ved direkte
skriptkjøring. Automatisk apply er fjernet; release-workflow tilbyr bare
dry-run og tester alle PR-baser. Ny CI kjøres på oppfølgingsgrenen.

Mobilruntime, RSS-rettelser, programregister, nyhetsmanus og konsolidert
godkjenningsflate er urørt. Render PR #21 og dens separat dokumenterte status
beholdes; ingen Render-tjeneste er endret eller verifisert her.
Tidligere mobil/RSS-aktivering er kun lest som GitHub-historikk.
Ingen ferske serverhasher eller innlogget produksjons-UI er kontrollert her.
Ingen Uniweb/Render-deploy, secretendring, artikkelpublisering eller e-post.

Neste steg: review og grønne kontroller på oppfølgings-PR-en, fersk
produksjonsavstemming, nytt selektivt manifest og Thomas sin eksplisitte
godkjenning før en ny utrulling. Se [preflight og rollback](STUDIO-DEPLOY.md).
Eksakt oppfølgingshead og endelig CI-status dokumenteres i PR-beskrivelsen.

## RSS: selektivt publisert og ekte-data-test – 05.10.2026

Studio PR #23, #24, #25 og #26 er merget. PHP 8.2/8.4 og mobiltester er grønne
også på hovedgrenens sluttpunkt 66966cc3509bcf2c3af496e5e996513fedaac92b.
Rettelsen i #26 er selektivt installert på Uniweb: app/news-script.php,
app/case-workflow.php og app/newsroom.php. Kilde 0612c6c650ac7a8b5e6857ac6e3fca2ab0c516f4.
Ingen helgren-deploy; øvrige aktive avvik og privat konfigurasjon er bevart.

Deployment via web PR #54, release e145c19f0a12f4c5431e536bb7afb900a00765fe,
[Actions 37299028081](https://github.com/toystad461/radiorubben-web/actions/runs/37299028081):
artefakt og installasjon besto. Åtte ferske før-hasher ble avstemt, og alle
tre etter-hasher er bekreftet. Privat backup og rollback står i web-repoets
RSS-STUDIO-RELEASE.md og scripts/rss-studio-release.json.
Uniweb-aliasstien er avstemt mot den observerte kanoniske app-stien; første
stopp endret ingen kode.

Testen brukte faktisk Bømlo RSS/original og aktiv Studio-konfigurasjon på
en isolert privat sendeliste. Nettutkast og radiostikk ble laget på samme
item.id, med identisk originaltekst, kildehash og hentetidspunkt.
Nettkontroll: bestått. Radiokontroll: kjørt, men ikke klar-status.
Begge utkast forble uten godkjenning; ingen WordPress-levering eller test-e-post.
Aktiv sendeliste var byte-identisk før/etter. Eldre web-only-saker er ikke
regenerert eller endret som del av denne utrullingen.

Innlogget UI er ikke kontrollert i denne leveransen: nettlesertilgangen var
utilgjengelig. Manuell sluttgodkjenning beholdes. Før faktisk publisering
må Thomas lese teksten og kontrollrapporten. robot.php og den separate
Robot-importen er fortsatt parallelle innganger, dokumentert i RSS-FLOW-AUDIT.md.
Full garanti for alle historiske veier eller feilfrie fakta gis ikke.

## Mobilflyt – aktivert 05.10.2026

Arbeid på `fix/mobile-newsroom`, basert på fersk `feat/unified-newsroom`
`871f4d91cc3894709a7084c6ce8a7f9a2c1938e4`. Kø og Studio-meny er foldet på
mobil. Saken, hovedbildet, endringsønsker og manuell godkjenning blir på samme
side. Bekreftet godkjenning/forkasting åpner neste ferdige sak; hver sak krever
ny avkrysning. Nettverksfeil beholder innskrevet tekst og sperrer ny innsending
inntil status er hentet. Serverroller, CSRF og eksakte revisjoner er bevart.

WordPress-avhengigheten er eksplisitt: Fotballrobot 0.10.4 på egen gren/PR
med hovedbilde i køresponsen og kvalitetssikret omskriving av ventende rettelser.
Ingen publisert sak eller e-post endres av deploy. Studio-bilder leveres via
en autentisert, domeneavgrenset bildeendepunkt; CSP utvides ikke.

Lokale tester på 375/390/800/1280 px dekker ingen sideskift, lesebekreftelse,
ny bekreftelse per sak, bevarte endringer, konflikt og nettverksbrudd. Isolerte
PHP-tester dekker køvalg og bilde-URLer samt fragment og eksisterende rollegater.
Runtime: Studio `e977efaf9a0e2e51abc9ede9513cd7a37f04b988` (PR 25), WordPress
`29e49e75b7890dd885050b4ad83d97ad266288fa` (PR 52, plugin 0.10.4).
Selektiv release `dd6b775ee2f80365c3b55b123ae8f418c9a9eea1`, Actions
37243206615, fullført med verifiserte før-/etter-hasher og privat backup.
Studio CI 37243066275/37243068279 og WordPress CI 37243108895/37243209317
var grønne. Postflight bekreftet at publisert sak 1091 og ventende rettelse var
urørt. Fersk autentisert kørespons viser hovedbilde 813, endring tillatt og
manuell godkjenning av rettelsen. Ingressen gjentas ikke i lesekopien.

Nettleserkontrollen ble sendt til normal innlogging. Innlogget produksjonsvisning
og fysisk iPhone er derfor ikke verifisert i denne leveransen; automatisk
mobiltest og skjermbilde er fra isolerte fiktive saker. Ingen reell godkjenning
eller test-e-post ble utført. Neste steg: redaktørens daglige bruk og eventuell
justering av leseflyt på fysisk mobil. Manifest/tilbakeføring er dokumentert i
web-repoets `MOBILE-NEWSROOM-RELEASE.md`.

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

Bekreftet ordinær bakgrunnskjøring 04.10.2026 kl. 21:59:09 UTC / 23:59:09 Oslo:
`rrfr_newsroom_last_prepare` returnerte `state: prepared`. WordPress sin cron-test
bekreftet fungerende oppstart (HTTP 200); `DISABLE_WP_CRON` er ikke satt.
Dette er en faktisk planlagt klargjøring gjennom WordPress, i tillegg til
manuell UI-kontroll. Ingen automatisk artikkelpublisering er aktivert.

## Konsolidering startet – 05.10.2026

Brukeren har bestilt å begynne å integrere PR-er og teste med ekte data.
PR #19 er merget til main som a1a17da5c39648fe73813ae67f41843e4b27daf7,
og PR #22 som 53fb663f288aec9ab4770bd5a8b5f668e52e06d6. Eksakte head-er
før merge var henholdsvis 049b42591e525055232503e4710787ad09b62139 og
c96b27af7ab184f2e407fc5b3e2b83b68671ac11. Begge hadde grønne PHP 8.2/8.4-kontroller.
Etter #19-mergen besto hovedgrenens PHP/JavaScript/pakke-kjøring 37289463910.
Release-kjøring 37289464018 besto verifisering, men stoppet i draft-jobben:
eksisterende v0.0.2 tilhører en annen commit og ble ikke overskrevet.
Deploy og publish ble hoppet over. Ingen serverfiler ble endret.

Isolert Robot-test på fem ekte Bømlo RSS-saker bekreftet fem førstegangsregistreringer,
null nye ved gjentakelse, fem eksporterte saker og felles event-ID-er. Dette er
ikke bevis for artikkelgenerering, kildekontroll, sluttgodkjenning eller WordPress-levering.
Ingen ekte sak ble godkjent eller publisert i denne testen.

PR #23 er retargetert til main som neste integreringstrinn; #24 og #25 følger
i avhengighetsrekkefølge etter ny gjennomgang. Historiske alternative grener og
main-auditen i #26 skal ikke massemerges. Ny release må få eget versjonsnummer
etter konsolidering. SSH-nøkkel/known-hosts manglet i siste deploy-forhåndskontroll;
fersk serveravstemming, backup og tilbakeføring kreves før produksjonsdeploy.
Denne oppdateringen endrer bare dokumentasjon.

## RSS: to produksjoner fra samme original – 05.10.2026

PR #23, #24 og #25 er merget til main i denne rekkefølgen. Nyhetsdesk og mobilflyt
har grønne PHP 8.2/8.4-kontroller; mobiltesten dekker 375/390/800/1280 px.
Sluttpunkt etter #25: e434969bc5f62ff1b1eb4af4a3bc3d47c0d1e886.
Ingen av disse mergene er bevis for en ny serverdeployment.

Kodegjennomgangen fant at studio_newsroom_prepare bare laget nettutkast.
PR #26 er derfor avstemt mot det konsoliderte grunnlaget og erstatter sin
tidligere patch mot gammel main. Den henter originalen én gang og lager web
og manglende radio-stikk med samme kildeobjekt på samme sak. Begge har separat
språk-/kildekontroll; ingen får automatisk sluttgodkjenning. Eksisterende
radiotekst bevares ved nettendringer. Radiofeil bevarer nettutkastet og viser
avvik; neste automatiske kjøring skal ikke gjenta betalt generering.

Autentisert WordPress-kø ble lest 05.10.2026. Reelle fotballsaker med manglende
kvalitetskontroll har canApprove=false. Dette bekrefter købroen og sperren,
ikke Studio-innlogging, RSS-generering eller offentlig publisering.
Faktisk fullflyttest, ferske serverhasher og selektiv deploy gjenstår.


## Neste RSS-rettelse: kildeattribusjon og eldre web-only saker

Ren, separat kildeattribusjon kan bekreftes deterministisk mot validert originaladresse og intakt kildehash. Andre faktapåstander og alle redaksjonelle issues beholder kontrollkravene. Ved fornyet webkontroll klargjøres også et manglende radiomanus fra samme nyhentede original; eksisterende webtekst og eksisterende radiotekst bevares. Ingen automatisk godkjenning eller publisering. Endringen krever grønne PHP- og mobile tester samt ny selektiv serverkontroll før deployment. Live-backfill er ikke kjørt i denne endringen.

## SSH-oppsett – 05.10.2026

PR #31 inkluderer nå en separat, manuelt startet `studio-ssh-check.yml`.
Den bruker miljøet `studio-production`, kjører bare fra main og er uavhengig
av release/tag og `STUDIO_DEPLOY_ENABLED`. Den kontrollerer nøkkel, kjent vert,
SSH, eksisterende Studio-mapper og PHP/verktøy uten opplasting eller skrivetest.
Den leser ikke lokal konfigurasjon, brukere eller redaksjonelle data.

Oppsett gjenstår: bekreft Studio-konto og SSH-vertsfingeravtrykk i Uniweb,
installer en dedikert offentlig nøkkel og lagre privatnøkkelen direkte som
`STUDIO_DEPLOY_SSH_KEY` i GitHub-miljøet. Legg verifiserte vertsnøkler i
`STUDIO_DEPLOY_KNOWN_HOSTS`, og sett `STUDIO_SSH_HOST` og `STUDIO_SSH_USER`.
Ikke kopier WordPress-kontoens verdier uten å bekrefte tilgang til Studio.
Hold `STUDIO_DEPLOY_ENABLED=false` under oppsettet. Kontroller miljøregler
og reviewer før manuell kjøring. Nøkler skal aldri legges i chat eller Git.

Workflowen må gjennomgås og merges før den kan startes fra Actions.
En grønn tilkoblingstest verifiserer ikke skriveadgang, domenets webroot,
produksjonens filhasher eller selektiv deploy/rollback. Disse kreves fortsatt
før utrulling. GitHub-pluginen kan ikke administrere secrets eller starte
workflowen; kontoinnstillinger og første kjøring gjenstår. Ingen ny tilkobling
eller produksjonsdeploy er utført i denne oppfølgingen.

## RSS-oppfølging ferdig deployet 2026-10-05

PR 28, 29, 30 og 32 er slått sammen etter grønne PHP 8.2/8.4- og mobiltester. Kildeattribusjon, bokmål og strengt format med begrensede avsnittsreferanser er rettet. Selektiv deployment med privat sikkerhetskopi og utfylling av to eldre web-only saker lykkes i https://github.com/toystad461/radiorubben-web/actions/runs/37302374295 . Netttekst og sak-ID er bevart; begge produksjoner deler samme nyhentede originalgrunnlag.

Sluttkontroll https://github.com/toystad461/radiorubben-web/actions/runs/37302620846 viser tre aktive RSS-saker med begge produksjoner og null web-only. Den tredje kom gjennom ordinær automatisk worker og bestod både web- og radiokontroll. Alle tre er fortsatt uten manuell godkjenning og WordPress-leveranse.

Redaksjonelle avvik: De to eldre netttekstene har udokumenterte påstander om blant annet personskader, brannvesen, myndighetsoppfølging og rasets konsekvenser. Ett eldre radioutkast krever også gjennomgang. Disse må korrigeres og kontrolleres før Thomas godkjenner. Den isolerte testen av Bømlo-saken om frivillighet fullførte begge kontroller; radio bestod, mens en webpåstand om prosjektets mål ble flagget. Testen endret ikke aktiv kjøreplan. Fungerende flyt er ikke en garanti for feilfri AI-tekst.

Detaljert dokumentasjon, sikkerhetskopier, stoppede forsøk og resterende arbeid: https://github.com/toystad461/radiorubben-web/blob/codex/rss-studio-release-20261005/RSS-STUDIO-RELEASE.md . Autentisert nettleserkontroll og pensjonering av robot.php/navigasjonen gjenstår. Ingen offentlig side, plugin, tema, legitimasjon eller publiseringsrettighet ble endret av denne oppfølgingen.


## SSH-oppsett verifisert i kontrollpanel – 05.10.2026 kl. 16:08 Oslo

Denne oppdateringen erstatter gjenstående konto-/secretoppsett i SSH-notatet over.
Thomas valgte Uniweb-konto 79007. Kontrollpanelet bekrefter vert
`ssh.cptk37ymg.service.one` og bruker `cptk37ymg_w1417156`.
Filbehandleren viser Studio-mappene under r1417157. Thomas sin lokale
SSH-test bekrefter PHP CLI 8.4.26, rsync og begge Studio-mappene.
En dedikert Ed25519-nøkkel ble opprettet lokalt av Thomas og installert
med `restrict`; BatchMode-test svarte «SSH-nøkkel fungerer». Dette begrenser
videresending/PTY, men avgrenser ikke nøkkelen til bare Studio-filer.
GitHub-miljøet studio-production viser begge secrets registrert:
STUDIO_DEPLOY_SSH_KEY og STUDIO_DEPLOY_KNOWN_HOSTS. Verdiene er ikke lest.
Known-hosts ble kopiert fra Mac-ens eksisterende vertsregister; uavhengig
bekreftelse av vertsfingeravtrykket er ikke dokumentert her.
STUDIO_DEPLOY_ENABLED og STUDIO_DEPLOY_AUTO_APPLY er begge false.
Miljøet viser No restriction for deploygrener og ingen reviewer-regel.

PR #31 oppdateres mot main ae67af071ec01d64ddedbfcf02a6fb9a01effd48.
Statuskonflikten løses ved å beholde både RSS-journalen og SSH-notatet.
Neste steg er review/merge av PR #31 og manuell SSH-tilkoblingstest fra
main. Første GitHub-tilkobling er ennå ikke kjørt. Ingen produksjonsfiler
er lastet opp; fullpakke-apply forblir sperret i denne PR-en.

## GitHub SSH-test – 05.10.2026 kl. 18:42 Oslo

PR #31 er merget som aa7af7732c20b83062406c2d8336899bfa04b500 etter Thomas sin godkjenning. Første SSH-test (Actions 37342869433) nådde serveren med streng vertsverifikasjon og nøkkelautentisering. Den stoppet på manglende CLI-verktøy realpath. Tilkoblingstesten bruker nå PHP realpath() for samme kanoniske stikontroll; ingen validering slås av. Nettstedfiler er ikke lastet opp. PR #31 inneholder kun driftsoppsett, tester og dokumentasjon, ingen runtime-endringer til Studio.


## Uniweb-katalogalias – 05.10.2026

Actions 37343319506 autentiserte over SSH, men avviste katalogaliaset
`/run/webroots/r1417157`. Fersk SSH-kontroll bekrefter at dette er en
serverstyrt symlink til `/customers/9/3/1/cptk37ymg/webroots/r1417157`.
Tilkoblingskontroll og fullpakkens dry-run verifiserer nå det eksakte
aliasmålet og de kanoniske Studio-katalogene med PHP realpath().
Et annet mål eller symlink for selve Studio-mappene avvises fortsatt.
Fullpakke-apply og automatisk publisering er fortsatt sperret.
Ingen runtime, hemmeligheter eller redaksjonelle data endres av rettelsen.
Neste steg: grønn GitHub SSH-test og fersk filavstemming før selektiv utrulling.


## GitHub-tilkobling bekreftet og ny preflight – 05.10.2026

PR #35 er merget etter grønne PHP 8.2/8.4- og mobilkontroller. GitHub Actions
37344110000 bekrefter SSH og serverforutsetninger fra GitHub til Uniweb.
Release-opprettelsen stoppet separat på en eksisterende versjon/tag.
Manuell dry-run avkobles derfor fra draft-jobben og trenger ikke at
STUDIO_DEPLOY_ENABLED aktiveres. Den kan aldri kjøre apply. Manglende SSH-oppsett
gir feil i stedet for en grønn, overhoppet preflight.

Ferske kodehasher mot main-grunnlaget: 84 av 90 identiske, seks avvik.
Avvik: app/bootstrap.php, app/views/head.php, app/views/sidebar.php,
public/login.php, public/local/login.php og public/robot.php. De første fem
gjelder logo/favikon; robot.php har ulike private innboksstier. Ingen kodefil
er installert som del av kontrollen. Vendor, binære ressurser og privat
konfigurasjon inngår ikke i denne 90-filers sammenligningen.


## Uniweb filtransport – 05.10.2026

PR #36 er merget. Actions 37344569738 bestod kodekontrollene, men SFTP
returnerte feilstatus etter at ZIP-filen var skrevet. Samme oppførsel ble
bekreftet med en separat opplasting til privat staging. Transporten bruker
nå SSH-stdin med umask 077 og SHA-256-kontroll av både pakke og skript før
kjøring. Streng vertsverifikasjon beholdes.
Den opprinnelige prøvepakken ble kontrollert via SSH og fullførte
DRY-RUN OK; ingen nettstedfiler ble endret. Ny ende-til-ende GitHub-kjøring
gjenstår etter merge. Fullpakke-apply forblir sperret.


## Automatisk publisering autorisert – 05.10.2026

Thomas ba eksplisitt «aktiver automatisk publisering» etter grønn SSH-
prøvekjøring 37345265396. Ny selektiv publisering erstatter ikke fullpakke-
sperren. Baseline er 489 kode-/avhengighetsfiler med ferske produksjonshasher
mot pakke e2e3bad750c7355feaf5b2fc7a6f48b26e949207. Kjente forskjeller
bevares frem til de aktuelle kildefilene faktisk endres i GitHub.

Implementert: main-trigger, PHP- og mobilgate, nyeste-main-kontroll,
kontrollsummer, privat backup/manifest, felles serverlås, atomiske filbytter,
selektive slettinger og rollback ved feil. Runtime-data og konfigurasjon er
utenfor filsettet. Tester dekker bootstrap, drift, symlinker, rettigheter,
oppdatering/tillegg/sletting, bevaring av privat data og rollback.
GitHub CI og aktivering av miljøvariabel/produksjonsstatus gjenstår etter PR.


## Automatisk publisering aktiv og verifisert – 05.10.2026

PR #38 er merget som 84aa03050e739f28bf81ceae18124f6294407912.
STUDIO_DEPLOY_AUTO_APPLY=true i studio-production. Push utløste Actions
37346998632 automatisk; PHP 8.2/8.4, mobil og deploy besto. Serverens
selective-state.json bekrefter denne committen og kjøring 37346998632-1.
Alle 489 registrerte filhasher er kontrollert mot faktisk produksjon, uten
avvik. Kun vendor/composer/installed.php ble oppdatert (byggets kilde-ID).
De kjente forskjellene i logo, favikon og robot.php er bevart.

Privat sikkerhetskopi og før-/ettermanifest finnes på serveren i
~/.radiorubben-studio-deploy/backups/selective-37346998632-1/.
HTTPS-/innloggingskontroll besto. Ingen manuelt innhold, konfigurasjon,
WordPress eller redaksjonell publisering ble endret. Fremtidige endringer
til main publiseres etter de samme kontrollene; direkte kodeendringer på
serveren stopper automatikken for avstemming. Fullpakke-apply er sperret.
