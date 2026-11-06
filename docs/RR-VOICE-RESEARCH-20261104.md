# Radio Rubben: troverdig Thomas-stemme og flere daglige innslag

Sak: **RR-VOICE-20261104**. Undersøkt 04.11.2026, Europe/Oslo.
Status: **kildebasert anbefaling og utviklingsgrunnlag; ikke implementert**.
Kodegrunnlag: `2c27a8f8c7f03ad55c446ecc1785d9af942e7fd2`, PR #118.

## Siste avgrensning: få innslag og gjenbruk

Thomas presiserte deretter 04.11 at noen innslag om dagen er nok, og ønsker
at opptak kan brukes flere ganger samme dag og også på flere dager.
**Dette erstatter anbefalingen om 40 nye innslag i fire daglige puljer.**
Modellundersøkelsen under beholdes som faggrunnlag; det tidligere store
produksjonsopplegget og kostnadstabellen er historiske alternativer.

Ny anbefalt start er **seks–åtte avspillinger gjennom dagen**, hovedsakelig
fra godkjente lydfiler som allerede finnes. Antall avspillinger er ikke
antall genereringer. Størrelsene under er forslag, ikke bestilt eller
ferdig produsert lyd:

| Gruppe | Liten startbank | Gjenbruk |
| --- | --- | --- |
| Tidløse stikk | Seks korte opptak om musikk, selskap og Radio Rubben | Flere dager og uker; ingen ukedag, dato, vær eller bestemt neste låt |
| Ukedagsstikk | Ett opptak for hver av de sju ukedagene | På riktig ukedag, også kommende uker; uten dato eller aktuelle hendelser |
| Ferskt/lokalt glimt | Null–ett nytt innslag når det finnes godt innhold | Kan gjentas samme dag; varer flere dager bare hvis innholdet faktisk tåler det |

Et mulig døgn er to avspillinger av dagens ukedagsstikk, tre–fire forskjellige
tidløse stikk og ett lokalt glimt. Det er ikke krav om nytt innhold hver dag.
Start med små innslag på omtrent 8–20 sekunder. Avgrensningen gjelder ekstra
programlederstikk; timesekvensarbeidet er en egen leveranse og aktiveres ikke her.

Forslag til variasjon: samme fil høyst to ganger i døgnet og minst fire timer
mellom, basert på faktisk avspilling. Bytt rekkefølge og plassering fra dag
til dag. Godkjente opptak gjenbrukes uten nye TTS-kall. Bevar hele ytringer;
unngå hørbare skjøter ved å lime et enkelt ukedagsord inn i en annen setning.

Ukedag beregnes i Europe/Oslo. «Onsdag morgen» får både ukedags- og
tidsromsbegrensning; et rent onsdagsstikk kan brukes gjennom dagen.
Unngå eksakt klokke i gjenbruksbanken. Hendelser og nettfunn får konkret
siste gyldighetsdato og bevarer kildegrunnlaget. Et gammelt opptak får ikke
automatisk forlenget aktualitet fordi det velges igjen.

Godkjent fil, manus, stemme og kontrollsum gjenbrukes samlet. Studio trenger
en avgrenset gyldighetsordning før dette kan kjøres; dagens ettimeskontroll
skal ikke omgås ved å merke gamle stikk som nye. Kilde-/nyhetsregler og
godkjenninger bevares. Hver faktisk avspilling får egen kvittering.

Neste anbefalte pilot er redusert til **tre korte prøver** med eksisterende
Thomas-stemme: ett tidløst stikk, ett ukedagsstikk og ett lokalt glimt.
V2 beholdes som klangreferanse. Ni-prøvers innstillingsmatrisen er valgfri
fordypning hvis stemmen trenger mer arbeid. Ingen ny TTS eller automatikk
er aktivert som følge av denne avgrensningen.

## Behov og beslutningsgrunnlag

Thomas ønsker en kraftigere, troverdig og personlig programlederstemme,
flere forskjellige innslag og generering flere ganger daglig. Innholdet skal
kunne handle om dag, tid, musikk, lokale glimt og morsomme funn på nettet.
04.11 vurderte han den private lydprøven v2 som «mye bedre».
Dette er positiv lyttefeedback, ikke registrert sendegodkjenning i Studio.

