# Automatisk Studio-publisering – gjeldende fra 05.10.2026

Thomas har uttrykkelig bedt om å aktivere automatisk publisering. Dette
avsnittet erstatter de historiske manuelle godkjenningskravene nedenfor.
Push/merge til main publiserer endret kode når STUDIO_DEPLOY_AUTO_APPLY=true.
PHP 8.2/8.4, kildekontrolltester, mobiltest og pakkevalidering må bestå først.
Manuell Run workflow tilbyr dry-run eller publish. Hovedgrenen kontrolleres
på nytt før publisering, slik at en foreldet kjøring hoppes over.

Den gamle fullpakke-apply er fortsatt sperret. Den nye selektive mekanismen
bruker scripts/studio-deploy-baseline.json: kodehasher avstemt mot produksjon
og pakke fra e2e3bad750c7355feaf5b2fc7a6f48b26e949207. Ved første kjøring
bevares eksisterende forskjeller der GitHub-koden ikke er endret. Deretter
publiseres bare endringer fra sist installerte kildeversjon. Alle registrerte
produksjonshasher kontrolleres før skriving; direkte serverendringer stopper
utrullingen. Nye kodefiler krever at målfilen ikke allerede finnes.

Omfang: app-PHP, public-kode/bilder, vendor, composer.json/lock og .htaccess.
Konfigurasjon, manus, brukere, cache, historikk, WordPress og DNS inngår ikke.
Ingen full kopiering eller rsync --delete brukes. Hver fil byttes atomisk;
hele filsettet er ikke én atomisk operasjon. Slettede kodefiler håndteres
selektivt og sikkerhetskopieres. Kodefilenes eksisterende rettigheter beholdes.

Privat serverstatus: ~/.radiorubben-studio-deploy/selective-state.json med
commit, kjøring og før-/ettergrunnlag. Privat backup og eksakt filmanifest:
~/.radiorubben-studio-deploy/backups/selective-<run>-<attempt>/.
Lås deles med den eksisterende publiseringskontrollen. Ved kontrollfeil
rulles skrevne filer tilbake; status flyttes først etter SHA-256- og HTTPS-
kontroll. Ved samtidig ekstern endring stoppes rollback for å bevare den.
Ved prosessdrap må manifestet brukes til manuell tilbakeføring av bare de
berørte kodefilene, med kontroll av etter-hash før gjenoppretting. Ingen
runtime-data skal gjenopprettes fra en eldre fullbackup.

GitHub Actions sin oppsummering viser eksakt publisert commit, antall filer
og backupsti. Jobben lager ingen betalte AI-jobber eller redaksjonell
publisering. Stans automatikk med STUDIO_DEPLOY_AUTO_APPLY=false i miljøet
studio-production. Manuell publish er fortsatt en eksplisitt handling.
Vanlige bygg lagres som Actions-artefakter; samme versjonstag omskrives ikke.

## Historisk dokumentasjon (erstattet der den strider med avsnittet over)

# Studio: GitHub → kontroller → v0.0.1 → studio.radiorubben.no

## Gjeldende gate for PR #25 – 05.10.2026

**Fullpakke-apply er sperret i både workflow og lokale/fjerne deployskript.**
Det finnes ingen variabel som åpner sperren. `STUDIO_DEPLOY_AUTO_APPLY` brukes
ikke lenger. Workflowen tilbyr bare dry-run, har én felles release-lås, tester
alle PR-baser og publiserer ikke GitHub-release etter en preflight.
Dette avsnittet erstatter den tidligere instruksen om å kjøre main → apply.

PR #25 gjaldt mobil nyhetsdesk. Under kartleggingen ble den retargetet til main
og merget eksternt 05.10.2026 10:14:19 UTC / 12:14:19 Oslo.
Slutt-head: `4d8ac6e4e477b0477592244c94598e74f6510815`; merge:
`e434969bc5f62ff1b1eb4af4a3bc3d47c0d1e886`.
Denne guardrail-oppfølgingen er basert på fersk main
`19bbb18d7e3250406e2de5dada7e5194c40818e9` og endrer ikke den mergede PR-en.
Release-klargjøringen endrer ingen mobilkode, programregister, nyhetsmanus,
redaksjonelle data eller Render-filer. Render PR #21 har separat hostingstatus
og SHA-gate; verken staging eller den grenen er aktivert/endret her.

