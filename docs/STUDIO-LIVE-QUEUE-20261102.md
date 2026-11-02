# Spill neste under avspilling – 02.11.2026

Arbeidsområder: Teknisk Produksjon, Kontrollsenter, System & Kvalitet.
Viderefører Studio #109/L02 og PR115.

Problemet var at request.dynamic reserverte neste låt straks aktiv låt startet.
Studio kunne derfor ikke sette inn et lagret innslag foran denne bufferen.

## Endringen

- Musikkstudio viser lagret musikk og jingler i samme innslagsvelger.
- «Spill neste» legger en kontrollert biblioteksversjon rett etter aktiv lyd,
  eller flytter et eksisterende, ulåst køinnslag dit uten å duplisere det.
- «Fjern» er tilgjengelig for ulåste framtidige innslag under standardrotasjon.
- Motoren kan utsette reservasjonen til 8 sekunder pluss utgående crossfade
  gjenstår i faktisk PCM-avspilling. Standard 3 sekunder gir omtrent 11 sekunder.
- Reservasjon/aktiv lyd kan fortsatt ikke endres. Under selve overgangen får
  operatøren tydelig beskjed; systemet flytter aldri bestillingen lydløst lenger ned.
- Jingler spilles sekvensielt uten musikkoverlapp. Tidene beregnes på nytt.
- Roller, CSRF, køversjon, busslås, programregler, kildehash og journal beholdes.
  Nye regelbrudd, manglende teknisk kontroll og utløpte daterte timestikk avvises.
- Automatiske timesignaler administreres fortsatt fra Jingles.

## Innføring og tilbakeføring

Forsinket reservasjon er eksplisitt aktivert med studio.editableNext=true i
privat køkonfigurasjon. Standard ved manglende flagg er gammel oppførsel.
Manifestet viderefører flagget; nye claim-prosesser leser endringen uten restart.
Eksisterende reservasjon får fullføre før den nye oppførselen kan observeres.
Eldre supervisors heartbeat støttes ved å avvise posisjoner som er eldre enn
aktivt innslags målte PCM-start. Ny supervisor sender også currentId.

Selektiv deploy må inkludere music-live-queue.php før music-studio.php samt
claim-adapteren i den faktisk brukte player-code-kopien. Ingen prosessrestart,
ingen omskriving av journal/bibliotek eller tilbakeføring av gammel sendekø.
Rollback: deaktiver flagget under buss-/kølås, republiser gjeldende kø, og
bruk hashkontrollert kodebackup. Aktiv lyd og journal skal beholdes.

## Verifisering

Isolert Liquidsoap/Icecast-test har spilt 26 sekunder uavbrutt første musikk,
slettet det opprinnelig neste innslaget, satt inn en lagret jingle, og målt
0,0 sekunder mellom første musikk og jingle. Slettet forekomst ble aldri spilt.
Testen avviser uautorisert rolle, feil CSRF, gammel revisjon, feil kildehash,
sletting av aktiv lyd og ny «Spill neste» etter reservasjon.

Enhetstester dekker posisjon, feil/foreldet heartbeat og kompatibel supervisor.
Nettlesertesten dekker knappenes låsing/opplåsing fra spilleroppdateringer på
390 og 1280 piksler. CI og installert status dokumenteres separat etter kjøring.

## Installert og kontrollert 02.11.2026

Ni selektive mål ble installert kl.12:33:42 Europe/Oslo fra
4a12d0fb0a4f16615f4d01cc7b4cd5b886879f74. Flagget ble aktivert kl.12:33:43,
kørevisjon 688. Kvittering: STUDIO-LIVE-QUEUE-DEPLOY-20261102.json.
Spiller-PID770 er uendret. Journalens eksisterende 7 817 515 byte og 110
beskyttede filer var uendret ved installasjonskontrollen.
Privat kodebackup: /var/lib/rr-vps-deploy/live-next-1ybalj7s.
Privat aktiveringsbackup: studio-media/music/live-next-activation-20261102113342.json.
Tilbakeføring: kjør /tmp/rr-live-next-20261102/activate-live.php disable som
rr-studio, deretter backupens rollback.py som administrator. Ingen gammel
sendekø skal gjenopprettes. Tilbakeføring er klargjort, ikke utført på aktiv sending.

Alle CI-workflows på installert kildecommit er success:
- [Kø, faktisk lyd og mobil/desktop](https://github.com/toystad461/radiorubben-studio/actions/runs/41436822802)
- [PHP og nettlesere](https://github.com/toystad461/radiorubben-studio/actions/runs/41436822779)
- [Release-verifikasjon](https://github.com/toystad461/radiorubben-studio/actions/runs/41436822882)

VPS kl.12:35:20: My Girl spiller, posisjon15,48 av208,219 sekunder,
streamConnected=true og reserved er tom. Knappen på lagret Jingle2 ble aktiv
uten reload. Faktisk innlogget desktop og390px mobil er kontrollert, uten
horisontal overflyt. En Spill neste-handling på allerede første framtidige
Andy Grammer-innslag ga «Lagret.»; aktiv sang og neste innslag forble uendret.
Kl.12:35:51 var samme aktive sang47,48 sekunder inn og streamen tilkoblet.
Ingen ny testjingle ble sendt på den aktive streamen som del av kontrollen;
ny jingleinnsetting og full avvikling er verifisert i isolert ekte lydtest.

Tidligere bestilt timestikk er separat bekreftet fullført kl.12:10:58–12:11:12
Europe/Oslo,14,32 sekunder, evidence=liquidsoap-pcm. Automatisk produksjon av
nytt TTS-stikk og tre musikkglimt hver time gjenstår.
