# Offentlig lytterstrøm – 01.11.2026 Europe/Oslo

Etter Thomas sin uttrykkelige bestilling om lytting fra hjemmesidens play-knapp er
https://5.249.252.78/radio.mp3 offentlig. Den videresender kun GET/HEAD til den
allerede aktive MP3-strømmen på loopback. Studio og originalfilene beholder innlogging.
Ingen DNS-, radio.co-, tema- eller applikasjonskode på WordPress er endret.

## Installert

Kilde: infra/studio-stream/enable-public-listener.py. Avgrenset endring av
/etc/nginx/conf.d/rr-test-tls.conf og stream-runtime/icecast.xml. Privat backup:
/var/lib/rr-vps-deploy/public-listener-oue7v39i (receipt.json med før/etter SHA256).
Nginx konfigurasjonskontroll besto. Icecast SIGHUP og Nginx reload;
Icecast PID 400409 forble uendret. Grensen er 100 samtidige klienter totalt,
ikke en dokumentert belastningsgaranti. Ingen lydmotoromstart.

WordPress Customizer, aktivt tema radio-rubben-next:
- rr_stream_url: manglende/tom → https://5.249.252.78/radio.mp3
- rr_now_title: «Studio: Thomas M.» → «Musikk og jingler fra Radio Rubben»
- rr_live_status forble av: spilleren viser AUTOMATIKK.
Endringen ble publisert gjennom normal innlogget tilpasser etter Thomas logget inn.
WPVibe avviser denne egendefinerte theme-mod-skrivingen; verktøyets sperre ble ikke omgått.

## Verifisert

- Anonym HTTPS fra Windows: HTTP200 audio/mpeg, 192kbps stereo/44100Hz.
  352800 byte / 14,691500 sekunder dekodet av FFmpeg uten feil. Curl ble
  bevisst tidsavgrenset til15sekunder (exit28 er forventet ved løpende stream).
- Syv samtidige HTTPS-lyttere fikk200 og4096lydbyte hver.
- Hjemmesiden uten cookies inneholder riktig RR_ONE.streamUrl og aktiv play-knapp.
- Faktisk klikk på hjemmesiden: readyState4, paused=false, currentTime58,52,
  ingen mediefeil. Ingen ny subjektiv lyttebekreftelse er påstått.
- Privat /music-test-stream/401, Studio303 til login, POST offentlig lyd403.
- rr-studio-stream, rr-studio-icecast og rr-studio-rotation.timer aktive.
- Alle tre CI-workflows på forrige head4b33d988 er grønne; dette er konfigurasjon
  og dokumentasjon, ikke ny installasjon av L02-motorkode.

## Begrensninger og videre arbeid

Eksisterende statisk «Om litt»-tekst i temaet er ikke koblet til den virkelige køen.
Offentlig nå/neste-metadata er et eget oppfølgingsarbeid. Strømnavnet i ICY-header
har fortsatt PRIVATE TEST; directory-annonsering er fortsatt avslått.
Sertifikatet er et gyldig Let's Encrypt IP-sertifikat fram til05.11.2026
22:32:17UTC; eksisterende certbot renew-timer er aktiv. Automatisk gjenstart av
selve musikkavspillingen etter serveromstart er ikke implementert. Offentlig
lyttering er derfor en første åpning, ikke dokumentert døgnkontinuerlig SLA.

## Tilbakeføring

Sett Strøm-URL tom og undertittel tilbake i WordPress Customizer og publiser.
Gjenopprett deretter de to konfigurasjonsfilene fra privat backup med eier/modus
bevart; nginx -t før reload, Icecast SIGHUP for gammel klientgrense. Ingen
bibliotek-, kø- eller journalfiler skal tilbakeføres for denne endringen.
Konfigurasjonsskriptet tilbakefører automatisk hvis selve installasjonen feiler.
