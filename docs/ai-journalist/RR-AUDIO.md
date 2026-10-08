# RR Audio – integrert implementeringsplan og leveranse

Oppgave: [Studio #54](https://github.com/toystad461/radiorubben-studio/issues/54). Samme branch `feature/ai-journalist-2` og avhengige draft-PR #55, basert på læringsflyten i PR #51. Ingen utrulling eller sammenslåing er utført.

## Kartlegging og gjenbruk

[Repositorykart og runtimeaudit](INVENTORY.md) gjelder fortsatt: Studio er den bekreftede PHP-runtime på Uniweb, med selektivt deploymanifest. 491 filer ble sammenlignet mot live-hash før arbeidet; null drift. Git-checkout alene er ikke produksjonssannheten. Web-SAPI, FFmpeg og ElevenLabs-kontotilgang er ikke verifisert i produksjon.

- Studio: `case.php`, `news-script.php`, `case-workflow.php`, `board.php`, eksisterende CSRF, roller, faktakontroll, revisjoner og læringsregister gjenbrukes. Radio/nett deler et originalsnapshot; RR Audio skriver et selvstendig manus fra samme kilde. Varianter lagres på samme sak som `audioScripts`, valgt sendefil som `audioQueue`. Ingen ekstra sendelistedatabase.
- Programregisteret gjenbruker den rene `app/programs.php`-modulen fra eksisterende `feature/god-morgen-vestland-learning` / PR #13, commit `7f72fbf7a9e0e33b488c74d8c0251021f9bbffd7`, under `overlays/uniweb/studio-private/app/programs.php`. Hele den eldre branchen er ikke flettet inn. Ufordelte eldre saker blir ikke automatisk tilordnet.
- `radiorubben-web`: eksisterende WordPress-bro beholdes. Radio.co-pluginen viser offentlig status/spiller og har ikke en etablert opplastingsflyt. WordPress-fotballkøen er ikke en godkjent, versjonert full faktapakke. NFF-faktagrunnlag må kobles inn med en avklart, lesende adapter før slike saker kan bruke automatisk Audio-flyt. Sportsprofilen kan brukes på støttede originalkilder nå.
- `RadioRubben-robot`: eksisterende RSS-innhenting/eksport gjenbrukes uendret. Ingen ny TTS-tjeneste eller parallell robot er opprettet.

## Implementert nå

| Trinn | Implementasjon | Sperrer |
| --- | --- | --- |
| Original og manus | Felles originalsnapshot, åtte profiler, samme Writer/FactCheck/Ethics og godkjente programråd | Ingen nettprosa som kilde; ingen automatisk godkjenning |
| Redigering | Manusvarianter i eksisterende Sak, optimistisk revisjon, historie | Lagret endring fjerner kontroll/godkjenning og valgt sendelyd |
| TTS | Fast HTTPS-endepunkt, servernøkkel, godkjente programstemmer, `eleven_v4` som valgt modell (v2 beholdt for eldre eksplisitt konfigurasjon), hastighet/uttrykk | Deaktivert standard, dagsbudsjett, ett pågående/uavklart kall, ingen automatiske omforsøk |
| Lagring | Immutable tilfeldige filnavn i privat `config/audio`, SHA-256, autentisert forhåndslytting | Ingen nøkler i frontend, ingen kundevalgte stier, ingen lyd i releasepakken |
| Lydbehandling | PCM16 mono fra 24 kHz TTS, WAV-master; WAV eller MP3 sendefil; FFmpeg loudnorm og etterkontroll | Godkjent versjonert stasjonsprofil kreves; varighet, stillhet, klipping, samplerate, LUFS og sant toppnivå kontrolleres |
| Sluttgodkjenning | Separat bekreftelse etter gjennomlytting | Faktakontroll, manus, stemme, ordbok, profil og filer må fortsatt stemme |
| Sendeliste | Eksisterende punkt får godkjent lyd, avspiller og autentisert filnedlasting | Ny kontroll ved køvalg og hver sendefilforespørsel; kopiert URL omgår ikke godkjenning |
| Uttale | Versjonert register på samme board; manuelt forslag og adminaktivering | Fire lokale navn er frø uten gjettede uttaler; aliaser endrer bare transporttekst |

Langvarige prosesser kjører uten board-lås, men reserverer token og kostnadsbudsjett før eksternt kall. Resultatet kan ikke overskrive et nyere manus. Feil/timeout med uavklart leveranse krever dokumentert avklaring av leverandørhistorikk før nytt forsøk. Også feil teller konservativt mot lokalt dagsbudsjett; dette er en tegnbasert kostnadssperre, ikke leverandørens faktura. En råfil kan behandles igjen uten ny TTS-kostnad.

Normalisering bruker FFmpegs `loudnorm`, med mål fra godkjent profil. Prosessgrense er 45 sekunder per FFmpeg-kall. Senderen kontrolleres etter eventuell MP3-koding, fordi komprimering kan påvirke toppnivå. Ubehandlet klipping kan ikke godtas ved bare å skru ned lydstyrken. Syntetiske testverdier er ikke Radio Rubbens valgte profil. Ingen automatisk klipping av pauser eller tidsstrekking er innført.

## Avklaringer før aktivering

Eier har bekreftet godkjent stemme og oppgitt `azrGjm6gYkR15bxb9cVv`. Dette ligger som eierbekreftet stemme i eksempelkonfigurasjonen. Eier har senere godkjent stemmen i alle Radio Rubben-sammenhenger (`scope=all`), også saker uten program. Dette tilordner ingen programmer og endrer ikke programisolert læring. TTS er fortsatt deaktivert. Det er ikke gjort et betalt prøvekall.

Følgende gjenstår:

1. Prøveprofilen er godkjent av eier: `rr-tale-trial-0.1.0`, −18 LUFS (±0,5), maks −2 dBTP, PCM16 mono WAV-master ved 44,1 kHz, MP3 192 kbps CBR ved 44,1 kHz, maks ett sekund sammenhengende stillhet. Dette er godkjenning for prøvekjøring, ikke produksjonsutrulling. Nivå og uttale skal vurderes i faktisk sendekjede før produksjonsbruk.
2. Installere/konfigurere nøkkel privat på server eller som `ELEVENLABS_API_KEY`, kontrollere stemmetilgang/modell/abonnement, sette dagsbudsjett og sikre riktig lagringskapasitet, backup og sletterutine. Nøkkel skal ikke deles i chat eller frontend. Leverandørens standardlogging er ikke null-lagring; eventuell databehandler-/retensjonsavklaring må gjøres før sensitiv redaksjonell tekst sendes.
3. Verifisere FFmpeg, `proc_open`, curl, mbstring, rettigheter og tidsgrenser i faktisk web-runtime. Den lokale FFmpeg-testen beviser ikke Uniweb-støtte. Dersom hosting sperrer prosesser, må en eksplisitt avklart lydarbeider bruke samme kontrakt og godkjenningsflyt; en slik produksjonstjeneste er ikke opprettet.
4. Gjennomføre ekte stemme- og uttaleprøve, høretest på mobil og avtale lytterinformasjon om KI-stemmen. Ingen automatisk radio.co-overføring finnes i denne fasen.
5. Avstemme PR #51/#53 og aktuell selektiv runtime, gjennomgå migreringsfri lagring og release-diff. Full pakke må fortsatt ikke erstatte selektiv produksjon. **Eksplisitt godkjenning kreves før merge til automatisk deployende main eller annen produksjonsutrulling.**

Det er ikke bedt om ny bekreftelse på allerede gitt stemmegodkjenning. Programomfang og prøveprofil er nå avklart. Sikker nøkkellagring, runtime og faktisk høreprøve gjenstår.

## Radio.co – dokumenterte grenser

[Radio.co API](https://www.radio.co/api) og [developer-portalen](https://developers-84608658bd058c817.radio.co/api-reference) er undersøkt. Portalen lenker til en [Studio API-referanse](https://developers-84608658bd058c817.radio.co/api-reference/openapi_specs/studio), men opplastingskontrakt og kontotilgang er ikke verifisert her. Det er derfor ikke implementert et gjettet upload-endepunkt eller automatisert innlogging. Leveransen stopper ved kontrollert fil i eksisterende sendeliste. Avklar støtte med Radio.co og test autentisering, metadata, idempotens og feiltilfeller før en overføringsadapter aktiveres.

## Tester og etterprøvbarhet

`tests/recovered/test-audio.php` dekker kjeden med mock-TTS og mock-FFmpeg, samt faktakrav, roller, budsjett, feil/timeout, regenerering, samtidige endringer, ordbokversjoner, filintegritet, varighet/stillhet/klipping, source sharing og manuelle godkjenninger. HTTP-responsklassifisering testes separat fra nettverkstransport. `test-audio-file.php` kontrollerer anonymtilgang, forhåndslytting, sendegodkjenning, endret fil, arkivering og metodekrav. Sakens eksisterende CSRF-/rolletester og deaktivert TTS-test kjøres også.

`test-audio-mobile.cjs` bruker faktisk Sak-mal og CSS ved 375, 390 og 1280 piksler. Profiler, manusfelt, uttaleordbok, fravær av uautoriserte knapper og horisontal bredde kontrolleres. Skjermbilde er visuelt gjennomgått. Dette erstatter ikke en ekte avspillingstest mot produksjonsnettleser og autentisering.

Samme kjedetest er kjørt med en midlertidig FFmpeg 7.1 fra `imageio-ffmpeg==0.6.0` i `/private/tmp`, både med WAV og MP3 som sendefil. Inndata er syntetisk sinuslyd, ikke en faktisk ElevenLabs-stemme. Ingen prosjekt- eller produksjonsavhengighet er installert av den testen. Gjenta lokalt med `RR_AUDIO_TEST_FFMPEG=/absolutt/ffmpeg php tests/recovered/test-audio.php`; sett også `RR_AUDIO_TEST_FORMAT=mp3` for MP3. Standardtestene krever ingen leverandørkonto eller FFmpeg.

Tidligere artikkelsammenligning ligger i [COMPARISON.md](COMPARISON.md); den måler ikke RR Audio-stemmekvalitet eller faktisk forbedring hos en kjørt modell.

Tekniske primærkilder: [ElevenLabs TTS](https://elevenlabs.io/docs/api-reference/text-to-speech/convert), [stemmeinnstillinger](https://elevenlabs.io/docs/api-reference/voices/settings/get), [uttaleordbøker](https://elevenlabs.io/docs/eleven-api/guides/how-to/text-to-speech/pronunciation-dictionaries), [FFmpegs loudnorm-eksempel](https://github.com/FFmpeg/FFmpeg/blob/master/tools/loudnorm.rb). Kontroller leverandørendringer igjen før aktivering.

Siste lokale kontroll: `php tests/run.php` fullført med 403 PASS/OK-linjer, ingen feil; pakke og private dataeksklusjoner passerte. `php tests/entra-preflight.php`, eksisterende JavaScript-saksflyt og deploy-sperre passerte. Nyhetsdesk (375/390/800/1280), læring (390/1280) og RR Audio (375/390/1280) passerte i Chromium. Eksisterende OpenID Connect-avhengighet gir PHP 8.4-deprecation-meldinger om nullable-parametere; den er ikke endret i denne leveransen. Ingen nye warnings/fatale feil i testløpet. GitHub CI kontrolleres separat på publisert branch.


## Sikker innlegging av nøkkel på utviklingsmaskinen

Eier kjører `python3 scripts/store-elevenlabs-key.py` i sin egen Terminal fra denne arbeidskopien. Skriptet tar nøkkelen med skjult inntasting, avviser piping/argumenter, lager `config/elevenlabs.key` eksklusivt med rettighet 600 og nekter å overskrive en eksisterende fil eller symlink. Ingen nettverkstrafikk, opplasting eller aktivering. Nøkkelen vises ikke i terminalutdata, Git, PR eller releasepakke. Ikke send nøkkelen i chatten.

`load_config` kan lese denne private filen; en eksplisitt nøkkel i privat `local.php` eller `ELEVENLABS_API_KEY` har prioritet. Lesing av filen krever at den ikke er en symlink og ikke er lesbar av gruppe/andre. Serverplassering må senere gjøres separat i faktisk privat config-mappe etter avklaring; lokal lagring er ikke bevis på serverkonfigurasjon.

Den godkjente prøveprofilen er kjørt gjennom faktisk FFmpeg med syntetisk lyd, inkludert omforming fra 24 kHz rålyd til 44,1 kHz WAV og 192 kbps MP3. Ingen påstand om at oppsampling gjenskaper manglende lydinformasjon. Målrettede tester dekker global stemmetilgang, fortsatt tilbakekallings-/rettighetssperre, avslag på ukjent program og privat nøkkellagring. Egen stemmegenerering er fortsatt uprøvd.

## Oppdatert testutrulling – 08.10.2026

Eier har nå autorisert Studio-testing med ekte saker. Eleven v4 med
`azrGjm6gYkR15bxb9cVv` er prøvd med ekte API og hørt av eier. Fiktiv morgensending
ble laget separat og er ikke en faktakilde eller en planlagt sending.
Tidligere formuleringer om uprøvd TTS beskriver opprinnelig implementeringsstatus.

Uniwebs faktiske PHP-web-runtime sperrer `proc_open`. Første Studio-test gir derfor
manus, godkjenningssperrer, TTS og privat rålyd-avspilling. Ingen automatisk
normalisering aktiveres på dette webhotellet. Den lokalt brukte to-pass-behandlingen
er ikke integrert i produksjon. Testmodus sperrer sendeliste og sendefilnedlasting.
En egnet lydarbeider med samme lagrings-/godkjenningskontrakt er en videre avhengighet.
Privat `config/audio-local.php` styrer aktivering/budsjett uten endring av innlogging.
