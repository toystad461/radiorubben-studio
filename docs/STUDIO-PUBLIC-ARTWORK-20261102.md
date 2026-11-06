# Cover i offentlig nettspiller

02.11.2026 Europe/Oslo. Arbeidsområde: Musikk & Tagging / Teknisk Produksjon.

Alle 96 musikkfiler er undersøkt. 95 mangler innebygd bilde. Kim Tanaia –
Back Again har en APIC-tag, men bildedataene mangler gyldig JPEG-start og
består ikke bildekontrollen. Resultat: 0 brukbare cover, logo for alle nåværende spor.
Ingen eksterne cover er hentet, og ingen originalfil eller lydmetadata er endret.

Bakgrunnsjobben klargjør bilder fra nye lydhash-er (maks ett nytt spor per kjøring).
Kun gyldig JPEG/PNG på inntil 2 MiB / 4096 piksler / 16 millioner piksler
dekodes og lagres som ny JPEG på maksimalt 512 piksler, uten originale metadata.
Privat cache: studio-media/music/public-artwork. Ingen bildeuttrekking skjer i
offentlige HTTP-kall. Feil i valgfri coverklargjøring stopper ikke rotasjonen.

radio-status.php legger til now.artworkUrl bare når aktuell musikk har gyldig
cachet bilde. Bildelenken bruker bildehash, ikke lydhash eller bibliotek-ID.
GET/HEAD med cover-parameter leverer bare bilder oppført i den private indeksen;
ugyldig/manglende bilde gir404, annen metode405. Musikkoriginalene forblir private.
Jingler bruker logo. Nettsiden godtar bare den eksakte offentlige bilderuten;
feil, manglende cover og utdatert sendestatus gir eksisterende Radio Rubben-logo.

## Installert og kontrollert

Studio runtime fra41fd93a: app/music-public-artwork.php,
public/radio-status.php og scripts/music-rotation.php. Kun disse tre filene.
1351fe2 oppdaterer kandidatmanifestets hash; runtime er identisk.
Web MU-tillegg1.0.3 fra d42fb72: kun rr-public-radio.js og rr-public-radio.php.
Ingen nginx-endring eller omstart av sendingen.

Målrettede Node-tester består for cover/feil/logo/ugyldig URL og tidligere
metadata-/gjenoppkoblingsfunksjon. PHP-tester på VPS består inkludert syntetisk
PNG→512×256 JPEG, ugyldig aktivt innhold, stivalidering og jingle-logo.
Alle ni preflight-tester består i isolert Linux-område etter manifestoppdatering.
Webs to CI-workflows er grønne på d42fb72. Første Studio-CI fant gammel
kandidatmanifest-hash; rettet i1351fe2. CI queue-contract er grønn på både
PHP8.2 og8.4 etter rettingen; bredere lyd-/pakkekjøringer pågår ved levering.
Anonym nettsidebundle inneholder covervisning. Faktisk nettleser laster logoen.
Offentlig status svarer200 og playing=true; ugyldig cover gir404.
Normal rotation.service kjørte med Result=success og ExecMainStatus=0.
POST til status avvises405, direkte forespørsel mot originalregisteret gir404.

Backuper/kvitteringer:
- VPS: /var/lib/rr-vps-deploy/public-artwork-j9_6jwv2
- Uniweb: /home/cptk37ymg_w1417156/.radiorubben-deploy/backups/radio-artwork-aor_yfvq

Rollback: gjenopprett de to eksisterende VPS-filene fra before-kopiene, fjern
kun den nye helperen når gammel kode er tilbake. Gjenopprett de to MU-filene
fra Uniweb-backup og regenerer minify-cache ved behov. Privat bilde-cache kan
beholdes; ingen kø, journal eller originalfiler skal gjenopprettes.
PR115 og Web PR111 er fortsatt draft, ikke merget.
