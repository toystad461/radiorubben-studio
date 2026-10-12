# Studio – konsolloppsett, 12.10.2026

Behov: En mer gjennomarbeidet nettflate for musikk, neste stikk og oversikt,
inspirert av Radio.co. Utgangspunkt er utrullet PR76, main ed2e4d80.

## Referanser og valg

Radio.co sin [publiserte dashboardillustrasjon](https://www.radio.co/blog/new-radioco-website)
og [dashboardguide](https://help.radio.co/en/articles/899708-dashboard-overview)
viser en fast navigasjon og tydelig inndeling av stasjonsinformasjon og musikk.
Deres [rapportoversikt](https://radio.co/blog/radio-reporting-software) skiller
nåværende informasjon fra historikk. Publiserte bilder er designreferanser;
de dokumenterer ikke alle funksjoner i dagens innloggede produkt.

[RadioKing](https://help.radioking.com/hc/en-us/articles/4407741831697-Getting-started-guide-for-the-radio-manager)
skiller også oversikt fra medie- og planleggingsarbeid.
[Airtime Pro](https://atpc.sourcefabric.org/features/) beskriver forhåndslytting
og tydelig programplanlegging. Dette er fremtidige muligheter, ikke nye
integrasjoner i denne leveransen.

Radio Rubben får egen blå profil og et produksjonsorientert oppsett:
- Spilles nå og tre kommende låter til venstre.
- Neste stikk, manusvalg og eksisterende godkjenning i midten.
- Faktiske tilkoblinger helt til høyre på stor skjerm.
- Kompakt program-/klokke-/værstripe og direkte snarveier til eksisterende flater.
- SMART Pads og hendelser kan utvides når de trengs.

Vi legger ikke inn lyttertall, signalmålere, tidsprogresjon eller sendestatus
uten tilkoblet datakilde. Musikkgrunnlag fra operatør beholder tydelig merking.
Manusarbeid er hovedsak; AI omtales som hjelpemiddel.

## Avgrensning og ferdigkriterier

Endringen gjelder eksisterende ai-studio.php og dens lokale CSS. Ingen endring
i API, lagring, manus-/lydgodkjenning, TTS, musikkavspilling, roller eller MIDI.
Alle eksisterende ID-er og handlinger beholdes. Innstillingslenken følger
eksisterende settings-rettighet. Observatørvisningen er fortsatt låst.

Ved 1440 px står musikk, manus og tilkoblinger ved siden av hverandre. Ved
mindre bredder ordnes feltene uten horisontal overflyt; på mobil følger manus
etter musikk. Køen og godkjenningen skal fortsatt virke, redigering skal
oppheve manusgodkjenning, og utvidbare felt skal vise eksisterende handlinger.

Tester bruker syntetiske data og mockede API-er, uten faktisk modellbruk,
lydproduksjon, avspilling eller innholdspublisering. Produksjonsutrulling krever
konkret autorisasjon for denne leveransen etter ferdige kontroller.

## Videre anbefaling

Test denne flaten i faktisk studioarbeid før flere funksjoner legges til.
Deretter vurderes verifisert musikk-/sendestatus fra eksisterende leverandør,
og om musikkvalg bør flyttes nærmere køen. Programplan og lyttertall krever
egne datakilder og avklart omfang. Ingen ny leverandør er opprettet eller koblet til.
