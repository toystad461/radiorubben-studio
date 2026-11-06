# Offentlig status og automatisk oppstart – 01.11.2026 Europe/Oslo

Thomas bestilte videreføring med riktig nå/neste, automatisk oppstart etter
serveromstart og lengre lytteprøve. Dette utvider forrige private/test-beskrivelse
bare for offentlig lyd/status og hjemmesidens spillerintegrasjon.

## Installert kode

Fem nye mål fra b6db5088f831847b65fdf3d5069579d13656ee4d:
app/music-public-status.php, public/radio-status.php, app/music-boot.php,
scripts/music-boot.php og /etc/systemd/system/rr-studio-boot.service.
Oppstartsskriptet er deretter oppdatert fra ff7925f24a4478a5e719ed865641f51e9cf8b98c
for å vente på en levende prosess og fersk heartbeat før gammel status tolkes.
Privat kvittering: /var/lib/rr-vps-deploy/public-boot-r8jsvn3s/receipt.json;
tilleggsrettelse: startup-race-receipt.json, backup music-boot.initial.php.
Siste oppstartsskript er 906057b0ae747fcc465713bb1b2af8ad94594a10, med samordnet
journal-/kontrolllesing under spillerlåsen. Kvittering startup-lock-receipt.json;
SHA256 640c6797dfe8e7a2f104f2936ce737c8ba1d30b6dad23cff5e7e0bb3d1162f55.
Eksisterende L02-motor, bibliotek, kø og journal er ikke overskrevet.

Statusendepunktet bruker en eksplisitt offentlig feltliste: schemaVersion,
generatedAt, playing, now, next; innslag inneholder bare artist/title/kind.
Kun fersk heartbeat, bekreftet forbindelse og aktivt innslag gir playing=true.
Forhåndslastet neste innslag beholdes som neste; aktive crossfade-bidrag utelates.
Feil gir503 uten interne detaljer, og andre metoder enn GET/HEAD gir405.

Oppstartstjenesten er enabled ved multi-user.target og starter etter Icecast og
lydmotor. Den bruker vanlig Studio-start med låser, revisjon, filkontroll og
planlegging. Bare tidligere Start og aktiv standardrotasjon kan gjenopptas.
Stopp/Pause, ventende operatørkommando, aktiv eller blokkert spiller og manglende
fersk heartbeat hindrer ny start. Den er ikke en generell feil-watchdog som
prøver igjen ved motorfeil under sending. Et avbrutt innslag spilles ikke på nytt.

## Hjemmeside

Web-repo PR111 installerer kun to nye MU-plugin-filer fra ea62278. Aktiv temakode
og database bevares. Oppdatering hvert tiende sekund uten cookies; gammel
statisk neste-låtpåstand skjules. Feil/foreldede data rydder visningen. Se Web
docs/PUBLIC-RADIO-METADATA-20261101.md for hasher, privat backup og rollback.
Nettleser bekreftet Jingle2 → AlanJackson → AaronPritchett med faktisk lyd;
anonym HTML og minifisert JS-bundle inneholder integrasjonen.

## Tester

tests/music-public-boot.php består: offentlig feltliste, stale/offline-status,
prefetch/crossfade-neste og oppstartssperrer. Node-test i Web består for tekstlig
DOM, ingen cookies, nå/neste og rydding ved feil. Isolert ekte HTTP/Liquidsoap/
Icecast-prøve tests/test-studio-boot-stream.py består: lyd etter prosessomstart,
ingen gjenspilling av avbrutt innslag, Stopp bevart over restart og offentlig
status uten private felter. Ingen produksjonsfiler brukes som testlyd.

## Faktisk sluttkontroll

23:12:22–omtrent 23:27:23 Oslo: 900 sekunder offentlig HTTPS-MP3 dekodet av
FFmpeg, exit 0 og ingen dekoderfeil. Journalen viste Blue Smoke, All His Saints,
Jingle 2, Ain't Got Trouble Now, When A Momma's Boy Meets A Daddy's Girl,
Fast Car og Jingle 3 fullført i perioden, uten motorfeil.

Faktisk VPS-omstart startet 23:27:32 Oslo. Oppstartstjenesten startet 23:27:46,
ba om validert Start 23:27:49 og offentlig playing=true ble observert 23:27:58:
omtrent 27 sekunder fra omstartskommando til bekreftet offentlig status.
Dean Brody – Beautiful Freakshow ble startet, med Tim McGraw som neste.
Ny boot-ID er bekreftet. Hele tidligere journalprefix og bibliotekets SHA256
er bevart, og ingen tidligere fullførte forekomster er startet på nytt.
Alle tre tjenester er aktive; boot-service Result=success/ExecMainStatus=0.
Ytterligere 20 sekunder offentlig MP3 etter omstart dekodet uten feil.
Kvittering: /var/lib/rr-vps-deploy/public-boot-r8jsvn3s/reboot-proof.json.
Kø174 var planlagt til 02.11.2026 kl.00:31:20 Oslo, uten kandidatunderskudd.

Alle tre Studio-CI-workflows på installert 906057b er grønne. Web-CI er grønn
på siste installerte caa33ac (MU-plugin 1.0.1), som også sikrer at Play etter en
avsluttet HTTP-strøm henter fersk lyd i stedet for å gjenbruke gammel buffer.
Åpne lytterfaner som mistet forbindelsen under omstart må trykke Play igjen;
automatisk nettlesergjenoppkobling er ikke innført. Serveren trenger ikke en
åpen nettleser. Ingen ny subjektiv lyttebekreftelse er påstått.

## Tilbakeføring

Deaktiver rr-studio-boot.service. Flytt bare de fem nye målfilene til privat
backup, og kjør systemctl daemon-reload. Ingen musikk/kø/journal tilbakeføres.
Den gamle oppstartsoppførselen krever da manuell Start etter omstart.
Web-tillegget tilbakeføres separat ved å flytte de to nye MU-plugin-filene ut av
wp-content/mu-plugins. Den offentlige radio.mp3-strømmen fra forrige steg bevares.

## Kjente grenser

Metadata beskriver serverens lydstatus; lytterens MP3-buffer kan ligge noen
sekunder etter. Automatisk gjenopptakelse betyr ny start på neste gyldige
forekomst etter serveromstart, ikke gjenopptakelse midt inne i en lydfil.
