# Musikkbibliotek – filopplysninger, visning og filslipp, 13.10.2026

## Oppfølging: liste og egen låtfane

Fra utrullet PR80 (440649dd). Biblioteklisten bruker hele bredden med egne
kolonner for låt, artist, album, tid, merkelapper og status. Låtlenken åpner
en egen nettleserfane (nettleseren avgjør fane/vindu), med tilbakekobling til
biblioteket. Klargjøring beholder detaljvisningen etter innsending.
Lagrede tekstmerkelapper vises og er søkbare; sjanger fra originalfilen
vises separat merket «fra filen». Ingen ny merkelapp lagres eller genereres.
Radio.co-videoen [Managing Media](https://www.youtube.com/watch?v=tZQArfKUwdc)
har kapitler om opplasting og organisering med tags og brukes som referanse.
Lokalt testet med syntetisk MP3: faktisk ny fane, rollebegrenset detalj,
klargjøring, escapede/søkbare merkelapper, upload/dublett/privat Range-lyd,
CSRF og liste/detalj ved 320, 390, 768 og 1280 px uten overflyt eller JS-feil.
Ingen ny kode er utrullet av denne oppfølgingen.

Behov: Bibliotek som arbeidsflate med Radio.co som visuell veileder, faktisk
varighet/filopplysninger og privat forhåndslytting. Filslipp ble uttrykkelig
lagt til under arbeidet. Base: utrullet PR79, main 69cb55572d17c7303c1d97e29ca21c02b0f84cfc.

## Levert i utviklingsgrenen

- Søk i artist, tittel, album og originalnavn; filter for klargjøringsstatus.
- Låtliste med tittel/artist, album, varighet og status. Kort på mobil.
- Valgt låt i detaljpanel med originalreferanse og filopplysninger.
- Opplasting i utvidbart felt. Dra filer inn i vinduet, kontroller listen og
  trykk Last opp originaler. Ingen automatisk upload ved filslipp. Native
  filvalg fungerer uten JS; mobil/tastatur kan fortsatt bruke det.
- Les filopplysninger og varighet analyserer én valgt original i PHP med
  [getID3](https://github.com/JamesHeinrich/getID3), låst til 1.9.27. Ingen
  eksisterende avhengighet oppdateres. Originalen skrives aldri av parseren.
- ID3-artist/tittel/album/sjanger/år og varighet merkes fra filen; ukjente
  felt forblir ukjente. Filnavn gir ikke identifikasjon. Dette er ikke
  nettoppslag, AI-vurdering eller redaksjonell godkjenning.
- Privat GET/HEAD-lydrute gjenbruker eksisterende byteområdehåndtering.
  Innlogging og produce-rolle kreves; observatør kan ikke hente lyd. Fil-ID,
  SHA256 og faktisk handleinnhold kontrolleres før lyd leveres. Ingen
  direkte original-URL, serverbane eller nøkkel gis til nettleseren.
- Forhåndslytting krever operatørens bekreftelse av lydutgangen og eget klikk.
  Ingen lyd hentes ved sidelasting eller låtvalg. Vanlige browserkontroller
  gir søking i lydfilen. Dette er ikke koblet til musikkø eller sending.

## Bevaring og kontroll

Klargjøring krever admin/produsent, CSRF, eksakt originalhash og aktuell
revisjon. Registeret lagres atomisk under eksisterende biblioteklås. Endret
original eller foreldet revisjon bevarer tidligere opplysninger og avviser
skriving. Andre felt som tags eller manuelle overstyringer fjernes ikke.
Metadata er bundet til originalens hash og presenteres ikke som gjeldende
hvis bindingen mangler. Parservedlegg/bilder og HTML-tags hentes ikke inn.

Det nye Composer-biblioteket utvider kodepakken. Før autorisert utrulling
må også nye vendor-filer og endret Composer-autoload avstemmes med faktisk
produksjon, ikke bare fem app/public-filer. Privat media/register/lock skal
ikke pakkes eller publiseres av kodeutrullingen. Kodebackup og rollback
skal inkludere den konkrete avhengighetsendringen.

## Gjenstår

Lydbølge, lagrede cue-punkter, crossfade og faktisk bruk av cue i avspilling
kommer etter denne grunnmuren. Cue-seksjonen sier uttrykkelig at ingen
punkter er lagret eller aktive. FFmpeg/VPS og nye programprofiler er ikke
innført. Nettidentifisering, robotens tags/programforslag, manuell redigering
og integrasjon i musikkø er egne neste trinn, ikke påstått levert her.

## Tester

PHP 8.2/8.4 med syntetisk MP3/ID3: faktiske tekstfelt og målt varighet,
originalbevaring, filhash/revisjon/rolle/CSRF, feil ved endret original og
bevaring av tidligere register. Full eksisterende opplastingskontroll beholdes.
Ekte PHP/multipart-nettleser: upload, dublett, klargjøring, GET/HEAD/Range,
stale lydhash, ugyldig range og sperret observatørlyd; ingen lydhenting på
GET; søk/filter og faktisk DataTransfer-filslipp uten ubedt upload.
320/390/768/1280 og eksisterende Studio 320–1440 kontrolleres. Ingen ekte
musikk, produksjonsmetadata eller faktisk avspilling brukes under utvikling.
Faktisk lydkvalitet og browserdekoding av brukerens filer må prøves etter
autorisert utrulling. Funksjonell bytelevering er ikke bevis på hørbar kvalitet.
