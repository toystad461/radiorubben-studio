# Musikkstudio: regler, kø og isolert testavvikling

## Avstemming og omfang

Arbeidet ligger på `feat/music-scheduler-vps-playout`, med PR #98
(`feat/music-queue-foundation`, `bf46227247195e5f64b5b816b1253b206f66dc6b`)
som **kodeavhengighet og PR-base**. PR #98 var åpen/utkast ved kontroll
19.10.2026. Den lagrede køen, originalreferansene, fil-låsen og lokal
prøvelytting gjenbrukes. Hovedgrenen var `96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60`.

PR #96 (`infra/uniweb-vps-test-5363103`,
`adb560c257b49a80d80d94b0bf345ef7714eadbb`) var også åpen/utkast. Dens
isolerte VPS er en **driftsavhengighet**, ikke en blind kode-merge.
PR #97 om WordPress-testmål er uavhengig og inngår ikke.
Ingen gren er merget, og ingen produksjonsdeploy er bestilt.

Ny side: `/music-studio.php`. PHP/Composer er beholdt. Ingen ny PHP- eller
JavaScript-pakke er nødvendig i appen. Den separate CLI-tjenesten trenger
Ubuntu-pakken FFmpeg. Node.js er ikke nødvendig for denne avvikleren.
Ingen AI-, ElevenLabs-, WordPress-, Radio.co-, e-post- eller DNS-kall inngår.

## Bruk

1. Klargjør eksisterende MP3-filer og analyser i Musikkbibliotek. Originalene
   beholdes. Målinger og AI-vurderinger er valgfrie; ukjente data vises som ukjent.
2. Velg **God morgen Bømlo**, dato og starttid i Europe/Oslo. Lagre.
3. Åpne «Godkjenning / rettelser» per låt. Produsent/administrator angir
   testgodkjenning, programtilhørighet, eventuelle sperrer, ukedager/musikkflater
   og manuell energi/stemning. Dette er ingen rettighets- eller offentlig
   sendegodkjenning. Godkjenningen er bundet til SHA-256 for lydversjonen.
4. Be om neste tre, se begrunnelser/mangler og velg «Bruk forslagene».
   Forslag alene endrer ikke køen. Låter kan også legges inn manuelt; sperrer
   vises og hindrer avvikling. Bytte gir et forslag som må bekreftes.
5. Lås, flytt eller fjern innslag. Et låst innslag kan ikke byttes/fjernes/flyttes,
   og en flytting av andre innslag kan ikke skyve det ut av sin plass.
   Aktivt/behandlet innslag kan ikke redigeres som et nytt innslag. «Ny testkø»
   tømmer en stoppet, ulåst kø; avspillingshistorikken bevares.
6. Når VPS-tjenesten er installert: synkroniser og trykk Start. Start er en
   **manuell teststart nå**, ikke en tidsstyrt sending. Planlagt dato/tid styrer
   musikkvalget; tjenesten venter ikke til neste morgen. Prøving av et fremtidig
   morgenprogram om kvelden bruker derfor morgenprofilen.
7. «Lytt til testutgangen» spiller faktisk dekodede, private WAV-biter fra
   tjenesten. Nettleseren er bare monitor/kontrollflate. Lukking av fanen sender
   ingen stoppkommando. Lokal prøvelytting fra PR #98 er fortsatt separat og
   skriver aldri avviklingshendelser.

## Datagrunnlag og regler

`music_library_metadata`, `music_library_analysis_current`,
`music_library_producer_current`, `music_library_tag_labels` og
`music_library_cues_current` gjenbrukes. Måleestimatene valideres mot lydhash og
format. AI-vurdering må i tillegg ha samme inputhash som dagens grunnlag.
Endret grunnlag gjør tidligere vurdering ubrukelig. «Utdatert» betyr her
kilde-/grunnlagsendring, ikke en oppdiktet utløpsdato for samme lydfil.

