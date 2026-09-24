# AzuraCast TEST – fase 1

## Arkitektur og avgrensning

Bygget i det eksisterende PHP/Composer-repoet `toystad461/radiorubben-studio`,
fra `main` ved `f3f43f761fe5e7b480bbd81369324b1aaa29bf5b`.
Den lokale mappen `~/RadioRubben/radiorubben-studio` hadde mange ukommitterte
endringer og en eldre main. Disse er ikke overskrevet eller tatt inn i denne branchen.
Integrasjonen ligger i en separat Git-arbeidskopi på `codex/azuracast-readonly-test`.

Eksisterende `app/bootstrap.php` håndhever venteside, HTTPS, konfigurasjonsvalidering
og sesjon. Den kjøres uendret først på `/azuracast-test.php`. Testflagget åpner
aldri ventesiden. Siden krever i tillegg Entra-modus og en gyldig eksisterende
Studio-sesjon; den er ikke tilgjengelig i offentlig demo. Ingen nye autentiseringsveier.
Ingen endringer i produksjonskonfigurasjon, releaseversjon eller publiseringsflyt.

`app/integrations/AzuraCastClient.php` bruker prosjektets eksisterende cURL-utvidelse.
Ingen nye Composer-avhengigheter eller JavaScript-bygg. Koden inkluderes på samme
måte som eksisterende PHP-integrasjoner. Trafikken er PHP → AzuraCast; nettleseren
mottar bare rendret HTML. Ingen API-nøkkel, rå JSON, stream- eller bilde-URL sendes
fra klienten til nettleseren. Testvisningen henter kun ett offentlig Now Playing-svar
per sideåpning, uten polling eller avspilling.

Leveransen omfatter:

- `station()` og `nowPlaying()` uten autentiseringsheader.
- Sang, kort historikk, samlede lyttertall, online-status og live DJ i testsiden.
- `playlists()`, `media()` og `queue()` som eksplisitte, autentiserte lesemetoder på
  serveren. De er ikke eksponert som nettleser-API eller kalt av testsiden.
- Syntetiske eksempeldata når baseadressen er tom. Administrative lister er da tomme.
- Feil fra en konfigurert server vises som feil (HTTP 502), aldri som eksempeldata.
- TLS-verifikasjon, 2 sekunders tilkoblingsgrense, 5 sekunders totalgrense,
  2 MiB svargrense, JSON/strukturkontroll og ingen omdirigeringer.
  Store mediebibliotek trenger senere en eksplisitt paginert løsning; denne fasen
  avviser store svar. Ingen automatiske gjentakelser eller bakgrunnskall.

## API-kartlegging (kontrollert 24. september 2026)

