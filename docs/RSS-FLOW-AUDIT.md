# RSS-flyt – avstemt 05.10.2026

Grunnlag: konsolidert Studio etter PR #19, #22, #23, #24 og #25.
Den tidligere main-auditen i denne PR-en var for snever og erstattes her.

## Bekreftet i kode
NewsDesk finner NRK/Bømlo-kilder. Kildens normaliserte identitet dedupliseres
og lagres som originId i board; web og radio bruker samme board-item.id.
Web krever hentet original, aktuell kildekontroll, administrator, lest-bekreftelse
og eksakt revisjon/hash. Språk og faktadekning vurderes i separat modellpass;
dette er ikke en garanti for feilfrie fakta og krever redaksjonell vurdering.

## Rettet avvik
Bakgrunnsjobben opprettet bare web. Den lager nå også et manglende radioutkast.
Begge produksjoner bruker samme originale tekst, hash og hentetidspunkt.
Radio forblir utkast til manuell godkjenning i Studio. Nettendringer erstatter
aldri en allerede lagret radiotekst. Feil vises, og nettutkastet bevares ved
radiogenereringsfeil.

## Gjenværende veier og begrensninger
robot.php er fortsatt en egen artikkelutkastflate. case.php har separate
manuelle radio-/web-handlinger. RadioRubben-robot har sin egen kommunale
RSS-import/eksport, mens NewsDesk leser RSS direkte og bruker egen originId.
Disse parallelle inngangene er ikke en samordnet, verifisert produksjonsmotor.
Robot event-ID må ikke omtales som identisk med Studio board-ID.
Automatisk nyhetsdesk er den undersøkte produksjonsveien; ikke alle eldre
veier eller fotballsaker inngår i denne rettelsen.

Daglig automatikk er opt-in, maks åtte saker og én ny sak per kjøring.
Originaler/kontroller kan blokkere klargjøring; ingen blind gjentakelse.
WordPress-kø er lest autentisert. Ekte RSS-test bekreftet registrering/dedup i
Robot, men fullflyt fra ny kilde til begge utkast på aktiv server gjenstår.
Ingen ekte sak er sluttgodkjent eller publisert i dette arbeidet.

## Deployment
PR-merge og CI er ikke serverbevis. Bevar aktive innloggingsfiler, private
data og konfigurasjon. Avstem råbyte-hasher, privat backup og rollback før
selektiv deploy. Hele hovedgrenen skal ikke lastes over serveren uten avstemming.
