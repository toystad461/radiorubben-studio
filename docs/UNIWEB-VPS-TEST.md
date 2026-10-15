# Isolert VPS-test — 5363103

Dette er testinfrastruktur, ikke en produksjonsmigrering. Ingen DNS-endringer,
kjøp, produksjonsdata, Entra-endringer, meldinger eller radiohandlinger inngår.
Bootstrap nekter å kjøre på andre verter og nekter å overskrive etablert data.
Grenen skal ikke merges til main som del av oppsettet: main har produksjonsdeploy.

## Fast grunnlag

- Ubuntu 24.04 LTS amd64, 2 vCPU, 4 GB RAM og 100 GB volum hos Uniweb.
- Studio: `96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60`.
- Web/Next-tema: `c349fc24dd8e6df588755680f1e16c87bf281204`.
- Eksisterende PHP/Composer-arkitektur; Ubuntu PHP 8.3, Nginx og MariaDB.
- Node.js 24 LTS og npm klargjøres etter Thomas sitt tillegg 15.10.2026.
  Offisiell binær signatur- og SHA256-kontrolleres; konkret versjon loggføres.
  Separat `rr-node`-bruker og datamappe. Ingen Node-jobb/scheduler startes.
- WordPress lastes fra offisiell distribusjon med kontrollerte kjernesjekksummer.
  Faktisk installert versjon registreres i leveringskvitteringen.
- WP-CLI hentes fra prosjektets offisielle distribusjon og signaturverifiseres.

## Inndeling og sperrer

Kode: `/srv/radiorubben-test/releases` og `/srv/radiorubben-test/wordpress`.
Studio-data: `/srv/radiorubben-test/studio-data`; sessions har egen privat mappe.
WordPress-database: `rr_test_wp`, ny og lokal; socket-autentisering uten DB-passord.
Separate systembrukere og PHP-FPM-pooler. Ingen kopiering fra produksjonsserverne.
PHP-FPM har privat nettverksrom, bare Unix-sockets og deaktivert mail/prosesskjøring.
WordPress har i tillegg sperrer for HTTP, mail, XML-RPC, cron og applikasjonspassord.
Ingen redaksjonelle bakgrunnsjobber startes. Alle WordPress-integrasjoner er inaktive.
Nginx offentliggjør bare Studio `public/`; konfigurasjon og data ligger utenfor.

Før sluttføring kjører appene bare på `127.0.0.1:8080/8081`. Port 80 svarer
kun på ACME-verifisering, ellers 404. Etter eierens sluttføring:

- Studio: `https://5.249.252.78`, lokal testkonto `thomas@example.test`.
- WordPress: `https://5.249.252.78:8443`, administrator `thomas`.
- Innlogging kreves også for WordPress-forsiden og REST. Begge har noindex.
- Eieren velger passord direkte i terminalen. Rettingen bruker PHP direkte og
  stdin uten WP-CLI, kommandolinjeargument eller miljøvariabel; appene lagrer hash.
  Den lokale Studio-kontoen gis adminrolle av den vertssperrede CLI-klargjøringen,
  slik at CRM og redaksjon kan testes uten å etterligne en Entra-identitet.

## Klargjøring og sluttføring

På den nye serveren installeres oppdateringer og nødvendige Ubuntu-pakker.
Kjør så `bash infra/uniweb-test/prepare.sh` fra den gjennomgåtte grenen.
Kjør `/usr/local/sbin/rr-test-verify` før eierens sluttsteg.
Eieren kjører `/usr/local/sbin/rr-test-finish`, godtar Let's Encrypt-vilkårene
og skriver et nytt testpassord to ganger. Se hendelsen nedenfor om første utgave.
HTTPS-portene åpnes først etter at kontoene finnes og kontrollene består.
Certbot snap fornyer IP-sertifikatet automatisk; deploy-hook laster Nginx på nytt.
IP-sertifikatet er kortlivet og fornyelse må etterkontrolleres med dry-run.

## Avgrensninger og neste arbeid

Studio har hardkodede produksjonsadresser i WordPress-integrasjonen. Denne
integrasjonen forblir frakoblet; det er ikke en ferdig ende-til-ende-testflyt.
AI, ElevenLabs, Vipps, OneDrive, Radio.co og kundemeldinger er ikke tilkoblet.
Next-temaet og tilgjengelig plugin-kode er klargjort, men ingen ekte kamp-/stemme-
eller artikkeldata importeres. Fotballrobot-mappen finnes ikke i avlest web-main;
aktiv runtime må avstemmes før den eventuelt installeres i testmiljøet.
Eksisterende leverandørbackup er ikke dokumentert gjenopprettingstestet.

