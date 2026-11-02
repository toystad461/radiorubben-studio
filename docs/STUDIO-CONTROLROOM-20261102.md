# Kontrollrom og navigasjon – 2. november 2026

Arbeidsområder: Teknisk Produksjon, Kontrollsenter, System & Kvalitet.
Videreføring av #109/L01–L02 i eksisterende PR #115, fra bde70e2.

## Avstemming

Administratortilgang via rubben-test er bekreftet. Aktiv release er
/srv/radiorubben-test/releases/studio-music-fad12a2320de0ff0a036854c414accee794143bb.
Liquidsoap/Icecast er aktive med fersk connected=true-heartbeat.
Installert ai-studio.php og app.mjs er innholdslike PR115 før endring
(linjeskift skiller filhash); navigasjonen er identisk. Særfiler fra andre
PR-er beholdes. #111 er merget i #99-grenen; #115 er fortsatt utkast.

## Presis sideoversikt

Alle disse rutene finnes på VPS. Vanlige lesesider er tilgjengelige for alle
fire innloggede roller. Produksjonshandlinger beholder serverbaserte rollekrav.

| Gruppe | Sider | Faktisk funksjon / rolle |
|---|---|---|
| Kontrollrom | /ai-studio.php, /control.php | Serverstatus og redaksjonsoversikt |
| Musikkbibliotek | /music-library.php, /jingles.php | Musikk og separate jingler |
| Musikkø / planlegging | /music-studio.php | Serverkø og avspillingsstyring; styring krever produce |
| Musikkø / planlegging | /music-queue.php | Lagret kø med lokal prøvelytting |
| Musikkø / planlegging | /music-plan.php | Redaksjonell plan, ikke tidsstyrt avspilling |
| Nyhetsdesk | /newsdesk.php, /sending.php | Kilder, saker og sendeliste |
| Produksjon / RR Audio | /desk.php → /case.php | Saksarbeid og sakens RR Audio-panel |
| Produksjon / RR Audio | /ai-studio.php#producer-title, #music-title | Manus/stikk og lokale verktøy |
| CRM | /crm.php | Bare administrator |
| System / administrasjon | /learning.php | Læringsarbeid; eksisterende handlingskrav beholdes |
| System / administrasjon | /ai-studio.php#innstillinger, #rode | Administrator/produsent |
| System / administrasjon | /users.php | Bare administrator |

news-input.php, learning-rules.php, case.php, crm-register.php,
crm-outreach.php og crm-demo.php beholdes som oppgave-/detaljflyter.
workspace.php sine social/schedule/sponsors/radio er plassholdere, ikke aktive
funksjoner. Sosiale medier og tidsstyrt sendeplan vises uten lenker.
Ingen ny selvstendig RR Audio-side, LIVE-funksjon eller TTS-automatikk aktiveres.

## Statuskontrakt

GET /controlroom-api.php krever eksisterende innlogging og kjent Studio-rolle.
Andre metoder avvises. Journal, heartbeat og manifest leses under eksisterende
lås; ingen kø-/journal-/konfigurasjonsendringer. Svaret inneholder bare
visningsfelter, ikke filbaner, originaler, brukere eller hasher.

Fersk heartbeat (<5 sekunder), tilkoblet utgang og aktivt innslag kreves for
«Spiller». Stopped, paused, blocked, disconnected, unknown og unconfigured
skilles. HTTP-feil/foreldet svar fjerner nå/progresjon. Lokal musikk og testdata
kan ikke overskrive servervisningen. Neste tre inkluderer jingler og reserverte
innslag før uavhentet manifest; aktive/fullførte og dubletter utelates.
Et blokkert køpunkt avslutter listen. Varighet er cue-justert; progresjon
vises bare med ferskt PCM-bevis og gyldig motorposisjon, uten ekstrapolering.

Lytting startes uttrykkelig til konfigurert /music-test-stream/. Stopp lytting
stopper bare fanens lyd. Nettleserbuffer kan gi forsinkelse mot motorstatus.
Observatør får servervisning; eksisterende produksjonsfelt er deaktivert.

## Kontrollstatus før utrulling

