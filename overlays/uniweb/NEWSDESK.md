# Nyhetsdesk for aktiv Studio-utgave

Et selektivt tillegg til `studio.radiorubben.no` sin aktive PHP-versjon. Behold eksisterende `config/local.php`, brukerdatabaser og AI Studio-filer. Ingen nye hemmeligheter trengs.

| Lokal fil | Uniweb-mål |
| --- | --- |
| `public/newsdesk.php` | `studio-public/newsdesk.php` |
| `public/assets/newsdesk.css` | `studio-public/assets/newsdesk.css` |
| `app/integrations/NewsDesk.php` | `studio-private/app/integrations/NewsDesk.php` |
| `app/views/sidebar.php` | `studio-private/app/views/sidebar.php` |

Før publisering: PHP 8.2/8.4 syntakskontroll og `php test-newsdesk.php`, kontroll av lesetilgang til RSS-kilder fra webhotellet, skrivbar privat `studio-private/config` for cache og sendeliste, og visuell kontroll i desktop/mobil. Brukeren må være innlogget. `newsdesk.php` sender aldri noe til WordPress eller på lufta.

Kilder: Bømlo kommunes Aktuelt RSS (15 min cache), NRK Siste nyheter (5 min cache), MET Locationforecast via Studios eksisterende værfunksjon, og Statens vegvesens åpne WFS/GeoJSON `SituationSimple` for Bømlo/Sunnhordland (5 min cache). Hver kildesak har tidspunkt og kilde; stale RSS-data merkes og skjules etter seks timer, trafikkmeldinger etter 15 minutter. Trafikk vises bare ved fullstendig svar, for hovedpostene uten tilgangsrestriksjoner og med sluttid som ikke er passert. Periodiske og planlagte meldinger krever alltid kontroll i Vegvesen trafikk før opplesing. På nettverksfeil påvirkes ikke de andre kildene. Den felles sendelisten er begrenset til 30 aktive punkter; produsent/programleder/administrator kan legge til saker, mens observatør kan lese. Manus og redaksjonell klarmelding håndteres i Sending.

Sendelisten lagres privat på Studio-serveren og kan lastes ned som tekst og legges i OneDrive under **Manus & Stikk**. Studio har foreløpig ikke OneDrive-skrivetilgang og gjør ingen automatisk arkivering der.

Kontroll 28.09.2026: Bømlo-RSS svarte HTTP 200 med `application/rss+xml`; NRK-endepunktet svarte HTTP 403 fra utviklingsmiljøet. Test NRK fra Uniweb før kilden regnes som operativ. Bruk ingen omgåelse av NRKs sperre.

Vegvesen: DATEX-pull krever registrert tilgang, mens den dokumenterte WFS/GeoJSON-tjenesten er åpent tilgjengelig. Testet 28.09.2026: geografisk avgrenset `SituationSimple` svarte HTTP 200 med 37 poster, inkludert Bømlo. WFS bruker BBOX 4.75,59.45–5.8,60.05 i EPSG:4326. Bekreft at Uniweb kan hente denne kilden før trafikkpanelet regnes som operativt. Kilde: https://git.vegvesen.no/projects/DATEX2/repos/datex2-spesifications/raw/3.1/NPRA_DATEXII_3_1_Specification_WFS-WMS_v1.1.pdf

Dette tillegget tar ikke med NRK-endringen i RR Robots eksisterende `robot.php`. Robot-innboksen og nyhetsdesken har ulike oppgaver: kildevurdering for direktesending versus artikkelutkast. En senere samordning kan dele kildeinnhenting og kilde-ID-er uten å slå sammen publiseringsflytene.