Neste steg etter innloggingskontroll: tilpass testdestinasjoner med målrettede
negative tester, avstem manglende Fotballrobot-kode, og test gjenoppretting før
miljøet brukes til komplette publiserings- eller kampreiser.

## Kvittering

2026-10-15: innlogging og root-tilgang bekreftet, Ubuntu 24.04.5 x86_64,
2 CPU, 3,8 GiB RAM og 96 GiB filsystem. Ingen feilede systemtjenester.
Ubuntu-oppdatering/pakker fullført og UFW aktivert med SSH bevart.
Node.js 24.22.0 LTS / npm 11.19.1 er installert og signatur-/SHA256-kontrollert.
Robot-main `8feae8aa35d604a817dd50a3ece091d6b3da8d4e` er klonet uten hemmeligheter:
`npm ci --ignore-scripts`, bygg og alle seks tester består i eget nettverksrom.
Studio sin fulle Composer-testpakke består på VPS-ens PHP 8.3.6, i separat
kildekopi og nettverksrom. Denne testen skrev ikke til testappens runtime-data.

WordPress 7.1.3 nb_NO er klargjort. Første WP-CLI-utpakking ga avkuttede lange
filnavn og ble avvist av kontrollsummesjekken. Uttrekket er bevart privat for
feilsøking. Offisiell ZIP pakket ut med native unzip består full kjernesjekk.
Ingen databasebruker eller offentlig tilgang ble aktivert fra det feilede uttrekket.
Nginx/PHP-FPM/MariaDB er aktive med loopback-appporter. Node-jobber er ikke aktivert.
Sluttkontrollen består: Studio-login 200, gjest sendes til innlogging, ekstern
auth og private filer avvises, faktisk PHP-socket mot internett mislykkes,
Studio kan ikke lese WordPress-konfigurasjon, og mail-funksjonen er deaktivert.
Studio merkes synlig som TESTMILJØ av testserverens Nginx-konfigurasjon.
Let's Encrypt-vilkårene ble godkjent etter konkret Y fra eieren. IP-sertifikatet
ble utstedt med utløp 22.10.2026 og automatisk fornyelse planlagt av Certbot.
Eieren skrev testpassord direkte i konsollen; WordPress-kjernen, Studio-kontoen
og Next-temaet ble aktivert. Sluttkontrollen besto før testportene ble åpnet.

Etterkontroll 15.10 kl. 15 norsk tid avdekket en sikkerhetsfeil i klargjøringen:
WP-CLI `--prompt=admin_password` skrev den rekonstruerte kommandoen med passord
i konsollutskriften. Verdien skal ikke kopieres til kode, dokumentasjon eller
artefakter. Testportene 443/8443 ble stengt umiddelbart; SSH og ACME er bevart.
Den første forsikringen om skjult verktøyutskrift var dermed feil.

Rettet løp: WordPress-passord går direkte til PHP-kjernens API via stdin,
uten WP-CLI. Kontrollerte PHP-hjelpere for begge appene skriver bare fast
status, avviser gjenbruk, og ugyldiggjør gamle sesjoner ved bytte. Eksisterende
testdata og roller bevares. ABSPATH-advarselen i WP-CLI er også rettet.
`rr-test-rotate-password` krever at eieren selv skriver og bekrefter et helt
nytt passord. Først etter vellykket bytte og isolasjonskontroll åpnes portene.
Faktisk passordbytte, nettleserinnlogging og fornyelses-dry-run gjenstår.

Rettelse 461247b er installert på VPS-en. Bash/PHP-syntaks, kontokontroller
og isolasjonskontroll består. Direkte prøver mot begge appenes hjelpere med
syntetiske inndata bekrefter at gyldig/ugyldig lengde håndteres og at ingen av
prøveverdiene finnes i stdout/stderr. Disse prøvene endret ingen passord.
WordPress nullstiller den generelle globale variabelen `$action` under oppstart;
hjelperen bruker derfor et eget variabelnavn, kontrollert på faktisk runtime.
Ingen reell nettleserinnlogging eller vellykket passordrotasjon er bekreftet ennå.
