# Radio Rubbens AI-erklæring v1.0

- Policyversjon: **1.0.0** (AI-erklæring v1.0)
- Dato: 2026-10-08
- Eier: Radio Rubbens redaksjonelle ledelse
- Omfang: Robot, Studio og Web; tekst, manus, syntetisk tale og bilder.
- Status: Versjonert utviklingskrav. En PR er ikke bevis på innføring i produksjon.

## Moderne teknologi. Ekte lokal formidling.

Radio Rubben kombinerer radio, musikk og lokalt engasjement med moderne teknologi.
Vi bruker kunstig intelligens (AI/KI) som et hjelpemiddel i redaksjonelt arbeid og
innholdsproduksjon. Teknologien skal gi mer lokal verdi og støtte menneskene som
skaper Radio Rubben. **Mennesket har alltid det redaksjonelle ansvaret.**
AI kan gjøre feil. Vi skal være åpne om bruken og utvikle kildekontroll,
kvalitetssikring og menneskelig godkjenning i tråd med kravene nedenfor.

Radio Rubben – Lokal. Inkluderende. Verdig. Engasjerende.

## Bindende prinsipper

1. **Åpenhet.** Publikum skal forstå vesentlig AI-medvirkning. Synlig merking må
   følge innholdet til publiseringsflaten, også ved eksport. Interne metadata
   alene erstatter ikke publikumsinformasjon. Ikke påstå intervju, tilstedeværelse,
   faktasjekk eller menneskelig gjennomgang som ikke er utført.
2. **Sporbare kilder.** Oppbevar kildeidentitet/URL, innhentingstidspunkt og det
   faktiske kildegrunnlaget (utdrag eller snapshot med kontrollsum). Beskytt
   fortrolige kilder; intern sporbarhet krever ikke offentliggjøring av dem.
   Manglende eller motstridende fakta skal gi avvik/manuell vurdering, aldri
   oppdiktede navn, sitater, hendelser, resultater eller utfyllende forklaringer.
3. **Menneskelig ansvar.** En teknisk eller modellbasert kontroll er ikke en
   redaktørgodkjenning. Generering, kontroll, menneskelig sluttgodkjenning og
   publisering er separate handlinger. Bevar autorisert bruker, tidspunkt og
   versjon/innholdshash for godkjenningen. Endret tekst, faktagrunnlag eller
   relevant merking krever ny kontroll og godkjenning. Feil, uavklart levering
   eller utløpt kontroll må ikke behandles som godkjent. Rolle-, CSRF-, revisjons-
   og testinnleggssperrer skal bestå.
4. **Syntetisk tale.** Registrer at lyden er syntetisk, generator/modell eller
   stemmereferanse, manusversjon, produksjonstidspunkt, lydfilens identitet/hash
   og menneskelig godkjenning av den ferdige lyden. Publikum skal få tydelig
   opplysning om syntetisk stemme i den aktuelle kanalen; ved radio må opplysningen
   også være tilgjengelig i sendingen. Ikke fremstille stemmen som et ekte opptak
   eller et autentisk sitat. Godkjent manus er ikke automatisk godkjent lyd.
5. **AI-bilder.** Registrer AI-opphav og generator når kjent, produksjonstidspunkt,
   filidentitet/hash og godkjenning. Merk synlig «AI-generert illustrasjon» ved
   bildet når det er AI-generert. Ikke presenter illustrasjoner som dokumentariske
   fotografier av en faktisk hendelse. Ukjent opphav skal avklares, ikke merkes som
   autentisk ved antakelse. Endret mediefil eller merking krever ny godkjenning.
6. **Læring fra rettelser.** Bevar original, rettelse, kildebelegg, ansvarlig person,
   tidspunkt og begrunnelse. Rettelse av en sak og godkjenning av en generell
   læringsregel er separate beslutninger. Versjoner regler; avgrens til relevant
   program/område og gjør deaktivering mulig. Eksempler fra tidligere saker er
   ikke faktakilder for nye saker. Læring kan aldri svekke kontroll, merking,
   rollekrav eller menneskelig sluttgodkjenning.

## Utviklings- og endringskontroll

- Les denne policyen sammen med repoets eksisterende regler før redaksjonelle endringer.
- Bruk eksisterende godkjenningsflyt. Ikke innfør en parallell godkjenningsmotor.
- Automatiske tester skal prøve faktiske kontrollgrenser: manglende godkjenning,
  endret innhold/kilder/metadata, urettmessige roller og feil skal avvises; en gyldig
  manuelt godkjent sak skal fortsatt kunne passere. Bruk syntetiske testdata.
- Nye lyd-/bildeflyter skal ha både mediemetadata, synlig/hørbar merking og egne
  negative tester før aktivering. Et generisk policyfelt er ikke bevis på dette.
- PR-en skal beskrive hva som håndheves, hva som bare er dokumentert, og kjent
  overlapp med andre grener. Se `docs/AI-POLICY-IMPLEMENTATION.md` i hvert repo.
- Tester kan kontrollere struktur og sperrer, ikke garantere sannhet eller erstatte
  redaksjonell vurdering. CI må ikke omtales som en obligatorisk merge-sperre uten
  at GitHub branch protection/rulesets faktisk krever kontrollen.
- Ingen merge som utløser deploy, utrulling eller innholdspublisering uten eksplisitt
  godkjenning. Grønn CI alene gir ingen slik tillatelse.

## Versjonering

Policyen er identisk i de tre repoene. Endringer skal ha versjonsnummer,
endringsnotat, redaksjonell begrunnelse og koordinerte PR-er. Tekniske felt bruker
`1.0.0`; dokumentets publikumsnavn er v1.0. Språkregler og Stylebook beholder egne
versjoner og supplerer denne policyen. Eksisterende strengere regler beholdes.

### 1.0.0 – 2026-10-08

Første versjon: åpenhet, kildebelegg, menneskelig ansvar, syntetisk tale,
AI-bilder og kontrollert læring fra rettelser. Teksten er ikke publisert på «Om oss».
