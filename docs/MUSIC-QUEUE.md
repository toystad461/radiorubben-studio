# Musikkø – første grunnlag

Videreføringen ligger i [Musikkstudio: regler og testavvikling](MUSIC-STUDIO-PLAYOUT.md).
Når køen har Musikkstudio-oppsett, styres redigering der; denne sidens
lokale prøvelytting endrer fortsatt ikke avspillingsjournalen.

Første leveranse er en lagret musikkø med prøvelytting i Studio. Målet er å
bygge grunnlaget før robotvert og programautomatikk for God morgen Bømlo.
Radio.co inngår ikke. Denne endringen etablerer ingen radiosending.

## Bruk

Åpne **Musikkø** i menyen. Legg til eksisterende MP3-filer fra musikkbiblioteket,
søk etter artist/tittel og sett rekkefølgen med Opp/Ned eller dra og slipp.
Endringene lagres umiddelbart. Fjerning og tømming gjelder bare køreferanser;
originalene i biblioteket beholdes. Samme låt kan legges inn flere ganger.

**Spill neste** starter lokal prøvelytting gjennom enhetens valgte lydutgang.
Automatisk fortsettelse er av ved åpning og krever et aktivt valg. Åpning,
omlasting, køendring og «Begynn køen på nytt» starter aldri lyd. Pause, stopp,
volum, neste låt og historikk gjelder denne fanen. Lagret rekkefølge konsumeres
ikke av prøvelyttingen. Omlasting starter en ny prøvelytting fra begynnelsen,
etter nytt klikk på Spill neste. Lukkes fanen, stopper lyden.

Køen deles mellom innloggede brukere; avspillingen er ikke koordinert mellom
faner/enheter. To faner kan spille uavhengig. Dette er ikke en felles on-air-
spiller, sendelogg eller serverbasert playout. Ingen crossfade, normalisering,
lydkortvalg, strømencoder, TTS-kall eller automatisk programstart er innført.

## Lagring og feil

- `config/music-play-queue.private.json` og tilhørende `.lock` er private
  runtimefiler utenfor public og Git. Maksimalt 100 innslag i én felles kø.
- Innslag har egen ID, type `music`, bibliotek-ID og SHA-256 for originalen.
  Tittel, artist og varighet hentes fra eksisterende bibliotekmetadata.
- Eksisterende innlogging og produsenttilgang beskytter endringer og lyd.
  Observatører kan lese køen. Alle skrivinger krever CSRF og riktig revisjon.
- Låsing og atomisk erstatning bevarer køen ved samtidige endringer. Konflikt
  krever Oppdater. Mislykkede/utløpte forespørsler gjentas ikke automatisk;
  serveren kan allerede ha lagret endringen.
- Manglende eller endrede originaler merkes og hoppes over ved neste valg.
  Eksisterende autentiserte lydrute verifiserer filen ved avspilling. Lydfeil
  slår av automatisk fortsettelse. Skadet køfil overskrives ikke.

## Kontroll og videre arbeid

`tests/recovered/test-music-play-queue.php` kontrollerer lagring, revisjon,
roller/CSRF, rekkefølge, gjentatte låter, filavvik og bevaring av originaler.
Den inngår i den eksisterende PHP-suiten. `tests/music-queue-http.cjs` bruker
faktiske ruter og lagring i en isolert PHP-testserver med syntetiske brukere.
`tests/music-queue-browser.cjs` bruker faktisk siderendering og klientkode,
mock-API og syntetisk WAV-lyd for å prøve lydhendelser og layout ved 390/1280 px.
Ingen reelle musikkfiler, brukere eller produksjonsdata brukes som test.

Lokalt er JavaScript/PHP-syntaks og nettleserflyten kontrollert. Kodecommit
`6951c8739840bd10fd67c391bacc59cc9e453555` består full PHP-suite på 8.2/8.4,
native lagring/HTTP og køens Chromium-kontroll i
[CI 39166287907](https://github.com/toystad461/radiorubben-studio/actions/runs/39166287907).
Lokal PHP-WASM støtter ikke fil-låsene og brukes ikke som bevis på serverlagring.
Reell Microsoft-innlogging, produksjonsserverens filrettigheter og faktisk
studiolydutgang er uprøvd. Gjennomgåbart utkast:
[PR #98](https://github.com/toystad461/radiorubben-studio/pull/98).

Neste funksjonelle steg er å avklare hvor den vedvarende avspilleren skal kjøre,
og koble køen til den med entydig kontroll over aktiv spiller og sendestatus.
Deretter kan God morgen Bømlo få egen programprofil, musikkblokker og plasser
for ferdig godkjente stikk. Robotvert/ElevenLabs blir en separat produsent av
lydinnslag med eksisterende godkjenningskrav. OneDrive-arkivering er ikke
implementert her.

Ingen merge eller utrulling inngår i dette utkastet. Før en eventuell
publisering må avvikene mellom aktivt Studio og main avstemmes, inkludert
eksisterende musikkbibliotek/lydrute, navigasjon og filrettigheter.
