# Radio Rubben: Liquidsoap og Icecast

Arbeidsområde: Teknisk Produksjon / IT & Utstyr.
Beslutning: Thomas har valgt Liquidsoap som sendemotor og Icecast som
strømmetjener i samtalen om Studio og avspilling. Eksisterende PHP-Studio
beholdes som kontrollpanel. Denne beslutningen autoriserer en isolert test,
ikke aktivering av en offentlig radiokanal eller utrulling til Uniweb.

## Ansvar og første leveranse

Studio eier bibliotek, roller, cue-punkter, sendekø og plan. Liquidsoap dekoder,
mikser og leverer lyd. Icecast tilbyr en stabil lytteadresse og kan være kilde
for senere reléer. Sending skal fortsette når operatørens nettleser lukkes.
Ingen av delene skal forutsette radio.co eller omskriving til Node.js.

Første pilot er en separat, lokal lydvei med eksplisitt valgte musikkfiler,
3 sekunders crossfade og MP3 192 kbit/s til Icecast på loopback. Originalene
endres ikke. Piloten er ikke Studio-integrasjon eller endelig sendelogikk.
Automatisk gjentakelse av pilotlisten er tilsiktet og skal ikke forveksles med
en engangskø eller faktisk-sendeloggen i Studio.

Videre leveranser, i rekkefølge:
1. Verifisere faktisk dekodbar lyd gjennom Liquidsoap og Icecast.
2. Installere isolerte tjenester på test-VPS, med avgrensede kontoer,
   privat konfigurasjon, oppstart/stopp og dokumentert tilbakeføring.
3. Koble eksisterende Studio-kø til motoren: vedvarende element-ID,
   kildehash, revisjon, cue-punkter og kvitteringer for faktisk avspilling.
4. Legge til operatørstyring, privat forhåndslytting og separat måling av
   motorstatus, forbindelsen til Icecast og mottatt lyd fra lytteadressen.
5. Tilføre LIVE-inngang, reservemusikk, tidsstyrte innslag og reléer.

## Avtalt funksjonsretning

- Musikk, stikk, reklame, jingle og programopptak har egne overgangsregler.
  Generell crossfade skal aldri kutte reklame eller tale.
- Start/stopp av automatikk, overgang til LIVE og frakobling av utsending
  skal være separate kommandoer. Ingen automatisk oppstart ved sidevisning.
- En bevisst stopp skal respekteres. Reserveinnhold ved feil/tom kø må ha
  eksplisitt definert regel, ikke overstyre operatørens stopp.
- På sikt: jevnt lydnivå, peak-beskyttelse, overvåking av stillhet, faktisk
  sendelogg og gjenoppretting uten utilsiktet dobbel avspilling.
- TTS skal kunne produseres og legges i kø automatisk etter definerte regler.
  Thomas har presisert at menneskelig forhåndsgodkjenning av hvert TTS-innslag
  ikke er et permanent krav. Eksisterende sperrer endres ikke i musikkpiloten;
  policy og implementasjon må avstemmes i den separate TTS-leveransen.

## Eksisterende arbeid og utrullingsgrense

PR99 dokumenterer en tidligere PHP/FFmpeg-testmotor og privat lydmonitor.
Dette er ikke en ferdig offentlig stream. Ukommittert crossfade-arbeid i
`studio-crossfade` bevares som utkast, men skal ikke utrulles parallelt med
Liquidsoap eller bli en konkurrerende sendemotor.

Ny pilot bygger separat på main 96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60.
Ingen av de eksisterende appfilene, køene eller musikkoriginalene endres.
Den begrensede GitHub-VPS-porten tillater bare app/public-kode; den gir ikke
rett til å installere operativsystempakker eller nye tjenester. Direkte
SSH fra agentmiljøet ble forsøkt på nytt og svarte Network is unreachable.
VPS-installasjon trenger derfor en tilgjengelig administratorterminal.
Denne grensen skal ikke omgås gjennom PHP eller endring av deployporten.

## Kilder

- https://www.liquidsoap.info/doc-2.2.5/cookbook.html
- https://www.liquidsoap.info/doc-dev/install
- https://www.icecast.org/docs/icecast-latest/basic_setup/
- https://www.icecast.org/docs/icecast-latest/config_file/

Bruk dokumentasjon og syntakskontroll for den faktisk installerte versjonen.
Utviklingsdokumentasjon alene er ikke bevis for støtte i Ubuntu-pakken.

## Verifisering av pilot

Kodecommit `c97977a73a8c0991774e77786c3b6099d96b6efe` består faktisk lydtest
i [CI 39687727865](https://github.com/toystad461/radiorubben-studio/actions/runs/39687727865).
MP3 mottatt over HTTP er dekodet og målt: begge syntetiske spor, hørbar
overlapping, ingen klipping i denne testlyden, ny lyttertilkobling og uendrede
originaler. Dette er test av pilotkoden, ikke VPS eller Studio-integrasjonen.
