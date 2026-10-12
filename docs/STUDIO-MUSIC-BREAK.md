# Studio: musikk og neste stikk – første versjon

Bestilt 12.10.2026: status helt til høyre, tydelig Spilles nå og inntil tre
neste låter, valg over stikket og enkel godkjenning for opplesning eller TTS.

Studio-status ligger i høyre kolonne på stor skjerm; på mobil følger den
etter produksjonsarbeidet. Lokal musikkspiller får en trelåters kø.
Operatøren legger låter i køen; avspilling starter bare via eksisterende
spillerkontroller/eksplisitt valgt automatikk. Nettversjonen kan bruke
manuelt oppgitt musikkgrunnlag når spillerdata mangler. Dette er tydelig
merket og bekrefter ikke Radio.co, BUTT eller faktisk sendestatus.

Stikkvalgene står over teksten. Ett klikk på Godkjenn stikk registrerer
menneskelig manusgodkjenning og lagrer en egen radiosak i eksisterende
sendeliste/arbeidsliste. Identiske godkjenningsforsøk gjenbruker saken.
Valget Les opp manuelt beholder teksten i Studio. Valget TTS åpner samme
sak i RR Audio med profil Programlederstikk og hashbundet manusgodkjenning.
Ingen lyd, avspilling eller webpublisering starter i dette trinnet.
TTS velger stemme og bruker eksisterende budsjett, opphavsmerking,
lydlagring, egen lydgodkjenning og testmodussperrer. FFmpeg/VPS er uendret.

Dette er et operatørkontrollert program-/musikkstikk, ikke en nyhetsartikkel.
Operatøren bekrefter tekst og sitt faktagrunnlag ved godkjenningen. Snapshot
av program, musikk, notater, AI-deklarasjon, ansvarlig og tidspunkt lagres.
Vi påstår ikke ekstern faktasjekk. Nyheter, vær og sponsor kan ikke bruke
dette nye TTS-sporet; de beholder sine eksisterende kildeflyter.
AI-erklæring og den hørbare opplysningen om syntetisk stemme bevares.

Endret manus, kontekst, metadata eller utløpt kontroll krever ny godkjenning.
Kontrollen av operatørgrunnlaget gjelder i én time. Ingen AI-kontroll gir
menneskelig sluttgodkjenning. Rolle, CSRF og eksisterende RR Audio-hasher
håndheves på serveren; faktisk TTS koster kvote først når operatøren velger
Generer lyd i RR Audio. Musikken og køen er fortsatt lokale i fanen, ingen
ny ekstern musikkleverandør er koblet til.

Ferdigkriterier: tre låter vises uten ubedt avspilling; status helt til høyre
på stor skjerm; manusvalg og ettklikkgodkjenning fungerer på mobil; endringer,
feil rolle, CSRF, testdata og manglende menneskelig godkjenning kan ikke
starte TTS eller gi gyldig manusgodkjenning. Mock-TTS følger eksisterende
syntetisk merking og gir ingen lydgodkjenning automatisk.
