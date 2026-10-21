# Privat musikkpilot

Dette er en separat teknisk pilot, ikke en installert Studio-avspiller.
Kjører Liquidsoap og Icecast som vanlige prosesser med privat konfigurasjon.
Icecast lytter bare på 127.0.0.1. Ingen DNS, brannmur, webkode eller eksisterende
systemd-tjeneste endres. Ingen produksjonslyd eller ekte musikk inngår i testen.

## Automatisert lydtest (Ubuntu 24.04 / CI)

Avhengigheter: Python 3, FFmpeg, Liquidsoap og Icecast 2 fra Ubuntu.
Kjør som en vanlig bruker, ikke root:

```sh
python3 tests/test-stream-pilot.py
```

Testen lager to syntetiske musikkfiler, private passord og et nytt testområde.
Den starter begge prosessene, mottar MP3 over HTTP, dekoder lyden og måler
begge toner og overlapp. Den sjekker også reconnect og uendrede originaler.
Begge prosesser stoppes og midlertidige filer fjernes i finally.

`RR_STREAM_CHECK_ONLY=1` gjør bare konfigurasjons-/syntakskontroll og skal
ikke rapporteres som bestått lydtest. LIQUIDSOAP_BIN og ICECAST_BIN kan peke
på allerede installerte binærfiler eller lokale testwrappere.

## Manuell prøve med utvalgte opplastede musikkfiler (VPS)

Forutsetter at pakkene allerede er installert av administrator, at du er en
vanlig testbruker og har lesetilgang til to eksplisitt valgte musikkfiler.
Bruk faktiske filbaner i stedet for eksemplene; ikke kjør med root.

```sh
python3 infra/stream-pilot/prepare.py --destination /tmp/rubben-music-pilot /faktisk/lat1.mp3 /faktisk/lat2.mp3
liquidsoap --check /tmp/rubben-music-pilot/radio.liq
```

I ett VPS-terminalvindu:

```sh
icecast2 -c /tmp/rubben-music-pilot/icecast.xml
```

I et annet VPS-terminalvindu:

```sh
liquidsoap /tmp/rubben-music-pilot/radio.liq
```

Lokalt på Windows (PowerShell) eller Mac (Terminal) kan eksisterende godkjent
SSH-adgang brukes for å lytte uten å åpne en offentlig port:

```sh
ssh -N -L 18980:127.0.0.1:18980 ubuntu@5.249.252.78
```

Lokal lytteadresse er da http://127.0.0.1:18980/rubben-test.mp3.
Dette er en planlagt tilgangsmåte, ikke en verifisert VPS-streamadresse.

Stopp med Ctrl+C i begge VPS-terminalene. Ingen av prosessene installeres
eller starter igjen automatisk. Behold testmappen privat; den inneholder
midlertidige passord og kopier av valgte filer. Ikke legg den i Git eller
webroten. Ny kjøring krever ny mappe og overskriver aldri eksisterende pilot.

## Avgrensninger

Bare musikk i denne piloten: fast crossfade på 3 sekunder og gjentakelse av
listen. Ingen Studio-kø, cue-eksport, LIVE, TTS, faktisk sendelogg, reservemusikk,
loudness-normalisering, limiter eller tjenesteovervåking er implementert her.
Ved manglende spillbar musikk er utgangen fallible; det er ingen skjult
reservelyd. Vanlig nettleserlukking stopper ikke motoren, men lukking av
terminalen kan gjøre det i denne manuelle piloten. Varig tjenestedrift er
neste installasjonssteg etter verifisert pilot og tilgjengelig VPS-admin.
