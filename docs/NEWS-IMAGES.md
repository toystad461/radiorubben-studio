# Nyhetsbilder v1.2 – implementasjon og aktivering

14.10.2026. Studio PR92 og PR93 er merget og selektivt publisert.
Siste kodecommit er `96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60`,
releasejobb `38623594055`, privat backup `selective-38623594055-1`.
WordPress-utvidelsen er aktiv med 21 faktiske mediefiler. Automatvalg ble
aktivert 14.10.2026 kl. 19:29 UTC etter bestått modellpilot `38623968749`.
Ingen tidligere publiserte artikler er migrert eller endret.

## Plassering i eksisterende system

Den operative generelle nyhetsflyten er PHP Studio: kilde → nettsak →
kildekontroll → redaksjonell sluttgodkjenning → WordPress. Det separate
TypeScript-grunnskjelettet og fotballrobotens kø er ikke riktig sted å innføre
et generelt bildevalg til denne godkjenningsflaten og er ikke endret.

`app/news-images.php` velger tema etter generering/kildekontroll i
`studio_web_prepare`. Den bruker samme `producer_request` og konfigurert
tekstmodell som resten av Studio, med hele overskrift, ingress, brødtekst,
kategori og tilgjengelige entiteter, samt alle registerets `use_when` og
`do_not_use_when`. Personer, organisasjoner og steder leses også i artikkelteksten.
Dette er en semantisk vurdering av hovedinnhold og motiv, ikke en nøkkelordtabell.
Strukturert JSON-skjema krever belegg fra ordrette utdrag av selve saken.
Ordrette belegg, hovedvinkling, motivenes egnethet og sikkerhet kontrolleres.
Bremnes krever uttrykkelig hovedklubb og fotballvinkling; Brann/Bømlo er ikke nok.
Ingen passende motiv, usikkerhet, modellfeil eller manglende medier gir en synlig
sperre. Det brukes ikke et tilfeldig reservebilde.

`app/news-images-contract.php` og `app/data/01-BILDEREGISTER.json` er byteidentiske
med kontrakt/register i WordPress-utvidelsen `rr-news-images` i `radiorubben-web`.
Ved fremtidige pakkeendringer må begge oppdateres sammen.

## Sak, godkjenning og deling

`web.imageSelection` lagrer pakke/versjon/registerhash, tema, begrunnelse,
automatisk/manuell opprinnelse, lås, konteksthash, tre varianter med asset_id,
faktisk media_id/URL, SHA-256, dimensjoner, alt-tekst og bildetekst.
Godkjenning lagrer aktør/tid og inngår i eksisterende `approvedHash` sammen med
teksten. Bildehistorikken bevares på saken. Et nytt forsøk gjenbruker samme valg.

Forhåndsvisning og endring ligger i både Nyhetsdesken og saksvisningen, via
`app/views/news-image-choice.php`. Redaktøren kan velge et annet av de sju
temaene, begrunne og låse valget. Ingen passende bilde kan eksplisitt godkjennes
som publisering uten bilde, med begrunnelse; en uavklart automatvurdering er
aldri det samme som dette manuelle valget. Hele bildet og KI-bildeteksten vises.
Filfeil vises. Endring trekker tilbake sluttgodkjenning. Eksisterende roller,
CSRF, revisjonskontroll og låsing brukes også for bildevalg.

Etter tekst-/kategoriendring må bildet bekreftes på nytt. En allerede overført
WordPress-kladd kan bekrefte **samme** bilde, men ikke få et nytt bilde gjennom
denne flyten. Et manuelt endret WordPress-hovedbilde bevares og gir konflikt ved
et foreldet Studio-forsøk. Saker med uavklart overføring følger eksisterende
statusavklaring. Etter publisering tilbys ikke endring av bildevalg i denne nye
utkastflyten. Oppdateringer som endrer bildets kontekst må avklares redaksjonelt.

| Publiseringsflate | Variant | Dimensjoner |
| --- | --- | --- |
| Artikkel og nyhetskort, også mobil | article_landscape / liggende | 1672 × 941 |
| Kvadratisk sosialt innlegg | feed_square / kvadrat | 1254 × 1254 |
| Story | story_vertical / story | 941 × 1672 |

Godkjente varianter kan lastes ned fra eksisterende sak via
`public/news-image-export.php`. Eksport krever gyldig sluttgodkjenning og
kontrollerer de faktiske filbytene mot registeret. Det legges ikke til en ny
automatisk sosial publiseringskanal. Ved 4:5/1,91:1 må plattformen vise hele
bildet med bakgrunn, eller redaktøren foreta en separat formattilpasning.

