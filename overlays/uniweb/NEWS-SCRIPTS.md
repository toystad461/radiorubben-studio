# Nyhetsmanus med originalkontroll

Første versjon utvider Sending med «Lag og kildekontroller nyhetsmanus» for
RSS-saker fra NRK og Bømlo kommune. Den lager et kort opplesningsmanus før
sending og henter originalartikkelen via sakens lagrede URL. Andre kilder,
inkludert Vegvesen, beholder sin tydelig merkede generator fra kildeomtalen.

## Arbeidsflyt

1. Legg en RSS-sak i sendelisten og åpne den i Sending.
2. Velg program og lagre hvis profilen skal endres. Trykk manusknappen.
3. Originaltekst hentes på nytt, og en separat AI-forespørsel kontrollerer
   alle påstander i hvert manussegment mot denne teksten. Godkjente
   programregler kan bare påvirke stil; de er aldri faktagrunnlag.
4. Rapporten viser kildebelegg, avvik og usikkerheter. Belegget må finnes
   ordrett i originalen. Et slikt treff beviser ikke semantisk støtte;
   AI-kontrollen er et hjelpemiddel, ikke en garanti for riktige opplysninger.
5. Rediger og lagre ved behov, og bruk «Kontroller lagret manus på nytt».
   Denne handlingen endrer ikke teksten eller det bevarte originalutkastet.
6. Les rapport og original, bekreft redaksjonell kontroll og merk klart.

Kilde- og manusfingeravtrykk, hentetid, modell, regelversjon og originaltekst
lagres privat sammen med rapporten. Endret manus, tittel, kilde eller program
krever ny kontroll. Kontrollen utløper etter én time; aktive sendelister og
teksteksport viser da utkast også når en tidligere godkjenning er lagret.
Kilden overvåkes ikke kontinuerlig. Ny kontroll må kjøres før en senere sending.
Ved feil/timeout er den gamle kontrollen ugyldiggjort, og saken forblir utkast.
Manushistorikken bevares. Private kildeutdrag blir ikke offentlig publisert.

## Kildekrav og begrensninger

Bare HTTPS-artikkeladresser på www.nrk.no og www.bomlo.kommune.no tillates.
Ingen videresending, private/reserverte IPv4-adresser, proxy, URL-parametre eller
påloggingsinformasjon tillates. cURL binder til kontrollert DNS-adresse og
validerer TLS. Maksimalt 2 MB HTML og 24 KB tekst. DOMDocument leser én entydig
articleBody/article/main; uklare, korte og for lange tekster stopper prosessen.
Enkelte artikkelmaler vil derfor trenge manuell behandling. Feilsider på HTTP
200 kan ikke utelukkes bare med HTML-parseren; kontrollpasset må også vurdere
om teksten gjelder riktig sak. Ingen omgåelse av betalingsmur eller innlogging.

Generering skjer ved knappetrykk, ikke periodisk eller automatisk ved RSS-import.
Ingen opplesning, lydproduksjon, playout, WordPress-publisering eller OneDrive-synk
er koblet til denne endringen. Teksteksporten fra Sending kan lagres i eksisterende
OneDrive-mappe for Manus & Stikk; den gjør ingen automatisk filoverføring.

## Utrulling

Bygger på feature/studio-control-flow d7535359 (PR #17). Ikke deployet.
Denne grenen inneholder tidligere programregister-/sendeforslagsarbeid som
ikke er godkjent for full utrulling. Ikke last opp hele grenen over aktivt Studio.
Avstem selektivt board.php og sending.php mot aktiv kode og installer news-script.php
sammen med disse, etter sikkerhetskopi av eksisterende filer. Programregisteret
er en avhengighet i utviklingsbasen og må avstemmes separat. Ingen private data
eller konfigurasjonsfiler inngår i endringen.

Krever eksisterende producer_request/OpenAI-konfigurasjon, PHP cURL og DOM.
En generering bruker to modellkall; ny kontroll bruker ett. Kjøres bare av
eksisterende produsentroller med CSRF- og revisjonskontroll. Interne lagrings-
handlinger kan ikke sendes direkte fra nettleseren.

Mockede tester dekker kildebegrensninger, parsing, manglende kildebelegg,
ugyldige svar, utløp, endringer, manuell godkjenning og samtidighet på PHP
8.2/8.4. Ekte kildehenting, kostnad/ventetid og AI-svar må kontrolleres i
driftsmiljøet før publisering. Testene påstår ikke at AI-faktasjekk er feilfri.

## Separat patch

Se [NEWS-RELEASE.md](NEWS-RELEASE.md) for avhengighetskart, låst patchbygger,
negative pakkekontroller og konkret sperre mot uavklart Uniweb-utgangspunkt.
Artefakten `news-scripts-pr18-review-only` er for offline avstemming, ikke direkte
opplasting. Den tar ikke med programregister, PR #15/#16 eller private data.

