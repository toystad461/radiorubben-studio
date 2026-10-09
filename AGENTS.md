# Radio Rubben Studio – arbeidsregler for Codex

## Start her
Les docs/STUDIO-STATUS.md før endringer. Les deretter eksisterende dokumentasjon,
CI og eventuelle lokale AGENTS.md for området du endrer. Hent fersk PR-status,
base/head og relevante filer; samtalehistorikk og gamle grønne kjøringer er ikke
bevis for dagens kode eller produksjon. Bevar ukommittert arbeid.

## Arbeidsmåte
- GitHub er kode-master. Arbeid på en avgrenset gren med en gjennomgåbar PR.
- Avklar avhengigheter før valg av base. Eksisterende PR-kjede skal ikke
  automatisk bli base for uavhengige endringer.
- Én aktiv hovedoppgave av gangen. Oppdater status, beslutninger og neste
  konkrete steg i docs/STUDIO-STATUS.md ved levering.
- Bruk eksisterende PHP/Composer-arkitektur og tester. Unngå omskriving av
  rammeverk som del av en avgrenset funksjon.
- Rapportér hva som er endret, testet, uprøvd og publisert hver for seg.
  Dokumentasjonsendringer trenger ikke nye runtime-tester.

## Produksjonsgrunnlag
Aktivt Uniweb-Studio avviker fra main og fra hele feature-grener. Selektivt
publiserte filer er dokumentert i flere PR-er. Ikke last opp main, en hel
feature-gren eller standardpakken over aktivt Studio før produksjonsgrunnlaget
er avstemt. Grønn CI, merge og bygget ZIP er ikke bevis på publisering.
Før en autorisert deploy: dokumenter eksakt kildecommit, berørte filbaner,
avhengigheter, ferske før/etter-hasher, privat backup og tilbakeføring.
Bevar config/local.php, nøkler, brukere, sendeliste, historikk og cache.
Ikke legg private filer eller hemmeligheter i Git, PR-er eller testartefakter.
Ikke endre WordPress, DNS, Entra eller andre repoer som skjult del av Studio-arbeid.
Denne etableringsoppgaven omfatter dokumentasjon og PR, ikke produksjonsdeploy.

## Redaksjon og brukerflyt
Radio Rubben: Lokal, Inkluderende, Verdig, Engasjerende.
Målet er automatisk forberedelse frem til manuell sluttgodkjenning, med kilder,
kontrollrapport, redigering og neste handling samlet i Studio.
Dette er et produktmål, ikke en påstand om at automatikk allerede er implementert.
AI skal aldri dikte fakta, sitater, hendelser, navn eller resultater.
Bevar kildebelegg, kildelenke, tidspunkt, originalutkast og manushistorikk.
AI-kildekontroll erstatter ikke redaksjonell vurdering. Kontrollfeil, endringer
og utløpt kildekontroll må hindre uriktig klar-status.
Bevar serverbaserte roller, CSRF, revisjonssperrer og atomisk lagring.
Programprofiler og læring skal være isolert per program. Bevar legacy-punkter
uten program; ikke innfør nye virkelige programmer uten bestilling.

## OneDrive og lyd
OneDrive er ønsket arkiv for manus, lyd, bilder og driftsdokumenter.
Bruk verifiserte eksisterende mapper/filreferanser; ikke konstruer lenker.
Skill lokal eksport fra faktisk overføring. Ikke påstå synk uten implementert
og testet integrasjon. Første TTS-spor er ElevenLabs etter brukerens prioritering.
Lydproduksjon, playout og publisering er separate handlinger med synlig status.

## Radio Rubbens AI-policy
Les og følg [AI-erklæring v1.0](docs/AI-POLICY.md) før endringer i generering,
kildekontroll, godkjenning, publisering, lyd, bilder eller læring. Bevar strengere
eksisterende regler. Se [implementeringsstatus](docs/AI-POLICY-IMPLEMENTATION.md)
for faktiske sperrer og gjenstående arbeid; ikke forveksle planlagt med aktivt.
Nye eller endrede redaksjonelle flyter skal ha målrettede negative tester for
manglende menneskelig godkjenning og endrede kilder/innhold/metadata.
Syntetisk tale og AI-bilder krever egen merking og sporbarhet etter policyen.
Rettelser kan gi versjonerte, separat godkjente læringsforslag, aldri automatisk
faktagrunnlag eller svakere kontroll. Ikke deploy, publiser eller merge til en
automatisk deploygren uten eksplisitt godkjenning. Bevar annet pågående arbeid.

## CRM for Radio Rubben
Thomas har uttrykkelig prioritert et CRM tilpasset Radio Rubben (07.10.2026).
Les docs/CRM-RADIO-RUBBEN.md før videre CRM-utvikling. Kontaktregisteret er
første grunnlag; videre arbeid skal støtte lokale partnerrelasjoner, konkrete
reklame-/sponsoravtaler, leveranser og fornyelse. Skill avtalt, godkjent,
planlagt og faktisk sendt. Ikke presenter planlagte funksjoner som levert.
Ved klargjøring av leads og utsending, les også docs/CRM-OUTREACH.md.
Thomas ønsker manuell godkjenning av de første 10–20 før eventuell
automatikk. Pilottelleren skal aldri alene aktivere kundesending.
