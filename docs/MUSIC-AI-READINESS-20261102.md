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

## Kontrollstatus

Ny PHP-test dekker gyldige og utdaterte vurderinger, teknisk cue uten full analyse, manglende artist, manglende original, kjørende analyse, jingler og at bibliotek/originaler ikke skrives. Den dedikerte CI-jobben prøver både PR104s appkode og fastlåst L02-kode 650e24cde9deebb98d65976189503e170fd7c56b. Native PHP finnes ikke i agentens lokale miljø; faktisk CI-resultat skal leses før testen meldes bestått.

VPS-kontroll er blokkert i agentøkten: ingen SSH-nøkkel er tilgjengelig, lokal nettleserkobling er ikke eksponert og sky-nettleseren avviser VPS-IP-ens sertifikat med ERR_CERT_COMMON_NAME_INVALID. Ingen sikkerhetssperre er omgått. Eksisterende Windows/Mac-alias rubben-test er dokumentert vei videre. Faktisk bibliotekantall, API-tilgang, installasjon og KI-pilot er derfor ikke bekreftet.
