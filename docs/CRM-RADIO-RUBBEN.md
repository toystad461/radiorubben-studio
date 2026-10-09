# Produktkrav: CRM for Radio Rubben

Oppdatert 07.10.2026. Thomas sitt førende krav er at CRM-et skal være
tilpasset Radio Rubben. Konkretiseringen nedenfor styrer videre utvikling;
den beskriver ikke funksjoner som allerede finnes. Gjeldende kodeomfang
og teststatus står i [CRM.md](CRM.md) og PR #48.

Thomas har også foreslått en flyt fra nye leads til nettsøk, kontaktmal,
15–20 sekunders reklameforslag, programsamarbeid, TTS og kundedemo.
De første 10–20 skal godkjennes manuelt før eventuell automatisk utsending.
Se [førstekontakt og lyddemo](CRM-OUTREACH.md) for konkretisert flyt og pilot.

## Arbeidshverdagen CRM-et skal støtte

Thomas skal kunne åpne Studio på telefonen og se hvem han bør kontakte,
hva de snakket om sist, hva Radio Rubben har tilbudt eller lovet, og neste
handling. Når et samarbeid blir avtalt, skal samme oversikt hjelpe ham å
følge opp materiell, reklameproduksjon, godkjenning, levering og fornyelse.
Norsk bokmål, tydelig lokal tone og korte skjema skal følge eksisterende Studio.

Grunnlaget er dialogen om lokale samarbeidspartnere på Bømlo og vedlagt
AI Startpromptpakke v1.0 (05.10.2025), særlig personlig og lokalt forankret
sponsorarbeid. Forretningsplanen fra 02.09.2025 er historisk bakgrunn;
dens økonomitall, målgruppestørrelse og eldre systemvalg skal ikke bli
dagens priser, lyttertall eller tekniske integrasjonsforutsetninger.

## Behov og leveransestatus

| Behov | Radio Rubben-tilpasning | Status i PR #48 |
| --- | --- | --- |
| Lokale relasjoner | Bedrift, kontaktperson, personlig inngang, lokal relevans og mulig samarbeid | Kontaktfelt og fri samarbeidsidé finnes; egne strukturerte muligheter gjenstår |
| Personlig oppfølging | Telefon, møte, e-post eller melding, faktisk svar, neste handling og dato | Kontaktlogg og oppfølging finnes; foretrukket kanal og strukturert avslag/utsatt kontakt gjenstår |
| Tilbud og avtaler | Samarbeidsform, innhold, prisgrunnlag, periode og partnerens aksept | OneDrive-lenke finnes; egne tilbud/avtaler gjenstår |
| Reklame og sponsorleveranser | Manus, lyd, kundegodkjenning, ønsket sendeflate og leveringsbelegg | Gjenstår |
| Oversikt og fornyelse | Hva må følges opp, hva mangler før oppstart, og hvilke avtaler nærmer seg slutten | Forfalte kontaktoppfølginger finnes; kampanje- og fornyelsesoversikt gjenstår |
| Microsoft-støtte | Studio som hovedregister, Teams-varsler og lenker til OneDrive-filer | OneDrive-lenke finnes; varsling og filoverføring er ikke implementert |
| Førstekontakt med demo | Godkjente maler, kildebelagt bedriftsnotat, reklameforslag, programsamarbeid og TTS-demo | Foreslått av Thomas; ikke implementert. Manuell pilot før eventuell automatikk |

## Bedrift, samarbeid og sending må kunne følges hver for seg

- En bedrift kan ha flere samarbeid over tid, med ulike kontaktpersoner,
  perioder og neste handlinger. En avslått kampanje skal ikke slette bedriften
  eller stoppe oppfølging av en annen, aktiv avtale.
- Foreslåtte samarbeidsformer er radiospotter, programsponsor,
  sesong-/arrangementskampanje og bytte av varer/tjenester. Dette er
  kategorier å støtte, ikke en vedtatt prisliste eller pakker som er solgt.
- Hver mulighet trenger navn, behov/mål, samarbeidsform, ansvarlig,
  kontaktperson, tilbudsstatus, neste handling og eventuell dato.
  Ved avtale registreres omfang, periode, beløp, betalingsperiode,
  eventuelt innhold i et bytte og dokumentasjon på aksept.
- En avtale kan ha flere leveranser. For radiospotter: versjonert manus,
  avtalt varighet, lydreferanse, ønsket program/sendeflate, hyppighet,
  start/slutt og godkjenning med tidspunkt og referanse til riktig versjon.
  En ny materiellversjon krever ny godkjenning før den merkes klar.
