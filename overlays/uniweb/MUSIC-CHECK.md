# Musikkontroll for God morgen Vestland

Første versjon lagrer en felles redaksjonell musikkplan med artist, tittel, sendetime og valgt reservespor. Den kontrollerer faktiske filer i en mappe brukeren velger på studiomaskinen. Ingen lydfiler eller komplette arkivlister lastes opp. Bare navn på planlagte og valgte reservespor lagres når brukeren sender skjemaet.

## Hvorfor lokal mappetilgang

Aktiv `music-api.php` på Uniweb krever allerede lokal PHP-server og loopback. Denne sperren skal bestå. Nettstedet kan ikke lese Macens musikkmappe gjennom den. Musikkontroll bruker nettleserens mappevelger; samme lokale/synkroniserte OneDrive-arkiv kan velges, og filtilgang prøves ved å lese starten av hver treffende fil. Dette bekrefter lesbarhet, ikke lydkvalitet eller formatdekoding. Skyfiler må være tilgjengelige lokalt. Ny sideinnlasting krever nytt mappevalg; ingen filstatus lagres som en varig garanti.

V1 leser navn fra `Artist - Tittel.ext` eller `Arkiv/Artist/Album/Tittel.ext`, ikke ID3. Matcher er eksakte etter normalisering av store/små bokstaver og mellomrom. «Ikke funnet» er ikke bevis for at låten ikke finnes et annet sted eller under en annen tagg. Duplikater krever avklaring. Reserver foreslås blant entydige lesbare filer, samme artist først, uten planlagte sanger og allerede valgte reserver. Brukeren bekrefter valget. Ingen kjøp eller nedlasting av musikk.

## Publiseringsfiler

I tillegg til læringsfilene i LEARNING.md:
- `studio-private/app/music-plan.php`
- `studio-public/music-plan.php`
- `studio-public/assets/music-check.mjs`
- `studio-public/assets/music-check.css`
- Oppdatert `studio-private/app/views/sidebar.php` (sist)

Bevar eksisterende musikk-API, AI Studio, private config-filer og sendeliste. De berørte læringsfilene ble lest fra aktiv Uniweb 28.09 og samsvarte med basen før endringene. Testdata skal ikke legges i produksjon. Planlagte musikkpunkter ligger i samme private sendelistefil med låsing og revisjonskontroll. Arkivering bevarer historikken.

## Timeintro – neste fase, ikke automatisk avspilling i v1

Ønsket er «Dette får du den neste timen» ved starten av partallstimer, f.eks. 06:00/08:00 Europe/Oslo, med 5–7 sekunder fra tre sanger som faktisk ligger i kø for timen som starter. V1 viser en tekstskisse med seks sekunder og de tre første sangene i den valgte sendetimen. Den redaksjonelle listen er ikke bekreftet avspillingskø; den starter ingen tidsstyrt lyd og bruker ikke reservespor automatisk.

Før automasjon: koble til faktisk playout-kø med revisjon og planlagt starttid; velg egnede klippunkter for hver fil; kontroller varighet, nivå og fade; legg til godkjent innlest intro; lag én ferdig avspillingsressurs. En lokal playout-tjeneste skal håndtere klokke/timehendelse, sommertid og én avspilling per hendelse. Klargjør etter oppdatert kø og verifiser den igjen før start. Avbryt klargjøring ved køendring, manglende fil eller færre enn tre brukbare sanger. Ikke avbryt aktiv mikrofon eller en låt ved å starte en nettlesertimer. Krev tydelig aktivering, status, testavspilling og umiddelbar stopp. Ingen aktivering i denne leveransen.

## Tester

PHP 8.2/8.4: programtilordning, reservehistorikk, leserolle, ugyldig time, samtidige endringer og arkivering. Node-testene kontrollerer arkiv ukjent/manglende/ulesbart/tomt, eksakte treff, dubletter, kandidatvalg og partallstime med tre sekssekunders klipp. Sidegjengivelse og tilgangsgrenser testes med lokale fixtures. Produksjonens reelle musikkfiler må velges på studiomaskinen for sluttkontroll.