### Kontrollerte GitHub-bevis

Første kartlegging (historisk, før ekstern merge): head: `2330214ff7335d2f497965e5cbc8f979610c488d`, base:
`871f4d91cc3894709a7084c6ce8a7f9a2c1938e4`. Da var PR-en åpen draft, mergeable=true,
to commits og 13 filer. Ingen reviews/review threads.
[CI 37243345398](https://github.com/toystad461/radiorubben-studio/actions/runs/37243345398)
besto PHP 8.2, PHP 8.4 og isolert mobiltest på dette eksakte head-et.
Ingen legacy commit-status ble returnert; jobber ble kontrollert separat.
Ny klargjøringscommit må få nye grønne kontroller, inkludert versjonert releasebygg.
Grønn ZIP er ikke et deploybevis.

Tidligere aktivering er beskrevet i
[web PR #52 sin releasejournal](https://github.com/toystad461/radiorubben-web/blob/c5dd3152d24f350ebf899dd186fe41e699621882/MOBILE-NEWSROOM-RELEASE.md)
og [manifest](https://github.com/toystad461/radiorubben-web/blob/c5dd3152d24f350ebf899dd186fe41e699621882/scripts/mobile-newsroom-release.json).
Disse peker på Studio-runtime `e977efaf9a0e2e51abc9ede9513cd7a37f04b988`,
WordPress-runtime `29e49e75b7890dd885050b4ad83d97ad266288fa`, plugin 0.10.4
og release `dd6b775ee2f80365c3b55b123ae8f418c9a9eea1`.
Dette er historiske bevis lest på GitHub, ikke fersk verifikasjon av Uniweb.
Fersk main-status beskriver også senere RSS-aktivering via web PR #54;
bevar disse endringene. Mobilmanifestets gamle før-hasher er derfor enda
mindre egnet som grunnlag for en ny utrulling.
Ikke kjør det gamle installasjonsmanifestet igjen: det forventer eldre før-hasher.

### Preflight før en ny selektiv release

1. Lås ferskt Studio-head, base og web-avhengighet i en gjennomgåbar plan.
   Kontroller CI på eksakt kildecommit, konfliktstatus og nye commits.
   #23/#24/#25/#26 er senere merget ifølge fersk hovedgrens statusfil;
   verifiser integrerte avhengigheter på dagens main. Programregister og
   Render skal ikke innlemmes som skjulte avhengigheter.
2. Les ferske råbyte-SHA-256 på alle seks potensielle runtime-mål:
   `studio-private/app/newsroom-view.php`, `studio-private/app/views/newsroom.php`,
   `studio-public/newsdesk.php`, `studio-public/newsdesk-image.php`,
   `studio-public/assets/newsroom.css`, `studio-public/assets/newsroom.js`.
   Public PHP må hashes etter eksisterende private-path-omskriving i packager.
   Hvis etter-hash allerede matcher, er filen en no-op og skal ikke overskrives.
   Manglende fil må registreres eksplisitt. Drift eller ukjent grunnlag stopper planen.
3. Lag et nytt selektivt manifest med eksakte kildecommits, pakkehash,
   tillatte mål, før-/etter-hasher og behandlingen av nye filer.
   Kontroller runtime-avhengighetene fra #24 og autentisert WordPress-kø
   med plugin 0.10.4. WordPress-endringer krever egen avgrensning og godkjenning.
4. Verifiser dokumentrot, HTTPS, webserver-PHP >=8.2, utvidelser, Entra/roller,
   known_hosts og separat deploytilgang. Branch protection kunne ikke leses
   med connectoren; rulesets-endepunktet returnerte 403. Produksjonsmiljøets
   reviewers og secrets-tilstedeværelse er ikke verifisert her.
   Thomas må kontrollere faktiske GitHub-innstillinger uten å vise secretverdier.
5. Privat backup av bare berørte kodefiler med hash og filmodus, utenfor webrot.
   Eksisterende config, nøkler, brukere, programprofiler, sendeliste, cache og
   manus-/godkjenningshistorikk skal ikke inngå i en commit eller CI-artefakt.
   Runtime-data må bevares under både deploy og rollback. Unngå bred
   `rsync --delete` som kan reversere nye redaksjonelle data.
6. Review/test av den selektive installasjonsmekanismen: lås, stopp ved drift,
   ny kontroll umiddelbart før hver overskriving, hash etter installasjon og
   tilbakeføring ved delvis feil. Nåværende Studio-workflow er kun fullpakke-
   preflight; den er ikke en implementert selektiv deploymekanisme.
   Innføring/gjenåpning av apply må være en separat gjennomgåbar kodeendring.

### Thomas sine manuelle gates

- **Integrering:** gjennomgå denne separate guardrail-PR-en og innlogget
  mobilkontroll. PR #25 er allerede merget; denne oppfølgingen skal ikke
  merges automatisk. Bekreft at produksjonsavvik fortsatt er bevart.
- **Produksjon:** gi eksplisitt godkjenning knyttet til eksakt Studio-SHA,
  selektivt manifest/pakkehash, filsett, avhengigheter og privat backup.
  Merge, grønn CI, en tidligere «Aktiver» eller en dry-run er ikke godkjenning
  av en ny Uniweb-deploy. En godkjenning åpner ikke den gamle fullpakkeveien.
- **Etterkontroll:** bekreft innlogget desktop/mobil, fysisk iPhone ved behov,
  bilde, kildevisning, kø og lesebekreftelse per sak. Ingen ekte publisering,
  forkasting, AI-bearbeiding eller e-post skal brukes som automatisk smoke-test.

### Verifikasjon og rollback for den selektive planen

Kontroller alle etter-hasher, HTTPS, innlogging, private-path-sperrer og
lesende autentisert kø. Bevar publiserte saker og ventende rettelser.
Hvis runtime-filene allerede matcher dagens plan, dokumenter dette uten deploy.
Release.json alene bekrefter ikke filsettet eller redaksjonell funksjon.

Ved delvis feil: stopp videre skriving under samme deploylås, gjenopprett
bare manifestets kodefiler fra den private backupen, og fjern nye filer bare
hvis deres nåværende hash matcher det installerte manifestet. Ved ny drift:
stopp og krev manuell vurdering; ikke overskriv en annen endring.
Verifiser gamle hasher, filmodus, HTTPS og lesende innlogging/kø etterpå.
Ikke gjenopprett gamle runtime-data over nye manus eller godkjenninger.
Rapporter feilen og behold releasen som utkast; ingen automatisk ny utrulling.

**Utført i denne oppgaven:** GitHub-kartlegging og guardrails.
**Ikke utført:** serverpreflight, ny Uniweb/Render-deploy, merge, secretendring
eller redaksjonell handling. Ferske serverhasher, selektiv implementasjon,
miljøregler og Thomas sin godkjenning gjenstår før produksjon.

## Ett prosjekt på begge maskiner

Bruk eksisterende `toystad461/radiorubben-studio`. Webutgaven er PHP/Composer,
ikke `radiorubben-web`, og skal ikke erstattes av et nytt Node-prosjekt.
Foreslått lokal mappe er `C:\RadioRubben\radiorubben-studio`.
Arbeid i en egen gren, test, slå sammen til `main`, og hent med `git pull --ff-only`
på den andre PC-en etter at lokale endringer er avklart. Ikke bruk hard reset.

## Releaseflyt

`VERSION` og `docs/releases/<versjon>.md` beskriver hver release. Workflowen
`Studio release and guarded Uniweb deployment` tester PHP 8.2/8.4 og bygger med
`composer.lock`. Ved main opprettes et **utkast** med ZIP, manifest og SHA-256.
Koden publiseres ikke bare fordi byggingen er grønn.

Studio-workflowen kan bare kjøre `dry-run`. Fullpakke-apply er sperret som
beskrevet øverst. En dry-run kan skrive privat staging, men endrer ikke
nettsidefiler. Ingen release publiseres automatisk fra denne workflowen.

## GitHub-innstillinger i Studio-repoet

Bruk repository-innstillinger eller miljøet `studio-production`:

- Secret `STUDIO_DEPLOY_SSH_KEY`: dedikert privat ED25519-deploynøkkel.
- Secret `STUDIO_DEPLOY_KNOWN_HOSTS`: known_hosts-linje bekreftet med webhotellet.
- Variable `STUDIO_SSH_HOST`: bekreftet SSH-vert.
- Variable `STUDIO_SSH_USER`: bekreftet SSH-bruker.
- Variable `STUDIO_DEPLOY_ENABLED=true`: først når vert, nøkler og mappene er avklart.
- Variable `STUDIO_DEPLOY_AUTO_APPLY`: ignoreres; automatisk apply er fjernet.

Hjemmesidens dokumenterte SSH-vert er `ssh.cptk37ymg.service.one`, bruker
`cptk37ymg_w1417156`, port 22. Dette er referanseverdier fra hjemmeside-repoets
publiseringsdokumentasjon, ikke bevis på at Studio-tilgangen er konfigurert.
Hemmeligheter fra `radiorubben-web` deles ikke automatisk med dette repoet.
Ikke lim private nøkler, passord eller Entra-hemmeligheter inn i en samtale eller Git.
Behold miljøets eventuelle godkjenningsregler; ikke deaktiver dem for å få grønn kjøring.

## Avgrensede mål og forhåndskontroll

Kun `/run/webroots/r1417157/studio-public` og
`/run/webroots/r1417157/studio-private` kan endres av skriptet.
Det stopper ved manglende eller symbolske målkataloger, uventet realpath,
feil HTTPS eller mismatch mellom domenets CSS og filen i Studio-dokumentroten.
Hovednettsidens rot, WordPress, database, DNS og domeneinnstillinger endres ikke.

Serveren må ha PHP CLI >=8.2 med curl/json/openssl/session/zip, samt bash,
rsync, tar, curl, flock og vanlige filverktøy. Webserverens PHP-versjon og
utvidelser må kontrolleres separat i Uniweb; en CLI-kontroll er ikke bevis for
webserverens runtime. Ingen PHP-oppgradering eller sertifikatbestilling utføres
automatisk. Ikke omgå krav med --insecure eller endret offentlig dokumentrot.

Eksisterende site_mode beholdes. App-modus krever gyldig Entra-konfigurasjon og
korrekt HTTPS-baseadresse. Publisering i demo-modus avvises. Ventesiden kan
oppdateres uten å åpne internfunksjoner. PHP-konfigurasjon fra webserverens
miljø kan avvike fra CLI; uenighet i HTTP-kontrollen stopper publiseringen.

Dry-run laster pakken til en privat stagingmappe og kontrollerer den, men bruker
rsync --dry-run mot nettsidemappene. Det er altså ikke null skriving på serveren,
men ingen endringer i nettstedets filer.

## Historisk fullpakke-backup og tilbakeføring (apply er sperret)

Apply tar komplett Studio-kodebackup i
`~/.radiorubben-studio-deploy/backups/<run-id>/studio.tar.gz` utenfor offentlig webrot.
Backups og staging er private (umask 077), og en lås hindrer samtidige Studio-deployer.
`config/local.php` kopieres ikke fra GitHub; eksisterende hemmeligheter beholdes.

Ved feil etter backup forsøkes gjenoppretting av bare de to Studio-mappene.
Tilbakeføring bruker --delete kun mot de eksakt validerte Studio-katalogene og
fra den komplette backupen. Et prosessdrap eller tapt forbindelse kan likevel
kreve manuell gjenoppretting. Utrullingen er ikke atomisk. Ikke gjør samtidige
serverendringer. Gamle Git-filer slettes ikke ved normal apply; eventuelle
fjernede inngangspunkter må vurderes eksplisitt før en senere release.

HTTP-testen kontrollerer venteside eller innloggingsredirect og release.json.
Den erstatter ikke en virkelig Entra-innlogging eller testing av interne funksjoner.
Før en offentlig/intern åpning må autorisert bruker, avvist bruker, utlogging,
sesjonsutløp, HTTPS og webserver-PHP verifiseres. Ikke aktiver site_mode=app bare
for å tilfredsstille en publiseringstest.

OneDrive: eventuelle langsiktige backupkopier og godkjenningsnotater kan legges
under Radio Rubben / IT & Utstyr / Backup. Denne workflowen kopierer ikke til
OneDrive og skal ikke omtales som en OneDrive-backup.

## SSH-oppsett – 05.10.2026

PR #31 inkluderer nå en separat, manuelt startet `studio-ssh-check.yml`.
Den bruker miljøet `studio-production`, kjører bare fra main og er uavhengig
av release/tag og `STUDIO_DEPLOY_ENABLED`. Den kontrollerer nøkkel, kjent vert,
SSH, eksisterende Studio-mapper og PHP/verktøy uten opplasting eller skrivetest.
Den leser ikke lokal konfigurasjon, brukere eller redaksjonelle data.

Oppsett gjenstår: bekreft Studio-konto og SSH-vertsfingeravtrykk i Uniweb,
installer en dedikert offentlig nøkkel og lagre privatnøkkelen direkte som
`STUDIO_DEPLOY_SSH_KEY` i GitHub-miljøet. Legg verifiserte vertsnøkler i
`STUDIO_DEPLOY_KNOWN_HOSTS`, og sett `STUDIO_SSH_HOST` og `STUDIO_SSH_USER`.
Ikke kopier WordPress-kontoens verdier uten å bekrefte tilgang til Studio.
Hold `STUDIO_DEPLOY_ENABLED=false` under oppsettet. Kontroller miljøregler
og reviewer før manuell kjøring. Nøkler skal aldri legges i chat eller Git.

Workflowen må gjennomgås og merges før den kan startes fra Actions.
En grønn tilkoblingstest verifiserer ikke skriveadgang, domenets webroot,
produksjonens filhasher eller selektiv deploy/rollback. Disse kreves fortsatt
før utrulling. GitHub-pluginen kan ikke administrere secrets eller starte
workflowen; kontoinnstillinger og første kjøring gjenstår. Ingen ny tilkobling
eller produksjonsdeploy er utført i denne oppfølgingen.


### Bekreftet katalogalias (05.10.2026)

Uniweb eksponerer `/run/webroots/r1417157` som symlink til
`/customers/9/3/1/cptk37ymg/webroots/r1417157`. Kjøring må bekrefte eksakt
samsvar før den bruker de kanoniske `studio-public`/`studio-private`-mappene.
PHP realpath() brukes fordi separat realpath-program mangler på verten.
Dette åpner ikke fullpakke-apply; samme sperre og krav om avstemming gjelder.


### Manuell preflight uten release-endringer

Actions → Studio release and guarded Uniweb deployment → Run workflow →
main → dry-run bygger og tester den valgte committen og overfører pakken til
privat staging. Deploy-jobben avhenger av verify, ikke draft. Den oppretter
ikke en release ved manuell kjøring, krever ingen aktivering av apply, og
endrer ikke nettstedets filer. Rsync sammenligner innhold med --checksum.
Manglende tilkoblingsinnstillinger feiler jobben. Fullpakke-apply er fortsatt
sperret. Behold STUDIO_DEPLOY_ENABLED=false og STUDIO_DEPLOY_AUTO_APPLY=false.
