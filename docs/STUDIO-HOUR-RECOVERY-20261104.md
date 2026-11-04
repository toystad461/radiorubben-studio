# Timesignal, oppstart og stemmetilgang – 04.11.2026

Arbeidsidentitet: RR-HOUR-20261104. Grunnlag: PR115 ved
`75c6e5688902b456fde653672a0a28bfb4134634`, fersk spillerjournal og isolert test
på samme VPS med Liquidsoap 2.2.4. Ingen endring av synkroniserte prosjektkilder.

## Verifisert hendelse og retting

Ved oppstart med det 1,2 sekunder lange timesignalet først rapporterte motoren
start og slutt ved samme PCM-klokke. Varighetskontrollen avviste null sekunder
og blokkerte sendingen. Dette er gjenskapt med syntetisk lyd i en separat
HTTP/Liquidsoap/Icecast-kjede. Det forklarer signalstoppet, ikke årsaken til
serverens tidligere omstart.

`cross` kan kalle overgangsfunksjonen med tom utgående buffer under innlesing
av et kort førstespor. En slik melding er ikke bevis på ferdig avspilling.
Rettingen holder derfor igjen sluttmeldingen til et nytt spor faktisk når
utgangen, eller til lydkilden er tømt. Overganger med en reell lydhale beholder
eksisterende PCM-klokkemåling. Kravet om riktig avspilt varighet er uendret.

Regresjonstesten dekker signal først, mellom hele sanger og tre signaler med
signal både først og sist. Den dekoder den mottatte MP3-strømmen og måler
begge 880 Hz-pulsene, hele sanglengder, rekkefølge og mellomrom. Tre signaler
ga seks pulser på 0,16 sekunder og 1,2 sekunders varighet per signal.
Timeplanen testes dessuten over tre påfølgende simulerte timer med beholdt
avspillingshistorikk og uten duplikat. Dette er ikke tre observerte timer
på offentlig sending.

Bestått i isolert miljø: vanlig signal mellom sanger; signal først; tre signaler
inkludert siste plass; musikkoverganger på 0/3/10 sekunder med målt lydblanding;
omstart uten gjentakelse og respekt for operatørens Stop. PHP-kontrollene for
timeplan, manglende/foreldet klipp, endret kilde/kø, spillerhendelser og tilstand
består. Full GitHub-CI på kodecommit
`a0108b1e1aeb0b71caa77198c55499f9c2e1ccc7` består, inkludert actual-stream
(9 minutter), PHP 8.2/8.4 og mobilkontroll. Køtesten bruker nå samme fillås og
atomiske lagring som avspilleren når en manglende lydfil injiseres; manifestet
er oppdatert til den testede Liquidsoap-malen. Se kjøring `41840121569` og
PHP-kjøringene `41840112813`, `41840121534` og `41840121573`.
Den komplette eksisterende transporttesten besto også (81,83 sekunder):
HTTP/rolle/CSRF, avbrudd, endret/manglende lyd, mistet forbindelse og frosset
PCM-motor. Ingen av disse feilene ble feilaktig registrert som fullført lyd.

## Eksisterende stemme og API

Tidligere status dokumenterer et vellykket Eleven v4-kall 08.10.2026 med
stemmen `azrGjm6gYkR15bxb9cVv` (Thomas M.). Studio-testen ble publisert samme
dag med privat `config/audio-local.php` og `config/elevenlabs.key`. En lokal
kontrollkvittering fra 12.10 bekrefter TTS på, nøkkel lastet, modell
`eleven_v4` og testmodus på den eldre Uniweb-installasjonen. Se også den
historiske RR Audio-statusen i `STUDIO-STATUS.md` og `ai-journalist/RR-AUDIO.md`.

