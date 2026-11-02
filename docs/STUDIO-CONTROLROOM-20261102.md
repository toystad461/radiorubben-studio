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
