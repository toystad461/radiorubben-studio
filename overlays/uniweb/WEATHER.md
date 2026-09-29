# Væroversikt for Studio

Status: kodeutkast, ikke publisert. Bygger på PR #15 (`ab7cf368563f377435f9b03f855eb9b10a784469`).

## Bruk

Åpne «Været» i menyen eller lenken fra værkortet i Nyhetsdesken. `/weather.php` krever eksisterende Studio-innlogging; også observatør kan lese. Fem faste steder: Bømlo (Bremnes/Svortland), Stord (Leirvik), Haugesund, Bergen og Stavanger.

Kolonnene viser nærmeste prognosetime (maks 30 min unna), i dag kl. 12 og i morgen kl. 12 i Europe/Oslo. Dagskolonnene er ikke døgnmaks. Etter kl. 12 merkes tidspunktet som passert, eller manglende hvis modellen ikke lenger inneholder det. Temperatur gjelder ett tidspunkt; symbol og nedbør gjelder de neste 1/6 timene. Vind og nedbør vises med «Vind og nedbør».

Fullskjermknappen vises når nettleseren støtter det. Visningen oppdateres manuelt. Etter 15 minutter vises en advarsel om at siden må oppdateres. Klokken viser norsk tid.

«Lag værstikk» åpner et ferdig regelbasert temperaturutkast fra samme datasett, uten AI-kall. Eldre data og passerte middagstidspunkter utelates fra opplesingsteksten. Rediger/kopier og lim inn i Sending; redigering i værfeltet lagres ikke automatisk. Manuell arkivering: OneDrive → Manus & Stikk. Ingen OneDrive-integrasjon eller automatisk publisering.

## Data og drift

MET Locationforecast compact, serverbasert HTTPS, identifiserende User-Agent, gzip, svargrense 2 MB, 3 s tilkobling / 6 s total timeout per sted. Faste koordinater, ingen brukerbestemt URL eller filsti. Fem sekvensielle førstegangskall kan gi inntil 30 sekunders lastetid ved nettfeil. Hvert sted feiler isolert. Omdirigeringer avvises; endret API-adresse må kontrolleres før oppdatering.

Privat cache: `studio-private/config/weather-overview-{sted}.json`, 0600. Låsing, Expires og If-Modified-Since, minst 10 minutter mellom kall og tilfeldig forsinkelse. Retry-After respekteres. Ingen nettleserkall til MET. Lastemeldingen og kildedato vises; 304 oppdaterer sjekktid, ikke opprinnelig hentetid. Modell eldre enn 12 timer eller nettfeil gir «Eldre data»; eldre enn 24 timer skjules. Ingen oppdiktede reservetall.

Kildedata krediteres Meteorologisk institutt med CC BY 4.0-lenke. Symbolene er lokal emoji-visning med norsk tekst, ikke Yr-logoer.

Referanser kontrollert 29.09.2026:
- https://api.met.no/doc/TermsOfService
- https://api.met.no/weatherapi/locationforecast/2.0/documentation

## Selektiv utrulling

Nye produksjonsfiler:
- `studio-private/app/integrations/WeatherOverview.php`
- `studio-public/weather.php`
- `studio-public/assets/weather-overview.css`
- `studio-public/assets/weather-overview.js`

Eksisterende filer: legg til bare menylenken i aktiv `sidebar.php` og værlenken i aktiv `newsdesk.php`. Sammenlign med faktisk Uniweb-kode først. PR #14 har blant annet Musikkontroll, mens PR #15 har Sendeforslag; ikke overskriv aktiv meny med denne grenens hele fil. Ingen full deploy av main/standardpakken. Bevar privat konfigurasjon, cache og manusdata.

Før publisering: kontroller MET-kall fra Uniweb, privat cache-skrivetilgang, aktiv innlogging, desktop/mobil og fullskjerm. Ekte MET-kall fra driftsmiljøet er ikke verifisert i denne endringen.

## Tester

`php overlays/uniweb/test-weather-overview.php` dekker validering, manglende data, norske datoer, sommertid, årsskifte, cache/304, feil/backoff, ukjent sted og utelatelse av gamle tall fra manus. `test-weather-page.php get|guest|method` kontrollerer sidegjengivelse med observatør, innloggingskrav og metodeavvisning uten nettverk. Native PHP 8.2/8.4 kjører i CI. Lokalt testet med PHP WASM.