Standardtekst: «KI-generert illustrasjonsbilde. Radio Rubben.» Ingen påstand om
dokumentasjon av en virkelig kamp, et bestemt møte, rådhus eller utbyggingsområde.
Faktisk brukt kilde og original lenke beholdes i eksisterende kilde-/tekstflyt.
NRK er kilde, ikke bildevalg; ingen NRK-logo eller kommunevåpen legges til.
Kildekreditering gir ikke tillatelse til kopiering av andres tekst eller bilder.

## Import, filplassering og arkiv

De påkrevde filene 00, 01 og 03 i vedlegget er lest. Alle 21 PNG-er er kontrollert
mot filstørrelse, SHA-256 og dimensjoner. 02 beskriver produksjonen og utløser
ingen ny bildegenerering.

Original vedlagt ZIP: `/workspace/scratch/816dfaad639c/upload/RadioRubben-Nyhetsbilder-v1.2.zip`.
ZIP SHA-256: `a2d27533d3b9455a96d7c967ed4f87f6aba3827156914a468104bb8b21303adc`.
Register SHA-256: `0f090e476d8042339e1dab75b7653b34548ff24496294bb38908b3db0fabe0da`.
Den lokale arbeidsstien er ikke et permanent produksjonsarkiv.

WordPress-importen bruker asset_id, pakkeversjon og checksum som identitet.
Hele ZIP-en valideres før første skriving. Eksklusiv importlås og identitet i
første vedleggsinnsetting gjør gjentatte og avbrutte forsøk gjenopptakbare uten
duplikater. Ferdige filer som er endret, overskrives ikke ved ny import.

Målplass er under **faktisk** `wp_upload_dir().basedir`, i
`rr-news-images/RadioRubben-Nyhetsbilder-v1.2/`. `rrni_package_receipt` lagrer
faktisk ZIP-sti, mediekatalog, kontrollsum og tidspunkt. Autentisert katalog-API
returnerer ID-er og URL-er fra reelle WordPress-vedlegg, aldri konstruerte URL-er.

I den isolerte testen ligger mediene under
`/workspace/scratch/816dfaad639c/runtime/wordpress/wp-content/uploads/rr-news-images/RadioRubben-Nyhetsbilder-v1.2/`.
Testinstallasjonen bruker lokal ruting og Radio Rubben-hostnavnet som testkonfig;
disse ID-ene/adressene er **ikke** produksjonsmedier. De skal ikke kopieres til drift.

