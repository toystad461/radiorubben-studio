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
