# Selektiv aktivering av nyhetsflyt – 04.10.2026

## Resultat

Brukeren ba «Aktiver». Nyhetsflyten er aktivert på Uniweb/Studio, men levering til WordPress er blokkert av den eksisterende WordPress-legitimasjonen. Ingen artikkel er publisert. PR #23 er fortsatt åpen som draft; ingen merge er utført.

Eksakt endelig runtime-kilde: `f0cbc59f348978daf1532fed93ad8deee8b15979` på `feat/news-publication-profile`. Full CI besto på denne commiten: [37229833327](https://github.com/toystad461/radiorubben-studio/actions/runs/37229833327). PHP 8.2/8.4, JavaScript, Composer og pakking er grønne. Den første åtte-filers aktiveringen var fra `fa8fed8cc7760fd06e8c9bf1fdbd9178a28a0c61` (CI 37220359542); oppfølgingen flytter sakssidens JavaScript/stiler og bilde til samme origin.

## Avstemming og sikkerhetskopi

Ferske app- og public-kopier ble hentet fra Uniweb før endring. Seks eksisterende filer som skulle erstattes samsvarte med PR #19-basen `049b42591e525055232503e4710787ad09b62139`; de to nye hjelperne manglet. Avhengighetene ble avstemt mot kildecommit. Seks andre aktive avvik ble bevisst bevart: `app/bootstrap.php`, `app/views/head.php`, `app/views/sidebar.php`, `public/login.php`, `public/local/login.php` og `public/assets/radio-rubben-logo.png`.

Privat backup: `/run/webroots/r1417157/studio-private/rollout-news-20261004-pr23/`, med `app/` og `studio-public/`. Dette er to sekvensielle mappekopier før kodeskriving, ikke en atomisk kopi av alle produksjonsdata. Config, nøkler, brukere og historikk er ikke kopiert til Git. Ingen konfigurasjon, innlogging eller sikkerhetsheadere ble endret.

Kun 11 runtime-filer er endret/opprettet. `app/*` går til `studio-private/app/*`; `public/*` går til `studio-public/*`. Public-PHP bruker samme include-omskriving som `scripts/package.php`. Ingen hel gren eller standardpakke ble lastet over Studio.

## Observasjoner og rettelser under utrulling

Kodeeditorens første innliming beholdt gammel tekst etter ny tekst i seks eksisterende filer. Råbyte-kontrollen oppdaget dette; filene ble erstattet korrekt og lastet ned på nytt. Alle sluttfiler samsvarer med forventede SHA-256-hasher. Det påstås ikke en utrulling uten avbruddsrisiko.

Innlogget UI avdekket deretter at eksisterende `default-src 'self'`, `style-src 'self'` og `img-src 'self'` blokkerte inline-script, inline-stil og WordPress-bildet på sakssiden. Rettelsen flytter JavaScript til `assets/case.js`, CSS til `assets/case.css` og forhåndsvisningsbildet til `assets/radio-rubben-nyheter.png`. PHP leverer escaped startdata som data-attributter. CSP er uendret. Side- og flyttestene er oppdatert. Ressursene ble lagt inn før sakssiden ble erstattet.

## Live kontroll

- Innlogget som administrator: Kontrollsenter og eksisterende sendeliste åpnet normalt.
- Bømlo kommune, NRK Vestland Toppsaker/Siste nytt, trafikk og vær viste oppdaterte data. Dette er faktisk Studio-henting; den tidligere begrensningen i søkeverktøyet er ikke omgått.
- Den eksisterende saken `f318942e42701255` ble bevart. Ny prøvesak `37115a841ef21a07` ble lagt til fra Bømlo kommunes «Hjelp oss å spara energi i kommunale bygg».
- Originalhenting og totrinns klargjøring kjørte i Studio: radiomanus og nettutkast ble lagret. Nyhetsbilde og Nyheter/Lokale Nyheter vises. Kildekontrollen markerte formuleringer for gjennomgang; ingen sluttgodkjenning ble gitt.
- Ett forsøk på «Overfør bare som WordPress-kladd» ga uavklart levering. Ingen automatisk eller blind gjentakelse ble gjort.
- Studios eksisterende `/wordpress-check.php` viste HTTP 401, transportfeil 0, ingen redirect og REST-kode `incorrect_password` for begge lesende kontroller. Passordet ble ikke lest eller endret.
- Autentisert WordPress-kontroll via den separate tilkoblingen fant ingen innlegg med slug `studio-37115a841ef21a07` (status=any), og heller ingen treff på prøvens tittel/emne. Studio-saken står fortsatt med sperret/uavklart levering. Den tidligere kladden 1100 er ikke opprettet av denne Studio-testen og ble ikke endret.
- Ingen offentlig publisering, sendegodkjenning, TTS, cron, OneDrive-overføring, temaendring eller endring i andre grener/repoer.

## Neste nødvendige steg

Få rettet Studios WordPress-applikasjonspassord gjennom en sikker administratorflyt; ikke legg legitimasjon i chat/Git. Kontroller tilkoblingen på nytt. Avklar og rekonstruer prøvesakens leveringsstatus kontrollert etter WordPress-oppslag, før et nytt utkastforsøk. Ikke fjern leveringssperren blindt. Bekreft deretter faktisk WordPress-ID, draft-status, media 1079 og kategorier 8/27. Redaksjonen må gjennomgå tekst og kontrollmerknader før eventuell manuell sluttgodkjenning. Vellykket overføring eller offentlig publisering er ikke påstått testet.

## Tilbakeføring

Gjenopprett de seks tidligere eksisterende filene fra backupens tilsvarende `app/` og `studio-public/` til samme aktive stier. Gjenopprett public-filene som lagrede serverbytes, uten ny include-omskriving. Kontroller før-hashene nedenfor. De fem nye, deretter ubrukte hjelpe-/ressursfilene kan beholdes til opprydding er godkjent; ikke gjenopprett eller nullstill config eller sendelisten. Bevar prøvesakens historikk og uavklarte levering. Kontroller innlogging, desk og eksisterende sak etter tilbakeføring.

## SHA-256 på rå serverbytes

Endelig app-nedlasting og siste public-nedlasting ble sammenlignet med pakkede kildefiler og før-kopiene. Ingen uventede endringer eller manglende filer i app/public utenfor de 11 oppgitte filene.

| Kildesti | Før | Etter |
| --- | --- | --- |
| `app/board.php` | `e35bb03c45db9df2aa858e04bbb0632e16fd2762acb784b80ba6753cfe213af8` | `aefe4af163ede3cb0e6abbb5d925a85b27e940c7e8501130576640258a0a3aea` |
| `app/case-workflow.php` | `169198608e182c0ae662945b00e78f67352d9a892a4c5ab4bdd4d96d238c24a7` | `87f3ab81bce5cd2fa925378fc874818c77bb533383b447371e4422f19bccedeb` |
| `app/integrations/NewsDesk.php` | `fe1ea9e1fcf8e6235164d3df41aa36f5600a3301a70d90ec9d8b67217ed1d552` | `d379593ff231196681aedbe594f3e8d15e50f283137d890ea83089c65692f23a` |
| `app/news-publication.php` | Ny fil | `37ab6f88138a53b95c4d0237284597436ff856d680d5a81b86b74784c3354ee7` |
| `app/source-identity.php` | Ny fil | `8d9d8c8b8d4b5e865b76edb2a0f94a23284545650b26f7e149ab09a183118a85` |
| `app/web-publish.php` | `e24e2dd90151d0e1177ae5acb9789efdfcea44c5f96306d713fd74f0706e0ffa` | `93051860561d1bd38ded2eca827b9e8138da438322f8e8d57f87e9238490c980` |
| `public/assets/case.css` | Ny fil | `28db40a4995d4f33f947a0c887b5ccfa7ea32ddb8ed711bdbee6f72a4169001c` |
| `public/assets/case.js` | Ny fil | `f8fe8191d521f44be70e2d541c0c6dcfb4f033567b025facd3b372b791cdd2a6` |
| `public/assets/radio-rubben-nyheter.png` | Ny fil | `45d404e78485cb8c018d164de7ac3c12b7cf7a992854040e0b7455d8a82c6ad6` |
| `public/case.php` | `ef87658ad41cc55575dfc53c5f3319c7df8ed81d362cfcd935c566d72f690945` | `e83f4b838ae36c27479a39bc776c2bc83dab470abde1af0b91af3ac9cc30c14f` |
| `public/newsdesk.php` | `e04148147c0f25ada4289436c12f87734fc992a7072167ff51c279451df7f1c8` | `507fc9a901620ce34f9e0b9c6ec35ae0456267aa3288c03ded4653db4b78df09` |
