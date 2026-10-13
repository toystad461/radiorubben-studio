# Cue-punkter i valgt låt

Behov13.10.2026: fire manuelle punkter, lydbølge, flyttbare markører, tidsfelt
og privat forhåndslytting før avspillerens senere fading. Base main5139965430e68b999701d6e2fa9c7cd16d4fed3b
(utrullet PR83). Dette er utvikling, ikke produksjonsutrulling.

## Flyt og avgrensning

Handlinger → Rediger cue-punkter går til cue-editor i valgt låt. Start og slutt
er påkrevd; vokalstart og overgang kan være tomme (ukjent). Utgangspunkt uten
lagring er hele låten, uten oppdiktet vokal/overgang. Tider er sekunder med
inntil tre desimaler. Last lydbølge henter privat original etter klikk og
kontrollerer SHA256 før ekte nettleserdekoding. Peak-kurven viser begge kanalers
amplitude. Ingen lyd hentes ved vanlig sidevisning, og waveformlast starter
ingen avspilling. Inntil20MiB og15min lyd støttes i nettleserbølgevisningen.
Dekoding av hele filen krever minne; tidsfelt kan brukes uten lydbølge.

Velg en markør og trykk/drag på lydbølgen, eller dra nær en eksisterende markør.
Tidsfelt og «sett ved avspillingsposisjon» gir finjustering og tastaturbetjening.
Ulagrede endringer varsles ved navigasjon. Ugyldig rekkefølge sperrer lagring.

Produsent/administrator kan lagre. Serveren kontrollerer rolle, CSRF, SHA256 og
revisjon, begrenset format, finitte tall innen kjent varighet, start<slutt og
start≤vokalstart≤overgang≤slutt for punkter som er satt. Privat original
kontrolleres under eksisterende biblioteklås og register oppdateres atomisk.
Originalfil, metadata, analyse og manuelle tags/felter beholdes. Punktene er
kildebundne; tidligere manuell versjon beholdes, inntil20 historikkversjoner.
Det finnes ingen gjenopprettingsknapp for denne historikken ennå.

Cue-lytting bruker det samme private audio-elementet som vanlig forhåndslytting,
uten ekstra spiller eller sending. Lytt fra startpunkt starter ved start;
overgangslytting starter maks5sek før overgang, aldri før start. Sluttpunkt
pauser lyttingen ved nettleserens timeupdate-hendelse. Dette er forhåndslytting,
ikke samplepresis playout. Stopp og navigasjon pauser. Vanlig bibliotekplay
spiller fortsatt originalen og bruker ikke cue-punkter i kø/sending.

En eksisterende avspillingsfeil rettes: generell [data-audio-url]-selektor kunne
behandle analyseknappen som play-knapp. Selektoren er nå avgrenset til faktiske
playknapper, og testen bekrefter at analyse og waveformlast ikke setter audio-src.

## Kontroller

PHP8.2/8.4: roller/CSRF/stale kilde/revisjon, negativ/NaN/utenfor varighet/
feil rekkefølge/ukjent felt, tomme valgfrie punkter, bevarte originaler/metadata
og begrenset historikk. Cue-endring endrer ikke AI-/workergrunnlag i denne
versjonen; punktene brukes bare til manuell privat lytting.

Ekte syntetisk nettleser: privat WAV-dekoding og SHA, lydbølge uten avspilling,
markørdrag/timefelt, feil rekkefølge, ekte POST/atomisk lagring/gjenåpning,
320/390/768/1280px. Avspillingsklokke/metadata/play/pause simuleres for å prøve
start, femsekunders preroll og stopp uten faktisk lyd. Dette beviser ikke
native MP3-avspilling eller samplepresise grenser. Syntetisk PCM injiseres kun
i isolert register; MP3-opplastingskontroll svekkes ikke. Eksisterende import,
duplikat, roller, privatbytelevering, redigering, sletting og lydanalyse består.

Gjenstår: kontroll med ekte låtlytting, AI-forslag til cue-punkter, zoom i bølgen,
historikkgjenoppretting, lydnivå, tospillers overgang/fading, kø og rotasjon.
Ingen AI-kall, automatisk punktvalg, cue i sending eller produksjonsdata endres
som del av utviklingstestene.
