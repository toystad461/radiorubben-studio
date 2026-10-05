# Konsolidert Studio-runtime, 30.09.2026

PR #19 samler innsamlet Uniweb-kode i repoets app/ og public/. Kildegrunnlaget
er det uendrede snapshotet fra commit 360874246bd31e0826a101d3b2beac5e47ee9580.
Dette er ikke en produksjonsdeploy.

## Avstemming

Alle 11 filer uten identisk treff i undersøkte branch-tips bruker serverversjonen:
app/board.php, app/views/sidebar.php, app/auth/init.php, app/config.php,
app/story-script.php, public/.htaccess, public/logout.php, public/robot.php,
public/sending.php, public/assets/studio.css og public/auth/start.php.
De øvrige innsamlede filene, inkludert 28 uten banetreff, er også tatt inn.
Programregister, sendeforslag og andre ikke innsamlede funksjoner fra PR-kjeden
blandes ikke inn. Historiske commits er ikke fullstendig undersøkt.

Endringer fra snapshotet:
- Offentlige PHP-filer bruker repoets app/ og config/. Pakking mapper begge
  til studio-private, også uten mellomrom og fra undermapper.
- Robotens to feedfiler får korrekt privat config-sti i Uniweb-pakken.
- Hvit hovedlogo og favicon kommer fra brukerens RadioRubben-Logopakke.
  PNG er 2400 × 1073; HTML-dimensjoner er oppdatert. SVG lagres som alternativ.
  SVG-en er vektorisert fra PNG, ikke en opprinnelig vektormaster.
- Privat config, nøkler, data og cache ignoreres av Git. Pakken begrenser
  filtyper og utelater private etterlatenskaper, sikkerhetskopier og symlenker.
- Eksempelinnstillinger beskriver eksisterende valg uten virkelige nøkler.

Composer-låsen beholdes fra GitHub. Den er byteidentisk med låsen i tidligere
0.0.2-pakke brukt lokalt. Aktiv servers lås og vendor er ikke bekreftet identiske;
de må avstemmes før reinstallasjon.

## Tester

Tester fra PR #18, commit 4fe4eb110b66752e281e4e23f460bf9a4d72df9d, er tilpasset
repoets mapper og isolerte testdata. Generiske sendelistetester bruker en
trafikkfixture; nyheter testes separat med fersk kontroll av nøyaktig tekst.
Ukjente programmer får tom læringskontekst i serverversjonen. Legacy-punkter
forblir uten programtilordning (tom verdi), med originaltekst i historikken.

Lokalt passerer PHP WASM 8.2/8.4: sendeliste/revisjoner, læring, nyhetskilder,
58 nyhetsmanus-sjekker, seks side-/tilgangsscenarier, lokale kontoer,
introduksjonsmail, manusgenerering og nettpublisering med simulerte API-svar.
Node tester automatisk radio-/nettforberedelse, revisjonsrekkefølge og stopp ved
feil, uten automatisk sluttgodkjenning eller publisering.
Pakkekontroll inngår i composer test. GitHub CI kjører også eksisterende
HTTP-/innloggingstester med ekte PHP. Se sjekkene på aktuell PR-head;
lokale WASM-tester erstatter ikke disse.

## Før publisering

Ingen aktiv Uniweb-fil er endret av konsolideringen. Ikke overskriv produksjon
med en hel pakke før råbyte-hasher, siste serverendringer og avhengigheter er
avstemt. Privat backup er fra flere trinn, ikke en atomisk datakopi.
Nettlesergjennomgang av innlogget Studio gjenstår, sammen med faktisk
Entra/Vipps, OpenAI, e-post, WordPress, Hue og andre eksterne integrasjoner.
Eksisterende CSP og inline JavaScript/stiler må vurderes i den gjennomgangen;
PHP-/Node-tester beviser ikke at nettleseren tillater alle funksjonene.
Deretter må eksakt commit, berørte filer, før/etter-hasher og tilbakeføring inngå
i en avgrenset publisering. GitHub skal være kilden for publisering fremover.
