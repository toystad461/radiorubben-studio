# Menyopprydding – Studio #115/L02, 02.11.2026

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

Lokal Node-syntakskontroll består. PHP og testnettleser er ikke installert i
denne arbeidsøkten; utførelse og resultat følges i GitHub Actions.
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
