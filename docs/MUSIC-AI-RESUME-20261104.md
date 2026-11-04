# KI-musikk – kontrollert gjenopptakelse 04.11.2026

## Bekreftet fra VPS kl. 15:01 norsk tid (14:01 UTC)

Thomas kjørte diagnostikken fra ad2a53c3. Prøvekallet returnerte HTTP200, gyldig vurdering og confidence=high. Prøven lagret ikke vurderingen; bibliotekets før-/etterhash er identisk. Dette beviser fungerende nøkkel, forbindelse og svarskjema for den avgrensede musikktransporten. High er modellens egen vurdering, ikke en kvalitetsgaranti.

Faded (09ae1d7bac4aedbd690a5c5f18b130cf) har komplett lydgrunnlag, men samme inputKey har tre feilede forsøk. Den ordinære workerens grense hindrer derfor et nytt automatisk forsøk. Fenrik Lane-jobben d6600d84cb255760c241105f6c46ac54 står running med utløpt arbeidsleie; den eksisterende nettleserkøen kan overta den ved neste kjøring. Ingen lydjobb er nullstilt eller merket ferdig av denne endringen.

Installed worker SHA256: c400e0e4433b297f2cb0887a13f97a44ccb545ae62b556d6add4b0c4089b5881. Aktuell app er fortsatt releases/studio-music-fad12a2320de0ff0a036854c414accee794143bb. Inaktiv oneshot med siste exit0 viser at programmet avsluttet, ikke at en låt faktisk fikk vurdering. Antallet121 stammer fra kontrollen02.11; det er ikke opptalt på nytt04.11.

## Rettelse og avgrensning

Nyere Studio-kode har producer_transport_config som kan styre den felles producer_request til manusrelé og bytte modell. Den gamle musikkworkeren bruker denne funksjonen, mens det vellykkede diagnostikk-kallet bruker musikkens egen HTTPS-transport. Dette er en konkret koblingsrisiko; historiske feil før denne endringen er ikke forklart av prøven.

Musikkworkeren bruker nå fast OpenAI-endepunkt med sin egen modell og eksisterende proxy/IP-isolasjon. Den deler fremdeles musikkprodusentens inndata, JSON-skjema, validering og lagring. Manusrelé, privat Studio-konfigurasjon og global modell endres ikke.

Ny CLI --retry krever én eksplisitt track-id. Bare samme feilede grunnlag med minst tre forsøk er kvalifisert. Ett nytt forsøk registreres før API-kallet, tidligere status/forsøk beholdes, og samme gjenopptakelse kan ikke kjøres flere ganger. Et allerede gyldig resultat gjenbrukes. Original, manuelle tags/cues, sendegodkjenninger og samtidige rettelser bevares. Lagret vurdering leses tilbake før prepared returneres.

## Selektiv oppdatering

scripts/music-ai-resume-vps.py er en ny oppdaterer for det allerede installerte testoppsettet. Den eldre --install skal ikke kjøres over aktive tjenester.

Oppdatereren krever root, kjent installert workerhash, verifiserte kildefiler, kjent tjenestekommando, ingen service-dropins og aktiv musikkproxy. Den kjører PHP-syntaks og syntetiske worker-tester mot den faktisk installerte appen, samt en lesende konfigurasjonskontroll før pause/filendring.

Deretter:
1. Privat sikkerhetskopi av worker, tidsstyringens opprinnelige status og bibliotek før forsøket.
2. Disable/stop kun rr-music-ai.timer og vent på at eventuell pågående vurdering fullfører.
3. Atomisk erstatt bare /etc/rr-music-ai/worker.php og verifiser SHA256.
4. Kjør ett eksplisitt nytt forsøk som rr-studio med samme proxy og IP-begrensning.
5. Returner lagret vurdering og la KI-timeren være deaktivert for den avtalte piloten.

Ved feil tilbakeføres worker og tidligere timerstatus. Bibliotekets forsøkshistorikk og samtidige endringer tilbakeføres ikke. Private backupfiler skrives ikke til GitHub eller utskrift. En kvittering viser bare backupbanen.

Kildepakken består av music-ai-resume-vps.py, music-ai-worker.php og test-music-ai-worker.php i samme midlertidige mappe. Oppdatereren verifiserer de to PHP-filenes hasher selv. Kjør sudo python3 music-ai-resume-vps.py --apply --track-id=09ae1d7bac4aedbd690a5c5f18b130cf. Dette er ikke en hel Studio-utrulling.

## Kontroller og videre arbeid

Seks syntetiske Python-kontroller dekker selektiv oppdatering, timerpause, privat backup, rollback uten tap av samtidige bibliotekendringer, avvisning av ukjent kilde/installasjon samtidige oppdaterere og bevart nettverksisolasjon. PHP-testene dekker eksisterende kø/lagring og nytt eksplisitt engangsforsøk, gjentatt kjøring, feilet nytt forsøk, modell/transport og original/manuell bevaring. CI prøver både PR104 og siste avstemte L02-kode75c6e568.

Faktisk oppdatering, lagret Faded-vurdering og ti-låts-pilot er ikke utført av agenten. Denne skyøkten har ingen SSH-agent eller test-VPS-nøkkel. Thomas sin Windows-opplåsing gjelder radiorubben_uniweb; rubben-test bruker radiorubben_test_ed25519. En lokal opplåsing videresendes ikke automatisk hit.

Neste etter kvittering: vurder første lagrede forslag, fullfør manglende lydanalyse via eksisterende bibliotekskø, velg omtrent ni ytterligere representative låter og kjør avgrenset pilot før periodisk KI-kjøring gjenopptas. Full lydanalyse er fortsatt nettleserbasert; denne leveransen flytter ikke analysemotoren til VPS.

Worker-koden i a8713a1 besto målrettet CI41845474039, også mot L02-kode75c6e568. Oppdatereren er i tillegg låst mot samtidig kjøring og etterkontrollerer workerhash/timerstatus etter forsøket. De seks lokale Python-testene består. Ny CI må fortsatt avleses for denne siste oppdatererendringen.
