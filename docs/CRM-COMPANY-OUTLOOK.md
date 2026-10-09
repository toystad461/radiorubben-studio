# CRM: registeroppslag, forslag og Outlook-demo

Implementert på `feat/crm-company-outreach`, basert på main `f10fbff`.
Dette er en kodeleveranse til review, ikke en produksjonsaktivering.

## Bruk

1. Åpne Ny bedrift, skriv organisasjonsnummer og velg Hent fra Brønnøysundregistrene.
   Navn, adresse, organisasjonsform, bransje og registrert hjemmeside fyller tomme
   felt. Egne felt bevares. Lagre etter kontroll av riktig bedrift.
2. Åpne Lag introduksjonsmail og lydforslag. Lag forslagspakken og tilpass
   introduksjonsmail, reklamemanus og forslag til programsponsing.
3. Godkjenn lagrede tekster. Bestill lydprøven med en godkjent kommersiell stemme,
   og trykk Start bestilt lydprøve. Dette bruker ElevenLabs-kvoten.
4. Lytt til hele prøven. Korrekt navn, uttale og hørbar KI-merking må bekreftes.
   Faktisk varighet må være 15–20 sekunder; feil lengde krever manuell justering.
5. Kontroller mottakeren og godkjenn pakken. Last ned .eml-utkast med WAV-vedlegg.
   Åpne i Outlook og send selv. Eksport merkes aldri som sendt kundemelding.

`.eml` bruker `X-Unsent: 1`, kodet norsk emne, UTF-8-tekst og et MIME-lydvedlegg.
Redigerbar åpning av .eml varierer med Outlook-versjon og er ikke prøvd i brukerens
Outlook. Ved åpning som mottatt melding kan videresending brukes etter kontroll
av tekst og mottaker. Studio sender ingen e-post, oppretter ingen Graph-tilgang,
og publiserer ingen kundelenke eller CRM-side.

## Kildegrunnlag

Offisiell dokumentasjon kontrollert 10.10.2026:
https://data.brreg.no/enhetsregisteret/api/dokumentasjon/no/index.html

Oppslaget bruker bare GET `/enheter/{orgnr}`, med fast HTTPS-vert, sertifikatkontroll,
ingen omdirigering og begrenset tid/størrelse. Kontrollsiffer, identitet og feilstatus
valideres. Avdelingsnummer uten hovedenhet, konkurs/sletting/avvikling krever manuell
behandling. Ingen person-/rolledata hentes. Et minimalt kildesnapshot med URL,
tidspunkt og hash lagres privat fra serverens oppslag, aldri fra et klientfelt.

Hjemmeside fylles når registeret oppgir en adresse. Vi gjetter ikke manglende
hjemmeside eller e-post. Nettsider skrapes ikke. Første tekstforslag er en fast,
redigerbar mal med bedriftsnavnet, ikke AI-basert analyse av bedriftens nettside.
Tjenester, kampanjer, priser, programflater og påstander må kontrolleres manuelt.
Et forslag er aldri bevis på sponsoravtale eller redaksjonell omtale.

## Privat oppsett før ekte TTS

Eksisterende ElevenLabs-transport, private nøkkel, WAV-lagring, måling og hørbar
KI-merking gjenbrukes. CRM har egen opt-in i `rr_audio`, via privat `audio-local.php`:

- `crm_demo_enabled`: av som standard.
- `crm_demo_daily_character_limit`: 0 som standard. Eieren velger eget budsjett.
- `crm_demo_voice_ids`: tom liste som standard. Valgte stemmer må i tillegg ha
  `approved=true` og dokumentert bruksrett i eksisterende stemmeregister.
  Listen er eksplisitt tillatelse til kommersielle kundedemoer.

Ingen private verdier endres i denne leveransen. RR Audio-testmodus og ordinær
sending påvirkes ikke. WAV-demoen trenger ikke FFmpeg/proc_open; den er et rått
kundeksempel, ikke en ferdig normalisert sendefil.

Lagring av bedriftskort gjør ingen TTS-kall. Bestilling reserverer budsjett under
CRM-låsen. En separat, manuelt startet jobb gjør ett begrenset leverandørkall uten
å holde CRM-/sesjonslåsen. Nettleseren viser arbeid og resultat. Ingen cron eller
varig bakgrunnsarbeider opprettes. En bestilt jobb kan startes etter ny sideåpning.
Timeout/avbrutt jobb må avklares manuelt; ingen automatisk gjentakelse eller
budsjettrefusjon. Produksjonsbruk krever at verten tåler det avgrensede HTTP-kallet.

## Sperrer og historikk

Administrator, POST og CSRF gjelder for alle endringer og eksport. Privat lyd
krever administrator og riktig kort/jobbreferanse. Forslag, godkjenninger og
lydfiler bindes til bedriftsgrunnlag, mottaker, manusversjon og filhash. Endringer
opphever godkjenning, også ved senere gjenoppretting. Stemmetilgang kontrolleres
igjen ved eksport. Flere samtidige bestillinger og uavklarte resultater blokkeres.
Avslåtte, arkiverte og pausede kort klargjøres ikke. Tidligere utkast bevares.

## Validering