- Salgsstatus, produksjonsstatus og leveringsstatus skal vises separat.
  Akseptert avtale betyr ikke at lyd er ferdig. Ferdig lyd betyr ikke at
  spotten har gått på lufta. CRM skal ikke merke noe som sendt uten
  faktisk dokumentasjon; manuell registrering merkes som manuell.
- Et tidligere forslag om 15-sekunders spotter, 2–3 innslag daglig,
  morgensending og seks måneders pilot er ett tilbudseksempel fra dialogen.
  Disse verdiene skal ikke låses som standard for alle samarbeid.

## Startbildet og enkel daglig bruk

Prioriter en liste over dagens/forfalte oppfølginger, samarbeid som mangler
materiell eller godkjenning, kommende oppstarter og avtaler som snart skal
vurderes for fornyelse. Hver rad skal ha partner, konkret neste handling,
ansvarlig og frist, og åpne riktig kort. Vis bare oversikter som bygger på
data og funksjoner som faktisk finnes.

Etter en telefon skal det være enkelt å registrere hva partneren svarte
og velge neste steg. Et budsjettavslag kan registreres med årsak og en ny
kontaktperiode hvis det er avtalt. Et uttrykkelig ønske om ingen ny kontakt
skal respekteres og utelukkes fra automatiske kontaktforslag. Ikke foreslå
gjentatte e-poster eller prisavslag automatisk etter avslag.

Foreslåtte og bekreftede beløp skal holdes atskilt. Bytteavtaler skal vises
separat fra pengeinntekter. Betalingsoppfølging må vise om status er manuelt
registrert eller hentet fra et faktisk tilkoblet system. Fakturering og
bokføring er ikke implementert eller bestilt som del av kontaktregisteret.

## Automatisering og redaksjon

Teams skal senere kunne gi én samlet påminnelse med lenker til Studio.
OneDrive skal holde tilbud, avtaler, logoer, manus og lyd via faktiske
filreferanser. OneNote kan være valgfri støtte for lange møtenotater.
Integrasjonene skal støtte arbeidsflyten uten parallelle kunderegistre.
Et varsel er ikke kundekontakt; kundeutsending, tilbudsaksept og avspilling
krever hver sin uttrykkelige handling og sporbar status.

Kjøpt reklame/sponsing skal følges som kommersielle leveranser. En avtale
skal ikke gi automatisk redaksjonell omtale, artikkelgodkjenning eller
publisering. Eksisterende redaksjonelle roller og godkjenningsflyt bevares.

## Akseptansekriterier for videre leveranser

Bruk fiktive bedrifter og avtaler i tester; offentlige kandidater er ikke kunder.

1. Etter en telefon kan Thomas finne svaret, ansvarlig, neste handling og dato
   på samme bedriftskort; et internt notat teller ikke som en telefon.
2. To samarbeid for samme bedrift beholder hver sin status, avtale og historikk.
   Ett avslag endrer ikke en annen avtale til avslått.
3. Et avslag med avtalt senere kontakt blir synlig til riktig tid. Et kort
   med uttrykkelig ingen ny kontakt kommer ikke med i kontaktforslag.
4. En spot kan følges fra avtalt omfang til manus, lyd og kundegodkjenning.
   Endret versjon mister klarstatus. Planlagte sendinger vises aldri som
   faktiske avspillinger uten belegg.
5. Sluttdato og avtalt fornyelsesoppfølging kan finnes før samarbeidet utløper.
   Ingen automatisk fornyelse eller inntektsføring følger av en påminnelse.
6. På telefonen kan Thomas se dagens oppgaver, ringe og registrere utfallet.
   Teams-feil hindrer ikke lagring i Studio. Eksisterende data, historikk,
   roller og revisjonskontroll bevares ved alle utvidelser.

## Anbefalt rekkefølge

1. Bygg videre på kontaktgrunnlaget med flere samarbeidsmuligheter/avtaler
   per bedrift, tydelig neste handling og håndtering av utsatt/avslått kontakt.
2. Bygg klargjøring av første kontakt og demo etter [CRM-OUTREACH.md](CRM-OUTREACH.md),
   med kilder, maler, TTS og manuell godkjenning. Gjennomfør piloten før
   automatisk utsending vurderes og eventuelt aktiveres av Thomas.
3. Utvid til avtalte leveranser: manus, lyd, versjonsgodkjenning,
   avtalt sendeflate og dokumentert levering, deretter fornyelsesoversikt.
4. Koble til Teams og eventuelle produksjonssystemer når arbeidsflyt,
   datakilder og Radio Rubbens konto/tilganger er avklart.

Behold eksisterende PHP-arkitektur og en avgrenset PR per leveranse.
Ingen nye pakkepriser, målte lyttertall, kundeløfter eller systemtilganger
skal antas. Publisering følger Studio-prosjektets etablerte prosess.
