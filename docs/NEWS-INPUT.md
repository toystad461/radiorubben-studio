# Nyhetsinngang – første Studio-integrasjon

Bestilling: innkommet RSS → les kildens tekst → vurder relevans og bruksområde.
Egen lokal gren `feat/news-input-portal` fra main
`2a187e070e9950376d1b3e592f9da88015ca2986`, kontrollert 11.10.2026.
Åpne PR-er #2, #13, #15, #16, #21 og #47 er avstemt. Denne leveransen er
uavhengig av deres baser; mobil-PR #47 må bevare den nye inngangen ved senere samordning.

## Implementert

- `/news-input.php`, lenket fra Nyhetsdesk, bruker eksisterende innlogging,
  roller, CSRF, RSS-cache, kildeidentitet, gruppering og atomisk sendelistelagring.
- NRK Vestland Toppsaker / Siste nytt og Bømlo kommune vises separat eller samlet.
  Doble NRK-saker får én identitet med medlemskap i begge feeder. Nyeste sak først;
  «Toppsak» er et kildemerke, ikke en automatisk lokal relevansvurdering.
- `rssTitle` og `rssText` bevarer hele normaliserte RSS-teksten ved siden av den
  uendrede korte legacy-forhåndsvisningen. HTML vises som ren, escaped tekst.
  Eldre cache merkes uttrykkelig som kort utdrag frem til neste vellykkede henting.
  Tom beskrivelse fylles ikke ut. Ingen bilder eller fullartikler hentes i portalen.
- Menneskelig innkommende-vurdering: Radio og nett / Nett / Radio / Vurder senere /
  Ikke aktuell. Begrunnelse 15–1000 tegn, bruker, kildeversjon, tidspunkt og historikk.
  `rssIntakeReviews` lagres i et separat privat register, `config/rss-input.private.json`,
  under kanonisk kildeidentitet. Eksisterende atomiske lagringsprimitiv gjenbrukes,
  men selve sendelisten leses eller endres ikke av inngangsvurderingen.
  Eksisterende `newsSelections`, saker, manus, lyd og godkjenninger endres ikke.
- Endret RSS-innhold, overskrift, URL eller publiseringstid gjør tidligere vurdering
  utdatert. Fersk hentetid alene endrer ikke innholdsversjonen. Samtidig redigering
  og gammel kildeversjon avvises på serveren.
- Desktop: liste og lesefelt ruller uavhengig. Mobil: nyhetsliste → valgt sak → tilbake.
  Ingen ny klientlagring, AI-generering eller godkjenningsmotor.

## Grense mot dagens behandling

Kanalvurdering er ikke rettighetsklarering, manusgodkjenning, lydgodkjenning eller
publiseringsgodkjenning. Dagens AI-relevansvurdering krever full originalartikkel;
den gjenbrukes derfor bare gjennom en uttrykkelig separat handling, under
«Videre behandling i dagens Nyhetsdesk». Dette er eksisterende `open_source` med
eksisterende sperrer. «Vurder senere» og «Ikke aktuell» får ingen slik knapp.
Ingen faktisk slik handling er utført under utvikling eller test.

Automatisk RSS-videreformidling til nettside/radio, nye eksterne feeder, OneDrive,
lydproduksjon og automatisk godkjenning inngår ikke i denne leveransen.
Full RSS-tekst kan ikke alene bevise gjenbruksrett. Eksisterende nyhetsjobber og
private automatikkinnstillinger endres ikke; kildeportalen styrer dem foreløpig ikke.
Historikk beholdes uten automatisk sletting; volum og backupdekning må følges opp.

## Ferdigkriterier og kontroll

Ny kode bruker eksisterende arkitektur og kan prøves med syntetiske saker uten
kontakt med WordPress, AI, TTS eller ekte avstemning. Tester:

