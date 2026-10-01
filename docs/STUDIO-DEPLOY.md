# Studio: GitHub → kontroller → v0.0.1 → studio.radiorubben.no

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

Studio-deploy må aktiveres separat. Første kjøring er `dry-run`. Etter kontroll:
Actions → Studio release and guarded Uniweb deployment → Run workflow → main → apply.
Oppgi også approved_sha: hele den testede og uttrykkelig godkjente commit-hashen.
Workflowen avviser apply hvis denne ikke er identisk med kildecommiten.
Kun vellykket apply publiserer GitHub-releasen. Automatisk apply er fjernet; produksjon krever alltid en manuell workflow-kjøring.

## GitHub-innstillinger i Studio-repoet

Bruk repository-innstillinger eller miljøet `studio-production`:

- Secret `STUDIO_DEPLOY_SSH_KEY`: dedikert privat ED25519-deploynøkkel.
- Secret `STUDIO_DEPLOY_KNOWN_HOSTS`: known_hosts-linje bekreftet med webhotellet.
- Variable `STUDIO_SSH_HOST`: bekreftet SSH-vert.
- Variable `STUDIO_SSH_USER`: bekreftet SSH-bruker.
- Variable `STUDIO_DEPLOY_ENABLED=true`: først når vert, nøkler og mappene er avklart.
- `STUDIO_DEPLOY_AUTO_APPLY` brukes ikke lenger; den kan ikke aktivere publisering.

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

## Backup og tilbakeføring

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

## Render-testmiljø
Se [RENDER-STAGING.md](RENDER-STAGING.md). Render-oppsettet er separat fra Uniweb-produksjon.
