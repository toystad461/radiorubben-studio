# Privat iPhone-app – retting etter skjermtest

7. oktober 2026. Oppfølging av PR #47, tidligere head
`20b36bdd548fdafccfeac62483a002e3d3ce9453`. Ingen merge, deploy eller
aktivering av privatmodus inngår. Eksakt ny head og CI-resultat føres i PR-en.

## Rettet

1. Saksvelgeren og skjemaraden kan krympe til tilgjengelig bredde.
   På 393 px visning er saksredigeringen nå 393 px, mot tidligere 642 px.
   På 320 px visning er den 320 px, mot tidligere 642 px.
2. Mobilmenyen beholdes i 852×393 liggende berøringsvisning. Utvidelsen til
   1000 px krever coarse pointer og manglende hover; et 852 px bredt
   musevindu får ikke telefonmenyen. Desktop på 1280 px er også kontrollert.
3. Forsiden og saksredigeringen tåler den samme 200-prosent tekststresstesten
   uten horisontal overflyt. Ingen overflow-hidden eller tekstklipping brukes
   for å skjule feil. Den native saksvelgeren viser naturlig bare tilgjengelig
   bredde; valgt sak har fortsatt full overskrift i innholdet.
4. Kontrollsenterets 322 px brede innhold på 320 px visning er rettet.
   Skjemaknapper, sammenleggbare overskrifter og saksvelger har minst 44 px
   høyde i de testede mobilvariantene.

Runtimeendringer: bare `public/assets/private-app.css` og versjonsparameteren
for dette stilarket i `app/views/head.php`. Eksisterende auth-, saks-, kilde-,
CSRF-, revisjons- og publiseringskode er urørt. Privatmodus er fortsatt av som
standard. Ingen OneDrive-mappe eller redaksjonelle data er endret.

## Gjentakbar kontroll

Den tidligere testen ble først reprodusert med **85/95** bestått. Etter retting
består de samme **95/95** kontrollene. Den versjonerte testen inkluderer også
25 nye kontroller av trykkflatehøyde og desktopmeny: **120/120 lokalt**.
Den eksisterende PHP-testen `tests/private-app.php` består fortsatt **51/51**.
Dette er avgrensede tester, ikke en garanti om feilfri app.

```sh
python -m pip install playwright==1.57.0 beautifulsoup4==4.14.3
python -m playwright install --with-deps chromium
python tests/private-app-screen/run.py /tmp/rr-screen-results
```

PHP 8.4 brukes i den separate workflowen `Private iPhone screen regression`.
Workflowen kjører på PR og manuell test, har bare leserettigheter og inneholder
ingen deployjobb. Den endrer ikke eksisterende produksjonspipeline.
`RR_SCREEN_BROWSER` kan peke til lokal Chromium; utelatt bruker Playwright sin.
Kun JSON-resultater og PNG-skjermbilder blir testartefakter, aldri sesjoner,
serverkonfigurasjon eller nøkler. Testserver og midlertidig mappe ryddes etterpå.

## Metode og begrensninger

Testen lager en isolert kopi av app/public og eksempelkonfigurasjon, med fire
merkede testutkast og kunstige serverlagrede sesjoner. Ekte PHP-HTTP-innganger
kontrolleres separat fra Chromium sin HTML-visning. Browser-nettverk avvises;
lokale ressurser bygges inn i testsiden. Lenkeklikk og HTTP-destinasjoner testes
separat. CSP sjekkes som header, ikke som håndheving i innbygd HTML.
Dette er ikke en full nettleser-ende-til-ende-test eller Microsoft-innlogging.

22 layoutvarianter og to redigeringsbilder gir 24 skjermbilder. Bildene viser
nå synlig viewport, mens breddemålingen fortsatt gjelder hele dokumentet.
I lokal Chromium 144.0.7559.96 / Playwright 1.57.0 endret fullsideskjermbilder
berøringsemuleringen til mus. Dette ble reprodusert separat. Viewport-bilder
bevarer inputmodusen, og testen avviser at et bilde endrer den. Ingen
nettleserpolicy eller produksjonssperre ble svekket for å få testen grønn.

200 prosent tekst er en simulering av doblet skriftstørrelse, ikke iOS Dynamic
Type. Redusert høyde er ikke et ekte iOS-tastatur. Ulagret tekst, før-avslutt-
varsel og feilet lagring kontrolleres som lokale komponenthendelser.

Ekte eierinnlogging og avvisning av en annen Microsoft-konto på skjermet
adresse, Safari/WebKit, fysisk iPhone 15 Pro, hjemskjerminstallering, safe-area,
tastatur og gjenåpning etter hvile gjenstår. Ingen ekstern testadresse er
opprettet. PR-en skal fortsatt være draft og ikke merges før separat
autorisasjon til utrulling. En grønn skjermtest er ikke et deploybevis.

Referanse for inputavgrensningen: https://www.w3.org/TR/mediaqueries-4/#mf-interaction
