# Musikkprodusentens lydgrunnlag

Behov 13.10.2026: BPM, toneart og dur/moll i Studio, som grunnlag for en større
musikkprodusentrolle. Base: main 1f8dc374ccde55343a5e8bd8895c84bfff6fc4f2, PR82.
Dette er utvikling, ikke produksjonsaktivering eller automatisk fading.

## Første innebygde versjon

Produsent/administrator velger Analyser låten i valgt låt. Ingen lyd hentes ved
vanlig sidevisning. Etter klikk hentes privat original gjennom eksisterende
autentiserte lydendepunkt. Nettleseren kontrollerer SHA256, dekoder og resampler
til mono 44100Hz. Opptil90 sekunder fra midten analyseres med Essentia.js0.1.3
i en egen Web Worker. Ingen avspilling eller overføring til ekstern tjeneste.
Låter10sek–15min støttes. Stillhet og analysefeil lagres ikke. Worker har120sek
tidsgrense og avsluttes etter bruk. Dekoding/resampling skjer i nettleseren;
hele originalen hentes og dekodes, så svært små enheter kan ha minneproblemer.

PHP-lagring krever administrator/produsent, CSRF, kildehash og uendret revisjon.
Kompakt JSON-grense4096bytes og fast motor/format, numeriske intervaller og
utsnitt innen kjent varighet. Original kontrolleres på server ved atomisk
lagring under biblioteklås. Lagring bevarer original, manuelle felter og tags.
BPM/toneart vises som estimater; rå tempo-confidence og key-strength vises
under analysegrunnlaget og er ikke prosentvis sannsynlighet.

Det er autentiserte nettleserestimater. Serveren gjentar ikke signalanalysen,
og validering beviser ikke at verdiene er riktige. Resultatene skal aldri regnes
som uavhengig verifiserte fakta eller rotasjonsgodkjenning.

Musikkprodusentens eksisterende AI-vurdering mottar gjeldende målinger når de
finnes. Ingen rå lyd sendes til AI. Prompt3 instruerer bruk av målinger som
estimater, ingen oppdiktet BPM/toneart og ingen slutning energi=tempo. Ny analyse
endrer grunnlagshash og ugyldiggjør gamle forslag/jobbgrunnlag. Eksisterende
forslag og jobbidentiteter uten lydanalyse beholder samme grunnlagshash.
Analyse og AI-vurdering er to synlige handlinger; ingen automatisk AI-utgift
eller godkjenning utløses av analyselagringen. AI-vurdering bruker eksisterende
betalte tjeneste når operatøren velger den; lydmotoren har ingen per-låt-avgift.

## Motor og lisens

Motorfilene er fastlåst til npm essentia.js0.1.3, hentet fra:
- https://cdn.jsdelivr.net/npm/essentia.js@0.1.3/dist/essentia-wasm.umd.js
- https://cdn.jsdelivr.net/npm/essentia.js@0.1.3/dist/essentia.js-core.umd.js
- https://cdn.jsdelivr.net/npm/essentia.js@0.1.3/LICENSE

Filer ligger lokalt i public/assets/music-analysis, ingen CDN ved bruk.
LICENSE.js inneholder full AGPL3-tekst som JS-kommentar for eksisterende
pakkeformat. Adapteren er merket AGPL3-or-later. Original kildekode:
https://github.com/MTG/essentia.js. Leverandørens side skiller ikke-kommersielt
åpent alternativ og kommersiell lisens:
https://essentia.upf.edu/licensing_information.html.
Lokal evaluering er utført; vilkår for permanent hostingbruk/helhetsintegrasjon
er ikke juridisk avklart. Eget bruk er ikke automatisk ikke-kommersielt.
Ingen abonnement, avtale eller leverandørkontakt opprettet.

## Kontroller og videre arbeid

PHP8.2/8.4 tester rolle/CSRF/revisjon, format/intervaller, original/manuell
bevaring, kildebinding og invalidering av produsentforslag. Nettlesertesten
kjører ekte WASM-worker på syntetisk C-dur PCM gjennom privat lydendepunkt og
PHP-lagring. PCM-fixturen injiseres kun i isolert register; opplastingskontrollen
for ekte MP3 svekkes ikke. Eksisterende multipart/dublett/privatlyd/redigering/
sletting/mobilflater testes fortsatt. Ingen ekte AI-kall eller produksjonsmusikk
endres. Den separate lokale testen har analysert tre ekte MP3, uten fasitkontroll.

Neste trinn: kvalitet på flere sjangre, redigerbare målte verdier med historikk,
intro/outro/cue og lydnivå, deretter låtkompatibilitet og separat playout-fading.
Det finnes ingen automatisk analyse etter upload, bakgrunnsanalyse når siden er
lukket, aktiv cue/fading, køtilkobling eller rotasjonsgodkjenning i denne versjonen.
