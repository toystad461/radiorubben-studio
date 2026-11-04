# Menyopprydding – Studio #115/L02, 02.11.2026

## Avvik forklart og selektiv pakke klargjort 04.11.2026

Innsendt diff viser eksisterende Vær-/Trafikk-/stikkarbeid. Begge komplette
serverhasher matcher PR117-head `290f80c4492122d3cb62060cdfab68872e758ff6`:
`app.mjs` = `c70656d0ba51850ebca827f30dc7263229bd9cee0706729e067c8c6407ac6ae1`,
`ai-studio.php` = `fa7fc0c94d258e3e6e01b2b1e73d087114b70fbef5db8dead87cc6ce8a5b9247`.
Dermed er hele filinnholdet avstemt selv om terminalutdraget viste bare de
første 20 av 25 tegnvise forskjeller i PHP-filen. PR117s eldre tekst «ingen
utrulling» er ikke brukt som bevis for dagens installasjon. Dagens filhasher
viser at disse to PR117-filene finnes på VPS; øvrige PR117-filer er ikke
kartlagt som del av menyoppdraget.

### Pakke og etterhasher

[Installasjonsskriptet](../scripts/studio-menu-vps.py) bruker bare det avtalte
seksfilers settet. Fire menyfiler lastes fra `9bbfae2c7b97414c2b3b96b18aeafa731cb6c5d8`
og beholder kandidatens fire første etterhasher nedenfor. Konsollen patches
på stedet i minnet, etter kontroll av de ferske PR117-førhashene:

| Fil | Avstemt etterhash, med PR117 bevart |
| --- | --- |
| `public/assets/ai-studio/app.mjs` | `6b0122f8933dc7c1b9241bcb83f83cc37b855c3aaaba4a98dc438ac30a5b857d` |
| `public/ai-studio.php` | `dc853d3248931c377e696b0678ec0556dc34b065b2470ca251231275837966c9` |

Bare fire linjer som åpner aktiv menygruppe tilføyes i JavaScript.
PHP endrer bare scriptversjonen fra `stikk-20261102` til
`menu-stikk-20261104`. Værkilder, Trafikk, lokal mal, importer, manus,
godkjenningsfelt og alle øvrige PR117-byte bevares. Dette er en selektiv
installasjonspakke i #115, ikke merge eller kopiering av hele PR117.

### Utførelse og tilbakeføring

Standardkjøring klargjør/kontrollerer uten runtime-endring; `--apply` krever
riktig vert, aktiv Nginx-release, nøyaktige seks førhasher, aktive tjenester,
tilgjengelig PHP/Node og FPM som oppdager endrede PHP-filer. Skriptet skriver
ikke kø, bibliotek, journal, innhold, konfigurasjon eller tjenester.

Ved installasjon opprettes en ny privat mappe under `/var/lib/rr-vps-deploy/`
med prefiks `menu-20261104-`. Den inneholder originaler, kandidat og kvittering.
Dette er planlagt plassering; en faktisk mappe/kvittering finnes først når
skriptet kjøres på VPS. Eier, gruppe og modus bevares. Filbytter er atomiske
per fil; ved feil forsøkes tilbakeføring av akkurat dette filsettet. Hele
seksfilers byttet er ikke én filsystemtransaksjon. Nye mtime-verdier gjør at
FPM kan oppdage filbyttet. CSS/JS får versjonerte nettleseradresser.

Skriptet skriver ut kvittering og eksakt rollbackkommando etter vellykket
filinstallasjon. Tilbakeføring avviser senere filendringer og endrer ingen
aktive data. Installerens ni isolerte tester dekker bevaring av PR117/data/
rettigheter, reell tilbakeføring, feil midt i installasjon, PID-avvik,
samtidige/senere endringer, ødelagt kilde og symbolske/harde lenker.
Alle ni består lokalt. PHP-/nettlesertest av kombinert kilde kjøres separat
av `studio-menu-overlay.yml`; resultat må leses før pakken tas i bruk.

Status nå: pakke klargjort, ingen ny serverinstallasjon i denne økten.
Etter faktisk kjøring gjenstår innlogget PC/mobil, menyer og konsollankre,
bevart ulagret manus og fortsatt stream. Full L02 krever fortsatt egne bevis
for øvrige spiller-/timeinnslagspunkter nedenfor. Fysisk iPhone er ikke testet.

