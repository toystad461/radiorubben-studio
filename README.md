# Radio Rubben Studio – PHP-utgave

Norsk, responsivt kontrollsenter for `studio.radiorubben.no`, tilpasset vanlig PHP-webhotell. **Ingen Node.js, npm eller JavaScript-bygging kreves.** GitHub er fortsatt kode-master, og repoet skal være privat.

## Venteside

Ventesiden er standard og stopper alle PHP-innganger før sesjon eller innlogging startes. Den viser bare offentlig informasjon og en lenke til hovednettsiden. Kontrollsenteret er bevart. For lokal utvikling kan `STUDIO_SITE_MODE=app php -S 127.0.0.1:8080 -t public` brukes. På produksjon skal `site_mode` først settes til `app` når HTTPS, PHP og Entra-tilgang er kontrollert, sammen med `auth_mode=entra`. Ingen URL-parameter åpner demoen. Ventesiden ble publisert 23. september 2026 ved å oppdatere `studio-private/app/bootstrap.php`; den bruker eksisterende logo og CSS. Offentlige PHP-innganger er kontrollert og svarer med ventesiden og HTTP 503.

## Status

- Kontrollsenter med samme utseende som første versjon, nå rendret med PHP.
- Offentlig venteside som standard. Demoen er bevart for lokal utvikling og må aktiveres eksplisitt.
- Microsoft Entra ID-innlogging via OpenID Connect, med tenant-avgrensing, PKCE, state, nonce, signaturkontroll og serverbaserte sesjoner.
- OneDrive og WordPress er forberedt som grensesnitt, uten aktive API-kall.
- Ingen ekte credentials er lagt inn. Ventesiden er publisert på Uniweb. Domenene er separert: hovednettsiden bruker roten og Studio bruker studio-public. PHP-oppgradering og gyldig HTTPS for Studio gjenstår.
- Reell Microsoft-innlogging og kjøring på Uniweb må testes etter konfigurasjon.

## Krav

PHP 8.2 eller nyere med curl, json, openssl og session. Bruk en støttet PHP-versjon hos Uniweb, gjerne 8.4. Composer brukes bare til å hente PHP-bibliotekene; det kreves ikke på webhotellet når `vendor/` følger opplastingspakken. PHP-sesjoner må ha en skrivbar lagringsplass utenfor offentlig webrot.

## Lokal oppstart

Hvis PHP og Composer er installert:

```sh
cd ~/RadioRubben/radiorubben-studio
composer install
php -S 127.0.0.1:8080 -t public
```

Åpne http://localhost:8080. PHPs innebygde server er kun for lokal utvikling. Den gamle Next.js-serveren på port 3000 brukes ikke av denne versjonen.

## Kontroller og ferdig opplastingspakke

```sh
composer validate --strict
composer audit
composer test
php scripts/package.php
```

Pakkeskriptet trenger PHP-utvidelsen zip. GitHub Actions kjører kontrollene med PHP 8.2 og 8.4 og lager artefakten `uniweb-package`. Den inneholder `radiorubben-studio-uniweb.zip` med alle PHP-avhengighetene, men aldri `config/local.php`. Last ned artefakten fra den grønne kjøringen under **Actions** i det private repoet.

## Uniweb: egen mappe før opplasting

**Ikke last opp i mappen som brukes av hovednettsiden.** Skjermbildene viste samme mappe for `radiorubben.no` og `studio.radiorubben.no`. Avklar et separat nettsted før Studio sin dokumentrot endres. Ved forsøk 23. september 2026 endret mappevalget i Studio-innstillingene også hoveddomenets mappe fordi begge var knyttet til samme nettsted. Endringen ble reversert og begge viser igjen `/r1417157/studio`. Ikke gjenta dette før nettstedene er skilt i kontrollpanelet eller av Uniweb support.

Filene er plassert i `/r1417157/studio-public` og `/r1417157/studio-private`. Midlertidige opplastingsarkiver ligger i den beskyttede private mappen. Uniweb viste PHP 8.1 og manglende gyldig HTTPS for Studio ved kontroll; disse må avklares før publisering regnes som fullført. Ingen SSL-bestilling er gjort.