| Opplysning | Bruk og prioritet |
| --- | --- |
| Artist/tittel/sjanger | Manuelle bibliotekrettelser foran kildebundne filmetadata |
| Varighet | Gyldige filmetadata; manglende varighet stopper beregning av senere tider |
| BPM/toneart | Eksisterende autentiserte måleestimater med manuelle lydrettelser foran |
| Energi | Manuell vurdering (også eksplisitt «ukjent») foran gyldig AI-vurdering |
| Stemning/merkelapper | Egne merkelapper og manuell stemning, kildebundne merkelapper, gyldige AI-forslag; kildetype følger verdien |
| Passende sendetid | Eksisterende `music_mood` for ukedag/tid og AI-perioder som preferanse; manuelt tillatte perioder er sperrer |
| Cue | Kildebundne manuelle punkter og analysegrunnlag bevares, men brukes ikke til klipping/overgang i denne leveransen |

BPM blir aldri oversatt til energi. Filnavn gir ikke musikalske egenskaper.
Gamle automatiske sjanger/år-merkelapper gjeninnfører ikke overstyrte verdier.
AI-energi-merkelapper får ikke overstyre en manuell energivurdering.

**Absolutte sperrer:** tilgjengelig/hashverifisert lyd, gyldig varighet og kjent
artist, manuell versjonsbundet testgodkjenning, programtilhørighet, manuell
sperre, tillatt ukedag/musikkflate, artist- og låtavstand. En endring av sperre
på et aktivt innslag avbryter også dette innslaget ved neste kontroll.

**Preferanser:** ønsket energi, energitrinn fra forrige låt, temposkifte,
stemningsmerkelapper, sjanger, AI-vurdert musikkflate og liten valgfri
samme-toneart-bonus. Ukjent egenskap gir ikke oppdiktede poeng. Førsteprofilen
har varm/positiv/lett/melodisk stemning og moderat energi. Programbeskrivelsen
er redaksjonell veiledning; de strukturerte innstillingene styrer velgeren.

Standardverdier er uttrykkelig **forslag til utprøving**: 60 minutter mellom
samme artist, 240 mellom samme låt, maksimalt 1 energitrinn og 20 BPM som
behagelig overgang, toneartvekt 1 (justerbar 0–2). De er ikke tidligere vedtak
fra Thomas. Ingen regel lempes automatisk. Energi-/temposkifter er preferanser,
ikke skjulte sperrer. Regelskjemaet viser skillet og kan redigeres separat
for hvert program. Det eksisterende redaksjonsprogrammet God morgen Vestland
og dets læring endres ikke.

Hvert innslag får beregnet starttid med sin faktiske registrerte varighet.
Oslo-tid konverteres til et entydig tidspunkt; ikke-eksisterende vårtid avvises,
og dobbel høsttid krever første/andre forekomst. Nye forslag vurderer hele
køen og faktisk startet historikk. Bytte kontrollerer også senere innslag etter
at varigheten er endret. Lik poengsum avgjøres stabilt ved låt-ID.

Lite bibliotek gir antall avvisninger per årsak og konkrete alternativer:
klargjør/godkjenn flere låter, flytt starttid eller endre en synlig regel
bevisst. Eksisterende kø tømmes aldri for å gjøre plass til forslag.

## Vedvarende avvikling

`config/music-play-queue.private.json` beholder PR #98-formatet og får et
`studio`-felt for profiler, programtid og manuelle godkjenninger. Hvert innslag
har unik forekomst-ID, `kind`, bibliotek-ID og SHA-256. Låsing og valgets
begrunnelse/datagrunnlag lagres per forekomst. Filer, gamle opplysninger og
køposter bevares; ingen automatisk datamigrering skriver på sidevisning.

Rolle, CSRF og revisjon kontrolleres på serveren. Kølåsen serialiserer ekte
parallelle operatører. Spillerkommandoer krever også fersk spillerrevisjon.
En ubekreftet kommando gjentas aldri automatisk. Etter tidsavbrudd må status
hentes på nytt. Den gamle køeditoren avviser endringer i en planlagt studiokø;
prøvelyttingen der kan fortsatt brukes.

Tjenesten `rr-music-playout` kjører som **rr-playout**. Den ser bare en privat
kopi av godkjente, hashverifiserte originaler og et manifest. Den får ikke
lese Studio-konfigurasjon, brukere, sessions, WordPress eller originalbiblioteket.
PHP-FPM får kun en ekstra, avgrenset filbane og gruppeadgang til testbussen;
`PrivateNetwork`, `AF_UNIX`, `NoNewPrivileges`, `PrivateTmp`, `allow_url_fopen`
og kommandosperrene består. Ingen lytteport eller offentlig strøm opprettes.

