# Studio – felles status og arbeidsliste

Oppdatert 30.09.2026 etter direkte lesing via Uniweb File Manager.
Eier: Thomas Magne Sellevold-Øystad.
Repo: https://github.com/toystad461/radiorubben-studio

## Start her
Følg [AGENTS.md](../AGENTS.md). Det ferske
[Uniweb-kodegrunnlaget](../snapshots/uniweb-20260930/README.md) erstatter den
tidligere antakelsen om at produksjonsfiler ikke var tilgjengelige.
[Manifestet](../snapshots/uniweb-20260930/manifest.json) inneholder 80 filhasher
og sammenligning med 20 låste GitHub branch-tips.

## Konsolidering i PR #19

Servergrunnlaget er samlet i repoets app/ og public/, med rettet Uniweb-pakking
og logo fra brukerens logopakke. Se [avstemming og tester](CONSOLIDATION-20260930.md).
Snapshotet beholdes uendret. Ingen produksjonsdeploy eller merge er utført.
Aktuell kildecommit og CI-status finnes på PR #19.

## Bekreftet ved kildeinnsamling
- Privat serverbackup av app og offentlig kode er opprettet; config, vendor,
  docs og rotfiler er også kopiert. Backupen er tatt i flere trinn.
- 80 tekstfiler er bevart i Git-snapshotet uten private innstillinger/data.
- Mot main: 10 identiske, 12 ulike og 58 manglende filer.
- Mot alle undersøkte branch-tips: 41 identiske, 11 ulike og 28 manglende.
- 54 PHP-filer passerer syntakskontroll på PHP WASM 8.2/8.4; 15 JS-moduler
  passerer Node-syntakskontroll. Ingen ende-til-ende-godkjenning er gitt.
- Nyhetsmanus og nettpubliseringsmoduler finnes på serveren og matcher deler
  av gjeldende PR #18. Den gamle teksten «ikke deployet» er ikke pålitelig
  som beskrivelse av hele dagens produksjonskode.
- programs.php finnes ikke i den kopierte app-katalogen. board/sending/story-script
  avviker fra gjeldende GitHub-grener. Ingen eksisterende patch skal lastes opp
  uten ny avstemming.
- Ingen aktiv kode er endret. Hovedgrenen og standardpakken er fortsatt ikke
  en bekreftet kopi av produksjonen.

## Arbeidsliste
| Prioritet | Status | Oppgave / ferdigkriterium |
| --- | --- | --- |
| 1 | Utført for tekstkode | Serverbackup, kildeinnsamling og filkart med teksthasher. Råbyte-hasher, binærlogo og avhengighetslås må fortsatt avstemmes før reinstallasjon. |
| 2 | Samlet i PR #19 | Lag konsolidert runtime-gren fra kjent servergrunnlag. Avstem 11 ulike filer og nødvendige eksempelinnstillinger/avhengigheter uten å miste aktive funksjoner. |
| 3 | Funksjonstester innført; UI gjenstår | Kjør funksjonstester og produksjonsnær UI; avklar PR #13-konflikt og integreringsrekkefølge for programregister, sendeforslag og vær. |
| 4 | Produktmål | Samle automatisk klargjøring, kilder, kontroll, redigering og manuell sluttgodkjenning i én Studio-flyt. |
| 5 | Senere | Verifiser faktiske OneDrive-mapper/filreferanser og bygg ElevenLabs-lydsporet; playout behandles separat. |

## Beslutninger
Codex er fast utviklingsverktøy. Tekniske regler og status skal ligge i repoet.
Kodeendringer går via GitHub før publisering; eventuelle nødrettelser på
serveren må tilbakeføres straks med dokumentert avvik.
Bruk én avgrenset hovedoppgave om gangen og gjenbruk eksisterende PR når det
er samme arbeid. Ikke opprett flere parallelle oversikter.
Manuell redaksjonell sluttgodkjenning beholdes. OneDrive-lenker skal være
verifiserte filreferanser; ingen synk eller lydintegrasjon er aktivert her.

## Oppdatering ved hver levering
Noter oppgave/PR og eksakt head, endrede filer, tester, uprøvde integrasjoner,
publiseringsbevis eller «ikke publisert», og neste steg.
Skill alltid mellom innsamlet kode, testet kode, integrert kode og deployet kode.
Samtalehistorikk, grønn CI og PR-tekst alene er ikke serverbevis.

## Kontroll 01.10.2026

Kontrollert mot runtime-commit f7ce90ce393a625ba4b9ffc4cf494457dbb04244:
Uniwebs aktive vendor/composer/installed.json oppgir samme versjon og source.reference
som GitHub composer.lock for alle fire pakker: jumbojett/openid-connect-php v1.0.2,
paragonie/constant_time_encoding v3.1.3, paragonie/random_compat v9.99.100 og
phpseclib/phpseclib 3.0.57. Dette bekrefter pakkemetadata, ikke råbyte-likhet for
composer.lock eller integriteten til hver vendor-fil.

Nedlasting av composer.lock ga tidsavbrudd. En separat kopi av aktiv låsefil
ligger i studio-private/reconciliation-backup-20260930-2002/dependency-check-20261001/.
Originalen og de tidligere backupfilene er urørt. Foreslått endring av filtype
ble avbrutt uten å godta filbehandlerens advarsel.