Anbefalingen er å beholde Thomas M., bruke Eleven v4 til forhåndsprodusert
tale og utvikle tre uttrykk innenfor samme stemmeidentitet. Manus og faktisk
sendekontekst er like viktige som stemmeinnstillingene. V2 beholdes som
lydreferanse. Den dokumenterte behandlingen og overlappingene kan gjenbrukes,
men må tilpasses nye opptak; høyere lydnivå alene gir ikke bedre fremføring.

Dette notatet omfatter undersøkelse, manusutkast, prøvedesign og krav.
Det er ikke utført nye TTS-kall, kjøp, stemmekloning, utrulling eller sending.
Ingen tidsplan er aktivert. Ingen OneDrive-overføring er utført.

Thomas sitt konkrete valg om å fjerne den talte KI-setningen fra
timesekvensen beholdes. Opphav og versjoner bevares. Generell policy og
nyhetsflyten endres ikke gjennom dette notatet. Den ønskede nærheten skapes
gjennom henvendelse, rytme og relevant innhold. Manus skal ikke finne på
studioopphold, personlige opplevelser, intervjuer eller lyttermeldinger.

## Hva undersøkelsen viser

Undersøkelsen tok utgangspunkt i [ElevenLabs-introduksjonen](https://elevenlabs.io/docs/overview/intro)
og fulgte de relevante modell-, produksjons-, klonings- og API-veiledningene.
Tabellen skiller dokumenterte egenskaper fra vår anbefalte bruk.

| Område | Dokumentert hos ElevenLabs | Anbefaling for Radio Rubben |
| --- | --- | --- |
| Modell | V4 prioriterer kvalitet; Turbo er laget for lav ventetid. Bokmål, IVC og PVC støttes. | Behold v4 som førstevalg for ferdige filer. Test vår stemme; modellstøtte beviser ikke lokal uttale. |
| V4-kontroller | Stability og Similarity; PVC har også Clone Strength. Style, Speed og SSML støttes ikke som v4-kontroller. | Gjør Studio modellbevisst. Ikke lov effekt av generiske glidebrytere. |
| Fremføring | Lydinstruksjoner i hakeparenteser kan styre fremføring, men etterlevelsen varierer. | Prøv korte, presise instruksjoner etter en nøytral sammenligning. |

Kilde til modellopplysningene: [Eleven v4](https://elevenlabs.io/docs/overview/capabilities/text-to-speech/eleven-v4).

ElevenLabs beskriver lavere stabilitet som mer variert, med risiko for
ustabilitet; svært høy likhet kan gi lydfeil. For en v4-trent PVC anbefales
Clone Strength fra null som utgangspunkt. Dette er veiledning, ikke en
ferdig fasit for Thomas. [Veiledning om samtalestemmer](https://elevenlabs.io/docs/eleven-agents/customization/voice/best-practices/conversational-voice-design).

## Stemmen: først forbedre det som allerede virker

Følgende er **våre startverdier til lyttetest**, ikke dokumenterte optimale
innstillinger. De brukes per forespørsel, uten å endre den globale stemmen.

| Prøveprofil | Bruk | `stability` | `similarity_boost` |
| --- | --- | ---: | ---: |
| Nær | Rolig, direkte samtale med én lytter | 0.50 | 0.75 |
| Driv | Musikkovergang og ny time | 0.40 | 0.75 |
| Glimt | En lett observasjon eller morsom detalj | 0.45 | 0.75 |

Første sammenligning bruker identisk tekst og ingen lydinstruksjoner, slik
at vi kan høre virkningen av stabilitet. Deretter prøves én kort instruksjon
på vinneren, eksempelvis varm samtale eller munter fremføring. Instruksjonen
er et forsøk, ikke garantert syntaks med en bestemt effekt. Pust og pauser
skal få plass; ikke legg kunstig latter eller nøling i hvert innslag.
Tegnsetting og utskrevne tall brukes aktivt. Presise fremføringsbeskrivelser
er bedre enn tvetydige effektord. [TTS best practices](https://elevenlabs.io/docs/overview/capabilities/text-to-speech/best-practices).

Clone Strength utelates til stemmetype og modelltrening er verifisert.
Ved bekreftet v4-PVC kan null sammenlignes med en forsiktig økning hvis
dialekt/identitet blir for svak. Ingen tonehøydeforskyvning foreslås.
Uttalen av Rubbestadneset, Svortland, Mosterhamn, Bremnes og Lykling må
lyttes til. Eksisterende uttaleordbok gjenbrukes; fonetiske former skal ikke
gjettes. En eventuell ny uttalemåte godkjennes og versjoneres.

Hvis eksisterende stemme fortsatt begrenser kvaliteten, undersøkes en
Professional Voice Clone. ElevenLabs oppgir minst 30 minutter materiale,
anbefaler minst én time og helst nærmere to–tre timer. Opptakene bør ha én
taler, samme språk og en konsekvent talestil, uten musikk, romklang eller
forvrengning. Stemmeeieren må verifisere stemmen. PVC krever et abonnement
med slik plass. [Professional Voice Cloning](https://elevenlabs.io/docs/eleven-creative/voices/voice-cloning/professional-voice-cloning).

Vårt opptaksforslag er først en 15–20 minutters stilprøve, deretter et
sammenhengende materiale på én–to timer hvis denne retningen er god.
Thomas snakker som til én radiolytter, med energi og naturlig egen dialekt.
Ikke bland roping, reklamespeak, hvisking og nøytral opplesning i samme
treningsgrunnlag. Stilprøven vurderer fremføringen; den alene er ikke
det anbefalte komplette PVC-grunnlaget.

To alternativer er vurdert, men er andrevalg:

- [Voice Changer](https://elevenlabs.io/docs/overview/capabilities/voice-changer)
  kan bevare mye av fremføringen fra et innspilt utgangspunkt. Det kan passe
  særlige signaturinnslag; nye daglige tekster trenger fortsatt en fremføring.
- [Voice Remixing](https://elevenlabs.io/docs/overview/capabilities/voice-remixing)
  kan lage en justert variant av stemmen. Identiteten kan endres, og resultatet
  må sammenlignes med originalen. Først prøves eksisterende Thomas-stemme.

## Manus som høres sagt ut

Vår anbefalte redaksjonelle oppskrift er én tanke per innslag: en kort
inngang, en konkret detalj og en naturlig overgang tilbake til musikken.
Bruk «du» der det passer. Varier mellom spørsmål, observasjon, fakta og
direkte overgang. Ikke åpne alt med «visste du at», «hei igjen» eller
stasjonsnavnet. Et kort innslag trenger ikke både hilsen og avslutning.

Skriv for øret: korte setninger, naturlige sammentrekninger og små pauser.
Dag og klokke beregnes fra planlagt sendetid i Europe/Oslo. «I dag», «nå»,
«i kveld» og «snart» må ha et faktisk gyldighetsrom. Vær og nyheter hentes
gjennom eksisterende kildeflyter. Alvorlige saker får saklig tone.

Morsomme nettfunn behandles som kildestoff: åpne originalen, lagre
kilde/tidspunkt/faktagrunnlag, skriv en egen kort versjon og skill faktum
fra vår humoristiske kommentar. Et gammelt funn skal ikke kalles dagens
hendelse. Ukjent eller motstridende faktum holdes tilbake.

Eksempel på eget manusutkast med lokalt kildegrunnlag:

> Et hotellskip på Lykling? Ja, faktisk. Steinriket Bømlo forteller at det
> kom både hoteller og et hotellskip rundt gullgruvene. Her nøyer vi oss
> med å grave fram litt musikalsk gull. Du hører Radio Rubben.

[Steinriket Bømlo](https://steinriketbomlo.no/gull/) dokumenterer hotellene og
hotellskipet. Avslutningen er vår egen ordlek, ikke et sitat. Utkastet er
ikke generert eller redaksjonelt sluttgodkjent.

Studio bør føre historikk over faktisk sendte emner, innledninger og
kildefunn. Forslag til startregler: ikke samme kuriositet på nytt innen
sju dager, ikke samme kildefunn i flere puljer samme døgn og ikke samme
innledning blant de siste seks innslagene. Ny vesentlig informasjon kan
begrunne en oppdatering. Reglene er forslag og må tilpasses lytting.

## Tidligere alternativ: fire daglige puljer

Historisk forslag, erstattet av avgrensningen om få innslag og gjenbruk øverst.

Foreslåtte produksjonstider: **05:30, 10:30, 14:30 og 18:30, Europe/Oslo**.
De gir forslag til henholdsvis morgen, dag, ettermiddag og kveld/natt.
Hver pulje omfatter omtrent ti nye innslag. Puljetid er forberedelsestid;
innslag er først klare etter godkjenning, ikke automatisk på klokkeslettet.

| Type innslag | Forslag til lengde | Viktigste binding |
| --- | ---: | --- |
| Dag og tid | 5–12 sekunder | Riktig dato, tidsrom og faktisk forventet start |
| Musikkovergang | 8–15 sekunder | Den aktuelle låten/køforekomsten |
| Lokal kuriositet | 15–30 sekunder | Kontrollert originalkilde og repetisjonshistorikk |
| Lett nettfunn | 15–25 sekunder | Bekreftet faktum, egen formulering og relevant humor |
| Arrangement/service | 15–30 sekunder | Arrangørkilde og utløp før informasjonen blir feil |

40 korte innslag daglig er et startmål, ikke en regel om tale etter hver
sang. Fordel gjerne to–tre i timen på dagtid og færre om natten. Timesekvensen
må inngå i totalen, ikke komme som et ekstra identisk innslag hver time.
Ferske musikkglimt klargjøres nær avspilling, etter at køen er fastlagt.

Eksakt klokkeannonsering passer dårlig når en sang får spille ferdig.
«Litt over tre» kan brukes i et kort godkjent tidsrom; utenfor det brukes
en tidløs variant eller bare musikk. Dato og tid kontrolleres igjen ved
kølegging og avspilling. Bruk UTC-identitet for sendesporet og Oslo-tid for
språk, slik at midnatt og den doble timen ved vintertid håndteres entydig.

## Produksjon og sammenheng

Foreslått kjede, med eksisterende Studio-godkjenning:

1. Finn innhold og kontroller originalkilder; velg tidsrom og planlagt bruk.
2. Lag varierte manusutkast, og bevar alle manuelle rettelser.
3. La redaktøren godkjenne de konkrete manusene samlet i en pulje.
4. Generer tale på serveren, lagre rålyd og kjør teknisk kontroll.
5. Vis en samlet lytteprøve; godkjenn eller ta ut hvert innslag.
6. Klargjør filer og sett dem i kø først når godkjenning og gyldighet stemmer.
7. Registrer faktisk avspilling, slik at variasjon bygger på det lytteren hørte.

Puljegodkjenning skal være en enklere betjening av eksisterende kontroller,
med identitet og innholdshash for hvert innslag. Den skal ikke godkjenne
framtidige ukjente tekster. Ingen lydkall skal være nødvendig idet innslaget
skal på lufta. Ved feil, utløp eller manglende godkjenning fortsetter musikken.

Ett helt kort innslag genereres som én ytring. For en lengre sammenhengende
tekst kan Request Stitching gi lydlig sammenheng. Dokumentasjonen tillater
inntil tre tidligere forespørsels-ID-er; de bør være under to timer gamle.
Dette er ikke hukommelse om sendinger gjennom dagen. [Request Stitching](https://elevenlabs.io/docs/eleven-api/guides/how-to/text-to-speech/request-stitching).

TTS-endepunktet med tidsstempler returnerer tegnjustering som kan hjelpe ved
klipping og pauser. Støtte og nytte må prøves med vår norske stemme før bruk.
[TTS with timestamps](https://elevenlabs.io/docs/api-reference/text-to-speech/convert-with-timestamps).
En mulig tilleggskontroll er talegjenkjenning mot godkjent manus, spesielt
navn og tall. Den beviser verken korrekt uttale eller sanne fakta og erstatter
ikke lytting. [Speech to Text](https://elevenlabs.io/docs/overview/capabilities/speech-to-text).

Eksisterende PCM-format fungerer. Høyere original samplingsfrekvens kan
vurderes hvis abonnementet tillater det; oppsampling lager ikke nye detaljer.
API-et tilbyr kontekstfelt og seed, men seed garanterer ikke samme lyd.
Ta derfor vare på godkjente filer framfor å regenerere dem ved avspilling.
[TTS API](https://elevenlabs.io/docs/api-reference/text-to-speech/convert).

V2s moderate EQ/dynamikk og nivåjustering er et utgangspunkt. Bevar naturlige
ordslutter; ikke kryssfade to talte setninger slik at ord overlapper.
De tre musikkglimtenes 0,55 sekunders overlapping prøves på nye klipp med
hensyn til rytme og vokal. Fravær av stille hull beviser ikke musikalsk flyt.

## Konkrete mangler i den undersøkte Studio-koden

Dette er funn i oppgitt kodecommit, ikke en ny full produksjonsavstemming.

| Funn | Betydning og avgrenset endring |
| --- | --- |
| `app/audio-workflow.php` sender både `speed` og `style` for v4 | Lag modellspesifikk validering. Avklar faktisk API-behandling i kontrollert prøve; vellykket HTTP-kall beviser ikke effekt av et felt. |
| `app/studio-break.php` avviser alle hakeparentesinstruksjoner | Bevar rent manus. Tilføy en separat, avgrenset og versjonert fremføringsprofil; godkjenning bindes også til den faktiske leverandørteksten. Ikke fjern all validering. |
| `rr_studio_break_checked` utløper etter 3600 sekunder | Flere timer forhåndsproduksjon trenger eksplisitt gyldighetskontrakt per innslagstype. Bevar nyhetskontrollens egne frister og alle innholdsbindinger. |
| Arbeidslisten har grense på 30 aktive saker | Puljer, visning og arkivering må håndtere kapasitet. Arkiv bevarer originaler og historikk. |
| Musikkbiblioteket stopper ved 200 filer eller 2 GiB | Ikke importer alle døgnets småinnslag som permanente musikklåter. Gjenbruk RR Audio-lagring med kontrollert kobling til avspilling og egen bevaringsplan. |
| Uniweb har lokal dagsgrense på 3000 tegn; VPS mangler eget TTS-oppsett | Velg faktisk tegnramme og konfigurer hemmeligheter privat på serveren. Dette er ikke en begrensning i antall daglige ElevenLabs-puljer. |
| Timeprøven v2 har fjernet talt KI-setning, mens produksjonsprofilen fortsatt legger den til | Gjennomfør brukerens timebeslutning som en avgrenset, versjonert endring; ikke skriv om alle nyhetsprofiler. |
| Eksisterende host-varighetskrav passer ikke alle korte overganger | Avklar egen lengdeprofil for korte stikk. Ikke fyll på tekst bare for å komme over en teknisk minstegrense. |

Gjenbruk PHP/Composer, RR Audio, uttaleordbok, filhasher, revisjoner, roller,
godkjenningsflyt og spiller. Ingen ny samlet repository- eller Docker-
omlegging er nødvendig for denne leveransen. Produksjonen skal kjøre på VPS,
uavhengig av Thomas sin PC. Én seriell worker er nok som første løsning.

Hver jobb må ha stabil identitet, lås, inputhash, tidsrom, kildegrunnlag,
manus-/ordbok-/stemmeversjon, lydhash og godkjenninger. Reserver tegn før
eksternt kall. Bevar dagens `unknown`-sperre ved uavklart leverandørresultat;
ikke gjenta et mulig belastet kall blindt. Avviste rate-limit-kall kan få
begrenset senere forsøk, mens autentiserings- eller kvotefeil stopper videre
generering og vises i Studio. [ElevenLabs-feilkoder](https://elevenlabs.io/docs/eleven-api/resources/errors).
Nøkkel og faktiske kontogrenser ligger privat; kall begrenses med rettigheter
og budsjett. [API-nøkler](https://elevenlabs.io/docs/overview/administration/workspaces/api-keys).

## Tidligere volum og kostnadsanslag

Dette gjelder det opprinnelige forslaget om mange nye innslag, ikke den
reduserte gjenbruksmodellen. Avspilling av en ferdig fil krever ingen ny TTS.

Vår regnemodell: gjennomsnittlig 220 fakturerbare tegn per innslag,
25 prosent reserve til nye forsøk/varianter og 30 dager. Faktisk tekst,
instruksjoner og leverandørens registrerte forbruk må måles i pilot.

| Nye innslag/døgn | Tegn/døgn inkl. reserve | Tegn/30 dager | Beregnet TTS-forbruk ved USD 0,08/1000 tegn |
| --- | ---: | ---: | ---: |
| 24 | 6 600 | 198 000 | USD 15,84 |
| 40 | 11 000 | 330 000 | USD 26,40 |
| 64 | 17 600 | 528 000 | USD 42,24 |

Den offentlige API-prissiden oppgir v4 til USD 0,08 per 1000 tegn og Creator
til ordinært USD 22 per måned med 275 000 v4-tegn inkludert. Tabellen er
forbruksregning, ikke et pristilbud; abonnement, andre funksjoner og faktisk
kontoplan må avstemmes. Priser er uten avgifter. Tekstmodell, nettsøk,
talegjenkjenning, server og lagring er ikke inkludert. Ingen plan eller
budsjett er endret. [API-priser, kontrollert 04.11.2026](https://elevenlabs.io/pricing/api).

## Utvidet prøve og tidligere ferdigkriterier

Den første anbefalte piloten er nå tre opptak, som beskrevet øverst.
Ni-prøvers sammenligning og kravet om fire døgnpuljer nedenfor er ikke lenger
startomfanget. Sperrene for endret innhold, kilder og godkjenning består.

Neste anbefalte lydprøve: tre manus i de tre profilene over, totalt ni
private opptak. Dette notatet er ikke godkjenning av nye TTS-manus.
Bruk gjeldende konkrete manusgodkjenning før et betalt genereringsforsøk.

- **S1, tidløs ny time:** «Nå starter en ny time på Radio Rubben. Her er tre
  musikkglimt fra timen som kommer. God fornøyelse!» Samme tekst som v2.
- **S2, onsdag ettermiddag:** «Det er onsdag ettermiddag. Fra Rubbestadneset
  til Svortland og Mosterhamn: hyggelig at du er med. Nå tar vi en liten
  musikkpause.» Bare privat test; dagsutsagnet begrenser senere sendetid.
- **S3, lokalt glimt:** Lykling-utkastet i manusavsnittet over, med kildebelegg.

Prøvene sammenlignes ved likt opplevd nivå, både ubehandlet og med samme
behandling. Bevar v2 som referanse. Thomas vurderer gjenkjennelighet,
naturlighet, energi, lokal uttale og passende humor på en skala fra én til
fem. Foreløpig mål: minst fire på identitet/naturlighet, ingen feil i ord,
navn eller tall, og ingen fremføringsinstruksjon lest høyt. En valgt profil
må også tåle en ny generering; ett heldig opptak beviser ikke stabil drift.

Før daglige puljer er ferdige, må følgende være demonstrert:

1. Fungerende modellspesifikke innstillinger, tidsrom og lydproveniens.
2. Manus og ferdig lyd krever egne menneskelige godkjenninger; endringer,
   manglende kilde eller teststatus kan ikke passere til sending.
3. Fire serverpuljer gjennom ett døgn uten PC, med bevarte manuelle rettelser
   og kontrollert forbruk; ingen dobbeltgenerering etter omstart.
4. Utløpt dag/tid, endret kø, manglende lyd, API-feil og overskredet budsjett
   lar musikken fortsette og gir en forståelig status.
5. Midnatt, sommertid/vintertid, arbeidslistens kapasitet og medielagring er
   prøvd med kontrollerte data; originaler og aktive filer bevares.
6. Faktiske avspillingskvitteringer og gjennomlytting dokumenterer resultatet.
   Simulerte tester må ikke omtales som offentlig sending.

## Leveransestatus

Kontrollert: offisiell dokumentasjon og priser, de oppgitte kodegrensene,
eksisterende v2-kvittering og Thomas sin positive tilbakemelding.
Notatet og kildehenvisningene er kontrollert som dokumentasjon.
Ingen ny runtime-test er nødvendig for denne dokumentasjonsendringen.

Ukjent: om Thomas M. er IVC eller PVC, eventuell v4-fintrening, kontoplan og
ledig kvote. Eksisterende API-nøkkel har fungerende TTS, men mangler lesetilgang
til stemme-/modelloversikten. Det stanser ikke denne undersøkelsen.

Gjenstår etter siste avgrensning: tre korte prøver, vurdering av klang og
en liten gjenbruksbank med regler for ukedag, aktualitet og repetisjon.
Ni-prøvers sammenligning og fire daglige puljer er ikke lenger startplanen.
[Timearbeidets status](STUDIO-HOUR-RECOVERY-20261104.md)
og [felles utviklingsstatus](STUDIO-STATUS.md) gjelder fortsatt. Dette
notatet endrer ikke den tidligere forberedte kodepakken eller aktiverer
timesekvensen.
