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
består. Regresjonsvariantene er lagt til i CI; lokal kontroll er ikke en
påstand om at den nye GitHub-kjøringen er grønn.
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

Dagens VPS har TTS av, ingen lastet nøkkel, ingen stemmeliste og tegnbudsjett
null. Ny lesing av den gamle serveren ble avvist fordi eksisterende SSH-tilgang
ikke er låst opp. Derfor er nøkkelens nåværende eksistens og gyldighet fortsatt
uverifisert. Søk etter de to kjente konfigurasjonsfilnavnene i lokale
prosjektkopier fant ingen fil. Ingen hemmelighet er skrevet til Git eller logg.

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

Manusutkast for ny time:

> Det er {ukedag}, og klokken har passert {time}. Du hører på Radio Rubben.
> Her er tre musikkglimt fra timen som kommer. Stemmen i dette innslaget er
> KI-generert med tillatelse fra Thomas.

Et eventuelt senere forhåndsinnslag må i stedet si «Snart er klokken {time}»
og må avsluttes før den aktuelle timen. Det skal ikke inneholde timesignalet.
Dette er manusutkast; ingen ny stemmefil eller publiseringsgodkjenning foreligger.

En ferdig pilot må bindes til Oslo-dato/time, tre køforekomster og kildehasher,
manusversjon, stemme/modell, lydhash og menneskelig lyttegodkjenning. Endret kø,
utdatert tidsutsagn, manglende fil eller godkjenning skal gi vanlig musikk.
Aktive/reserverte spor og manuelle rettelser skal bevares.

Neste nødvendige tilgangssteg er lokal opplåsing av eksisterende Uniweb-nøkkel,
deretter skrivefri kontroll av den gamle private Audio-konfigurasjonen. En
eventuell overføring til VPS må beholde privat lagring og avklart tegnbudsjett.
Ingen nye nøkler eller abonnement skal opprettes som skjult del av arbeidet.

## Leveransestatus

Feilrettingen er forberedt for gjennomgang; ingen produksjonskode er byttet i
denne utviklingsrunden. Den gjenopprettede strømmen fortsetter med timesignalet
deaktivert. Full stemmesekvens og tre faktiske påfølgende timeskifter gjenstår.
Etter ferdig lyd og menneskelig gjennomlytting må pilotens utrulling ha eksakt
filsett, privat kodebackup, før/etter-kontroll og tilbakeføringsmulighet.