- Full Windows/PHP 8.4 Composer-test består, inkludert eksisterende Studio-pakke.
- Nye domenetester dekker registerdata, duplikater, rolle, budsjett, godkjenning,
  eksakt MIME/WAV-vedlegg, endret mottaker/lydfil, stemmetilgang, timeout og varighet.
- 14 rutetester dekker adgang, metode, CSRF, lagring og feilhåndtering.
- Mobil-/autofyllingstester på 320/390/800/1280 px inngår i GitHub Actions.
- Ett ekte, lesende oppslag av Brønnøysundregistrenes eget nummer 974760673 bestod
  med full TLS-verifisering. Lokal PHP måtte få eksisterende Git CA-fil via
  `-d curl.cainfo=...` for den testen; ingen global innstilling ble endret.
- Ingen ekte kunder, betalt TTS, kundeutsending eller produksjonsdata er endret.
  Faktisk stemmekvalitet og brukerens Outlook-utgave gjenstår å prøve.

Neste steg: grønn Linux-/mobil-CI, review og eksplisitt produksjonsgodkjenning.
Deretter privat CRM-budsjett/stemmevalg og én kontrollert lyd-/Outlook-prøve.

## Navnesøk og eksisterende kort – 10.10.2026

«Finn og legg til bedrift» søker etter minst to tegn i CRM og Brønnøysundregistrene.
Trefflisten viser inntil 20 lokale treff (inkludert arkiv) og 10 registertreff med
navn, organisasjonsnummer, sted og merking for allerede registrert bedrift.
Ved flere registertreff bes brukeren avgrense navnet. Ved registerfeil er lokale
treff fortsatt tilgjengelige med tydelig feilmelding. Endrede søk fjerner gamle
valg; forsinkede svar kan ikke erstatte et nyere søkeresultat.

Velg treff og «Åpne valgt bedrift». Lokale treff åpner kortet. Registertreff hentes
på nytt med organisasjonsnummer og matches på nummer eller normalisert navn etter
samme regler som duplikatsperren. Flere identitetsmatch eller samme navn med et
annet organisasjonsnummer krever manuell avklaring. Ingen automatisk sammenslåing.

Nye treff klargjør kandidatkort. Eksisterende registertreff foreslår nye,
ikke-tomme registerfelt på samme kort. Kontaktperson, telefon, e-post, notater,
status og oppfølging beholdes. Brukeren kontrollerer og lagrer. Ingen CRM-data
skrives av søket eller valget. Lagring beholder lås, revisjonssperre,
duplikatkontroll og historikk. Registergrunnlaget utløper etter én time.
Ulagrede endringer varsles før et annet treff åpnes.

API: https://data.brreg.no/enhetsregisteret/api/dokumentasjon/no/index.html
Navneparameter på enheter, avgrenset størrelse. Ingen person-/rolleoppslag.
TTS og private budsjett-/stemmeinnstillinger endres ikke.

## Hjemmesidebasert reklameutkast – 10.10.2026

«Hent hjemmeside og lag reklameutkast» leser den lagrede offentlige HTTPS-siden
og ber Studios eksisterende tekstgenerator skrive et første reklamemanus.
Denne leveransen leser én side, med inntil to videresendinger på samme domene
(www-variant tillatt), ikke et helt nettsted. JavaScript-sider, innlogging og
mangelfull/for omfattende tekst stopper med beskjed; ingen generisk tekst
presenteres som et vellykket hjemmesideutkast. Gamle utkast bevares ved feil.

Generatoren mottar bare bedriftsnavn, offentlig hjemmeside og hentet tekst,
ikke CRM-notater, kontaktperson, e-post eller historikk. Ett begrenset modellkall
per klikk; ingen automatiske forsøk eller TTS. Eksisterende OpenAI-konfigurasjon
gjenbrukes. Nettinnhold er ubetrodde data. Adressekontroll, offentlig IPv4,
DNS-binding per kall, verifisert TLS, ingen proxy/credentials, begrenset svarkropp
og manuell redirectkontroll beskytter innhentingen.

Manus, originalutkast, modell, URL, hentetid, kildehash og kildeutdrag lagres
sammen. Utdrag sjekkes mot hentet tekst. Dette garanterer ikke sannhet eller at
alle påstander er dekket: operatøren må kontrollere riktig bedrift og innhold.
Grunnlaget utløper etter 24 timer og må da hentes/genereres på nytt.
Endret grunnlag, nettside eller manus krever ny godkjenning; tekstendringer
beholder opprinnelig kilde og manus i historikken.

Ett reklamefelt brukes i samlet meldingsforhåndsvisning, eksport og TTS.
TTS legger kun til den obligatoriske hørbare KI-merkingen foran manuset.
Manuell godkjenning av melding og mottaker gir .eml-eksport uten lyd mens TTS
er deaktivert. Dette er ikke manusgodkjenning for TTS eller lyttegodkjenning.
Eksport sender ingen mail eller registrerer kundekontakt. Utkastet merkes
KI-støttet i meldingsteksten. Lydversjonen beholder separat godkjenning.

Responses/strukturert svar følger:
https://developers.openai.com/api/docs/guides/structured-outputs?api-mode=responses
Ingen betalt AI-/TTS-kjøring eller produksjonsdata brukes i testene.
