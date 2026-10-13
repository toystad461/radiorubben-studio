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

PHP8.2/8.4 med syntetiske transport-/modelldata: tvetydige treff, feil varighet,
annen utgave, ingen dublettjobb, manuell invalidering, tre forsøk, rolle/CSRF,
timeout, samtidige rettelser, kildehash og bevaring. Sletting prøvd med bekreftet
register-/filfjerning og simulert purgefeil. Ekte multipartnettleser prøver
sletting med checkbox og bekrefter at syntetisk original og registerrad er borte.
Eksisterende liste/låtfane, private lydruter og mobilkontroller beholdes.
