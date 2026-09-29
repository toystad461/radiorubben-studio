# God morgen Vestland – redaksjonell hukommelse v1

Bygger på Studio PR #12 (`e130c6be6867e4ea9b3b5c746bd4038392f2b6cc`). Dette er et selektivt Uniweb-tillegg. Main og standardpakken inneholder ikke hele den aktive Studio-utgaven.

## Bruk

1. Nyhetsdesk → Sending. Nye punkter får God morgen Vestland som program. Eksisterende punkter er uendret; velg programmet og lagre før generering.
2. Generer manus, rett teksten, bekreft kildekontroll, lagre og merk klart.
3. Velg «Lær av rettelsen». Skriv en generell språk-/stilregel og lagre forslaget. Originalutkast og rettet manus lagres som dokumentasjon.
4. Administrator åpner «Robåt – læring», kontrollerer at regelen ikke motsier kildekrav eller andre regler, og godkjenner/avviser. Produsent og programleder kan foreslå; observatør leser.
5. Neste generering for God morgen Vestland bruker godkjente regler. Deaktivering påvirker fremtidige utkast. Tidligere genereringer beholder kopien av reglene som faktisk ble brukt.

En generell programregel kan også foreslås direkte på læringssiden uten manus. Forslag blir aldri automatisk aktive. V1 har ikke automatisk AI-analyse av rettelser, automatisk konfliktkontroll, OneDrive-synk eller modelltrening. Regler påvirker bare manusgeneratoren i Sending, ikke AI Studio eller artikkelroboten. Faste kildekrav står over preferansene; modellinstruksjoner erstatter ikke menneskelig faktakontroll.

## Data

Bruker eksisterende private `studio-private/config/sending-board.json` og separat fillås. Original og historikk ligger på hvert punkt. `editorialRules` inneholder regler, status, revisjon, dokumentasjon og beslutningshistorikk. Ingen egen database eller utvidelse kreves. Historikk legges til ved vellykkede redaksjonelle endringer; før denne versjonen finnes ingen gjenopprettbar historikk. Kildegrunnlag og regelversjoner følger historikken. Opp/ned lager ikke ekstra manusversjoner.

Registeret har maks 300 regler og 20 aktive regler per program på 500 tegn. Historikk slettes ikke automatisk. Overvåk filstørrelse og ta regelmessig privat backup; ved større bruk bør lagringen flyttes til en transaksjonsdatabase med eget arkiv. Ingen personlige kontonøkler eller modellnøkler lagres i historikken. Medarbeidernavn og manus er interne redaksjonelle data.

## Selektiv utrulling

Avstem først disse filene med aktuell Uniweb-kode. Ta privat backup av de berørte kodefilene og sendelisten under vedlikehold, slik at ingen samtidige endringer går tapt. Bevar privat konfigurasjon og øvrige serverfiler.

- `studio-private/app/programs.php` → nytt versjonert programregister (må følge med)
- `studio-private/app/board.php` → samme sti på Uniweb
- `studio-private/app/editorial-memory.php` → ny privat modul
- `studio-private/app/story-script.php` → samme sti
- `studio-public/learning.php` → ny side
- `studio-public/sending.php` → samme sti
- `studio-private/app/views/sidebar.php` → samme sti, sist

Stiene over er relative til `overlays/uniweb/` i repoet. Last opp de private modulene først, så sidene og menyen. Ikke bruk standardpakken som full erstatning. Ingen private JSON-data skal følge kodepakken eller skrives over. Gjeldende AI-konfigurasjon og eksisterende innlogging brukes videre.

Tilbakeføring: gjenopprett forrige kode samlet. De ekstra JSON-feltene kan bli liggende. Eldre kode lager ikke ny historikk; gjenopprett aldri en gammel sendeliste over nyere medarbeiderarbeid uten avstemming.

## Verifisering

`php overlays/uniweb/test-editorial-memory.php`, eksisterende board- og story-script-tester samt syntakskontroll inngår i CI med PHP 8.2 og 8.4. Testene bruker mockede AI-kall og midlertidig lagring, aldri produksjonsdata.

Etter utrulling: kontroller innlogging og rollegrenser, mobil/desktop, programvalg før generering, rettelse → forslag → godkjenning → nytt manus og at deaktivering stopper videre bruk. Bekreft manushistorikken etter ny innlogging. Ekte modellrespons og produksjonsvisning må kontrolleres på Uniweb.



## Programregister (PR #13-refaktorering, ikke deployet)

`studio-private/app/programs.php` er det kodeeide registeret. `registryVersion` versjonerer registeret; `defaultProgram` brukes bare ved opprettelse av nye punkter. Hver oppføring har `id`, `name`, `profileVersion`, `style` og `learningEnabled`. Øk profilversjonen ved endring av profilens innhold, og registerversjonen ved endringer i registeret. En ny programoppføring krever ingen nye programbetingelser i sendelisten, læringssiden eller kontekstbyggeren. Bare God morgen Vestland er registrert i produksjonskoden.

Gamle punkter leses uten migrering. Manglende programfelt forblir manglende ved lagring uten programvalg; tom program-ID gir ingen profil eller regler. Ukjent ID avvises ved tilordning, kontekstbygging og regelforslag, uten fallback til standardprogrammet. Grensesnittet viser navn fra registeret, og læringssiden viser regler for valgt program.

Læring av rettelser krever at punktets program samsvarer med den lagrede genereringsprofilen. Et programbytte gjør derfor ikke et gammelt utkast til gyldig læringsgrunnlag for et annet program. Genereringshistorikken beholder profil, registerversjon og regelversjoner. Deaktivert læring beholder programmets stilprofil, men utelater reglene og blokkerer nye forslag/godkjenninger. Avvisning og deaktivering er fortsatt mulig.

`test-programs.php` injiserer en testprofil gjennom valgfrie serverinterne registerparametere. Registeret leses aldri fra POST eller sendelistefilen. Testene dekker andre programprofil, programisolasjon i begge retninger, lik regeltekst i to programmer, ukjente ID-er, gamle punkter, programbytte etter generering og deaktivert læring. CI kjører disse sammen med eksisterende sikkerhets- og kildekontroller på PHP 8.2/8.4. Ingen produksjonsdata eller ekte AI-kall brukes.
