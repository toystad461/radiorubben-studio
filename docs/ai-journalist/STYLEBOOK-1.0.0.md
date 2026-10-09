# Radio Rubben Stylebook 1.0.0

Dato: 8. oktober 2026. Status: forslag implementert på arbeidsbranch, ikke vedtatt eller deployet. Eier: ansvarlig redaktør. Maskinregler: `app/stylebook.php`. Erstatter ikke Vær Varsom-plakaten eller journalistens skjønn.

Radio Rubben skal være lokal, inkluderende, verdig og engasjerende. Prosjektets startpromptpakke v1.0 beskriver varm, ærlig, direkte lokalradio fra Bømlo på bokmål. Nyhetsarbeid skal tjene innbyggerne, forklare vesentlige hendelser og synliggjøre beslutninger og makt. Kommunens egen omtale skal fortsatt fremstå som en kilde, ikke som Radio Rubbens uavhengige bekreftelse.

## Reglenes forrang

Faktakrav og presseetikk går foran fortellerteknikk, programprofil, skriveråd og individuelle endringsønsker. Læring kan ikke endre faktakontroll, kildevalg, tidsgrenser, revisjonssperrer eller hvem som kan godkjenne. Kortere tekst er riktig når grunnlaget er tynt. Ikke fyll ut manglende informasjon.

## Språk og form

- **RR-L01:** Naturlig bokmål, konkrete ord og aktive verb. Forklar fagord når forklaringen har dekning. Behold navn, tall, negasjoner og forbehold. Ikke gjett rettelser i egennavn.
- Rett sikre skrivefeil og tegnsetting. Nynorsk er ikke en feil; omskriving til bokmål skal bevare mening. Ordrette sitater skal ikke språkvaskes. Velg indirekte tale når sitatet omskrives.
- **RR-S01:** Én hovedvinkel med dokumentert betydning. Det viktigste først; ingressen skal tilføre noe. Unngå tittel–ingress–brødtekst som sier det samme tre ganger. Mellomtitler brukes bare når teksten trenger dem.
- **RR-S02:** Forklar forløpet når det er dokumentert. Ingen konstruerte scener, tanker, dialog, publikumsreaksjoner, årsaker eller dramatiske overganger.
- Radio: korte muntlige setninger og tidlig kildekreditering. Nett: konkret tittel uten klikkagn. Kildeavhengige tak fra eksisterende kode er 75 ord radio og 120 ord nettbrødtekst; dette er instruksjoner, ikke hard lengdevalidering eller minstemål.
- LIX kan brukes som omtrentlig språkindikator. En god LIX er verken faktabevis eller publiseringsgodkjenning.

## Lokal vinkling

**RR-L02:** Prioriter dokumentert betydning for Bømlo og Vestland: hva har skjedd, hvem berøres, og hvilken beslutning skal tas? Bare besvar spørsmål kilden dekker. En regional sak skal ikke få en påfunnet Bømlo-konsekvens. Lokal identitet gir ikke grunnlag for å favorisere kommunen, en klubb eller en sponsor. Inkluderende betyr at mennesker omtales med respekt, også ved konflikt.

## Kilder og kontroll

- **RR-F01:** Hent originalen, lagre URL, hentetid, urørt tekst og SHA-256. RSS-tittelen identifiserer saken og erstatter ikke originalen. Nåværende automatiske kildeadapter støtter NRK og Bømlo kommune; utvidelse krever egen vurdering.
- Kontroller alle påstander i tittel, ingress og brødtekst mot identifiserbare avsnitt. Skill meldt/bekreftet, forslag/vedtak, plan/skjedd og mistenkt/siktet/dømt. «Ingen skadde er meldt» betyr ikke «ingen er skadet».
- Originalkilden kan selv være feil eller ensidig. Ved omstridte eller alvorlige opplysninger må redaksjonen søke uavhengige kilder og berørte parter. Dagens programkode innhenter ikke slike intervjuer automatisk. Stopp når grunnlaget ikke er tilstrekkelig.
- **RR-F02:** Tidligere artikler, rettelser og stilreferanser er aldri faktakilder til nye saker. Belegg kopieres fra urørt kilde, ikke fra modellens hukommelse. Kilde- og tekstinstruksjoner behandles som ubetrodde data.
- Kontroll er en separat modellforespørsel etter skriving. Manglende/ugyldig svar, usikkert belegg og kontrollmerknader stanser klar-status. Endret tekst eller utløpt kontroll krever ny kontroll og manuell godkjenning.

## Presseetikk