Dagens VPS har fortsatt TTS av, ingen lastet nøkkel, ingen stemmeliste og
tegnbudsjett null. Etter Thomas sin lokale opplåsing ble eksisterende
Uniweb-konfigurasjon kontrollert: Thomas-stemmen er godkjent, Eleven v4 er
valgt, dagsgrensen er 3000 tegn og testmodus er på. Ett faktisk TTS-kall med
det konkret godkjente manuset lyktes 04.11 kl. 13:43 UTC (130 tegn). Dagens
bruk ble 130 tegn. Nøkkelen ble på originalserveren. Lesekall til stemme- og
modelloversikt mangler rettighet, men talegenerering fungerer; dette krever
ingen ny nøkkel eller utvidet rettighet for prøven.

Lydkvitteringen fra 02.11 bekrefter at Thomas M. også ble brukt gjennom
ElevenLabs-nettsiden. Det beviser stemmevalg og generering, men ikke API-oppsett
på VPS. Nettleserkoblingen feiler nå før siden kan leses. Den gamle
mandag-klokken-tolv-filen skal ikke gjenbrukes med feil ukedag eller tidspunkt.

## Avgrenset pilot som gjenbruker løsningen

Første pilot bør være én sammenhengende ny-time-sekvens ved naturlig låtskifte:
dobbel pipelyd, godkjent Thomas-stemme, tre glimt fra de neste faktiske
sangforekomstene, deretter hele første sang. Eksisterende klippgenerator og
RR Audio-flyt gjenbrukes. Kort forhåndsinnslag før timen holdes av under denne
piloten, slik at samme time ikke får doble presentasjoner.

Manus godkjent av Thomas i chat 04.11.2026 med «Godkjenn manuset og lag
lydprøven»:

> Denne stemmen er KI-generert. Nå starter en ny time på Radio Rubben.
> Her er tre musikkglimt fra timen som kommer. God fornøyelse!

Et eventuelt senere forhåndsinnslag må i stedet si «Snart er klokken {time}»
og må avsluttes før den aktuelle timen. Det skal ikke inneholde timesignalet.
Det alternative forhåndsmanuset er ikke godkjent. Den nye timeprøven har
ingen menneskelig lyttegodkjenning eller publiseringsgodkjenning ennå.

En ferdig pilot må bindes til Oslo-dato/time, tre køforekomster og kildehasher,
manusversjon, stemme/modell, lydhash og menneskelig lyttegodkjenning. Endret kø,
utdatert tidsutsagn, manglende fil eller godkjenning skal gi vanlig musikk.
Aktive/reserverte spor og manuelle rettelser skal bevares.

## Faktisk privat lydprøve

Studio-sak `5ca7944a7a194580`, revisjon 5 på Uniweb: ferdig behandlet, teknisk
kontroll bestått, `approval=null`, ikke kølagt, testmodus beholdt. Originalen
er bevart (9,6 sekunder). Ett rålydopptak ble behandlet uten flere TTS-kall i
et privat prøveområde på VPS. Tempo 0,94, moderat dynamikkbehandling og
nivåjustering gir 10,204 sekunder tale. Både WAV og MP3 består Studios
eksisterende varighets-, nivå-, topp-, stillhets- og klippingsgrenser.
Den ferdige stemmen er lagret tilbake i samme sak med revisjonsvern.

Stemmens MP3-SHA256:
`4355b699c0b8b14411e37c63ab4671d0a6061f8b0ed9d60ccf33341edf83555b`.
Rålyd-SHA256:
`33b7c56877d4c2328f5e70ef1d2907d224ce5ba409b3398f4a48106a2aa0607c`.

Komplett privat prøve: 1,2 sekunder dobbelpip, stemmen og tre klipp à sju
sekunder fra Five – Got The Feelin', DNCE – Cake By The Ocean og Demi Lovato –
Cool For The Summer. Eksisterende klippgenerator verifiserte alle tre
originalhashene. Køøyeblikket var revisjon 2534 kl. 13:48:35 UTC; planlagt
første hele låt var kl. 15:01:11 Oslo. Dette er en bundet prøve, ikke en
generisk timefil som kan gjentas etter at køen har endret seg.

