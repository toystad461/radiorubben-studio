# Musikkprodusent – nettoppslag, jobb og sletting

Utvikling 13.10.2026, del av PR81. Ingen produksjonsaktivering.

## Automatisk klargjøring

`app/music-library-worker.php` kan kjøres med PHP CLI av hostingens scheduler.
Den er privat, ikke et åpent webendepunkt, og behandler maksimalt én låt per
kjøring. Jobblås hindrer overlapp. Manglende filmetadata klargjøres først.
Jobbidentitet bygger på original, metadata og manuelle rettelser. Ferdig arbeid
hentes ikke på nytt; nye rettelser starter nytt arbeid. Ved midlertidig feil:
nytt forsøk etter 15/30 minutter, maksimalt tre forsøk. Ingen rå feilmeldinger,
API-nøkler eller filer legges i jobbstatus.

MusicBrainz-søk krever eksakt normalisert artist/tittel, score100, varighet
innen tre sekunder og én opptaksidentitet uten uavklart disambiguering. Andre
treff blir «må vurderes» og utløser ikke AI-kall. Dette er en konservativ
matchregel, ikke garanti for riktig opptak. Remix/radio edit kan derfor trenge
manuell vurdering. Treffer man én kandidat, hentes sjangerforslag med kilde-URL,
tidspunkt, kilde-/grunnlagshash og kontrollsum for det lagrede utdraget.
Originalfil og manuelle tags skrives ikke av oppslaget.

Bekreftet tekstgrunnlag brukes av eksisterende AI-transport til energi og
musikkflateforslag. Ingen musikklipp sendes. Forslag klargjort er ikke godkjent
rotasjon. Cue-/lydkontroll og endelig menneskelig rotasjonsgodkjenning gjenstår.

## Aktivering etter utrullingsgodkjenning

Avklaring 13.10: eksisterende fotballrobot bruker GitHub Actions over SSH,
ikke påvist lokal crontab. Kilde er radiorubben-web sin
`.github/workflows/fotballrobot-scheduler.yml`, kontrollert fra main via
GitHub-koblingen. Den har eksplisitt aktiveringsvariabel, fastlåst runnercommit,
concurrency og kjent SSH-vertsnøkkel. Driftskvitteringen fra 10.10 dokumenterer
ti vellykkede kjøringer og observerte mellomrom 448–867 sek selv om planen er
300 sek. Dette er et gjenbrukbart driftsmønster, ikke aktiv musikkjobbstøtte.
SSH-kontrollen fra 10.10 fikk exit127 for crontab. Kommandoen var ikke
tilgjengelig i den kontrollerte kontoen; dette utelukker ikke leverandørstyrt cron.

Anbefalt musikkdrift: egen, avslått Studio-jobb med eksplisitt aktivering,
fastlåst utrullet commit og kontrollert privat PHP-worker. Ikke legg musikk inn
i fotballjobben eller kopier ny runnerkode ved hver kjøring. Før aktivering må
Studio-repoets eksisterende SSH-oppsett avstemmes og syntetisk CLI-kjøring på
isolert mål bekreftes. Ingen workflow, hemmelighet eller aktiveringsvariabel er
opprettet eller endret her. GitHub-jobber kan være forsinket; musikkklargjøring
tåler dette, sanntidsavspilling må bruke annen mekanisme.

Oppslagsvilkår: skill datalisens fra API-bruk. MusicBrainz-kjernedata er CC0,
tilleggsdata er CC BY-NC-SA3.0. Sjangerforslag er derfor ikke automatisk fritt
til kommersiell bruk bare fordi artist/tittel er det. API-dokumentasjonen
henviser kommersielle brukere til planer eller kontakt. Bronze er publisert
som 100–599USD/måned for aktuelle offentlige produkter, men er ikke et
pristilbud eller bekreftet riktig kategori for Radio Rubben. Liten intern
lokalradio-bruk og sjangertags må avklares før terms-flagget settes. Ingen
bestilling eller kontakt er gjennomført.