## Ferske serverhasher 04.11.2026 – avstemming før installasjon

Thomas har levert resultatet fra en lesekontroll i sin eksisterende SSH-økt
(`ssh rubben-test`, `ubuntu@vps-5363103`). Banner: 04.11.2026 kl.15:12:29 CET.
Assistentøkten har ikke selv en tilkoblet SSH-terminal; resultatet nedenfor er
innsendt faktisk serverutdata sammenlignet med GitHub-kode, ikke en egen fjernmåling.

Aktiv release fra Nginx-konfigurasjonen: `/srv/radiorubben-test/releases/studio-music-fad12a2320de0ff0a036854c414accee794143bb`.
Nginx, PHP8.3-FPM, rr-studio-stream og rr-studio-icecast viser active/running;
PID henholdsvis 810, 763, 773 og 770. Aktiv tjeneste dokumenterer ikke alene
sammenhengende eller hørbar lyd.

| Fil | Innsendt SHA256 fra VPS |
| --- | --- |
| `app/views/navigation.php` | `b88904b9d42a6243c11de8d6c22af513c17957787cc261c132cb72b2f27cb92d` |
| `app/views/sidebar.php` | `e459b4277a7124cc896eaf73ff283327f25a659b1b3c8003a87b01621abe813a` |
| `app/views/head.php` | `0d4e5414c4410365d06b257e487e34b5348a08def262dff53f73375d04ea0866` |
| `public/assets/studio.css` | `405e2b52f4c836fc19aaf4cbc9566873d57e8e0421238632392e1b2283223ae5` |
| `public/assets/ai-studio/app.mjs` | `c70656d0ba51850ebca827f30dc7263229bd9cee0706729e067c8c6407ac6ae1` |
| `public/ai-studio.php` | `fa7fc0c94d258e3e6e01b2b1e73d087114b70fbef5db8dead87cc6ce8a5b9247` |

De fire første filene er byteidentiske med `650e24cde9deebb98d65976189503e170fd7c56b`,
altså grunnlaget før menyoppryddingen. Den gamle menyen finnes på disk;
nettlesercache kan ikke alene forklare avviket.

De to siste filene avviker fra både grunnlaget og kandidatens seksfilers sett.
Kontrollerte varianter med LF/CRLF, BOM og avsluttende linjeskift ga ingen treff.
Producer-kvitteringen fra 02.11 har andre etterhasher, og erstatter ikke dagens
ferske måling. Årsaken til disse to avvikene er foreløpig uavklart. Neste steg
er lesing av den konkrete kodediffen; ikke full overskriving av konsollfilene.

GitHub-head ved avstemming er `75c6e5688902b456fde653672a0a28bfb4134634`.
Kandidatens seks kildefiler har fortsatt hashene i tabellen nedenfor.
Denne oppdateringen endrer bare dokumentasjon. Ingen installasjon, privat backup,
rollback, tjenesteomstart eller endring av aktive data er utført her.

## Ny kontroll 04.11.2026 Europe/Oslo – installasjon fortsatt blokkert

Thomas bekrefter i denne økten: «Time signalet fungerer i streamen».
Ordinært timesignal er dermed operatørbekreftet i streamen. Dette er ikke en ny
måling fra denne økten og gjelder ikke automatisk montasje med tre musikkglimt.
Den historiske feilen 03.11 ved oppstart med 1,2-sekunders signal først i køen
står som et separat, ikke gjenprøvd scenario; den beskriver ikke vanlig timesignal
som ute av drift. CI-testen `tests/test-studio-hourly-stream.py` legger signalet
mellom to sanger og dekker ikke signal først ved oppstart.

### Kilde og CI

Kontrollert PR-head: `1d3b1228b60f2043245c6a91d8451115ff3e8bfd`.
Base: `4fcdff392475db0c03fefa8b6990f77edcc223d2`.
PR115 er åpen, draft og ikke merget. Sammenligning med menycommit
`9bbfae2c7b97414c2b3b96b18aeafa731cb6c5d8` viser bare to dokumentasjonsfiler
endret, ingen runtime-endring.