Komplett MP3: 32,404 sekunder, −18,25 LUFS, −4,81 dBTP, ingen klipping;
SHA256 `631e21f8c1b5933c418fcb9d89cdb0c4425fb2e52c4e539a50ced8534b536c29`.
WAV og ferdig dekodet MP3 består nivå- og varighetskontroll. Full kvittering
og private lydfiler ligger lokalt under `output/hour-pilot-20261104/`, utenfor
kode-repository. Ingen lyd eller nøkkel er lagt i Git. Ingen OneDrive-
overføring er utført. Automatisk produksjon av full timesekvens på VPS er
fortsatt ikke implementert eller aktivert.

## Revidert privat prøve v2 etter lytteinnspill

Thomas ba 04.11 om mer kraft i stemmen, fjerning av KI-merkingen og mer
sømløse overganger mellom musikkglimtene. Den nyeste konkrete instruksen
er fulgt for timesekvensprøven: den talte KI-setningen er fjernet. Dette er
registrert som en uttrykkelig innholdsendring; syntetisk opphav, original,
modell/stemme, versjon og kontrollsummer er fortsatt dokumentert. Ingen
generell endring av policy eller produksjonsprogramvare er utført.

V2 gjenbruker samme råopptak og samme tre musikkilder, uten nye TTS-kall.
Klipp i naturlige pauser fjerner første setning og strammer inn to lange
pauser. Stemmen får tempo 1,02, mer fylde og tilstedeværelse med EQ og
dynamikkbehandling. Tonehøyden er bevart. Ferdig stemme varer 6,119 sekunder.
Hvert musikkglimt nivåjusteres før to overlappinger på 0,55 sekunder med
kvartsinuskurver. Ingen stille hull ble målt i de dekodede overgangene.

Komplett v2: 27,139 sekunder, −18,25 LUFS, −4,71 dBTP, ingen klipping.
MP3-SHA256:
`451c6c1392e5b32ecb1269ececf75a7e604bdbffb45aaede0bf72051594dc819`.
Stemmens MP3-SHA256:
`9ed8bfca2c6bea2492236b4e0af61de003dbeed8f9df978c9c458a47ea047c83`.
Filer og egen kvittering: `output/hour-pilot-20261104/v2/` utenfor Git.
Original og v1 er bevart ved kontrollsum. V2 er en privat lydmessig
sammenligning med det tidligere køøyeblikket, ikke en ny påstand om aktuell
sendekø. Prøven er ikke kølagt eller merket som sluttgodkjent i Studio.
Studio-saken med v1 er ikke skrevet over. Lydkontrollen er teknisk; Thomas
vurderte deretter v2 som «mye bedre». Dette er positiv lyttefeedback på
prøven; ingen ny Studio-godkjenning eller offentlig sending er utført.

Videre undersøkelse av stemme, manusvariasjon og flere daglige puljer er
dokumentert i [RR-VOICE-20261104](RR-VOICE-RESEARCH-20261104.md). Den gir et
konkret prøvedesign og kodefunn, ikke en aktivert produksjonsplan.

## Leveransestatus

Feilrettingen er forberedt for gjennomgang; ingen produksjonskode er byttet i
denne utviklingsrunden. Den gjenopprettede strømmen fortsetter med timesignalet
deaktivert. Strømmen ble kontrollert på nytt kl. 14:51 Oslo: HTTP 200,
96 000 mottatte lydbyte, spiller i playing og tilkoblet motor.
Revidert privat lydprøve v2 har fått positiv tilbakemelding. Sluttgodkjenning
av en fersk købundet pilot, selektiv utrulling av oppstartsrettingen og tre faktiske påfølgende
timeskifter gjenstår. Før eventuell offentlig pilot må klippene bindes til
fersk kø; denne prøven skal ikke spilles som om dens køøyeblikk fortsatt er
gjeldende. Godkjenning av lyd og utrulling av kode holdes adskilt.