Manifestet er et bevisst øyeblikksbilde ved synkronisering. Kø-/profil-/policy-
endringer i Musikkstudio publiserer et nytt manifest. Ved endringer i selve
biblioteket må «Synkroniser kø» brukes før fortsatt avvikling; start synkroniserer
også på nytt. Allerede klargjorte kopier erstattes aldri med en annen lydversjon.
En sperret/usynkronisert kø stopper før neste innslag, og manglende/endrede
lydkopier gir feil. En synkroniseringsbarriere hindrer bruk av et gammelt
manifest hvis kopiering eller lagring feiler. En allerede aktiv låt kan
fortsette frem til neste innslag under en slik feil; Stopp er tilgjengelig.

En livslang `flock` hindrer to avspillere. FFmpeg dekoder til PCM via pipe,
uten nettprotokoller. PHP-tjenesten leverer PCM i faktisk tidsrytme og håndterer
pause uten rask «innhenting». Dekoderstans og avvikende lydlengde gir feil.
Monitoren får 24 kHz stereo WAV-biter på inntil 2 sekunder gjennom en
rollebeskyttet PHP-rute. Monitorforsinkelsen er omtrent 2–5 sekunder; dette er
ikke en sample-synkron produksjonsutgang, gapless-mikser eller offentlig sending.
Køsynkronisering bruker felles lås og kan gi kort opphold mens lyd verifiseres.

`events.jsonl` er en append-only, fsync-bekreftet journal. Hver post inneholder
hendelse og resulterende spillerstatus; avsluttede innslag lagres som delta,
ikke full historikk i hver post. Tilstand rekonstrueres fra journalen. Skadet
eller ufullstendig journal stopper tjenesten for manuell kontroll; den slettes
ikke. Monitorfiler er korttidsdata; originaler og journal slettes ikke.
Ubrukte spoolkopier ryddes ved synkronisering, mens aktiv lyd beholdes.

| Status | Betydning |
| --- | --- |
| Planlagt | Forekomsten står i varig kø; ved aktivering logges `planned` |
| Startet | Første faktiske dekodede prøver er levert til testutgangen |
| Fullført | Dekoder avsluttet korrekt, forventet lydlengde levert |
| Avbrutt | Operatørstopp, ny sperre eller omstart under aktiv avvikling |
| Feilet | Manglende/endrede data, dekoderfeil, tids-/lengdeavvik |

Alle hendelser har `output=isolated-test`; ingen kalles radiosending.
Historikken teller bare innslag som faktisk har `startedAt`, ikke køplassering
eller feil før første lydprøve. Etter omstart markeres aktivt innslag avbrutt,
eldre startkommando kvitteres uten å spilles, og ny manuell Start kreves.
Avbrutte/feilede forekomster gjentas ikke automatisk. Lag en ny forekomst for
et bevisst nytt forsøk.

Status inneholder faktisk `lastStarted`, `lastCompleted`, neste ubehandlede
innslag og programprofil. Dette er kontrakten en senere robotvert kan bruke;
ikke anta at siste køpost er sist spilt. `kind` reserverer også `jingle` og
`program_break`. Disse typer er eksplisitt sperret i plan/avvikler nå, frem til
egen kilde- og innholdsgodkjenning er implementert. Cue-punktenes eksisterende
`rotationApproved=false` blir ikke omtolket til godkjent automatisk overgang.
Ingen robotvert eller betalt lyd aktiveres.

## Installasjon på test-VPS

**Installert på test-VPS 19.10.2026.** Autentisert Linux/sudo og fersk
preflight bekreftet vps-5363103, IP 5.249.252.78, Ubuntu 24.04.5, forventet
Studio-baseline `96ff332` og eksisterende PHP-isolasjon. Første installasjon
av `961a6d3f730d665cc18da6f9d26976eb5c73900b` ble fullført kl. 12:39 Europe/Oslo.
FFmpeg 6.1.1 kom fra Ubuntu-pakkene. Nginx/PHP-konfigurasjonene bestod før/etter.

