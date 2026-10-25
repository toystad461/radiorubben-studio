# L01 – avgrenset installasjon på Studio VPS – test
Dato: 25.10.2026. Sak #109, PR #111. Dette er en klargjort installasjon, ikke en kvittering.

## Faktisk grunnlag
Thomas sin inventarkontroll 14:43:46 UTC / 15:43:46 Europe/Oslo viser:
- Nginx-test og auth_request-støtte består.
- /etc/nginx/rr-test-apps.conf, Studio på loopback8080, FPM /run/php/rr-studio.sock.
- Egen WordPress-rute/socket finnes i samme fil; den blokken skal være byte-identisk.
- Port18980 ledig, eksisterende rr-playout/rr-stream/rr-studio-brukere.
- Spiller stoppet, ingen aktiv forekomst, gammel release fad12a2, kø24 med tre innslag.
- Liquidsoap2.2.4-1 og Icecast2.4.4-4build4 er installert.
- Runtime og Studio-systemd-enheter mangler. Biblioteket er kjent PR102 og bevares.

## Gjennomgåbart filsett
Web: app/music-playout.php, app/music-liquidsoap.php, app/music-studio.php,
public/music-studio.php, public/music-stream-auth.php, public/assets/music-studio.mjs.
Spiller: app/music-playout.php, app/music-liquidsoap.php, scripts/music-liquidsoap.php,
scripts/liquidsoap-child.py, infra/studio-stream/radio.liq.
Begge music-playout-kopiene har samme kandidatbytes. Førhash eller eksplisitt fravær er fastlåst.
Ingen helbranch, Composer/vendor, bibliotekfil, WordPress, DNS, SSH eller sudoers endres.

Beskyttede endringer: to nye enheter rr-studio-stream og rr-studio-icecast,
utvidelse av kun Studio-blokken i /etc/nginx/rr-test-apps.conf,
og stream_path i eksisterende private music-playout.local.php.
Ny privat runtime på /srv/radiorubben-test/stream-runtime med tilfeldige
kildepassord generert på VPS; ingen passord sendes til GitHub/chat.
Dette krever konkret installasjonsautorisasjon før --apply.

## Utførelse og feilgrenser
Bygg selvstendig fil med build-test-installer.py --source-commit <eksakt SHA> --output <fil>.
Standardkjøring er bare lesende plan. --apply er den godkjenningspliktige installasjonen.
Filen validerer hostname, root-administrator, aktiv release, stopped/status, konkrete
førhash-er, konti, pakker, ledig port, uendret Nginx-dumpdel og kø24.
Driftsavvik gir stopp, ikke automatisk tilpasning.

Privat backup ligger i /var/lib/rr-vps-deploy/l01-installs/l01-<unik verdi>,
root0700 og backupfiler0600; lagrer kode, metadata, berørt config og journalprefix.
Originale lydfiler kopieres ikke. Deres hash, bibliotek og kø/manifest kontrolleres
før/etter. Journalen skal kun få lovlige nye servicehendelser og beholde sitt prefix.
Gatewaylåsen hindrer parallelle gateway-deployer. Under den korte installasjonen
returnerer bare Musikkstudio-API503 slik at nye kontrollkommandoer ikke går inn.

Gammel rr-music-playout stoppes og deaktiveres først etter stopped-kontroll.
Root-eide kodefiler skrives atomisk; nye kodekataloger er root0755.
Privat konfig lastes/bearbeides som rr-studio, ikke som root-PHP.
Nye units verifiseres med systemd-analyze verify. PHP-FPM reloades og dedikert
rr-entra restartes for cache, som i eksisterende gateway; poolisolasjon endres ikke.
Nginx testes før reload. Nye tjenester aktiveres og startes, men ingen musikk-Start sendes.
Ferskt heartbeat fra riktig nye supervisor-PID, stopped og anonym stream401/403 kreves.
TLS-fronten endres ikke; X-Accel-Buffering:no motvirker buffering også i eksisterende frontproxy.

## Tilbakeføring
Ved installasjonsfeil forsøkes tilbakeføring automatisk. Den samme filen støtter
--rollback <eksakt privat backupmappe>. Den stopper/deaktiverer de nye tjenestene,
kontrollerer at ingen målfil har ukjent nyere hash, gjenoppretter kode/config og
gammel tjenestetilstand og tester/reloader Nginx. Ny runtime flyttes til privat backup
uten å slette logger/passord. Kø, bibliotek, originaler og journal tilbakeføres aldri.
Ukjent nyere kode eller ødelagt backup gir recovery_required; ingen overskriving.

Lokalt er parser-/backup-/rollbackfunksjoner testet i midlertidige kataloger,
med systemtjenestekommandoer simulert. Dette er ikke en faktisk VPS-rollbacktest.
Prosessdød/strømbrudd krever kontroll av privat manifest; det er ingen global transaksjon.

## Etter installasjon – gjenstående faktisk test
Kvitteringen installed_stopped_not_audio_pass betyr bare korrekt installasjon,
ikke hørbar strøm. Innlogget monitor/TLS og roller må testes i faktisk nettleser.
De tre gamle køforekomstene/historikken bevares; fullførte forekomster skal ikke
spilles om igjen. Bruk Ny testkø når stopped, legg tre allerede godkjente låter
inn som nye forekomster, synkroniser og Start. Kontroller rekkefølge og hørbar,
sammenhengende lyd, lukk/gjenåpne Studio og avstem journal/filhash.
Filfeil, omstart og tilbakeføring må kontrolleres før L01 lukkes.
Produksjon er utenfor. Ingen ferdig milepæl kan rapporteres fra installasjon alene.
