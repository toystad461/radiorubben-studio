# L02 – kontrollrom, første avgrensede leveranse

Arbeidsområder: Teknisk Produksjon, Musikk & Tagging, Kontrollsenter.
Grunnlag kontrollert 01.11.2026: #109, merget #111 på
feat/music-scheduler-vps-playout @ 4fcdff392475db0c03fefa8b6990f77edcc223d2.

## Første leveranse

- Kø og bibliotek gjenbruker uendrede DOM-rader ved oppdatering, også når køens
  revisjon endres. Lange lister ruller separat på store skjermer.
- Forhåndslytting/detaljer åpnes i en separat dialog uten automatisk lydstart.
  Brukeren starter privat originallyd fra eksisterende autentiserte, versjonerte
  musikkrute. Lukking og skjult fane stanser privat lytting. Ingen kø-, motor- eller
  journalendring sendes. Valgt køforekomst beholdes.
- Detaljer og cue-redigering gjenbruker eksisterende bibliotek i egen fane.
- Nå/neste skiller sist kjent status, reservert neste og neste i synkronisert kø.
  Reservert låt har prioritet foran en senere, ureservert låt. Frakoblet/foreldet
  status fremstilles ikke som bekreftet avspilling.
- Aktive og reserverte forekomster er fortsatt sperret for endring; privat
  forhåndslytting kan brukes uavhengig av disse sperrene.

## Avstemming

PR #103 har kildebundne cue-punkter og manuell forrang. PR #106 har egne
bibliotekvinduer, cover og køhandling, selektivt installert på test-VPS.
Disse filene erstattes ikke. #104s analysearbeider endres ikke. L01-motorens
PCM-/hendelsesprotokoll, filkopier og sendekøformat beholdes.

## Kontroll

Lokalt: JS-syntaks, PHP-syntaks, eksisterende spillerstatus- og
kø/polling/monitorregresjoner består. Utvidet ekte Chromium/Edge-test ved
390 og 1280 piksler består med syntetisk lyd, private preview-kall, uendret
motorstatus/antall skrivekall, vedvarende radidentitet, dialog og utvalg,
reservert neste, foreldet status og eksisterende PHP-/Liquidsoap-monitor.
Dette er nettleserbelegg med fixture-API, ikke en ny faktisk VPS-sending.

## Selektiv testinstallasjon

Bare public/music-studio.php, public/assets/music-studio.mjs og
public/assets/music-studio.css inngår. Førhasher fra faktisk VPS:

| Fil | SHA-256 før |
| --- | --- |
| public/music-studio.php | 3551b2ef117e80f62d1fbc7bb4bbb83b71d746a87375d7338053477ff447ea88 |
| public/assets/music-studio.mjs | 18e29e380783a104302d779789061ff0ae29ef3651ee55c956aeb309c8a42524 |
| public/assets/music-studio.css | 43bab865910c87d3e2362a3f96b881744f89aec0b9deb678b24ebf80e530738e |

Før utrulling kontrolleres stoppet motor, eksakte førhasher og privat backup.
Installerens kandidatmanifest inkluderer nå CSS; den opprinnelige L01-pakken
og dens private backup beholdes. L01-nyinstalleren skal ikke kjøres over en
allerede installert motor. Testoppdateringen er tre UI-filer, ikke hele grenen.

## Gjenstår før L02 kan ferdigmeldes

Koble eksisterende kildebundne inn-/utpunkter og overgangsverdier til faktisk
Liquidsoap-avvikling, med korrekte varigheter og historikk. Avstemme og støtte
lydtypene stikk, jingle, reklame, lydteppe og program gjennom samme mediegrunnlag.
Prøve redigering/privat lytting under faktisk sending og kontrollere originale
medier og sendelyd. Første UI-leveranse er ikke fullført L02.

## Faktisk leveranse og neste trinn

## L02 – første kontrollromsdel installert, 01.11.2026 kl. 21:23 Europe/Oslo

Thomas autoriserte videreføring av L02. Første avgrensede leveranse er i PR #115,
gren feat/studio-l02-controlroom, kodecommit 39e58b13a83b93f5d472c5abfa34ff21fc614d23.
Base er merget L01 på feat/music-scheduler-vps-playout, ikke main.

Kø og bibliotek beholder uendrede rader, utvalg, tastaturfokus og rulleposisjon.
Privat forhåndslytting/detaljer åpnes separat. Eksisterende cue-editor gjenbrukes
i egen fane. Nå/neste viser reservert låt før senere ureservert innslag og
skiller sist kjent status fra bekreftet motorstatus.

Alle tre CI-workflows på kodecommitten besto: 41346214350 (PHP/mobil),
41346214368 (Liquidsoap/kontrakt/nettleser) og 41346214450 (releasekontroll).
Lokale syntaks-/regresjonstester og utvidet ekte Chromium/Edge ved 390/1280
besto også. Én lokal kjøring fikk tidsavbrudd ved køplanoppdatering; ny kjøring
med diagnostikk besto, og samme funksjonelle tester besto i CI.

**Faktisk installert:** eksisterende root-eide VPS-gateway returnerte deployed.
Kun public/music-studio.php, public/assets/music-studio.mjs og
public/assets/music-studio.css ble endret. Før-/etter-snapshot av alle gatewayens
kodefiler viser nøyaktig disse tre endringene; recoveryRequired=false.
Privat backup: /var/lib/rr-vps-deploy/backup-2c5kcroc.

Etter vanlig Microsoft-innlogging ble ny visning og dialog kontrollert på VPS.
Alison Moyet-originalen spilte privat i nettleseren: currentTime 7,777 sekunder,
readyState 4, ingen mediefeil, varighet 239,334 sekunder. Lukking stoppet lokal
lytting; motoren sto stoppet. Dette er faktisk dekoding/avspilling i nettleser,
ikke en ny subjektiv lyttebekreftelse eller testsending gjennom Liquidsoap.
Kø33 med fire forekomster, kontrollmanifest, journal, bibliotek og alle fem
originalfiler har identiske SHA-256 før deploy og etter privat lytting.

Tilbakeføring: bruk den klargjorte tre-filers reverspakken gjennom samme gateway.
Den verifiserer nåværende etterhasher før den gjenoppretter nøyaktig L01-UI.
Lokal kvittering og reverspakke ligger i denne oppgavens output/l02-deploy-receipt.json
og output/l02-rollback.json. Ved senere full L01-rollback må L02-UI tilbakeføres
først; L01-installeren skal fortsatt avvise ukjent nyere UI-kode. L02-rollback er
klargjort, ikke kjørt som ny gjenopprettingstest.

**L02 er fortsatt under arbeid:** lagrede cue-/overgangsverdier må inn i faktisk
Liquidsoap-avvikling, øvrige lydtyper må avstemmes, og redigering/privat lytting
under faktisk sending må prøves. Se STUDIO-L02-CONTROLROOM.md. Ingen merge av
#115, ingen produksjons-, WordPress-, DNS- eller radio.co-endring.
