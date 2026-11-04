# KI-musikkvurdering – lesende kontroll, 02.11.2026

## Behov og avgrensning

Brukeren har bestilt kontroll av eksisterende bibliotek, ferdigstilling av KI-jobben, et prøveutvalg på omtrent ti låter og deretter behandling av resten. Viderefør PR104. Før aktivering må faktisk VPS-tilstand være kjent; appgrener er selektivt installert og main er ikke driftsfasit.

Denne delendringen lager en lesende inventarkontroll. Ingen runtime-fil, tidsstyring, nøkkel, godkjenning, kø eller original endres av kontrollen. Den bruker den installerte appens egne valideringsfunksjoner og laster ikke privat konfigurasjon.

## Hvorfor kontrollen trengs

L02s technicalCheck og automatiske stillhetspunkter er ikke det samme som audioAnalysis og musicAudioProfile. PR104s KI-worker krever begge de sistnevnte. En avspillingsklar låt kan dermed mangle grunnlaget som KI-jobben krever.

Kontrollen teller musikk separat fra jingler, gyldige metadata, tempo/toneart, full lydprofil, teknisk kontroll og gjeldende/utdaterte KI-forslag. Per låt vises manglende forutsetninger, energi/sendeflater og tidligere jobbstatus. ReadyForAi betyr bare at forutsetningene er på plass; modellen, API-tilgangen og eventuell ventetid/forsøksgrense må fortsatt kontrolleres. Manglende fil kontrolleres slik dagens leser gjør det; denne rapporten dekoder eller hasher ikke samtlige lydfiler.

## Kjøring på VPS

Kjør en hashkontrollert kopi av scripts/music-ai-inventory.php som rr-studio:

```sh
sudo -u rr-studio php /path/to/music-ai-inventory.php --app-root=/srv/radiorubben-test/studio
systemctl show rr-music-ai-proxy.service rr-music-ai.service rr-music-ai.timer --property=Id,LoadState,ActiveState,SubState,UnitFileState
```

/path/to er en plassholder for den kontrollerte kopien, ikke en opprettet serverfil. Rapporten inneholder ingen API-nøkler. Ikke last opp hele library.json eller config/local.php. Dersom status er library_changed_during_read, må kontrollen gjentas når den samtidige bibliotekendringen er ferdig.

## Videre gjennomføring

1. Avstem rapportens kodehasher og forutsetninger mot aktivt Studio. Identifiser manglende grunnanalyse, metadata og eventuell stoppet KI-jobb.
2. Kontroller eksisterende jobboppsett og API-tilgang uten å vise hemmeligheter. Ikke kjør den eldre engangsinstallatøren blindt over eksisterende enheter.
3. Klargjør kun nødvendige mangler. Bevar originaler, manuelle felt/cues, programgodkjenninger og aktuell sendekø.
4. Kjør omtrent ti representative låter med den eksisterende strukturerte vurderingsfunksjonen, avgrenset fra timerkjøring. Kontroller lagring, grunnlagsbinding, begrunnelse og om sendeflateforslagene passer. Dette er fortsatt forslag, ikke automatisk sendegodkjenning.
5. Etter tilfredsstillende pilot: behandle resterende kvalifiserte låter, dokumenter antall ferdige og avvik, og verifiser at musikkvelgeren bruker gjeldende vurderinger.

Det eldre --install-løpet aktiverer timeren etter én vurdering. Det er ikke den avtalte ti-låts-piloten og skal ikke brukes uendret for denne leveransen.

## Faktisk VPS-kontroll mottatt 02.11.2026 kl. 13:59 UTC

Thomas kjørte den fastlåste inventory-kopien fra 5267ad23. Rapporten har status read_only_inventory, og bibliotekets før-/etterhash er identisk. Aktiv app peker på releases/studio-music-fad12a2320de0ff0a036854c414accee794143bb. Dette er en faktisk runtime-observasjon; den erstatter eldre antakelser om at KI-tjenestene ikke er installert.

