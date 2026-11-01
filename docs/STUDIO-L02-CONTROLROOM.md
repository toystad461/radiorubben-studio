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