Kilder: [offisiell API-veiledning](https://azuracast.com/docs/developers/apis/),
[Now Playing](https://azuracast.com/docs/developers/now-playing-data/),
[offisiell Swagger](https://azuracast.com/api/) og
[OpenAPI-kilden som Swagger bruker](https://github.com/AzuraCast/AzuraCast/blob/main/web/static/openapi.yml).
Den offentlige dokumentasjonen beskriver Rolling Release. Kontroller alltid
`http://127.0.0.1:8085/api` på den faktisk installerte testversjonen før neste fase.
Dette er dokumenterte ruter, ikke et løfte om uendret kontrakt på alle versjoner.
`{station_id}` kan være numerisk ID eller stasjonens shortcode.

### Offentlige lesekall

Disse er eksplisitt merket `security: []` i OpenAPI og får aldri vår API-nøkkel.
Tilgjengelige stasjoner avhenger av innstillingene på AzuraCast-instansen.

| Metode og sti | Innhold | Fase 1 |
| --- | --- | --- |
| `GET /api/stations` | Offentlig stasjonsliste | Kartlagt |
| `GET /api/station/{station_id}` | Stasjonsinformasjon | `station()` |
| `GET /api/nowplaying` | Samlet offentlig Now Playing | Kartlagt |
| `GET /api/nowplaying/{station_id}` | Stasjon, sang, `song_history`, `listeners`, `live`, `is_online` | `nowPlaying()` og testside |

`listeners.total` er samlet, ikke-unikt lyttertall; `unique` er unike lyttere.
Det eldre `current` brukes kun som reserve når `total` mangler. `live.is_live`
angir tilkoblet live DJ, mens `is_online` angir streamstatus. Fravær av live DJ
beviser ikke i seg selv at AutoDJ spiller. Kort historikk er `song_history` i
Now Playing-svaret; lengden styres av AzuraCast. `now_playing` kan være null.
Now Playing kan være mellomlagret og er ikke presis avspillingssynkronisering.

### Autentiserte lesekall

| Metode og sti | Innhold | Fase 1 |
| --- | --- | --- |
| `GET /api/station/{station_id}/history?start=…&end=…` | Detaljert historikk/rapport | Kartlagt, ikke kalt |
| `GET /api/station/{station_id}/listeners` | Detaljer om enkeltlyttere | Kartlagt, ikke samlet inn |
| `GET /api/station/{station_id}/playlists` | Spillelister | `playlists()` |
| `GET /api/station/{station_id}/files` | Mediebibliotek | `media()` |
| `GET /api/station/{station_id}/queue` | Kommende AutoDJ-kø | `queue()` |
| `GET /api/station/{station_id}/playlist/{id}/queue` | Intern avspillingskø i spilleliste | Kartlagt |
| `GET /api/station/{station_id}/status` | Status for kringkastingstjenester | Kartlagt |

Disse rutene arver API-autentisering. GET betyr ikke nødvendigvis offentlig.
Bruk `Authorization: Bearer …` server-side. Nøkkelen arver AzuraCast-brukerens
rettigheter; begrens brukeren til teststasjonen og nødvendige rettigheter.
Klienten er lesende, men det gjør ikke selve API-nøkkelen til en read-only-nøkkel.
Ingen nøkkel trengs for den første Now Playing-testen.

### Kontroller – bare kartlagt, ikke implementert

| Metode og sti | Effekt |
| --- | --- |
| `POST /api/station/{station_id}/backend/skip` | Hopp over gjeldende AutoDJ-spor |
| `DELETE /api/station/{station_id}/queue/{id}` | Fjern én kommende køoppføring |
| `DELETE /api/station/{station_id}/playlist/{id}/queue` | Nullstill intern kø for stokket, sangbasert spilleliste |
| `PUT /api/station/{station_id}/files/batch` | Mediehandlinger, inkludert kø/umiddelbar avspilling |
| `POST /api/station/{station_id}/files` | Last opp media |

Batch-endepunktets OpenAPI-beskrivelse er ufullstendig for request body.
Den [offisielle BatchAction-implementasjonen](https://github.com/AzuraCast/AzuraCast/blob/main/backend/src/Controller/Api/Stations/Files/BatchAction.php)
skiller `do=queue` fra `do=immediate`. Payload og køsemantikk må verifiseres på
valgt installasjon før en kontrollfase; det finnes ikke et dokumentert generelt
`POST /queue` i spesifikasjonen som ble kontrollert. `GET …/file/{id}/play`
laster ned/spiller filen til klienten og betyr **ikke** «spill denne på radio nå».

Neste kontrollfase trenger separat autorisasjon, CSRF, eksplisitt teststasjon,
handlingstilgang og integrasjonstester. Ingen TTS-opplasting, køendring, skip,
start/stopp eller LIVE-encoder er en del av denne leveransen.

## Konfigurasjon

Bruk miljøvariabler eller ignorert `config/local.php` utenfor offentlig webrot.
Eksempler finnes i `config/example.php`. Det leses ingen `.env`-fil automatisk.

| Miljøvariabel | Konfigurasjonsnøkkel | Standard |
| --- | --- | --- |
| `AZURACAST_TEST_ENABLED` | `azuracast_test_enabled` | `false` (bare miljøverdien `1` aktiverer) |
| `AZURACAST_BASE_URL` | `azuracast_base_url` | Tom = eksempeldata |
| `AZURACAST_STATION_ID` | `azuracast_station_id` | `1` |
| `AZURACAST_API_KEY` | `azuracast_api_key` | Tom |
| `AZURACAST_ALLOW_LOCAL_HTTP` | `azuracast_allow_local_http` | `false` (bare `1` aktiverer) |

Baseadressen skal være serverens origin, uten `/api`, sti, brukernavn, passord,
spørring eller fragment. HTTPS er standardkravet. Eksplisitt lokal HTTP tillates
bare for `localhost`, `127.0.0.1`, `[::1]` og `host.docker.internal`.
Adressen og stasjonen kommer kun fra serverkonfigurasjon, aldri URL-parametre.
Ikke bruk lokal-HTTP-innstillingen på et delt webhotell.

## Første lokale test uten AzuraCast

1. Arbeid i utviklingsbranchen. Kjør `composer install`.
2. Sett lokal Entra-konfigurasjon i `config/local.php` eller miljøet. Bruk en
   tildelt testbruker og registrert redirect URI `http://localhost:8080/auth/callback.php`.
   Sett `base_url` til `http://localhost:8080`. Ingen endringer på produksjonsserveren.
3. Start **bare lokalt**:

```sh
STUDIO_SITE_MODE=app STUDIO_AUTH_MODE=entra AZURACAST_TEST_ENABLED=1 \
  php -S 127.0.0.1:8080 -t public
```

4. Logg inn på `http://localhost:8080/login.php`, og åpne deretter
   `http://localhost:8080/azuracast-test.php`. Innloggingen går til vanlig Studio;
   åpne testadressen igjen etter innlogging. Siden viser «EKSEMPELDATA» når
   AzuraCast-adressen ikke er konfigurert.

Uten lokal Entra-konfigurasjon kan klienten kontrolleres med testpakken under.
Det finnes ingen offentlig demo-bypass til testsiden.

## Koble til lokal AzuraCast på MacBook senere

Docker/AzuraCast installeres ikke av denne endringen.
Følg [offisiell Docker-installasjon](https://azuracast.com/docs/getting-started/installation/docker/).
Docker Desktop omtales i [utviklerveiledningen](https://azuracast.com/docs/developers/getting-started/).
[Installasjonsoversikten](https://azuracast.com/docs/getting-started/installation/)
oppgir ARM64-støtte, men kravsiden har også en eldre M1-advarsel. Verifiser derfor
at valgt image-versjon faktisk starter på den aktuelle MacBooken før kompatibilitet
regnes som testet. Ingen fysisk Mac/Docker-installasjon er verifisert i denne fasen.

1. Sett opp en separat AzuraCast-instans og stasjon «Radio Rubben TEST». Velg en
   ledig webport, for eksempel `8085`, siden lokal Studio bruker `8080`.
   Bind publiserte Docker-porter til loopback for denne testen, og kontroller den
   ferdige portkonfigurasjonen. Ingen tunnel, DNS-endring eller portvideresending.
2. Åpne `http://127.0.0.1:8085/api` og bekreft installasjonens API-versjon.
   Finn teststasjonens ID/shortcode og kontroller `/api/nowplaying/1`.
   Last senere inn noen få testfiler og aktiver AutoDJ for realistisk historikk.
3. Start Studio lokalt med den samme Entra-konfigurasjonen som over:

```sh
STUDIO_SITE_MODE=app STUDIO_AUTH_MODE=entra AZURACAST_TEST_ENABLED=1 \
AZURACAST_BASE_URL=http://127.0.0.1:8085 AZURACAST_STATION_ID=1 \
AZURACAST_ALLOW_LOCAL_HTTP=1 \
  php -S 127.0.0.1:8080 -t public
```

4. Åpne testsiden etter innlogging. Kontroller stasjonsnavn, sang, lyttertall,
   historikk og overgang mellom offline/online og live DJ. Stopp AzuraCast og
   bekreft at siden viser feil, ikke eksempeldata.
5. Bare hvis administrative lesemetoder skal prøves: opprett en nøkkel for en
   begrenset bruker på testinstansen. Lagre den privat i `config/local.php` eller
   servermiljøet. Ikke legg nøkkelen i kommandolinjehistorikk, Git, URL eller JS.

Hvis PHP senere kjøres i en egen Docker-container, peker `127.0.0.1` til den
containeren. Bruk et avtalt Docker-nettverk/HTTPS eller en verifisert
`host.docker.internal`-tilkobling; vertens loopback-binding kan kreve tilpasning.
Når Studio kjører på Uniweb, peker localhost til Uniweb, ikke MacBooken.
Denne fasen kobler derfor ikke produksjons-Studio til hjemmemaskinen.

## Tester og pakking

```sh
composer validate --strict
composer audit
composer test
php scripts/package.php
```

`composer test` kjører eksisterende tester og `tests/azuracast.php`. Det bruker
syntetiske svar, en lokal falsk AzuraCast-server og midlertidige PHP-sesjoner.
Portene `8197` og `8198` må være ledige. Ingen ekte AzuraCast-, Microsoft- eller
produksjonskall utføres av testene. Testsesjonene opprettes utenfor webrot og slettes.

Testene dekker rutene/headerne, mock-modus, URL/header-injeksjon, HTTP-feil,
ugyldig JSON, null sang, tom historikk, HTML-escaping, svarstørrelse, omdirigering,
tidsavbrudd, avslått flagg, aktiv venteside, demo-avvisning, utløpt sesjon og
Entra-sesjonskrav. En faktisk Entra-innlogging og faktisk AzuraCast-kompatibilitet
må fortsatt prøves manuelt på den lokale installasjonen.

Pakkeskriptet inkluderer klient, privat fixture og testside i eksisterende
public/private-struktur. Lokal konfigurasjon og testsesjoner følger ikke med.
Å bygge en pakke publiserer ingenting. Denne branchen skal ikke merges eller
produksjonsdeployes som del av denne testen.