Originalpakken er faktisk arkivert og lest tilbake med identisk SHA-256 i
OneDrive: `Radio Rubben / 02 Profil og maler / Nyhetsbilder / RadioRubben-Nyhetsbilder-v1.2`.
Item: `012VNUSIGB4FUDOXKHQRAYD3WJSRTTNAJY`.
[Originalpakke](https://radiorubben-my.sharepoint.com/personal/thomas_sellevold-oystad_radiorubben_no/Documents/Radio%20Rubben/02%20Profil%20og%20maler/Nyhetsbilder/RadioRubben-Nyhetsbilder-v1.2/RadioRubben-Nyhetsbilder-v1.2.zip).
Samme mappe inneholder `MEDIEREGISTER-PRODUKSJON.json` med faktiske ID-er/URL-er
og skjermbilder fra mobil- og skrivebordskontrollen.

Produksjonsarkiv:
`/customers/9/3/1/cptk37ymg/users/cptk37ymg_w1417156/.radiorubben-deploy/news-images/archive/RadioRubben-Nyhetsbilder-v1.2.zip`.
Produksjonsmedier:
`/run/webroots/r1417157/wp-content/uploads/rr-news-images/RadioRubben-Nyhetsbilder-v1.2`.
ID-er 1352–1372. Bremnes: liggende 1361, kvadrat 1362, story 1363.
Produksjonens katalog-API er kontrollert, og alle 21 offentlige HTTPS-filer er
lastet ned og sjekket mot originalhashene. Gjentatt import ga samme mapping.
WordPress-kontroll 38621341817 bekrefter 76 uendrede artikler.

## Verifisert lokalt

- Studios komplette PHP/Composer-suite, JavaScript-flyttest og ZIP-pakking.
- Elleve syntetiske semantiske svar gjennom hele bildevalgfunksjonen:
  Bremnes, Brann, politikk/tjeneste, høring/vedtak, konsert/idrett,
  maritim virksomhet, frisørsalong uten passende motiv og lokalsamfunn.
- Eksplisitt Bremnes-sperre ved Brann som hovedklubb, oppdiktet belegg,
  manglende medie-ID/hash, kilde/tema-separasjon, manuell lås og stabilt nytt forsøk.
- Samme menneskelige sluttgodkjenning kreves; bilde-/tekstendring invaliderer den.
  Opplastingsfeil stopper før overføringen reserveres. Ny opplastingskobling
  godkjenner ikke samtidig endret tekst. WP-kladd kan bekrefte samme låste bilde.
- Reell WordPress 7.1.3 / SQLite / PHP 8.3.30 i isolasjon: gjentatt import,
  avbrudd/gjenopptakelse, lås, korrupt fil, endret alt-tekst, doble identiteter,
  REST-publiseringssperre og bevaring av WordPress-redaktørens hovedbilde.
- Nettleserkontroll ved 390 og 1280 px: Studio viser alle tre Bremnes-varianter;
  faktisk WordPress-bildepipeline med repositoryets next-tema viser sju kort og
  artikkelbildet. Bildene lastes, logoene er hele, contain og ingen horisontal
  overflyt. Mobil bruker fortsatt liggende artikkelbilde. Visuelt inspisert.

Dette dokumenterer programflyt og lokal rendering. De semantiske testsvarene er
syntetiske; de er **ikke** en evaluering av den levende modellens treffsikkerhet.
Produksjonens WordPress 7.1.3/PHP 8.2.34, aktive Next-tema, Optimole 4.2.16,
PHP-bildemarkup og faktiske CSS er etterkontrollert. Alle ni kontrollbilder
(sju kort, artikkel og prioritert kort) er hele ved 390/1280 px. Visningen er
syntetisk; ingen artikkel ble opprettet. Eksterne sosiale flater er uprøvd.
Ingen reell sak er godkjent eller publisert under testene.

## Verifisert aktivering

[Produksjonspilot 38623968749](https://github.com/toystad461/radiorubben-web/actions/runs/38623968749)
bestod med faktisk `gpt-4.1-mini` og autentisert WordPress-katalog. Bremnes fikk
bremnes-fotball, Brann fikk sport, politikk/tjeneste og konsert/idrett ble skilt,
frisør fikk INGEN_PASSENDE_BILDE, maritimt verksted fikk naeringsliv. Alle valg
var stabile ved nytt forsøk. Dette er åtte syntetiske prøvesaker, ikke ekte
redaksjonelt innhold eller en garanti for alle fremtidige motivvurderinger.

`studio-private/config/news-images-enabled.php` ble atomisk satt til `true`
og lest tilbake. Øvrig privat konfigurasjon og sendeliste var uendret. Backup:
`/home/cptk37ymg_w1417156/.radiorubben-studio-deploy/backups/news-images-activation-38623968749-1`.
Kun nye utkast tar i bruk automatvalget. Eksisterende sluttgodkjenning består.
DRIFTSKVITTERING.txt, aktiveringskvittering, faktiske medier og skjermkontroll
ligger sammen med originalpakken i OneDrive. Første virkelige sak skal gjennom
vanlig redaksjonell kontroll; ingen slik sak ble publisert som utrullingstest.

## Innføringsprosedyre (gjennomført 14.10.2026)

1. Gjennomgå begge PR-er. Avstem selektivt mot ferske produksjonsfiler og ta
   privat kodebackup. Ikke rull ut hele repositoryet eller eldre PR-kjeder.
2. Etter konkret godkjenning: installer WordPress-utvidelsen, arkiver ZIP-en,
   kjør `wp rr-news-images import /faktisk/sti/pakke.zip`, deretter
   `wp rr-news-images verify`. Registrer kvittering og produksjonens faktiske medier.
3. Rull ut berørte Studio-filer. `news_images_enabled` er `false` som standard.
   Aktivering skjer med den private filen `config/news-images-enabled.php`, som
   returnerer `true`. Eksisterende `config/local.php` og nøkler omskrives ikke.
4. Prøv nye utkast med den levende modellen, inkludert grensetilfellene over.
   Kontroller artikkel, kort, mobil, desktop og tilgjengelig deling i faktisk tema/CDN.
   Ingen virkelig publisering uten separat redaksjonell sluttgodkjenning.
5. Aktiver flagget for nye utkast etter kontrollene. Eksisterende utkast med
   redaksjonelt bilde/profil og alle tidligere publiserte artikler migreres ikke.

Tilbakeføring: slå av nytt automatvalg; bevar allerede lagrede valg, godkjenninger
og mediefiler. Ikke slett vedlegg brukt av saker. Full kode-tilbakeføring krever
avklaring av saker som allerede har tatt i bruk kontrakten.