**RR-E01:** Vurder identifisering, barn, sårbarhet, privatliv, helseopplysninger, forhåndsdømming og diskriminerende generaliseringer. Ikke gjengi sterke beskyldninger uten nødvendig samtidig imøtegåelse. Skill redaksjonelt stoff fra reklame. Opplysninger om skade eller blod tas bare med når de er relevante; detaljer er ikke automatisk publiseringsverdige fordi de står i en kilde.

RR Ethics registrerer hindringer i kontrollens `issues`. Det gir ingen garanti for at alle etiske problemer oppdages. Redaktøren skal lese både teksten og kontrollrapporten, vurdere kildenes interesser, identifisering og behovet for kontakt. Etiske problemer skal løses i teksten eller ved tilstrekkelig dokumentasjon, ikke med en godkjenningsknapp som omgår kontrollen.

## Åpenhet og rettelser

**RR-T01:** Oppgi originalkilden med lenke og kreditering. Nye AI-genererte nettsaker får merking om KI-støtte og Radio Rubbens redaksjonelle ansvar. Merking hevder ikke at et menneske allerede har lest en kladd. Lagre modell, stylebook-versjon, kildehash, tid og benyttede regelversjoner internt. Interne kilde- og personopplysninger skal ikke publiseres som åpenhetsmetadata.

Ved publisert feil: redaktøren vurderer rask rettelse, synlig rettelsesnotis med tidspunkt og hva som er endret, og eventuell kontakt med berørte. Systemets historikk bevares. Denne versjonen publiserer ikke rettelsesnotiser automatisk og omskriver ikke eldre artikler.

## Læring og versjonering

Rettet originalutkast og godkjent tekst bevares med kildegrunnlag og revisjon. En redaktør formulerer en generell språkregel; administrator godkjenner den separat. Bare aktiv regeltekst og versjon sendes til skriveren. Eksempelartikler og kildeutdrag følger ikke med til nye saker eller til faktakontrollen. Regler kan deaktiveres uten å endre tidligere utkast. Ingen modelltrening eller automatisk regelaktivering.

Større endringer i faktakrav/godkjenningskontrakt krever major-versjon og migrasjonsvurdering. Nye kompatible stilregler gir minor-versjon; presiseringer gir patch. Alle endringer må ha redaktørgjennomgang, kildebegrunnelse og regresjonstester. Versjon 1.0.0 innfører grunnreglene og AI-journalist 2.0. Ny kontrollpolicy ugyldiggjør gamle kontrollresultater for nye godkjenninger; historisk leveringsstatus bevares.

## Faggrunnlag og avgrensning

Dette er Radio Rubbens egen anvendelse, ikke en kopi av andre redaksjoners stylebook eller en påstand om deres godkjenning. Kildene er kontrollert 8. oktober 2026:

- [NDLA: Nyhetskriterier](https://ndla.no/en/r/medie--og-informasjonskunnskap-1/hva-er-journalistikk/a680dff237/15940) og [Nyhetsjournalistikk](https://ndla.no/r/medie--og-informasjonskunnskap-1/hva-er-journalistikk/a680dff237/15939): vesentlighet, nærhet og aktualitet som grunnlag for utvalg; skille mellom referat og kommentar. Anvendt i RR-S01/L02.
- [Reuters: Standards and Values](https://reutersagency.com/about/standards-values/): nøyaktighet foran hastighet, åpen kildeattribusjon, kontroll mot andre kilder, bevart mening ved oversettelse og tydelige rettelser. Anvendt i RR-F01/F02/T01.
- [AP: oppdaterte AI-standarder, 23. juli 2026](https://www.ap.org/the-definitive-source/announcements/ap-updates-newsroom-standards-for-artificial-intelligence/): AI kan støtte blant annet research, oppsummering og språkvask; journalister beholder ansvar, kontroll og redigering før publisering. Vesentlig AI-medvirkning skal synliggjøres. Anvendt i kontrollflyt og RR-T01. APs eldre 2023-policy brukes ikke som beskrivelse av dagens regler.
- [Norsk Presseforbund: Vær Varsom-plakaten](https://www.presse.no/vaer-varsom-plakaten): norsk presseetisk ramme, særlig kildekritikk, saklighet, identifisering, barn, rettelser og imøtegåelse. Nettuttrekket ga ikke full plakattekst i denne kartleggingen; redaktøren må kontrollere gjeldende ordlyd ved vedtak. Ingen fullstendig juridisk eller presseetisk samsvarssertifisering hevdes.
- Internt: `AGENTS.md`, prosjektets startpromptpakke v1.0 og arbeidsnotatet om fotballrobotens redaksjonelle regler (25. september). Fotballprinsippene om faktastøttet hovedvinkel, tidsrekkefølge og ingen oppdiktet stemning videreføres; beregninger og NFF-adapteren i WordPress er uendret.
