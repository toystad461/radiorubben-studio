# WordPress-mål for isolert VPS-test

## Formål og avgrensning

Første kodeleveranse etter bekreftet Studio-innlogging 16.10.2026. Grunnlaget
prioriterer en komplett kampreise. Et nødvendig forarbeid er at Studio ikke
har produksjonsadresser innebygd i hver WordPress-transport.

Denne endringen samler målvalget for innlegg, statuskontroll, redaksjonskø,
bilderegister og tilkoblingskontroll. Testprofilen er **bare lesende**.
Den oppretter ingen tilgang, åpner ikke nettverk og aktiverer ingen integrasjon.
Den er ikke en ferdig kampreise eller en utrulling til VPS/produksjon.

Base: main `96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60`, separat gren
`feat/wordpress-test-destinations`. Infrastrukturen fra draft
[PR96](https://github.com/toystad461/radiorubben-studio/pull/96) er ikke tatt inn
som kodeavhengighet. Ingen merge til automatisk produksjonsdeploygren.

## Implementert

- Produksjon beholder eksisterende konfigurasjonsformat og mål.
- Test krever både et servervalgt testmiljø og et uttrykkelig testmål i privat
  WordPress-konfigurasjon. Feil eller ufullstendig konfigurasjon stanser kallet.
- Bare den avtalte HTTPS-adressen og `/wordpress/`-prefikset tillates i test.
  Ingen fri URL fra nettleser, sak, API-svar eller spørring kan velge server.
- VPS-vertsnavnet `vps-5363103` avviser produksjonsprofil også hvis testflagget
  mangler. Dette supplerer serverens eksisterende nettverksisolasjon.
- Testens POST-operasjoner blokkeres både ved transportgrensen og før
  publiseringsflyten leser/endrer saken, henter bilder eller reserverer levering.
- HTTPS-verifisering, redirectforbud, tidsgrenser og svargrenser er bevart.
- Returlenker og mediefiler må høre til gjeldende miljø og korrekt prefiks.
  Produksjonssvar kan ikke bekrefte en testoverføring. Testkvitteringer omtales
  som WordPress-test, ikke som publisering på radiorubben.no.
- Tilkoblingskontrollen viser målet og testprofilens lesebegrensning. Manglende
  tilgang eller målkonflikt gir en forklaring før nettverkskall.

## Privat konfigurasjon ved senere testutrulling

Sett `wordpress_environment` til `test` i testserverens eksisterende private
`config/local.php`, eller sett `STUDIO_WORDPRESS_ENVIRONMENT=test` i prosessmiljøet.
Miljøvariabelen har forrang. Bare `production` og `test` godtas.

Privat `config/wordpress.php` må i tillegg inneholde:

```php
'environment' => 'test',
'site_url' => 'https://5.249.252.78/wordpress',
```

Eksisterende feltnavn `username` og `application_password` beholdes, men denne
leveransen oppretter eller lagrer ingen verdier. Ikke kopier produksjonstilgang.
Manglende testkonfigurasjon skal fortsatt gi frakoblet status.

VPS-en blokkerer for tiden PHP-nettverk og WordPress-applikasjonspassord.
Denne koden skal ikke brukes som grunn til å fjerne disse sperrene. En senere
intern forbindelse trenger et avgrenset transport-/tilgangsoppsett og egne
isolasjonsprøver. Ingen serverkonfigurasjon er endret i denne leveransen.

## Kontroller

Lokal PHP 8.3.30: full `composer test` består, med pakking og eksklusjon av
private data. Nye målprøver kontrollerer faktiske transportinnganger med feil
miljø, manglende konfigurasjon, uventede ruter, manipulerte lenker og testskriving.
Syntetisk statusavstemming kontrollerer korrekt testmål, bevarte manuelle tekster,
godkjenning og uendret lagring ved produksjonssvar. Diagnostikksiden prøves med
administrator/gjest/observatør, feil CSRF, feil mål og manglende tilgang.

Ingen ekte WordPress-HTTP-forbindelse er brukt av disse testene. PHP 8.2/8.4 og
de eksisterende nettleserkontrollene kjøres i CI etter opprettet PR. Lokale grønne
tester er ikke en kvittering for installasjon eller komplett kampreise.

Cloud Browser 16.10: eieren skrev testpassordet i sikker innlogging; agenten
bekreftet `/control.php` som Thomas — TEST, Administrator. CRM og Nyhetsdesk åpner
med TESTMILJØ-merke og tomme testregistre. Nyhetsdesk viser forventet manglende
WordPress-tilkobling. Ingen innholdsendring eller utsending ble utført.

## Neste avgrensede leveranse

Avstem og hent den faktiske Fotballrobot-koden før installasjon i test. Avlest
web-main `c349fc24dd8e6df588755680f1e16c87bf281204` inneholder avstemningssimuleringen
fra PR90, men ikke hele aktive Fotballrobot-pluginen. Bevar eksisterende
simuleringsregler og skill kampkilde, speaker, stemmer, jobb og referatstatus.
Prøv gjenoppretting av testdata og en intern leseforbindelse før testskriving
eventuelt bygges. Produksjonens bilde-/kategori-ID-er skal aldri gjenbrukes som
testdata. Videre flyt må fortsatt kreve menneskelig redaksjonell godkjenning.

Tilbakeføring av denne kodeleveransen: tilbakefør det avgrensede filsettet til
den registrerte basen. Ingen data-/databaseskjemaendring inngår. Eksisterende
testisolasjon og private kontoer skal bevares.