PHP/Node-tester dekker foreldelse, feil, timing, rekkefølge, sperret kø,
personvern, fire roller og lytteisolasjon. Lokal automatisk nettlesertest
mangler installert testnettleser. Full CI, selektiv installasjon og faktisk
VPS-kontroll dokumenteres med separat kvittering etter gjennomføring.


## Installert og funksjonskontrollert – 02.11.2026, 09:47 Europe/Oslo

Ni runtime-filer er selektivt installert. Etter mobil- og lenkeretting er alle
ni identiske med kildecommit 907b0f013afcbbc839ea631266e77c6c002b3bb7.
Ingen merge, full branch-deploy eller restart av lydmotoren er utført.
Eksakte før-/etter-hasher, UTC-tider og backuper står i
[deploykvitteringen](STUDIO-CONTROLROOM-DEPLOY-20261102.json).

Hovedinstallasjon 09:42:22 norsk tid fra f78df87, mobil-CSS 09:44:14 fra
3ea18bc og to navigasjonsfiler 09:47:14 fra 907b0f0. Hver installasjon
kontrollerte 120 uendrede beskyttede filer, bevart journalprefix og samme
motor-PID 770. Begge lydtjenestene forble aktive. Køen og journalen fortsetter
å utvikle seg normalt gjennom avspilling og eksisterende rotasjonstimer.

Innlogget Chrome på faktisk VPS viser artist/tittel, målt varighet/progresjon
og neste tre inklusive jingler. PSY → DJ Snake ble observert uten reload;
køen flyttet jinglen fram. Lytt til teststream startet HTML-lyd med framdrift
uten mediafeil. Stopp lytting ga paused=true mens serverstatus forble playing.
Separat 10 sekunders offentlig MP3-dekoding avsluttet uten dekoderfeil.
Dette er teknisk lydkontroll, ikke en ny subjektiv avlytting fra Thomas.

Kontrollert ved 1440, 390 og 320 CSS-piksler. Mobilmenyen har de samme gruppene;
ingen horisontal overflyt. En gammel fast topphøyde ble rettet slik at brukerfelt
og Logg ut ikke dekker innhold. Hopp til innhold og lyttesnarveien bruker nå
samme eksisterende konsollruting. Fysisk telefon/Safari er ikke prøvd her.

Alle tolv hovedruter er åpnet innlogget på VPS: control, music-library,
jingles, music-studio, music-queue, music-plan, newsdesk, sending, desk,
crm, learning og users. Dette er visnings-/tilgjengelighetskontroll,
ikke utførelse av innholds-, opplastings-, kunde- eller brukerhandlinger.
Anonym kontrollroms-API gir 401. Rolle/method/negative status-scenarier er
prøvd syntetisk for å unngå å avbryte den virkelige sendingen.

Eksisterende begrensninger: VPS-Nyhetsdesk oppgir manglende WordPress-tilkobling,
værdata er utilgjengelige, og intro-e-post for nye brukere er ikke konfigurert.
RR Audio finnes som saksfunksjon, men lydproduksjon er ikke testet/aktivert i
oppdraget. Disse forholdene er ikke løst eller skjult som aktive integrasjoner.

Tilbakeføring skjer i motsatt rekkefølge med private rollback.py-filer:
1. /var/lib/rr-vps-deploy/controlroom-jg0oghdw/rollback.py
2. /var/lib/rr-vps-deploy/controlroom-37ubhf76/rollback.py
3. /var/lib/rr-vps-deploy/controlroom-gap3qz8l/rollback.py

Hver tilbakeføring verifiserer gjeldende etterhash før den erstatter bare
sitt filsett. Den stopper ikke motoren og skriver ikke til produksjonsdata.
Rollback er klargjort og kodekontrollert; ingen unødvendig faktisk tilbakeføring
av den fungerende live-visningen er utført.

Neste tre steg i hovedsporet: gjennomgå/samle eksisterende PR-kjede uten blind
helutrulling; avstemme de manglende VPS-integrasjonene separat; fortsette øvrige
L02/L04-funksjoner i #109 med samme kø og motor. L02–L05 er ikke ferdigmeldt.
