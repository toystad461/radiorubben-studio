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


## Forkast – lokal oppfølging 11.10.2026

«Forkast sak» bruker samme rolle-, CSRF-, kildeversjon- og revisjonssperrer
som vurderingen. Begrunnelse kreves. Kanal lagres som skip uansett innsendt
kanalvalg. Arbeidsliste, Forkastet og Alle er tilgjengelige filtre.
Velg nytt bruksområde og lagre vurderingen for å ta en sak tilbake.
Original og historikk beholdes; endret kilde trenger ny vurdering.
Ingen lyd, generering eller publisering utløses. Foreløpig bare lokal kode.


## RSS-oppfølging v1 – bestilt live-test, 11.10.2026

Behov: prøv relevansvalg, forkasting og oppdaterte kilder i aktivt Studio uten
å overskrive publisert innhold. Thomas har bestilt levering for live-testing.

- Automatiske, forklarte relevansforslag fra RSS: lokale stedsnavn eller kommunekilde.
  Lokale treff vises først i arbeidslisten. Andre saker er usikre, aldri automatisk forkastet. Dette er en fast første regel,
  ikke en trent AI-modell. Menneskets valg og begrunnelse registreres med historikk;
  automatisk læring eller godkjenning er ikke aktivert.
- Filteret Oppdateringer viser endret kilde, uavklart kilde og lagret oppdateringsutkast.
  Overskrift, RSS-tekst, kildelenke og publiseringstid sammenlignes med sist vurderte
  snapshot. Hentetid alene gir ikke ny innholdsversjon. Samlet NRK-visning sammenligner
  samme feed som ved vurderingen, slik at ulik tekst i Toppsaker/Siste nytt ikke
  omtales som en faktisk kildeendring.
- Godtatte saker som faller ut av RSS beholdes som sist vurdert snapshot og merkes
  som ukontrollerte. Feilet feed og annen feed gir ikke «uendret»-bekreftelse.
- Eksisterende Studio-saker kobles ved kildeidentitet, uten skriving til sendelisten.
  Lagret bekreftet leveringsstatus kan vises; det er ikke en fersk WordPress-kontroll.
  WordPress-saker som ikke har en koblet Studio-sak inngår ikke i denne overvåkingen.
- Oppdateringsgrunnlag vises side om side. Et redigerbart utkast starter med den
  faktiske RSS-teksten, uten AI-omskriving. Lagre oppdateringsutkast / Behold eksisterende
  innhold krever rolle, CSRF, fersk kilde, kildehash, revisjon og begrunnelse.
  Utkastet lagres i inngangsregisteret, ikke direkte i artikkelen eller radiomanuset.
  Eksisterende sak åpnes for videre kontroll og manuell overføring. Publiserings-
  og lydgodkjenning er fortsatt separate; gammel lyd erstattes ikke.
- /news-input.php?demo=updates&view=updates er et skrivebeskyttet, syntetisk eksempel.
  Det leser ikke RSS-cache eller ekte redaksjonelt register og har ingen redaksjonelle
  handlinger. POST avvises. Innlogging kreves som ellers i Studio.

Avgrensning: RSS hentes ved åpning etter eksisterende TTL (NRK 300 sekunder,
kommune 900 sekunder). Ingen ny kontinuerlig jobb eller nettleser-polling.
Artikkelendringer som ikke vises i RSS oppdages ikke. Hele artikler, bilder,
WordPress og TTS hentes ikke av den nye oppfølgingen. Automatisk oppdatering av
publisert innhold og regler lært automatisk av valg er videre arbeid.

Ferdigkriterier: negative kilde-/rolle-/CSRF-/revisjonstester; uendret sendeliste
ved alle inngangsvalg; synlige forskjeller og uavklarte kilder; bevart historikk;
responsiv visning; grønn CI; fersk produksjonsavstemming, privat kodebackup og
bekreftet leveranse før det rapporteres live. Publiser aldri prøveartikler eller lyd.
