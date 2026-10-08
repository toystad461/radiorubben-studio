# Studio – produksjonsavstemming 8. oktober 2026

## Gjeldende kilde og mandat
Thomas ba om å avstemme produksjonen mot GitHub og samle det fungerende
grunnlaget. Studio beholdes på kildegrunnlaget
`1aff7adeb2b90c9576265c70f06bab141a1519a0` fra main. Det inkluderer PR #50 sine
forsiktigere lengdekrav og bevaring av kildefakta ved språkvask.

Denne avstemmingsgrenen legger bare til dokumentasjon og maskinlesbar
kildereferanse. Ingen runtime, arbeidsflyt, innlogging, manifest for utrulling,
privat konfigurasjon eller data endres. Main er ikke oppdatert og ingen ny
utrulling er startet. Dette er ikke godkjenning for å laste hele pakken opp.
Samlet koordinerings-PR: [Web #67](https://github.com/toystad461/radiorubben-web/pull/67).

## Faktisk utgivelsesbevis, ikke bare grønn CI
Den eksisterende kjøringen [37739713565](https://github.com/toystad461/radiorubben-studio/actions/runs/37739713565)
på main brukte source-commit `1aff7adeb2b90c9576265c70f06bab141a1519a0`.
PHP 8.2, PHP 8.4, mobiljobben og deploy-jobben er kontrollert som fullført med
success. Deploy-jobben er `113187636289`.

Loggens siste resultat 8. oktober kl. **08:50:49 Europe/Oslo** er:

```json
{"commit":"1aff7adeb2b90c9576265c70f06bab141a1519a0","changed":4,"backup_id":"selective-37739713565-1"}
```

Dette er et dataminimert utdrag: originalen har nøkkelen `backup` med privat
serversti. Ingen hemmelige verdier er kopiert til denne rapporten.

Deploypakken i den eksisterende jobben var artefakt `11533017112`,
`studio-release`, med verifisert nedlastingsdigest
`dfd6019231f8aad6220a9d81cffcc21e4394f9725cd33339998cc54edbe18758`.
Dette er digesten for GitHub-artefakten, ikke et samlet fingeravtrykk av
produksjonsserveren. Ingen ny artefaktnedlasting eller gjenoppretting er gjort her.

## Hvorfor main likevel ikke er en komplett serverkopi
`studio-auto-publish.sh` kjører først en fullpakke-prøvekjøring og deretter
`studio-selective.php`. Den brede rsync-listen foran «DRY-RUN OK» i loggen er
ikke en liste over utførte filendringer. Faktisk publisering rapporterte fire.

Den selektive koden skiller mellom tidligere `source`-hash og `live`-hash.
Når kilden ikke er endret, beholdes et tidligere registrert serveravvik.
Når serveren avviker uventet fra registrert live-verdi, stoppes utrullingen.
Etter vellykket publisering kontrolleres de administrerte filene og en offentlig
release.json mot den aktuelle pakken. Denne historiske etterkontrollen
beviser ikke at serveren er uendret ved en senere kontroll.

Dette er avgjørende for reset-arbeidet: en fullpakke-opplasting kan overskrive
nettopp de bevarte servertilpasningene som den selektive løsningen tar hensyn til.
`selective-state.json` på serveren er gjeldende tilstand dersom den finnes;
repositoryets eldre `studio-deploy-baseline.json` er fallback, ikke nødvendigvis
siste produksjonsmanifest. Ingen av dem skal normaliseres ved å gjette.

## Avvik som fortsatt krever fersk kontroll
Dry-run før den siste utgivelsen viste blant annet ulik kilde for
`app/bootstrap.php`, `app/views/head.php`, `login.php`, `local/login.php` og
`assets/radio-rubben-logo.png`, i tillegg til utgivelses-/avhengighetsmetadata.
Dette er observasjoner fra den tidligere jobben, ikke nye målinger.
Fire filer ble deretter faktisk publisert; rapporten gjetter ikke hvilke
serveravvik som er igjen ut fra filnavn alene.

Det trengs en dataminimert sammenligning av den gjeldende selektive tilstanden,
faktiske kodehasher og denne source-commiten. Bevar server-only-kode til
opprinnelse og behov er avklart. Ikke kopier køer, brukerdata, local.php,
private JSON-filer, nøkler, sesjoner eller kildegrunnlag for artikler til Git.
Ekte innlogget funksjonstest og kontroll av jobb-/WordPress-avhengigheter er
fortsatt nødvendig før en bredere driftsendring.

## Tilgang og avgrensning i denne oppgaven
En foreslått ny SSH-lesekontroll i Web-repoet ble blokkert av verktøyets
sikkerhetskontroll og er ikke opprettet eller kjørt. Ingen ny fjernkjøring
legges til her. Offentlige HTTP-forsøk ga ikke et ferskt kontrollgrunnlag i
arbeidsmiljøet. Det påstås derfor ikke full, ny serveravstemming.

Eksisterende Studio-workflow er uendret. Den tillater deploy bare for main
ved push eller workflow_dispatch. Den kontrollerte siste deployloggen viste
`AUTO_PUBLISH=true`. En merge av denne dokumentasjonsgrenen kan derfor også
starte eksisterende publiseringsløp: ikke merge som en del av avstemmingen.
Denne teksten deaktiverer ingen GitHub-variabler eller serverjobber.

## Beholdt og utsatt
CRM #48, privat iPhone-app #47, ny feedback-læring #51 og Render #21 er ikke
importert. De eksisterende PR-ene, historikken og nylig konsoliderte funksjoner
beholdes. Det er ikke nødvendig å gjenskape Studio fra en eldre versjon.
Web-repoets henvisning til Fotballrobot 0.10.5 må avklares separat; den gjør
ikke en eldre robotkodekopi til riktig produksjonsversjon.

## Videre ferdigkriterier
Før denne oversikten kan merkes fullt avstemt: dokumenter ferske administrerte
filhasher, forklar hvert gjenværende avvik, fastslå aktive eksterne avhengigheter,
kjør samlet funksjons-/innloggingstest og gjennomgå tilbakeføring og deploymål.
Ingen nye funksjoner, innholdspublisering eller gjenoppretting av database
inngår i disse kontrollene.

GitHub er teknisk bevisarkiv; OneDrive er drifts-/mediearkivet. Operativ rutine
kan knyttes til eksisterende `Radio Rubben/System & Kvalitet/AI og arbeidsregler`.
Denne GitHub-endringen synkroniserer ingen filer dit.

## Kilder
- [PR #50](https://github.com/toystad461/radiorubben-studio/pull/50)
- [Siste kontrollerte deployjobb](https://github.com/toystad461/radiorubben-studio/actions/runs/37739713565/job/113187636289)
- [Fastlåst workflow](https://github.com/toystad461/radiorubben-studio/blob/1aff7adeb2b90c9576265c70f06bab141a1519a0/.github/workflows/studio-release.yml)
- [Fastlåst selektiv kode](https://github.com/toystad461/radiorubben-studio/blob/1aff7adeb2b90c9576265c70f06bab141a1519a0/scripts/studio-selective.php)
- [Studio-status](STUDIO-STATUS.md)