1. Pakk ut arkivet. Last opp begge mappene `studio-public/` og `studio-private/` ved siden av hverandre i webhotellets rot, uten å overskrive hovednettsidens filer.
2. Sett dokumentroten for `studio.radiorubben.no` til **`/r1417157/studio-public`**. Det er bare innholdet her som skal være offentlig.
3. `app/`, `config/` og `vendor/` ligger i `studio-private/`, utenfor offentlig dokumentrot. Denne mappen er også sperret med `.htaccess`. Uniweb tillater bare én undermappe som dokumentrot; pakkeskriptet tilpasser PHP-filbanene til dette. Ikke gi `studio-private/` et domene.
4. Velg PHP 8.4 og kontroller at nødvendige utvidelser er aktive.
5. Test demoen når DNS/hosting og HTTPS senere er avklart. Det er ikke nødvendig å endre hoveddomenet, e-postposter eller navnetjenere.
6. Når Entra-oppsettet er klart, kopier `studio-private/config/example.php` til `studio-private/config/local.php` på serveren og fyll inn verdiene. Begrens filrettighetene til kontoen/PHP-prosessen som trenger den.

Rotens `.htaccess` blokkerer utilsiktet eksponering av prosjektmappen. `public/.htaccess` tillater nettsiden og slår av kataloglisting. Dokumentrot til `public/` er likevel et krav. Hvis Uniweb gir HTTP 500, be support kontrollere tillatte .htaccess-direktiver og dokumentrot; ikke eksponer private mapper for å løse feilen.

## Microsoft-innlogging

Registrer en single-tenant **Web**-app i Entra ID. Redirect URI for PHP-versjonen:

- Lokalt: `http://localhost:8080/auth/callback.php`
- Produksjon: `https://studio.radiorubben.no/auth/callback.php`

Disse erstatter NextAuth-adressene fra den gamle versjonen. Sett **Assignment required** og tildel bare aktuelle medarbeidere/grupper i Enterprise Application.

I `config/local.php`:

- `auth_mode`: `entra` for innlogging, `demo` bare for offentlig statisk demonstrasjon.
- `base_url`: eksakt adresse uten ekstra sti. Produksjon krever HTTPS.
- `tenant_id`, `client_id`, `client_secret`: fra appregistreringen. Ingen verdier skal legges i GitHub eller klientkode.

Tilsvarende miljøvariabler kan brukes: `STUDIO_AUTH_MODE`, `STUDIO_BASE_URL`, `ENTRA_TENANT_ID`, `ENTRA_CLIENT_ID`, `ENTRA_CLIENT_SECRET`.

Entra-modus med manglende konfigurasjon gir HTTP 503 og faller aldri tilbake til demo. Tilgangen sjekkes på serveren. Kun navn og bruker-ID lagres i sesjonen; ingen Graph-tokens lagres. Sesjonen utløper senest ved ID-tokenets utløp eller etter åtte timer. Utlogging krever POST og CSRF-token og avslutter kun Studio-sesjonen.

Før intern bruk: test tildelt og ikke-tildelt bruker, utløpt sesjon, utlogging og feil i callback. Ikke legg interne data inn i demonstrasjonen.

## Struktur og GitHub

- `public/`: offentlig dokumentrot, sider og CSS.
- `app/`: serverkode, innlogging, visningsmaler og integrasjonsgrensesnitt.
- `config/`: eksempel og ignorert lokal konfigurasjon.
- `tests/`: syntaks-, konfigurasjons- og HTTP-tester.
- `scripts/`: lager opplastingspakke uten lokale hemmeligheter.

Repo: https://github.com/toystad461/radiorubben-studio (privat). Den tidligere Next.js-utgaven er bevart i Git-historikken, blant annet commit `edd36fd`. Bruk `git pull --ff-only` før videre arbeid. Commit `composer.lock`; aldri `vendor/` eller `config/local.php`.

Referanser: [PHP lokal server](https://www.php.net/features.commandline.webserver.php), [OpenID Connect-biblioteket](https://github.com/jumbojett/OpenID-Connect-PHP), [Uniweb webhotell](https://www.uniweb.no/hosting/webhotell/).