Alle tre kjøringer knyttet til denne headen er ferdige med success:

| Kjøring | Kontrollert resultat |
| --- | --- |
| [PHP og pakke 41643464543](https://github.com/toystad461/radiorubben-studio/actions/runs/41643464543) | PHP 8.2/8.4, kontrollrom/roller og mobil-/desktop-suite bestått |
| [Liquidsoap 41643464573](https://github.com/toystad461/radiorubben-studio/actions/runs/41643464573) | Køkontrakter 8.2/8.4, nettlesermonitor og faktisk isolert PCM/MP3 bestått |
| [Release 41643464522](https://github.com/toystad461/radiorubben-studio/actions/runs/41643464522) | Verifikasjon/pakking bestått; deploy-jobben er skipped |

Loggene for lyd og mobil viser checkout av PR-merge-ref
`65645ad9ffa930fe2176ee04043ca6be68112bcf`.
GitHub-sammenligning mot kontrollert head viser null filforskjeller.
Dette dokumenterer testet kilde, ikke installert serverkode.

Kontrollerte logger:
- [Mobiljobb 125942573288](https://github.com/toystad461/radiorubben-studio/actions/runs/41643464543/job/125942573288):
  admin/producer/presenter/observer, 320/390/800/1440 px, tastatur uten JS,
  rollegrenser og ingen horisontal overflyt.
- [Lydjobb 125942574042](https://github.com/toystad461/radiorubben-studio/actions/runs/41643464573/job/125942574042):
  cue-kutt og crossfade-innstillinger 0/3/10 gir målt overlapp 0/2,96/9,96 s;
  jingleovergang uten planlagt overlapp. Køinnsetting bevarer hel 26 s sang,
  målt gap 0,0 s og fjernet innslag blir ikke avspilt.
  Montasje er 21 s, idempotent import, ni originalspor bevart og gap 0,0/0,0 s.
  Timesignal mellom hele sanger er 1,2 s uten musikkoverlapp.
  Disse resultatene kommer fra isolert CI, ikke fra aktiv teststream.

### Eksakt menyfilsett

UTF-8-innholdet er kontrollert mot hver Git-blob-SHA før SHA256-beregning.
Dette er kandidatens kildehasher. Ferske før-/etterhasher fra VPS mangler for
alle seks filer. Gamle deploykvitteringer er ikke brukt som dagens førhash.

| Fil | SHA256 i kontrollert kilde |
| --- | --- |
| `app/views/navigation.php` | `3f6da7444e8d69992edf26f26fe953d45f5033570c5ae796ecc150193f5982cd` |
| `app/views/sidebar.php` | `aebb929943b86d89cb32d54e27a3501c2b47438f4893cd1b8346f3b5589f3980` |
| `app/views/head.php` | `61901ae3982df5c6fc90d602583eb07b8a16da23cce791fcbf6d6b08afc17b6d` |
| `public/assets/studio.css` | `97c3ab5f6e5f2874426ab18488faee9135ac865b447dd5dd614f2221ba4e1ba9` |
| `public/assets/ai-studio/app.mjs` | `0059f57ef9ebe3891b87ef2eb8b2e22b636b7bcbec3c7eb02218433da2830741` |
| `public/ai-studio.php` | `dc38fb9de1be8768187c7be72fd1d4153879ec5e5b77f830863d7a32915b3f56` |

Ingen backup eller installasjonskvittering er opprettet på VPS i denne økten.
Ingen serverfil, tjeneste, kø, journal, bibliotek, lydmotor eller produksjon er
endret gjennom denne kontrollen. Dagens tilstand for disse er ikke hashverifisert.

### Konkret tilgangshinder

Denne økten har ingen konfigurert `rubben-test`-SSH-identitet og ingen tilkoblet
SSH-agent. Nettlesertilgang til `https://5.249.252.78/ai-studio.php` stopper med
`ERR_CERT_COMMON_NAME_INVALID`; separat HTTPS-kontroll stopper ved samme
vertsnavn-/sertifikatavvik. Sertifikatkontrollen er ikke slått av.
Ingen eksisterende innlogget VPS-konsoll var tilgjengelig i nettleseren.

Eksisterende `scripts/studio-selective.php` og
`.github/workflows/studio-ssh-check.yml` er kontrollert: disse gjelder
Uniweb-produksjon, ikke de seks menyfilene på VPS. De er ikke kjørt eller
omdirigert. En ny maskinnøkkel, ny administrasjonsbakdør eller omgåelse av
eksisterende tilgang er ikke del av denne leveransen.

### Gjenstående ferdigkriterier på test-VPS

| Kontroll | Status ved denne gjennomgangen |
| --- | --- |
| Faktisk release, særendringer og seks ferske serverhasher mot PR-head | Blokkert av manglende driftstilgang |
| Privat backup med rettigheter, selektiv installasjon og kontrollert rollback | Ikke utført i denne økten |
| ai-studio.php mot faktiske nå-/neste-tre-forekomster og motorjournal | Ikke etterkontrollert på VPS |
| Automatisk oppdatering gjennom reelt låtbytte uten reload | Ikke etterkontrollert på VPS |
| Rollenavigasjon på PC/mobil, konsollankre og bevart ulagret manus | CI bestått; innlogget VPS-/mobilkontroll gjenstår |
| Køinnsetting/fjerning under kontinuerlig lyd | CI 0,0 s gap; fersk VPS-måling gjenstår |
| Lagrede cue-/overgangsverdier brukt av installert motor | CI målt; faktisk spillerkopi og journal må avstemmes |
| Ordinært timesignal i streamen | Bekreftet av Thomas 04.11.2026 |
| Automatisk montasje med tre musikkglimt | CI bestått; automatisk avvikling på aktiv stream ikke dokumentert |
| Bibliotek/originaler, voksende journal og uavbrutt lydmotor bevart | Må kontrolleres før/etter faktisk installasjon |

Neste handling er å gjenoppta eksisterende autoriserte VPS-forbindelse, lese
fersk release og førhasher, og avstemme eventuelle lokale særendringer før
skriving. Bruk bare de seks avstemte menyfilene; ikke bytt hel release eller
restart lydmotoren. Ta privat backup før atomisk filbytte. Verifiser etterhasher,
HTTP-/cacheversjoner, roller og kontinuerlig avspilling. Ved avvik gjenopprettes
bare berørte kodefiler, kontrollert mot installert etterhash; aldri eldre kø,
bibliotek eller journal over aktive data. Automatisk timeinnslag må knyttes til
time, kildehash, køforekomster og faktisk fullført lyd i journal/stream.
L02 skal ikke markeres ferdig før gjenstående driftspunkter har konkrete bevis.

---

Arbeidsområder: Teknisk Produksjon, System & Kvalitet, Kontrollsenter.

## Omfang

Brukerens «Utfør» gjelder avgrenset menyopprydding i eksisterende #115.
Grunnlag: 650e24cde9deebb98d65976189503e170fd7c56b.
Ingen ny plattform, nye funksjonssider eller WordPress-migrering.

| Gruppe | Eksisterende funksjoner |
| --- | --- |
| Studio | Kontrollrom, Sendekø, Lydproduksjon og stikk |
| Lydbibliotek | Musikk, Jingler |
| Forberedelse | Lokal prøvelytting, Musikkplan, Musikkverktøy |
| Redaksjon | Redaksjonsoversikt, Nyhetsdesk, Redaksjonell kjøreplan, Saksarbeid og RR Audio |
| Robåt | Eksisterende regler og læring; teknisk jobboversikt er ikke tilkoblet |
| Samarbeid | Samarbeidspartnere, eksisterende administratorgrense |
| Innstillinger | Studioinnstillinger og RØDE for admin/produsent, Brukere for admin |

Redaksjon/Robåt/Samarbeid ligger under Arbeidsrom. Native details-elementer
fungerer med tastatur og uten JavaScript. Gjeldende side åpner sin gruppe.
Eksisterende konsollruting åpner gruppen ved hashlenker og beholder ulagret manus,
MIDI og lytteøkt. Mobil og desktop bruker fortsatt samme menyfil.
Redaksjonell kjøreplan peker fortsatt til sending.php; sidens data og arbeidsgang
endres ikke. Bibliotektyper utover dagens musikk/jingler opprettes ikke her.
Inaktive plassholdere fjernes bare fra menyen. Eksisterende funksjoner slettes ikke.

Robåt-gruppen er foreløpig inngangen til dagens læringsside, ikke en ferdig
VPS-jobbkonsoll. Redaksjonelle regler skal senere følge WP-ansvaret.
VPS-nyhetsdesken mangler fortsatt WP-tilkobling. Ingen ny lenke til produksjons-WP
eller test-WP er lagt inn uten verifisert mål og fungerende overgang.

## Selektivt runtime-filsett

1. app/views/navigation.php
2. app/views/sidebar.php
3. app/views/head.php
4. public/assets/studio.css
5. public/assets/ai-studio/app.mjs
6. public/ai-studio.php

head.php endrer bare CSS-versjonen for å unngå gammel meny-CSS i cache.
ai-studio.php endrer bare JavaScript-versjonen i script-adressen.
app.mjs endrer bare åpning av menygruppen rundt aktiv konsolllenke.
Ingen API, autentisering, kø, lydmotor, innhold, brukerregister eller
redaksjonell godkjenning endres. Hver lenke beholder originalruten og
serverbasert tilgang; visning erstatter ikke tilgangskontroll.

## Kontroller

Utvidet tests/controlroom.php kontrollerer alle tidligere destinasjoner nøyaktig
én gang per rolle, tilgangsgrenser, valgt arbeidsrom og ulikt navn på redaksjonell
liste og faktisk kø. tests/navigation-mobile.cjs prøver fire roller ved
320/390/800/1440 px, tastatur uten JS, åpne/lukkbare grupper og horisontal overflyt.
Eksisterende studio-board-mobile-test kontrollerer også at overgang til
innstillinger og tilbake bevarer ulagret manus uten å starte lyd.
CI lager skjermbilder som studio-menu-screenshots.

Kontrollert kildecommit: 9bbfae2c7b97414c2b3b96b18aeafa731cb6c5d8.
[PHP-/mobilkjøring 41459886557](https://github.com/toystad461/radiorubben-studio/actions/runs/41459886557)
består på PHP 8.2/8.4 og i full eksisterende mobil-/desktop-suite.
De nye menytestene og konsollbytte med bevart ulagret manus består.
Skjermbildene på 390 og 1440 px er også visuelt gjennomgått.
Køkontraktene på PHP 8.2/8.4 besto på 49e37ac; de siste endringene gjelder
bare fokusmarkering i CSS, fjerning av dobbelt Studio-navn og uendret filmodus.
Kandidatmanifestet er oppdatert til ny navigasjonshash. Ingen test er fjernet
eller gjort svakere for å få grønt resultat.
Grønn CI og skjermbilder dokumenterer isolert kode, ikke aktiv VPS.

## Installasjonsstatus og neste handling

Ikke installert gjennom denne leveransen. Ingen merge eller helutrulling.
Den tilgjengelige økten har ingen konfigurert VPS-SSH-identitet.
Nettleseren avviser https://5.249.252.78/sending.php med
ERR_CERT_COMMON_NAME_INVALID. Sertifikatvarselet er ikke omgått.

Ved gjenopprettet, autorisert driftstilgang:
- Les faktisk release og ferske hasher for de seks målfilene; bruk ikke gamle
  kvitteringer som dagens førhash. Avstem særendringer, særlig head.php/CSS/app.mjs.
- Kontroller grønn målrettet CI for eksakt commit. Lag privat kodebackup med
  opprinnelige rettigheter. Stopp ved uventet endring, og tilpass diffen før skriving.
- Installer kun avstemt filsett atomisk, med før-/etterhash og tilbakeføringsplan.
  Bevar alle øvrige filer og aktive data. Ikke start tjenester eller lyd på nytt.
- Kontroller innlogget desktop og mobil, alle arbeidsrom og rollegrenser,
  fungerende konsollankre, ulagret manus og fortsatt aktiv stream.
- Ved feil: gjenopprett bare de seks sikkerhetskopierte filene etter kontroll mot
  installert etterhash. Registrer kildecommit, konkret installasjon og etterkontroll.

Fysisk iPhone/Safari gjenstår til menyen er installert. Prøv menyåpning,
bibliotek, Redaksjon → Redaksjonell kjøreplan og tilbake til Studio.
