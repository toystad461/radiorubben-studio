# PR #52 – serveravvik og innlogget funksjonskontroll

Kontrollert 9.–10. oktober 2026, Europe/Oslo. Produksjonsgrunnlag:
`f601a370caf2beacd40da21ad62545a4151445ad`.

## Fersk serverinventering
[Lesekjøring 37996893036](https://github.com/toystad461/radiorubben-studio/actions/runs/37996893036)
kontrollerte 495 registrerte filer mot gjeldende selective-state. Alle faktiske
hasher samsvarte med registrert live-verdi: **0 uventede avvik**. Seks registrerte
source/live-forskjeller er forklart nedenfor. Skanning av studio-public,
studio-private/app og studio-private/vendor fant **0 uregistrerte kodefiler**
innenfor publiseringens filfilter.

Kontrollen brukte verifisert SSH, delt lås mot deploy og kontroll av uendret
manifest før/etter. Den leste filinnhold for hashing; ingen produksjonsfiler
ble skrevet. Konfigurasjon, brukere, køer, cache, backup og symlinker ble ikke
inventert. Dette er en kodekontroll, ikke en full disk- eller sikkerhetsrevisjon.
Alle 495 hashposter finnes i kjøringsloggen. De seks forskjellene er lagret i
[dataminimert inventeringsbevis](production-inventory-2026-10-10.json).

## Hver bevart forskjell
| Produksjonsbane | Forklaring og kontroll | Vurdering |
| --- | --- | --- |
| studio-private/app/bootstrap.php | Main med bildedimensjoner 2400×1073 erstattet av 2172×724 gir eksakt observert SHA-256. | Bare logoens HTML-dimensjoner; autentisering og sikkerhetsheadere er ellers byte-identiske med main. |
| studio-public/login.php | Main etter standard pakkeomskriving og samme dimensjonsbytte gir eksakt observert SHA-256. | Bare logoens HTML-dimensjoner utover standard pakking. |
| studio-public/local/login.php | Samme kontroll som login.php gir eksakt observert SHA-256. | Ingen ytterligere avvik i innloggingslogikken. |
| studio-public/assets/radio-rubben-logo.png | Byte-identisk med Git-versjonen fra 4d5b0e007ad62d8ce432d488413b1871bdd18120. | Kjent eldre logo, beholdt i produksjon. |
| studio-public/assets/radio-rubben-logo.svg | Registrert live=null; filen mangler fortsatt. Ingen referanse funnet i undersøkt app/public-kode. | Ubrukt alternativ ressurs i main. |
| studio-public/assets/favicon.ico | Registrert live=null; filen mangler fortsatt, men app/views/head.php refererer til den. | Manglende favikon må rettes i en separat, autorisert selektiv endring. |

De tre PHP-filene i main er uendret siden 8dba38833f5f02620e267855d12d486de3685149.
Sammenligningen gjenskapte produksjonshashene fra Git lokalt. Serverkode,
privat konfigurasjon eller innloggingsdata ble ikke hentet ut.
Tidligere avvik i app/views/head.php er ikke lenger et source/live-avvik.

## Innlogget kontroll i produksjon
Eksisterende administratorøkt i Chrome ble brukt. Ingen passord eller
sesjonsverdier ble hentet ut eller lagret i rapporten.

| Område | Utført kontroll | Resultat |
| --- | --- | --- |
| Kontrollsenter | Åpning, saksliste, valgt sak, radio/nettstatus og vær/trafikk. | Eksisterende saker og separate kanalstatuser vises. |
| CRM | Liste, søk, filtrert resultat og bedriftskort med kontaktlogg. | Fungerer; norske tegn vises riktig. Ingen kort eller notater lagret. |
| Læring | Åpning av instruksforslag, aktive regler og administrasjonsvalg. | Ny instruksflyt og eksisterende regel vises. Ingen forslag eller regler aktivert. |
| Nyhetsdesk | Sak, kommentarfelt og lesebekreftelse slått på og av igjen. | Publiseringsknappen var sperret, ble aktiv etter avkrysning og sperret igjen etter fjerning. Ingen publisering. |
| Automatikk | Lest synlig klargjørings-/varselstatus. | Klargjøring er på. Faktisk tidsstyrt kjøring er ikke testet. |
| Radioliste | Eksisterende manus og kildekontrollstatus. | Utløpt kontroll vises som må kontrolleres; saken står som utkast. |
| AI Studio | Dashbord, tilkoblingsstatus og fanget nettleserfeillogg. | Studioserver svarer; ingen fangede JavaScript-feil. MIDI, musikkarkiv og sendeovervåking er ikke tilkoblet på denne PC-en. |
| Musikkplan | Åpning og tom/ikke-tilkoblet status. | Viser ukjent arkivstatus; ingen musikk startet. |
| Sosiale medier | Åpning. | Viser at publisering ikke er tilkoblet. |
| Brukere | Åpning av administratorsiden. | Rolle-/brukeradministrasjon vises; ingen konto endret. |
| WordPress | Eksisterende wordpress-check.php sin lesende kontroll. | Begge kall ga HTTP 200, transport=0; authenticated=true. Ingen testkladd funnet, ingen innlegg opprettet. |
| CRM på 390 px | Bredde og tilgjengelig navigasjon. | Ingen målt horisontal overflyt, men hovedmenyen skjules uten alternativ menyknapp. Logo/hjem finnes fortsatt. |
| Uten innlogging | Separate HTTP-kall uten sesjon til CRM, læring, brukere, Nyhetsdesk og WordPress-kontroll. | Alle fem returnerte 303 til /login.php. |

## Funn og begrensninger
1. Favikon er referert, men mangler på serveren. Direkte HTTP-forsøk fikk timeout;
   fraværet er bekreftet gjennom filinventeringen, ikke gjennom HTTP-status.
2. CRM mangler direkte hovedmeny på mobilbredde. Eksisterende CSS skjuler
   sidebar-nav ved maks 800 px, uten alternativ meny i crm.php.
3. Sosiale medier og lokalt lydutstyr er ikke ferdig tilkoblet. Dette er synlig
   funksjonsstatus, ikke dokumentert regresjon fra den siste utrullingen.

Dette er en innlogget lese-/navigasjonskontroll med test av klientens
lesebekreftelse og en faktisk lesende WordPress-integrasjonskontroll. Lagring,
rollebytte, ny innlogging, betalt AI/TTS, e-post, publisering og lyd på lufta er
ikke ende-til-ende-testet i produksjon. Runtime-testene i CI supplerer denne
kontrollen, men erstatter ikke slike tester. Ingen produksjonsdata ble brukt
som testinnsending. Nettlesertilgangen fikk et tidsavbrudd under videre
mobilkontroll; det påstås derfor ingen full mobiltest av alle sider.

## Beslutning og neste steg
Alle seks bevarte kodeavvik er identifisert og forklart. Bevar selektiv deploy;
ikke overskriv hele produksjonen med standardpakken. Planlegg favikonrettelse
og mobilnavigasjon som en avgrenset kodeendring. Lydutstyr trenger egen test
før faktisk sending kan bekreftes. #47 er fortsatt utsatt.
Ingen merge eller deploy inngår i denne kontrollen. Den midlertidige
lesekontrollen på PR-grenen fjernes før levering; main-workflows og
produksjonens tilgangsregler endres ikke.