Kontroll med faktisk rr-studio-bruker avdekket chmod på en korrekt 0660-lås
som var eid av rr-playout. Rettelsen `fad12a2320de0ff0a036854c414accee794143bb`
er installert som ny release etter privat backup og kontrollert stopp/start.
Aktiv symlink peker på
`/srv/radiorubben-test/releases/studio-music-fad12a2320de0ff0a036854c414accee794143bb`.
Begge installerte kopier av `app/music-playout.php` har SHA-256
`b92a1dc3c7e393f12462cd0cbbcd7bd3341ee851cd475b27ffdd57c52c45552b`;
forrige fil i backup har `9ef159ca72e6a6231d0c9ca8bb7ab0f07196ec65eabbf977b7a4c529cd38a979`.
Rettelsens backup ligger i
`/var/lib/radiorubben-test/music-fix-fad12a2320de0ff0a036854c414accee794143bb`.

Tjenesten er aktiv som rr-playout med PrivateNetwork=yes, AF_UNIX og
NoNewPrivileges=yes. rr-studio leser spillerstatus uten advarsel: online=true,
mode=stopped, current=null. En ny avspillerprosess ble avvist med exit 3
(`One active player only`). Testmediemappen er tom; ingen musikk er importert.
Ingen lyd er startet på VPS. Journalen er bevart
gjennom kontrollert stopp/start. HTTPS åpner test-Studios lokale innlogging.
**Gjenstår på VPS:** innlogging i Studio, godkjent testbibliotek og faktisk
lytting/lukking/gjenåpning av Studio-fanen. Disse siste lydkontrollene er ikke
utført på VPS ennå. Den tidligere Uniweb-konsollfeilen blokkerer ikke lenger.
Microsoft-innlogging er utsatt; ingen administratorpassord er lest eller lagret.