Studio åpner innloggingssiden. Forespørselen om innloggingsmetode ble avbrutt;
innlogget nettlesergjennomgang er derfor fortsatt ikke utført. Ingen kode,
konfigurasjon eller redaksjonelle data er endret på produksjonsserveren.
Ingen merge eller deploy. Neste steg er innlogget UI-kontroll og avstemming av
ferske serverfiler før eventuell publisering.

## NRK Vestland RSS – 03.10.2026

Ny hovedoppgave: RSS-utvikling med brukerens to NRK Vestland-feeder, Toppsaker
og Siste nytt. Arbeidet bygger på PR #19-head
`049b42591e525055232503e4710787ad09b62139`, på egen gren `feat/nrk-vestland-rss`.
Render- og Entra-grenene er ikke endret.

Fire runtime-filer: ny `app/source-identity.php`, `app/integrations/NewsDesk.php`,
`app/board.php` og `public/newsdesk.php`. Felles Vestland-seksjon, toppsakmerking,
identitet på tvers av feedene og vern av eksisterende manus/godkjenninger.
Nasjonal NRK-feed erstattes; Bømlo kommune, trafikk og vær beholdes.
Egne syntetiske tester dekker dubletter, kildefeil, cache og eksisterende sendepunkter.

Ikke publisert. NRKs feedinnhold kunne ikke leses gjennom søkeverktøyet på grunn
av tilgangsblokkering; ingen omgåelse er forsøkt. Neste steg før selektiv
aktivering er fersk serveravstemming av berørte filer og bekreftet
Studio-deploytilgang, etterfulgt av faktisk kilde- og innlogget UI-kontroll.
Se [NRK Vestland RSS](NRK-VESTLAND-RSS.md) for avhengigheter og avgrensning.

Leveranse: [PR #22](https://github.com/toystad461/radiorubben-studio/pull/22).
Testet kode-head: `f39e10997205232b894ee4d51b7c00fdb69c6efd` (påfølgende commit
oppdaterer bare denne statusen). Hele PHP-suiten, JavaScript-kontroller,
Composer-validering/audit og pakking besto i
[CI-kjøring 37156663637](https://github.com/toystad461/radiorubben-studio/actions/runs/37156663637)
på PHP 8.2 og 8.4; pakking ble kjørt på 8.4. De 28 nye Vestland-kontrollene
og alle seks nyhetsdesk-tilgangstestene besto. Lokalt besto også RSS-parseren
og de 28 nye kontrollene i PHP-WASM. Testene bruker syntetiske RSS-data,
ikke NRK-innhold. Live kildehenting, innlogget produksjons-UI og deploy er
ikke utført. Ingen redaksjonelle data, brukerinnstillinger eller andre
greners kode er endret.


## Nyhetssaker til nett – 04.10.2026

Avgrenset neste trinn bygger på PR #22-head
`c96b27af7ab184f2e407fc5b3e2b83b68671ac11`, på egen gren
`feat/news-publication-profile`. Sakssiden får forhåndsvisning av nyhetsbildet
(media 1079), kategorivalg og korrekt leveringsstatus. Nye nyhetssaker bruker
Nyheter (8), kommunesaker også Lokale Nyheter (27). Objektene er bekreftet med
lesende WordPress-kall; ingen WordPress-innstillinger eller innlegg er endret.

Fire runtime-filer: `app/news-publication.php` (ny), `app/web-publish.php`,
`app/case-workflow.php` og `public/case.php`. Bildet/kategoriene følger første
opprettelse; senere oppdatering bevarer WordPress-redaktørens valg. Godkjenning
bindes til tekst, kildeattribusjon og nyhetsprofil. Gjenåpning fra en annen
NRK-feed gjenbruker eksisterende redigert sak. Kilde-/språkkontroll og manuell
sluttgodkjenning beholdes. Se [nyhetspublisering](NEWS-PUBLICATION.md).

Lokalt besto 33 nye publiseringskontroller, åtte sakssidekontroller og 10
regresjonskontroller i PHP-WASM, pluss JavaScript-simuleringer. Full native CI
kjøres ved levering; testet kodecommit og resultater dokumenteres på PR-en.
Ikke produksjonsaktivert. Neste steg er fersk serveravstemming av disse fire
filene og PR #22-avhengighetene, så innlogget Studio-test og kontrollert ekte
WordPress-kladd. Live originalhenting og ende-til-ende-overføring er uprøvd.
Ingen artikkel, automatisk jobb, OneDrive-overføring, merge eller deploy er gjort.


Verifisering: [PR #23](https://github.com/toystad461/radiorubben-studio/pull/23),
testet kodecommit `9795a3735c9d147e263f5deb66777c2103262275`.
[CI 37220266577](https://github.com/toystad461/radiorubben-studio/actions/runs/37220266577)
besto på native PHP 8.2 og 8.4: hele suiten, JavaScript, Composer-validering/audit
og pakking. Denne etterfølgende oppdateringen endrer bare dokumentert status.
PR #23 er åpen som draft og mergebar; ingen merge eller deploy er utført.
