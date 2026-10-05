# Uniweb – gjenfunnet produksjonskode 30.09.2026

Denne katalogen bevarer 80 tekstfiler lest fra en privat serverkopi av aktivt
Studio. Den er et avstemmingsgrunnlag, ikke en opplastingspakke eller en ferdig
konsolidert main-versjon. Ingen aktive produksjonsfiler er overskrevet.

## Innsamling og backup
Uniweb File Manager var innlogget. App-katalog og studio-public ble kopiert til
studio-private/reconciliation-backup-20260930-2002/ før lesing. Senere ble
config, vendor, docs, .htaccess, composer.json og composer.lock kopiert dit.
Disse åtte oppføringene ble kontrollert etter ny lasting av filbehandleren.
README.md ble forsøkt kopiert, men var ikke til stede ved sluttkontrollen.
Backupen ble tatt i flere trinn mens Studio var i drift; den er ikke et atomisk
databasesnapshot og tilbakeføring må avstemmes mot nyere data.

Vanlig mappe-nedlasting feilet i nettleserintegrasjonen. Tekst ble derfor
hentet med editorens «velg alt / kopier», uten lagring av redigeringer.
SHA-256 og Git-blob-SHA gjelder den innsamlede UTF-8-teksten; serverens råbyte-
hasher og eventuelle forskjeller i linjeskift er ikke kontrollert separat.
41 tekster matcher eksisterende Git-blobs eksakt. Kildetidspunkter vist i
backupmappen er kopieringstidspunkter, ikke sikre opprinnelige endringstider.

## Avvik
Sammenligningen dekker 20 navngitte branch-tips, med commits låst i manifestet.
Den søker ikke gjennom alle historiske commits eller slettede grener.

| Referanse | Identiske | Forskjellige | Mangler |
| --- | ---: | ---: | ---: |
| main ved 5b87408b0e413770e1486029a18e0edabfcf0ebe | 10 | 12 | 58 |
| Alle 20 undersøkte branch-tips | 41 | 11 | 28 |

«Mangler» betyr at kodefilen ikke finnes på tilsvarende kartlagt sti i disse
branch-tipsene og ikke har identisk blob der. Det beviser ikke hvem som
publiserte filen, eller at den aldri tidligere har vært versjonert.

Viktige gjenfunn: producer.php, VippsClient.php, AI Studio og dets tjenester,
Hue-, musikk- og værkode, samt eksisterende konto-/tilgangskode.
Nyhetsmodulen, case-workflow.php, web-publish.php, case.php og wordpress-check.php
matcher nåværende PR #18. Eldre tekst om manglende nettpubliseringskode er
derfor utdatert. Dette dokumenterer kode på serveren, ikke vellykket ekstern
publisering eller en funksjonstest med ekte konto.

board.php, sending.php og story-script.php avviker fra branch-tipsene.
Produksjonskopien har ingen programs.php i app-katalogen; ikke legg til
programregisteret som en skjult del av nyhetsmanusutrulling.
Eksisterende review-only-patch fra PR #18 skal ikke legges oppå denne serveren
uten ny avstemming: news-script.php finnes allerede.

## Hva er med / utelatt
source/studio-private/app og source/studio-public inneholder innsamlet aktiv
tekstkode, inkludert den eksisterende head (2).php som må vurderes separat.
.htaccess og composer.json fra privat rot er også med.

Ingen private config-filer, API-nøkler, passord, brukerlister, sendelistedata,
cache, gamle .bak-filer eller vendor-kode er lagt i Git.
Config og vendor er bevart på serveren. Logoen, composer.lock og private
driftsdokumenter er ikke lest inn i dette Git-snapshotet.
Dette er derfor ikke en komplett, selvstendig reinstallasjonsleveranse.
OneDrive.php og WordPress.php i integrations er identiske med tidligere
grensesnitt; den reelle nettpubliseringsflyten bruker app/web-publish.php.

## Kontroller
- 54 PHP-filer er syntakskontrollert med TOKEN_PARSE på PHP WASM 8.2 og 8.4.
- 15 JavaScript-moduler passerer node --check.
- Innsamlingen har 80 unike, ikke-tomme tekstfiler.
- Kontroll for kjente nøkkelformater og gjennomgang av credential-relaterte
  tildelinger fant ingen innlagte credentialverdier i de innsamlede filene.
  Dette er ikke en generell sikkerhetssertifisering.
- Ingen PHP-appkode er kjørt mot produksjon eller ekte API-er under kontrollen.
- Manifestet beskriver hver teksthash, kartlagte referansestier og Git-treff.

## Neste integreringsarbeid
1. Bruk dette snapshotet som kjent kildegrunnlag for å lage en egen testbar
   runtime-gren. Bevar main og eksisterende PR-er inntil avhengighetene er løst.
2. Avstem de 11 ulike filene eksplisitt; ikke velg automatisk «nyeste gren».
3. Fullfør oversikt over eksempelkonfigurasjon, låste avhengigheter og binære
   ressurser uten å hente private nøkler inn i Git.
4. Kjør relevante bruker-, board-, manus-, kilde- og publiseringstester mot
   den konsoliderte koden og verifiser produksjonsnær UI.
5. Først da kan main og standardpakken bli autoritativt deploygrunnlag.
   Videre kodeendringer skal gå via GitHub før publisering.
