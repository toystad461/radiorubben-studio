# Nettsak og publisering – selektiv utrulling

## Status
Implementert og testet med PHP WASM 8.2/8.4. Ekte WordPress-overføring og visuell Studio-test gjenstår. Ingen artikkel publiseres som del av installasjonen.

## Utrulling
Bare disse fem filene: studio-private/app/web-publish.php, studio-private/app/case-workflow.php, studio-public/case.php, studio-public/desk.php og den aktive sending.php med to navigasjonslenker. Bevar aktiv board.php, news-script.php, programprofil og alle private data. Ikke last opp hele PR-grenen.

## WordPress
Opprett et applikasjonspassord i WordPress for en godkjent bruker med nødvendige innleggsrettigheter. Brukeren gjør dette selv; passordet skal aldri i chat eller GitHub. Lag studio-private/config/wordpress.php fra wordpress.example.php med brukernavn og applikasjonspassord. Fast mål er https://www.radiorubben.no/wp-json/wp/v2/posts, HTTPS med sertifikatkontroll og uten redirects. Ingen endring av WordPress-roller eller sikkerhetsinnstillinger.

Administrator i Studio kan sende kladd. Publisering krever separat, fersk kilde-/språkkontroll for nettsaken og manuell godkjenning. Radio og nett beholder separate tekster/kontroller. Rettelser opphever godkjenningen. Nettoperasjoner endrer ikke radiomanuset. Bildevalg er ikke med i denne første versjonen.

Uavklart transport låser ny overføring: ikke fjern sperren eller send på nytt automatisk. Finn eventuell post med slug studio-<saksid> i WordPress og avstem lagret ID/status før administrator gjenåpner saken. Egen brukerflate for avstemming gjenstår. Dette er bevisst en stopp ved ukjent resultat, ikke en påstand om exactly-once-levering.

Ny saksflyt: /desk.php → Åpne sak → klargjør radio eller nett → se konkrete avvik → lagre rettelser og kontroller → godkjenn → sending / WordPress-kladd / publisering. Robåt-læring bruker eksisterende godkjenningsflyt via lenke på saken; ingen automatisk læring fra rå RSS.

Tester: stale revisions, manglende godkjenning, gjentatt kladd, publisering av samme post-ID, opphevet kontroll etter redigering, rollebegrensning, timeout og sperret retry, bevaring av radiomanus. Nettverk og modell er mocket. Før fullføring må en ekte kladd opprettes og bekreftes i WordPress. Ingen endelig liveverifisering er gjort ennå.
