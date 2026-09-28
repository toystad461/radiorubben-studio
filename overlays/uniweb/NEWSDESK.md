# Nyhetsdesk for aktiv Studio-utgave

Et selektivt tillegg til `studio.radiorubben.no` sin aktive PHP-versjon. Behold eksisterende `config/local.php`, brukerdatabaser og AI Studio-filer. Ingen nye hemmeligheter trengs.

| Lokal fil | Uniweb-mål |
| --- | --- |
| `public/newsdesk.php` | `studio-public/newsdesk.php` |
| `public/assets/newsdesk.css` | `studio-public/assets/newsdesk.css` |
| `app/integrations/NewsDesk.php` | `studio-private/app/integrations/NewsDesk.php` |
| `app/views/sidebar.php` | `studio-private/app/views/sidebar.php` |

Før publisering: PHP 8.2/8.4 syntakskontroll og `php test-newsdesk.php`, kontroll av lesetilgang til RSS-kilder fra webhotellet, skrivbar privat `studio-private/config` for cache, og visuell kontroll i desktop/mobil. Brukeren må være innlogget. `newsdesk.php` sender aldri noe til WordPress eller på lufta.

Kilder: Bømlo kommunes Aktuelt RSS (15 min cache), NRK Siste nyheter (5 min cache), MET Locationforecast via Studios eksisterende værfunksjon. Hver kildesak har lenke og tidspunkt; stale data merkes og skjules etter seks timer. På nettverksfeil påvirkes ikke de andre kildene. Sendelisten er begrenset til åtte saker per innloggingsøkt, krever produsent/programleder/administrator, og er ikke et arkiv eller en godkjenningsmekanisme.

Trafikkpanelet viser inntil videre lenke til Vegvesen trafikk. DATEX trafikkmeldinger dekker hele landet, men krever registrering/tilgang. Den nyere REST/JSON-katalogen har en tilgangsportal; lokalt filter og innhenting må verifiseres med dokumentert tilgang og respons før ekte hendelser vises.

Dette tillegget tar ikke med NRK-endringen i RR Robots eksisterende `robot.php`. Robot-innboksen og nyhetsdesken har ulike oppgaver: kildevurdering for direktesending versus artikkelutkast. En senere samordning kan dele kildeinnhenting og kilde-ID-er uten å slå sammen publiseringsflytene.
