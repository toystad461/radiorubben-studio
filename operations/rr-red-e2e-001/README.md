# RR-RED-E2E-001 – isolert Studio–WordPress-test

Utført 2. november 2026 etter uttrykkelig godkjenning av en avgrenset testkobling og egen testredaktør. Behovet var å prøve den eksisterende redaksjonelle flyten helt frem til faktisk WordPress-lagring. Produksjon, Site Kit, L02/avspilling og fotball er utenfor endringen.

## Resultat

- Studio-sak `c1e86b371a2a9b2d`, WordPress-innlegg `4`, status `publish`.
- Testadresse: https://5.249.252.78/wordpress/?p=4 (krever innlogging).
- Studio: https://5.249.252.78/case.php?item=c1e86b371a2a9b2d.
- 24 kontroller gjennom ordinær Studio-innlogging og HTTP-skjemaer besto. Kildekontroll, godkjenningshandlinger, overføring og statuslesing brukte den installerte applikasjonen og faktisk test-WordPress.
- 6 avsluttende HTTP-kontroller etter kodeopprydding besto, inkludert innlogget lesing av WordPress-frontenden. 14 sluttkontroller av innhold, identitet og sperrer besto.
- Nøyaktig ett innlegg finnes for testen. Innhold, ingress, tittel, kilde og KI-merking samsvarer. Gjentatt publisering beholdt innleggs-ID og revisjon. Andre sendelistepunkter ble ikke endret (listen var tom ved oppstart).

## Avgrensning av beviset

Dette er en syntetisk teknisk ende-til-ende-test gjennom HTTP-grensesnittene, ikke en visuell nettleser-/JavaScript-test eller en vurdering av en levende KI-modell. Den syntetiske kilden er fast og tydelig merket. Et deterministisk testsvar kontrollerer eksakt samsvar med kildeavsnittene; den vanlige `studio_news_review`-validatoren håndterer segmenter, belegg og kontrollstatus. Ingen ekte hendelse eller ekstern kildeside fremstilles som kontrollert.

Testverktøyet utførte godkjenningshandlingene som egen TEST-redaktør, etter brukerens autorisasjon. Dette er ikke en påstand om menneskelig redaktørgodkjenning av en virkelig sak. Syntetiske faktaavvik, manglende bekreftelse, endret tekst og gjenbruk av tidligere godkjenning ble avvist. Kildehash, endret kildeadresse, utløpt kontroll og feil rolle ble også kontrollert.

Neste redaksjonelle steg er en reell redaktørprøve av et kildekontrollert artikkelutkast. Ingen slik artikkel er publisert gjennom denne testen. Bildevalg, eksterne KI-tjenester, radio og lyd er ikke del av prøven.

## Installert avgrensning

Test-VPS: `/srv/radiorubben-test`, eksisterende Studio-release `studio-music-fad12a2320de0ff0a036854c414accee794143bb`. Den aktive utgaven avviker fra repositoryets main. Filer under `installed/` er en gjennomgåbar kopi av det avgrensede testoverlegget, ikke nye produksjonsfiler under `app/` eller `public/`.

Studio fikk en separat privat WordPress-konfigurasjon, en testredaktør og en testkilde. WordPress fikk en egen bruker med author-rollen og et separat applikasjonspassord. Hemmelighetene finnes kun i private filer på test-VPS-en og er ikke med i Git eller rapportene.

PHP-FPM beholder `RestrictAddressFamilies=AF_UNIX`. Testforbindelsen bruker en dedikert lokal Unix-sokkel i en katalog med tilgang for rr-studio. Nginx gir TLS med sertifikatkontroll og videresender bare den eksakte WordPress-inngangen til eksisterende test-WordPress. Ingen nettverkstilgang åpnes for PHP, ingen produksjonsadresse kan velges og omdirigeringer følges ikke.

WordPress-utvidelsen gjelder bare staging med den eksakte testadressen. Applikasjonspassord er tilgjengelig bare lokalt og bare for testbrukeren. REST-tilgang begrenses til egne testinnlegg og GET/POST. Den opprinnelige `rr-test-isolation.php` er byteidentisk: `d32cdf4fd93bc7e0405b8fb8d861d640594162f5effad10f1c67f96e26399bcf`. Anonym REST returnerer fortsatt 401. E-post og eksterne WordPress-kall forblir sperret.

Nginx ble kontrollert med `nginx -t` og lastet om uten omstart. Eksisterende radio-/playout-oppsett ble ikke endret. Cron i test-WordPress kan endres av ordinære publiseringshendelser; rapporten hevder ikke uendret test-cron. Produksjonens cron og Site Kit ble ikke berørt.

## Feil som ble avklart

To første kladdforsøk stoppet på nettverkssperren og ble korrekt markert som uavklarte. Direkte databasekontroll bekreftet null innlegg for testbrukeren før hver kontrollert avklaring. Forsøkene ble bevart i sakens leveringshistorikk. Etter innføring av Unix-sokkelen besto hele flyten. Det ble ikke gjort blind automatisk gjentakelse.

## Sporbarhet og tilbakeføring

`deployment-manifest.json` inneholder før-/etterhasher for endelig installasjon. `http-results.json`, `final-http-results.json`, `verification.json` og `wp-isolation.json` inneholder testkvitteringer. `run.py` dokumenterer første gjennomløp; det må ikke kjøres ukritisk på en allerede publisert sak. `verify.php` er lesekontrollen. Testoppsettet krever eksisterende privat konfigurasjon og syntetisk sak; det er ikke en generell automatisk installasjonspakke.

Private originaler ligger på VPS under `/srv/radiorubben-test/rr-red-e2e-001/backup/`. Ved tilbakeføring skal testens private `studio/config/wordpress.php` først tas ut av bruk og testkontoens økter sperres. Deretter kan bare de manifestførte originalfilene gjenopprettes, nye testfiler fjernes og den dedikerte Nginx-konfigurasjonen avvikles med konfigurasjonstest og kontrollert omlasting. Dette er viktig fordi originalkoden har en fast produksjonsadresse. Ikke gjenopprett originale transportfiler mens testens WordPress-konfigurasjon fortsatt er aktiv. Bevar syntetisk innlegg og historikk frem til avtalt opprydding; ingen andre konfigurasjonsfiler eller brukere skal erstattes.

Registrert fra main `96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60` som avgrenset testdokumentasjon. Ingen merge, produksjonsdeploy eller endring i andre aktive utviklingsgrener.
