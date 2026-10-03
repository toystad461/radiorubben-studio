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

## Deployment fra 01.10.2026
- Følg docs/RENDER-STAGING.md for isolert Render-test; dagens produksjon er Uniweb.
- Automatiske tester og staging kommer før godkjenning av en eksakt produksjonscommit.
- Produksjon krever manuell apply med approved_sha som matcher kildecommiten.
  Ikke gjeninnfør automatisk apply eller utrulling fra vilkårlig nyeste main.
- Render-testdisken skal aldri inneholde produksjonsdata eller produksjonsnøkler.
- CI-grønt, opprettet tjeneste, publisert testadresse og innlogget funksjonstest
  er ulike milepæler; rapporter dem hver for seg.