| Kontroll | Resultat |
| --- | ---: |
| Registrerte filer | 129 |
| Musikk / annen lyd | 121 / 8 |
| Gyldige filmetadata | 121 |
| Gjeldende tempo/toneart | 2 |
| Full lydprofil | 1 |
| Teknisk cue-kontroll klar | 114 |
| Gjeldende KI-vurdering | 0 |
| Komplett grunnlag for KI | 1 |

119 låter mangler gjeldende tempo/toneart, og 120 mangler full lydprofil. Én låt mangler artist. Én lydjobb står running; rapporten beviser ikke om arbeidsleien fortsatt er aktiv. Den ene kvalifiserte låten har musicAiJob=failed og tre forsøk; det må kontrolleres om inngangsgrunnlaget fortsatt er identisk før den tolkes som sperret av forsøksgrensen.

rr-music-ai-proxy.service er active/running og rr-music-ai.timer er active/waiting, begge enabled. Worker-tjenesten er inactive/dead mellom kjøringene; dette alene er normalt for Type=oneshot, ikke bevis på en feil. Ingen gjeninstallasjon er nødvendig ut fra dette funnet.

Full analyse i eksisterende kode skjer fortsatt i musikkbibliotekets åpne nettleserfane. L02s tekniske serverkontroll leverer ikke tempo/toneart og full profil. Avstem nå også kodehasher for køsiden og nettlesermotoren før videre feilsøking. Bruk eksisterende klargjøringskø; ikke merk tekniske cue-punkter som fullført analyse.

## Avgrenset API-diagnostikk

scripts/music-ai-diagnostics.php --check --app-root=... gir bare lokal status. --probe krever i tillegg --track-id=... og gjør maksimalt ett kall med den installerte musikkprodusentens virkelige skjema og låtens metadata/målinger. Den sender ikke original lyd og lagrer ikke vurderingen. Et gyldig prøvesvar er derfor ikke en ferdig vurdert låt i biblioteket.

Kjør kopien midlertidig som rr-studio med samme localhost:3140-proxy og IP-begrensning som KI-jobben, men med hele filsystemet skrivebeskyttet. Ingen timer, konfigurasjon, forsøksgrense, godkjenning, original eller sendekø endres. Ikke bruk --once eller nullstill tre forsøk før årsaken er kjent.

Rapporten viser transportfeilkode, HTTP-status, et begrenset sett kjente API-feilkoder, ufullstendig svar eller valideringsfeil. Rå API-feiltekst, exception-tekst, nøkler og svarinnhold skrives aldri ut. Kontroll av nøkkel er bare apiKeyPresent; et positivt felt beviser ikke at nøkkelen virker. Kontroll av likt inngangsgrunnlag og lydjobbenes aktive arbeidsleie er inkludert.

Målrettede tester bruker syntetisk transport med den faktiske produsentfunksjonen og kontrollerer 401/429/transportfeil, ufullstendig/ugyldig svar, ingen nøkkellekkasje, nøyaktig ett kall, gyldig uspart vurdering og uendret bibliotek/original. De kjøres også mot fastlåst L02-kode. CI-resultat må leses før bestått status oppgis.

Neste: få denne rapporten via eksisterende rubben-test-tilgang, rett den konkrete API-/svarfeilen, og avgrens timeren før ca. ti låter ferdiganalyseres og vurderes. Full pilot og behandling av resterende bibliotek gjenstår; agenten har fortsatt ingen egen SSH-tilgang.

## Tidligere verifisering

Inventory-kode 5267ad23 besto målrettet PHP8.3/installer og L02-kompatibilitet i run 41457009471, PHP8.2/8.4 og nettleser/mobil i run 41457009329. Ingen serverutrulling ble kjørt av agenten. Den faktiske lesende VPS-kontrollen over er mottatt etter disse kjøringene.
