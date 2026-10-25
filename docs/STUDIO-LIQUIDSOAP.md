# L01 – eksisterende Studio-kø til Liquidsoap og Icecast

Dato: **23.10.2026**, Europe/Oslo. Hovedmål: [#109](https://github.com/toystad461/radiorubben-studio/issues/109).

Oppfølging 25.10.2026: den eksisterende statusrettelsen fra #113 er tilpasset
L01-grenen. Gamle sluttmeldinger erstattes bare ved fersk, bekreftet aktiv
motorstatus. Ved nettbrudd/foreldet svar vises siste melding som ubekreftet.
Dette er en visningsrettelse, ikke en ny sendemotor eller løsning på lydhakking.
Førkontrollen beholder hashen fra tidligere UI og registrerer kandidatens nye
hash; feltet `reviewed_audio_runtime_source` viser uendret lydkodegrunnlag,
mens `candidate_ui_revision` identifiserer visningsrettelsen. Den tidligere
fastlåste kontrollfilen fra 07d0d25 er fortsatt en gyldig historisk inventory;
en avvikende UI-hash skal avstemmes, aldri overskrives automatisk.

Denne leveransen kobler den eksisterende musikkøen til en egen Liquidsoap-motor
og en privat Icecast-teststrøm. PHP/Composer, biblioteket, programreglene,
godkjenninger og køens forekomst-ID-er beholdes. Radio.co/Playout.co er
produktinspirasjon; ingen slik tjeneste eller konto inngår i lydkjeden.

**Leveringsgrensen er kode, tester og PR. L01 er ikke ferdig før den samme
kjeden er kontrollert på den faktiske test-VPS-en gjennom Studio.**

## Grunnlag og avhengigheter

| Del | Grunnlag | Bruk i denne leveransen |
| --- | --- | --- |
| Kø, regler, kontrollbuss og UI | PR #99, `3b34aad9dae654d19853a216070f85549530f2f0` | Direkte PR-base; PR #98 er en underliggende avhengighet. |
| Isolert Liquidsoap/Icecast-pilot | PR #108, `f76569778256b40f14399a1e31668216ce95291d` | Gjenbruk av loopback-/konfigurasjonsprinsipp; den gjentakende pilotspillelisten tas ikke inn. |
| Bibliotek under FPMs mediegrense | PR #102, `ea1b86c447c0ee40fe0af1fb8e0f2d48f22066ed` | Minimal foreldremappe-rettelse og egen `open_basedir`-regresjon. |
| Senere analyse-/cue-arbeid | PR #103/#104/#106 | Ikke valgt som kodebase. Selektivt installerte nyere filer må avstemmes før eventuell utrulling. |

Gren: `feat/studio-liquidsoap-queue`. PR skal vurderes mot
`feat/music-scheduler-vps-playout`, slik at L01-endringen kan gjennomgås for seg.
Ingen full feature-gren eller standardpakke skal kopieres over dagens Studio.

## Flyten som implementeres

1. Operatøren bruker eksisterende Musikkstudio til å velge program, godkjenne
   musikk og lagre køen. Serveren beholder rolle-, CSRF- og revisjonskontroll.
2. PHP publiserer et privat manifest og en kontrollsumverifisert MP3-kopi fra
   biblioteket. Originalen endres ikke. Hver køforekomst har egen ID, også
   dersom samme lydfil brukes flere ganger.
3. Den separate PHP-supervisoren starter Liquidsoap etter en ny, eksplisitt
   Start-kommando. En omstart av tjenesten starter ikke avspillingen igjen.
4. Liquidsoap kobler seg til Icecast på `127.0.0.1`. Først etter tilkobling kan
   `request.dynamic` reservere neste forekomst via en fast, lokal PHP-kommando.
5. Liquidsoap dekoder MP3, legger på ett sekund inn-/uttoning og sender
   192 kbit/s MP3, 44,1 kHz stereo til `/rubben-test.mp3`.
6. PCM-hendelser føres tilbake til den eksisterende journalen. Studio henter
   status fra serveren. Nettleseren trenger ikke være åpen for avspilling.
7. Valgfri lokal lytting bruker en innloggingsbeskyttet Nginx-rute og ekte
   Icecast-lyd. Den starter bare ved et eget klikk i Studio.

L01 bruker **sekvensielle, myke overganger uten overlapp**. Crossfade, cue-basert
klipping, pregap, jingler, talespor og andre tidskritiske overganger hører til
L02 eller senere leveranser. Pause er deaktivert for Liquidsoap-motoren; Stopp
avbryter aktiv forekomst, og neste Start går videre. Gamle PHP/FFmpeg-funksjoner
har fortsatt sin egen pause og WAV-monitor.

## Status betyr noe konkret

| Felt/hendelse | Betydning | Beviser ikke alene |
| --- | --- | --- |
| `online` | Ferskt heartbeat fra riktig supervisor, mindre enn fem sekunder gammelt. | Lyd eller Icecast-tilkobling. |
| `streamConnected` | Supervisor er i live, Icecast har meldt tilkobling og PCM-livstegnet er ferskt. | At en ekstern lytter faktisk mottar lyden. |
| `reserved` | Motoren har fått eierskap til en køforekomst for forhåndslasting. | Startet, spilt eller fullført. |
| `started` | Forekomstens PCM-start er observert etter fade-kjeden i gjeldende motorsesjon. | At hele låten er avviklet. |
| `completed` | Slutthendelse fra PCM-grensen, samme forekomst/sesjon, plausibel varighet og fortsatt tilkoblet. | Personlig lytting, rettighetsrapportering eller offentlig sending. |
| `position` | Fremdrift fra siste observerte PCM-klokke, normalt oppdatert hvert sekund. | Nøyaktig lytteposisjon i en nettleser som har egen buffer. |
| `interrupted` / `failed` | Avbrudd eller feil med bevart identitet og eventuell faktisk starthistorie. | Fullført låt. |

Lydintegrasjonen kontrollerer i tillegg MP3 mottatt av en separat HTTP-lytter.
Dette er sterkere leveringsbevis enn bare `completed` i journalen. Testen bruker
syntetiske toner; den erstatter ikke brukerens lytting på test-VPS-en.

## Kø, feil og omstart

- Én ustartet reservasjon er tillatt. Aktiv låt A kan ha neste låt B reservert.
  Hvis A ikke kan dekodes, får ikke motoren hente og spille B forbi A.
- Aktive og reserverte forekomster kan ikke flyttes, erstattes, slettes eller
  låses om. Endring av programtid/reset krever at motoren har sluppet køen.
  Spolerydding bevarer filer motoren fortsatt eier.
- Kontrollsum, vanlig fil og fravær av symlink kontrolleres ved claim og ved
  observert start. Endrede godkjenninger/manifest eller en ufullstendig
  publisering stanser barnet; en request som allerede er forhåndslastet kan
  ikke trekkes tilbake bare ved å stoppe nye claims.
- En ubehandlet Stopp-kommando blokkerer nye claims umiddelbart. Supervisoren
  behandler allerede mottatte PCM-hendelser før Stopp, slik at faktisk start
  ikke forsvinner fra historikken.
- Samme `daemon.lock` deles med gammel PHP-spiller. Liquidsoap arver låsen;
  Linux parent-death-signal dreper lydmotoren hvis supervisoren forsvinner.
  Reservasjoner frigis først etter bekreftet child-exit.
- En tom/ferdig kø får ett sekund til siste encoderbuffer før teststrømmen
  stoppes. Ingen automatisk looping eller start etter veggklokken er aktivert.
- Manglende PCM-livstegn i fem sekunder gir feil og child-stopp. I tillegg
  finnes 20 sekunders oppstartsgrense, 15 sekunder for reservert lyd som ikke
  starter, og en sluttgrense på varighet + maks(5 sekunder, 2 %).
- Brutt Icecast-forbindelse stopper gjeldende sesjon. Ny Start er nødvendig;
  en gjenopprettet forbindelse gjenopptar ikke automatisk en avbrutt forekomst.
- Journalen er autoritativ og har fortløpende sekvensnummer. Ufullstendig eller
  ugyldig journal feiler lukket og må kontrolleres, uten automatisk gjenspilling.

PCM-livstegnene lagres flyktig i supervisoren. De lager ikke en permanent
journalrad hvert sekund. Dette er et avgrenset testgrunnlag; journalrotasjon og
langvarig døgnkontinuerlig drift må få egen driftsleveranse.

## Versjon og callback-valg

Malen er kontrollert mot Ubuntu **Liquidsoap 2.2.4-1** og **Icecast 2.4.4**.
`source.on_frame` kjøres i lydtråden etter fallback, også for blanke lydrammer.
En separat timertråd ville kunne fortsette mens lydtråden hadde hengt.

`source.on_end(delay=-1.)` brukes bevisst: i den fastlagte 2.2.4-koden slår
negativ delay av grenen for estimert gjenværende tid, slik at faktisk
PCM-slutt/track boundary brukes. Start/end har lydrammens tidsoppløsning.
En svært kort fil kan derfor starte og slutte i samme klokkeframe.

Kildegrunnlag: [on_end i v2.2.4](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/operators/on_end.ml),
[request-kilden](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/sources/request_source.ml),
[Icecast-utgangen](https://github.com/savonet/liquidsoap/blob/v2.2.4/src/core/outputs/icecast2.ml)
og den installerte 2.2.4-hjelpen for `source.on_frame`. Versjonsoppgradering
krever ny syntaks- og lydintegrasjon; nyere callbacks er ikke antatt tilgjengelige.

## Kontroller og reproduksjon

Bruk native PHP med `pcntl`, Python 3, FFmpeg, Liquidsoap og Icecast på Linux.
Ingen test bruker eksisterende mediefiler, VPS, betalte API-er eller offentlig
utgang. Nettlesertesten trenger Playwright/Chromium som testverktøy.

```bash
composer install --no-dev --prefer-dist --no-interaction
composer test
php tests/music-library-open-basedir.php
php tests/music-liquidsoap-state.php
php tests/music-liquidsoap-events.php
python3 tests/test-studio-stream.py
node tests/music-liquidsoap-ui.cjs
node tests/music-studio-integration.cjs
```

`PHP_BIN`, `LIQUIDSOAP_BIN`, `ICECAST_BIN`, `FFMPEG_BIN` og `FFPROBE_BIN` kan
peke på absolutte testbinærer. `RR_PLAYWRIGHT_MODULE` og
`RR_CHROMIUM_EXECUTABLE` kan peke på allerede installerte testverktøy.
`RR_STREAM_TEST_ARTIFACTS` lagrer bare syntetiske MP3-opptak og JSON-kvittering.
Den private runtime-konfigurasjonen med tilfeldig passord lastes ikke opp.

Den nye GitHub-jobben `Studio Liquidsoap queue` kjører PHP-kontrakten på
8.2/8.4, den faktiske lydkjeden på Ubuntu 24.04/PHP 8.3 og grensesnittet på
390/1280 px. Den har kun leserettighet til repoet og ingen deployjobb.
Eksisterende PHP-, pakke- og nettleserkontroller beholdes.

Lokal kontroll 23.10.2026: full Composer-suite, 60 tilstandskontroller,
79 hendelseskontroller, mediegrense, ny UI-test og gammel PHP/FFmpeg-integrasjon
består. Den samlede native lydtesten består på 87,62 sekunder før opprydding:
430 056 byte mottatt MP3, 17,92 sekunder dekodet lyd og 440/660/880 Hz i riktig
rekkefølge. Inn-/uttoning ble målt til henholdsvis 0,246/0,160 av midtnivået.
Opptaket inneholder et bevisst lytteravbrudd; motorens tre fullførte forekomster
kontrolleres i tillegg i journalen. Testen fremprovoserer gammel blokkert status
med en ubehandlet Start-kommando og krever kvittering for den nye kommandoen før
reconnect-kontrollen. Ved krasj kreves faktisk frigitt spillerlås og heartbeat
fra den nye supervisor-PID-en. CI-kjøring og eksakt commit lenkes i
[PR #111](https://github.com/toystad461/radiorubben-studio/pull/111).

## Konkret neste steg: avgrenset test-VPS-utrulling

Dette er en **plan for neste avtalte steg**, ikke en installasjonskvittering.
Ingen av tjenestemalene eller Nginx-eksemplet installeres av denne PR-en.

### Lesende førkontroll klargjort 25.10.2026

Miljø: **Studio VPS – test**, separat fra **Studio Uniweb – drift** og
**WordPress Uniweb – produksjon**. L01 har fortsatt førsteprioritet i #109.

`infra/studio-stream/preflight.py` samler en avgrenset JSON-kvittering gjennom
eksisterende administratoradgang. Den krever vertsnavnet `vps-5363103` og
en aktiv Studio-symlink direkte under `/srv/radiorubben-test/releases`.
Kjør som administrator med `python3 -B`; ingen installasjon, tjenestestart,
konfigurasjonsendring eller køendring inngår. Ikke kjør den gamle
`infra/music-playout/install-test.sh` på nytt over eksisterende installasjon.

Kontrollen bruker de faktiske L01-tjenestenavnene `rr-studio-stream.service`
og `rr-studio-icecast.service`, i tillegg til eksisterende `rr-music-playout`.
`rr-playout` og `rr-stream` er tjenestebrukere og må ikke alene brukes som
tjenestenavn ved vurdering av om L01 er installert.

Kvitteringen inneholder pakkeversjoner, utvalgte systemd-felt, web-/spillerkodehasher
og antall kø-/manifestinnslag samt heartbeat-alder. Kode sammenlignes med
L01-kandidaten `a34d50b`, PR99-base og sist rapporterte testrelease `fad12a2`.
Ukjente selektive endringer merkes `unreconciled` og skal avstemmes før skriving.
Manglende/uleselig kø rapporteres som manglende/ukjent, aldri som en tom kø.
Rapporten er ikke et atomisk øyeblikksbilde av flere filer; den beviser ikke
synkron kø, lyd eller ferdig installasjon. Ingen rå konfigurasjon, journal,
sesjoner, passord, originalmedier eller spor-/personnavn skrives ut.

Tilgangskontroll 25.10: direkte SSH fra assistentøkten ga `Network is unreachable`.
Cloud-nettleseren stoppet IP-testadressen med `ERR_CERT_COMMON_NAME_INVALID`.
Eksisterende GitHub-gateway har fortsatt begrenset `app/public`-utrulling og
faste statusprober; den gir ikke administratoradgang til installasjon av
systemd-/Nginx-konfigurasjon. Tilgangsgrensene omgås ikke.

Neste avhengige handling er å lese en faktisk førkontrollkvittering fra VPS,
avstemme filer og konfigurasjon, og gjøre den begrensede installasjonen med
privat backup og tilbakeføring konkret. Ingen VPS-resultater er bekreftet av
den lokale førkontrolltesten. Trelåters-test, omstart og lydkontroll gjenstår.

1. Kontroller gjeldende PR-head, grønn CI, aktiv release og faktiske hasher på
   test-VPS-en. Avstem de berørte filene mot selektive PR #102/#103/#104/#106-
   endringer og annen nyere kode. Ta privat backup og fastlås et begrenset
   filmanifest med før-/etter-hasher og tilbakeføring.
2. Behold PHP-FPMs `PrivateNetwork=yes`, `AF_UNIX`, `open_basedir` og
   `disable_functions`. Den gamle `rr-music-playout`-enheten er laget uten TCP;
   Liquidsoap skal ha en **egen** tjeneste med bare loopback-nettverk.
3. Installer native Ubuntu-pakker på testmiljøet etter avstemming. Lag privat
   konfigurasjon med `python3 infra/studio-stream/prepare.py --destination
   /srv/radiorubben-test/stream-runtime`. Generatoren starter ingen tjenester
   og krever en ny mappe; den overskriver ikke eksisterende konfigurasjon.
4. Gjennomgå de to systemd-malene. `rr-playout` kjører supervisor/Liquidsoap,
   `rr-stream` kjører Icecast. Planlagt eierskap: runtime-mappen `root:root`
   med `0711`, `engine.json` som `rr-playout:rr-playout` med `0600`,
   `icecast.xml` som `rr-stream:rr-stream` med `0600`, `logs` og `empty-web`
   som `rr-stream:rr-stream` med `0700`. `stream-runtime/engine` eies av
   `rr-playout` med `0700`. Kjørbar motorfil/passord ligger dermed utenfor den
   webskrivbare kontrollbussen; delte kontroll-/journalfiler beholder eksisterende
   gruppemodell.
5. Kopier et eksplisitt filsett til web- og spillerrelease. Spillerkopien
   trenger `app/music-playout.php`, `app/music-liquidsoap.php`,
   `scripts/music-liquidsoap.php`, `scripts/liquidsoap-child.py` og
   `infra/studio-stream/radio.liq`. De to `music-playout.php`-kopiene må ha
   identisk hash. Ikke kopier konfigurasjon, originaler eller andre private data.
6. Tilpass `nginx-stream.conf.example` til den **verifiserte eksisterende**
   Studio-FPM-socketen. Ruten `/music-test-stream/` autoriseres mot gjeldende
   Studio-sesjon/produce-rolle; Icecast er kun bundet til loopback. Webpoolen
   får ingen HTTP-proxykode eller utvidet nettverkstilgang. Aktiver først da
   `'stream_path' => '/music-test-stream/'` i den private spillerkonfigurasjonen.
7. Kjør `systemd-analyze verify`, `nginx -t`, PHP-kontroll og tilgangskontroll
   med de faktiske tjenestebrukerne. Kontroller anonym/observer-avvisning og
   autorisert MP3-lytting gjennom Nginx; dette er ikke bevist av lokal PHP-test.
8. Stopp gammel avspiller, behold dens journal, og start nye tjenester i
   stoppet modus. Gjennomfør minst tre godkjente låter i riktig rekkefølge,
   lukket/gjenåpnet nettleser, tom kø, filfeil og omstart. Registrer hørbar lyd,
   forekomst-ID, originalhash, faktisk kildecommit, miljø og kontrolltid.

Ved tilbakeføring stoppes nye tjenester først. Gjenopprett verifisert web- og
spillerkode, Nginx og privat konfigurasjon fra kvitteringen. Bevar journalen;
den oppdaterte PHP-spilleren kan registrere gjenværende Liquidsoap-aktive
forekomster som avbrutt og slippe ustartede reservasjoner. Også da kreves en ny
manuell Start. Tilbakeføring må kontrolleres på VPS-en før L01 lukkes.

Produksjon, offentlig radiosending, WordPress, DNS, Entra og TTS inngår ikke
i dette test-VPS-steget. Neste funksjonsleveranse etter godkjent L01 er L02:
kontrollrom, køatferd og overganger med korrekt observasjon av eventuell overlapp.
