# Automatisk lydanalyse og enklere cue-punkter – 14.10.2026

Behov: Klargjør én eller flere opplastede låter etter hverandre, med en enkel kontroll av inn- og utpunkter.

Leveransen er lokal, ikke utrullet. Opplasting setter nye låter i en vedvarende analysekø. Studio må være åpent under behandling. Køen måler BPM, toneart, signalnivå og stillhet, og bruker YAMNet til å foreslå vokalpunkter. Manuelle rettelser og lagrede cue-punkter beholdes. Cue-forslag krever ett menneskelig godkjenningsklikk. Ingen automatisk rotasjon, sending, fading eller betalt tekst-AI kjøres.

Cue-flaten viser lydbølge, fire markører i minutter/sekunder, intro- og avslutningslytting og én godkjenningsknapp. Sekundfelt og detaljjustering ligger under «Juster punktene».

Køen har én aktiv låt, kildehash, revisjonskontroll, brukerbundet tidsbegrenset leie, gjenopptakelse, pause etter gjeldende låt og separat nytt forsøk. Originalfilen sendes ikke til en ekstern analysetjeneste. Modellen lastes fra Studios egne filer.

YAMNet: Google TFJS-modell v1, TensorFlow.js 4.22.0, Apache 2.0. Kilde: https://tfhub.dev/google/tfjs-model/yamnet/tfjs/1/model.json?tfjs-format=file . Dokumentasjon: https://www.tensorflow.org/hub/tutorials/yamnet . Lisens følger modellen. Eksisterende Essentia.js har AGPL-lisens.

Begrensninger: BPM, toneart og vokalpunkter er estimater. Signalnivå er dBFS, ikke LUFS. Ingen bekreftet treffsikkerhet mot et representativt musikksett ennå. Nettleseren gjør tung analyse, ikke webhotellets PHP. En avbrutt nettleser kan kreve at leien utløper før køen fortsetter.

Kontrollert: PHP 8.2/8.4 køtester; eksisterende cue-, analyse- og arbeidertester; isolert nettlesertest med virkelig WASM og YAMNet, syntetisk lyd, opplasting, feilstatus, manuell bevaring, godkjenning og responsivitet 320/390/768/1280. Ingen ekte sending eller musikkfiler endret i disse testene.

Ferdigkriterier: nye opplastinger starter køen; feil isoleres per låt; rettelser beholdes; forslag kan godkjennes eller justeres uten flere vinduer. Gjenstår: PR/CI, autorisert utrulling og kvalitativ kontroll av forslag på ekte musikk.