Kilder kontrollert 13.10:
- https://github.com/toystad461/radiorubben-web/blob/main/.github/workflows/fotballrobot-scheduler.yml
- https://musicbrainz.org/doc/MusicBrainz_API
- https://musicbrainz.org/doc/About/Data_License
- https://metabrainz.org/supporters/tiers/2
- Driftskvittering: production-inventory/backup-job-summary.json
- SSH-kvittering: production-inventory/access-ops-server-confirmed-account.json

Privat konfigurasjon må eksplisitt sette `music_processing.enabled`,
`music_processing.online_enabled` og `music_processing.provider_terms_confirmed`
til true. Eksisterende OpenAI-konfigurasjon må være tilgjengelig. Standard er
avslått; ufullstendig konfigurasjon gir configuration_required før arbeid.
Ingen lokal.php eller scheduler er endret i utviklingsoppgaven. En verifisert
hostingplan/CLI-planlegger og aktivering må avklares før drift uten brukerens PC.

MusicBrainz krever meningsfull User-Agent og maksimalt ett kall per sekund.
Koblingen har delt lås og minimum1,1sek mellom kall, TLS-kontroll, fast HTTPS-
vert, ingen redirects, tidsgrenser og maksimalt256KiB respons. Ingen API-nøkkel
kreves for lesing. Kommersiell API-bruk krever avklaring av leverandørens vilkår
før flagget for terms settes. Ingen avtale/kjøp eller eksternt API-testkall utført.
Kilde: https://musicbrainz.org/doc/MusicBrainz_API

## Sletting

Administrator/produsent kan velge «Slett låten» i låtfanen, krysse av eksplisitt
permanent bekreftelse og trykke Slett låt og originalfil. Serveren krever rolle,
CSRF, ID, originalhash og aktuell revisjon. Låsen beskytter mot samtidige jobber;
en kjørende jobb kan ikke gjenopprette en slettet låt ved senere lagring.

Originalen flyttes midlertidig privat mens registerendringen bekreftes, og
slettes deretter. Mislykket registerlagring tilbakefører originalen. Feilet
endelig filfjerning vises som ufullført sletting, med privat filrest for
administrativ oppfølging. Brå prosessavslutning mellom flytting og registerbytte
kan kreve tilbakeføring av `.pending-delete-ID.mp3` til `originals/ID.mp3` etter
kontroll av registeret. Denne feilgjenopprettingen er foreløpig administrativ;
ikke påstå automatisk crash-recovery. Hostingbackuper endres ikke.

## Kontroll

Scheduler klargjort 13.10 i `.github/workflows/music-library-scheduler.yml`.
Den bruker eksisterende Studio production-miljø og SSH-innstillinger. Jobben
kjører bare fra main når `STUDIO_MUSIC_SCHEDULER_ENABLED` er nøyaktig true og
`STUDIO_MUSIC_SCHEDULER_COMMIT` er satt til godkjent 40-tegns commit. Den kopierer
ingen kode til serveren. Privat `music-library-scheduler.php` holder delt
deploylås, avstemmer release.json/selective-state og ti jobb-/avhengighetsfiler
før konfigurasjon leses og worker kjøres. Begge de separate aktiveringsnivåene
(GitHub og privat music_processing-konfigurasjon) må være på.

Planlagt intervall er ti minutter, maksimalt én låt per kjøring. GitHub kan
forsinke eller hoppe over kjøringer; dette er ingen sanntidsgaranti.
En senere kodeutrulling krever ny avstemming av fastlåst commit for å fortsette.
Det er ikke opprettet GitHub-variabler, nye nøkler, konfigurert hosting eller
startet jobb i produksjon. Isolert hosting-test avventer autorisert utrulling.
PHP8.2/8.4 tester riktig versjon, feil ref/state/release og endret worker.

PHP8.2/8.4 med syntetiske transport-/modelldata: tvetydige treff, feil varighet,
annen utgave, ingen dublettjobb, manuell invalidering, tre forsøk, rolle/CSRF,
timeout, samtidige rettelser, kildehash og bevaring. Sletting prøvd med bekreftet
register-/filfjerning og simulert purgefeil. Ekte multipartnettleser prøver
sletting med checkbox og bekrefter at syntetisk original og registerrad er borte.
Eksisterende liste/låtfane, private lydruter og mobilkontroller beholdes.
