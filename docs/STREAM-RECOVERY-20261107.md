# RR-STREAM-20261107 – strømbrudd og gjenoppretting

## Behov og avgrensning
Gjenopprett offentlig lyd og rett kø-/oppstartshåndteringen uten å endre
redaksjonelle godkjenninger, køinnhold, originalmedier, WordPress eller DNS.
Thomas autoriserte gjenoppretting og feilretting i denne samtalen 07.11.2026.
Ferdigkriterier: offentlig dekodbar lyd, fersk PCM-status, naturlige overganger,
regresjonstester for låsekonflikt og reell motorstans, og sporbar installasjon.

## Verifiserte funn (Europe/Oslo)
- 04.39.55: journalhendelse 8092, `engine_fault`, `claim_error`.
- 04.39.56: avspilling avbrutt og motor stoppet. Tjenesten var fortsatt aktiv.
- 07.52: `/radio.mp3` svarte 404; Icecast/VPS var i drift.
- Første Start kl.07.56.38 fikk forbindelse først kl.07.57.18 og ble stoppet
  av gammel 20-sekunders oppstartsgrense. Mottatt MP3 var bare stillhet.
- 56 MB journal ble JSON-tolket fra begynnelsen ved hvert tilstandsoppslag.
- Kø-claim ventet på eksklusiv lås inne i en RPC med fem sekunders timeout.
  En opptatt lås kunne derfor bli behandlet som en fatal køfeil.
- Isolert sekssekunders låsekonflikt avdekket dessuten falsk PCM-timeout i
  supervisor. Den testen feilet før korrigeringen av hendelsesbufferen.

Den gamle loggen lagret ikke underliggende CLI-feil/returkode. Det kan derfor
ikke bevises i ettertid hvilken konkret venting eller exception utløste 8092.
Låsekonfliktmekanismen er gjenskapt; ikke presenter den som fullstendig
rekonstruksjon av nattens hendelse.

## Rettet
- Claim returnerer uten reservasjon når kølåsen er opptatt. Motoren prøver igjen.
- Supervisor samler PCM-hendelser uten blokkerende låseventing. Alle hendelser
  behandles når låsen blir ledig; bufferen har en øvre grense.
- Kald oppstart får 90 sekunder til tilkobling; etter tilkobling er grensen
  for første lyd fortsatt 20 sekunder. PCM-watchdog beholdes.
- Lokal minnebuffer gjenbruker journaltilstand bare ved uendret XXH128 over
  hele journalen. Endret innhold blir fullt validert på nytt. Dette er en
  endringsdetektor for lokalt betrodde skriverprosesser, ikke autentisering.
  Lydfiler bruker fortsatt SHA-256. Ingen journalhistorikk slettes eller roteres.
- Tidligere PR118-retting av kort innslag først i køen er gjenbrukt.
- Ingen automatisk forbikjøring av blokkert eller endret lyd; Stop respekteres.

## Kontroller
- PHP state-/event-regresjoner: består på VPS i isolert testmappe.
- Ny regresjon: opptatt lås returnerer innen 0,5 sekunder; append, samme-lengde
  korrupsjon og ufullstendig journal; separate oppstartsgrenser: består.
- Ekte isolert Liquidsoap/Icecast: fire 14-sekunders innslag, ca.2,96 sekunders
  musikkoverganger og ingen tilsiktet jingle-overlapp: består.
- Seks sekunders lås under ekte avspilling: begge innslag fullført, 985600 byte
  mottatt: består etter supervisorrettingen.
- Windows-testkjøring traff plattformens rename-begrensning; de samme testene
  er derfor gjennomført på Linux. Windows-feilen er ikke ført som bestått.

## Produksjon
Første selektive installasjon: kilde89e14f5, backup
`/var/lib/rr-vps-deploy/stream-recovery-20261107-h4b63_3s`.
Fire player-code-filer installert med før/etter-hasher. Eksisterende eldre
publish-funksjon i player-code/app/music-playout.php beholdes uendret;
Studio-kopien av denne filen er ikke byttet. Dette er selektiv installasjon.
08.02.34: ekte PCM-start av Radio Rubben Jingle. Deretter Fake It og Capsize,
med bekreftede naturlige overganger. Eksternt HTTPS-mottak: HTTP200,
278600 byte på12sekunder, TLS-verifisering0. Servermålt15sekunders opptak:
358400 byte, gjennomsnitt -23,7dB, topp -3,3dB. Lyd er målt, ikke bare HTTP.
Endelig supervisorinstallasjon og siste etterkontroll føres nedenfor.

## Neste anbefalte arbeid
En egen VPS-basert lydvakt bør kontrollere faktisk lyd og varsle ved stans.
Automatisk gjenoppretting må skille midlertidig venting fra ugyldige filer og
operatørens Stop. Dette er en anbefaling, ikke opprettet overvåking i denne saken.
Journalreplay er fortsatt lineært når historikken endres; en validert checkpoint-
løsning er aktuelt ved videre vekst. Ingen garanti om ubegrenset drift er testet.

## Endelig supervisor aktivert08.11.40 (07.11.2026)
Kildecommit2c1f417. Kun scripts/music-liquidsoap.php oppdatert i andre trinn.
Backup: `/var/lib/rr-vps-deploy/stream-recovery-20261107-7_t656v0`.
Ny Start08.11.43; faktisk PCM08.11.59, kort jingle etterfulgt av Shape of You.
Eksternt15sekunders mottak: HTTP200,354200byte,TLS-verifisering0.
Frosset PCM ble fortsatt oppdaget og avsluttet i separat isolert test.
Tilbakeføring må skje i omvendt rekkefølge (andre backup før første), med
kontroll av at nåværende hasher matcher kvitteringen og manuell Start etterpå.
GitHub PR: https://github.com/toystad461/radiorubben-studio/pull/120
Første CI-runde avdekket at kandidatmanifestets kodehasher måtte oppdateres;
kontrollen er beholdt og manifestet avstemt mot Git-filene med LF-linjeskift.