- `tests/news-input.php`: faktisk RSS-parser, kildeidentitet, lagring og PHP-visning.
  Negativ kontroll av rolle, CSRF, falsk handling/kilde, gammel kilde, samtidig
  redigering og uendret board ved avvisning. Kontrollerer bevart eksisterende manus,
  godkjenning, lyd og kildevurdering; godkjent innkommende-vurdering lagres normalt.
- `tests/news-input-page.php`: faktisk controller, cache, lagring og visninger;
  kun autentisering erstattes med testbruker. GET, gjest, observatør, CSRF, metode,
  lagring, endret kilde, revisjonskonflikt og falsk publiseringshandling.
- `tests/news-input-mobile.cjs`: PHP-generert syntetisk visning på 390, 768, 1280
  og 1920 px; uavhengig rulling, mobilbytte og ingen horisontal overflyt.

Ny PHP-kontroll inngår i Composer og nettleserkontrollen i eksisterende CI.
Lokal teststatus dokumenteres i STUDIO-STATUS. Full CI, ekte innlogging og
den aktive serverens filavvik må verifiseres før en separat autorisert selektiv deploy.
Ingen produksjonskode, privat konfigurasjon, publiserte artikler eller stemmer er endret.

## Kodegjennomgang og isolert innlogget test – 11.10.2026

Egen kodegjennomgang fant at den første lagringskoblingen til sendelisten kunne
utløse eksisterende kontroll av samlesaker. Dette er rettet ved separat lagringsfil.
Det trengs ingen migrering av produksjonsdata: første versjon var bare lokal.
Controller-testene kontrollerer nå også byte-for-byte at en separat sendeliste
forblir uendret når en inngangsvurdering lagres eller avvises.

`tests/news-input-http.cjs` og `tests/news-input-http-seed.php` gjennomførte
en lokal HTTP-test med uendret autentiseringskode og en midlertidig syntetisk
produsentkonto. Passordet ble generert i prosessminnet; ingen ekte konto eller
Microsoft-tjeneste ble brukt. Tre offentlige RSS-feeder ble hentet og behandlet
av den faktiske Studio-parseren. Testserveren lyttet bare på 127.0.0.1 og tillot
inngang, lokal innlogging/utlogging og statiske ressurser. Andre Studio-handlinger
og private filer var sperret. AI- og lydnøkler var tomme; cURL var ikke aktivert.

Resultat 11.10.2026 kl. 10:08:39 UTC: 19 brukbare toppsaker, 20 siste nytt-saker
og 5 kommunesaker. Antall er et datert testresultat, ikke faste forventninger.
Gjest ble sendt bort fra den beskyttede inngangen; faktisk lokal passordinnlogging
ga tilgang. Kildevalg og lagring av en uttrykkelig teknisk testvurdering besto.
Sendelisten var byte-identisk, produksjonsruter/private filer svarte 404,
nettleseren gjorde ingen eksterne forespørsler, og full Studio-visning på 390 px
hadde fungerende mobilbytte uten horisontal overflyt. Midlertidig server ble
stoppet og testkonto, sesjoner og runtime-kopi fjernet etter testen.

PHP 8.2 og 8.4: de 23 modul-/visningskontrollene og ni controller-scenarioene
besto etter rettelsen. Fersk main er fortsatt `2a187e070e9950376d1b3e592f9da88015ca2986`;
de seks åpne PR-enes head/base er lest på nytt. Ingen andre grener er endret.

Dette er lokal kodegjennomgang og lokal innlogging, ikke en uavhengig review,
ekte Microsoft-innlogging, full CI eller produksjonskontroll. Ingen GitHub-PR
er opprettet i denne leveransen. Backupdekning og selektiv serveravstemming
gjenstår før produksjonsutrulling. Neste steg er en gjennomgåbar utviklings-PR
med full CI; deretter separat autorisert test/utrulling i aktivt Studio og
avtalt kildebruk og kobling til eksisterende manus-/nettflyt. Backupdekning venter
fortsatt på Uniweb-sak TKT-19054505. VPS og FFmpeg holdes utenfor dette løpet.
