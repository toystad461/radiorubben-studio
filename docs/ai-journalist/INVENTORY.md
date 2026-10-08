# Repository- og runtimekartlegging – 8. oktober 2026

Kartlagt før implementering via GitHub-connector, lokal kode, release-workflow og autentisert, skrivefri SSH-kontroll. GitHub er kode-master. Lokal prosjektmappe er ikke selv et Git-repository; eksisterende checkouter hadde andre oppgaver eller ukommittert arbeid. Managed worktree-verktøyet svarte «Not a git repository». Derfor er en separat klone opprettet med branchen `feature/ai-journalist-2`.

| Repository | Bekreftet rolle | Beslutning |
| --- | --- | --- |
| `toystad461/radiorubben-studio` | PHP/Composer; Nyhetsdesk, radio/nett, manuell godkjenning, læring og WP-bro | Integrasjon her |
| `toystad461/radiorubben-web` | WordPress, fotballrobot, tema og deployverktøy | Eksisterende bro bevares; ingen plugin-/temaendring |
| `toystad461/RadioRubben-robot` | Liten TypeScript-kodebase for Bømlo RSS-innhenting/eksport og fotballtyper; tre kontrollert ved `e9fcdab676c58fbf4bd017e1a1beace64011e197` | Ikke påvist som aktiv produksjonsjournalist; ikke bygg parallell generator her |
| `toystad461/radiorubben-speaker` | GitHub oppgir størrelse 0 | Ingen dokumentert relevant runtime |

Et femte tilgjengelig repository, ZinusADSLogger, er ikke relevant og ble ikke undersøkt videre.

## Produksjon bekreftet

8. oktober kl. 14:36 UTC ble manifestet og de faktiske filene kontrollert via SSH med eksisterende nøkkel og streng vertsverifisering. Ingen serverfil ble skrevet. [Sanitert kontrollresultat](runtime-audit.json):

- Aktiv Studio-commit: `1aff7adeb2b90c9576265c70f06bab141a1519a0`.
- Deploykjøring: [37739713565](https://github.com/toystad461/radiorubben-studio/actions/runs/37739713565), forsøk 1.
- 491 registrerte kode-/ressursfiler kontrollert mot manifestets live-hasher, null avvik.
- Ni bevarte forskjeller mellom kildepakke og aktiv fil er spesifisert i kontrollresultatet. De er ikke feilrettet eller overskrevet.
- PHP CLI 8.4.26 bekreftet. Dette er ikke en ny bekreftelse av PHP-versjonen i web-SAPI.
- Uniweb har aktivt Studio med offentlig/privat katalogdeling. Hele `main` er fortsatt ikke byteidentisk med serveren på grunn av bevarte forskjeller.

Ingen påstand om Render-produksjon. En åpen Render-test-PR (#21) er ikke driftsbevis. Produksjonens innlogging, faktisk modellkvalitet og samtlige WordPress-pluginfiler er ikke ferskt ende-til-ende-testet i denne oppgaven.

## Base og overlapp

Arbeidet startet fra bekreftet produksjonscommit/main `1aff7ad`. Fersk gjennomgang av åpne PR-er avdekket #51, `feat/newsroom-feedback-memory`, head `7edbf808a9abfc9e99405066edf907acf5e2b260`. Den inneholder allerede kommentarflyt og kobling av godkjent læring til nett/radio. AI-journalist-branchen bygger videre på denne som eksplisitt avhengighet; den omskrives eller deployes ikke her.

PR #53, `codex/direct-writing-instructions`, er separat arbeid med flere instruksjoner på læringssiden. Den er ikke innlemmet. Overlapp i `editorial-memory.php`, `public/learning.php` og test-fixtures må avstemmes ved senere integrasjon. PR #52 gjelder produksjonsavstemming og er heller ikke automatisk base.

## Ingen deploy

`studio-release.yml` publiserer selektivt fra `main` etter tester. En merge til `main` kan derfor utløse deploy og skal ikke gjøres uten brukerens uttrykkelige godkjenning for denne leveransen. Branch og gjennomgangs-PR er tillatt; verken merge, workflow-dispatch, ny publisering av artikler eller aktivering av læringsregler er utført.

Før eventuell utrulling: avstem begge PR-avhengigheter, kjør kontrollene på eksakt head, forny produksjonshasher, dokumenter berørte filer og privat backup/rollback. Eksisterende selektive deploy gjenbrukes først etter godkjenning. Forvent at ventende kontroller må fornyes på grunn av ny policy.