Commit `fad12a2` består [CI 39239703318](https://github.com/toystad461/radiorubben-studio/actions/runs/39239703318):
PHP 8.2/8.4, Composer/pakking, to faktiske Unix-UID-er og lyd-/nettleserintegrasjon.
UID-testen består også på VPS; lokal container støtter bare UID/GID 0 og er
ikke brukt som bevis for den. Den øvrige lokale lydintegrasjonen består.
Release-verifikasjon 39239703687 består. [PR #99](https://github.com/toystad461/radiorubben-studio/pull/99)
er fortsatt utkast; ingen merge eller produksjonsdeploy.

Installasjonsverktøy:

- `infra/music-playout/preflight.sh`: sjekker hostname **vps-5363103**, IP
  **5.249.252.78**, Ubuntu 24.04, aktiv release, PHP-isolasjon og webkonfigurasjon.
  Leser ikke passord eller `local.php`-innhold.
- `infra/music-playout/install-test.sh <eksakt kildecommit>`: krever ren,
  gjennomgått checkout. Sammenligner alle berørte aktive filer med den kjente
  main-/PR #98-basen og stanser ved uavstemte avvik. Installerer FFmpeg,
  begrenset bruker/tjeneste, privat buss og en ny release med eksisterende
  kode/config og bevarte mediefiler. Bytter release atomisk. Bevarer gammel
  release og privat kvittering/backup. Feil gjenoppretter tidligere
  release/PHP-pool og stopper tjenesten. Tjenesten starter **uten avspilling**.
- Systemd-enheten holder også spilleren uten nettverk og gjør lydspoolen
  skrivebeskyttet for spilleren. Den åpner ingen ny brannmurport.

Kildekopien eies av rr-build. Ved root-kjøring brukes en avgrenset Git-innstilling
for akkurat denne kontrollerte kopien, ikke et globalt unntak:

```sh
sudo env GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=safe.directory \
  GIT_CONFIG_VALUE_0=/srv/radiorubben-test/sources/radiorubben-studio \
  bash infra/music-playout/install-test.sh <eksakt-commit>
```

Installeren bruker `GIT_OPTIONAL_LOCKS=0`, slik at statuskontrollen ikke erstatter
rr-builds indeks med en root-eid fil. Første installasjon manglet dette;
eierskapet til akkurat `.git/index` ble kontrollert og gjenopprettet til rr-build.
Ikke kjør førstegangsinstalleren på nytt over en eksisterende spillerbuss.

Kjør først fersk preflight, avstem avvik og velg eksakt testet commit. Etter
installasjon må `systemctl is-active rr-music-playout`, PHP-isolasjon,
innlogging, bibliotek og privat monitor kontrolleres. Godkjenn en liten testkø,
start, lytt, lukk fanen og kontroller fortsatt avvikling i ny fane. Kontroller
også omstart, journal og manglende fil. Lydkontroll på den faktiske
VPS-en må fullføres selv når lokal integrasjonstest består.

Tilbakeføring: stopp og deaktiver `rr-music-playout`; bruk `previous-release`
og `php-pools.conf` fra `/var/lib/radiorubben-test/music-playout-<commit>` til å
gjenopprette symlenken og testpoolen, flytt `music-playout.local.php` til privat
backup og last PHP-FPM på nytt. Bevar kø, bibliotek, originale filer, spool og
journal for avstemming. Ikke kjør hovedgrenens produksjonsdeploy.

## Videre arbeidsmåte: SSH

Thomas prioriterte SSH 19.10 kl. 13:00. VPS-ens SSH-tjeneste er aktiv og
lytter på port 22 på IPv4/IPv6. Agentens direkte TCP-forsøk feiler med
`Network is unreachable` før autentisering. Dette er en konkret nettverkssperre
fra dette arbeidsmiljøet, ikke bevis på feil passord/nøkkel eller SSH-tjeneste.
Ingen ny nøkkel eller SSH-tilgang er opprettet. Neste steg er å koble til fra et
miljø som faktisk når VPS-en, og etablere nøkkelbasert, avgrenset testutrulling.
Verifiser serverens offentlige vertsnøkkel via Uniweb-konsollen ved første
forbindelse. Privat nøkkel skal bli på den valgte klienten/hemmelighetslageret;
ikke legg den eller administratorpassord i chat, repository eller dokumentasjon.
Produksjonswebhotellets SSH-konto er ikke denne VPS-ens administrator.

## Verifikasjon og begrensninger

- Native PHP-regeltester: Oslo/ukedag, DST-gap/fold, fremtidig morgen,
  tidsgrense mellom køinnslag, manglende/utdaterte data, manuell prioritet,
  godkjenning, program/sendetid, repetisjon, lite bibliotek og toneartvekt.
- Native lagring: rolle, CSRF via HTTP, forslag uten lagring, nøyaktig godkjent
  forslag, låsing/bytte, to samtidige PHP-prosesser med samme revisjon,
  skadet journal og bevarte originaler.
- Ende-til-ende: ekte PHP-API, separat PHP/FFmpeg-prosess, syntetiske MP3-filer,
  nettleser med faktisk dekodet, ikke-still lyd, mobil/desktop, en aktiv spiller,
  pause/fortsett, lukket nettleser, varige utfall, prosessdød/omstart og manglende
  lydkopi. Testen lager aldri reell radiolyd eller betalt innhold.
- Hele Composer-suiten og eksisterende køtester kjøres i tillegg. CI har
  PHP 8.2/8.4 og egen faktisk lydtest. Siste commit/kjøringsresultat skal stå i
  STUDIO-STATUS/PR; eldre grønne kjøringer er ikke bevis for nyere kode.

Dette er første isolerte testavvikler. Automatiske veggklokkestarter,
produksjonsstrøm, miksing/fading, bruk av cue-punkter som overganger,
loudness-normalisering, robotvert og generert tale inngår ikke. Historikken
leses fra append-only journal ved kontroll; langvarig drift trenger måling og
eventuelt et validert checkpoint/arkiv før større biblioteker eller last.

Teknisk referanse: [FFmpeg-protokoller](https://ffmpeg.org/ffmpeg-protocols.html),
[FFmpeg PCM/formater](https://ffmpeg.org/ffmpeg-formats.html) og
[systemd-isolasjon](https://www.freedesktop.org/software/systemd/man/systemd.exec.html).
