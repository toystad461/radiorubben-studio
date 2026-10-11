# Permanent lesende Studio-kontroll

Behov: kode-/driftsinventering uten avhengighet av Thomas sin PC eller personlig
nøkkelopplåsing. Egen driftsgren fra main 2a187e0, uavhengig av nyhetsinngang PR #69.

En dedikert ed25519-maskinnøkkel skal få bare fast kommando
`rr-studio-inventory-v1`. Serverens authorized_keys binder nøkkelen til PHP-leseren
med forced command og uten port-, agent- eller X11-forwarding eller PTY.
Leseren tillater ingen brukerbestemte filbaner og laster ikke WordPress/Studio.
Den leser kodehasher, manglende mål, deploymetadata og PHP-/rettighetsstatus
under eksisterende delt deploy-lås. Den leser ikke nøkkelinnhold, manus, CRM
eller velgerdata. Andre kommandoer avvises før serverfiler leses.

Workflow bruker `STUDIO_INVENTORY_SSH_KEY`, aldri deploynøkkelen som fallback,
og miljøvariabel `STUDIO_INVENTORY_SSH_USER` i `studio-production`. Eksisterende
kjent vert og vertsadresse gjenbrukes. Privat nøkkel må lagres som GitHub-secret,
aldri i repository, chat eller rapporter. Workflow starter bare ved eksplisitt
workflow_dispatch på main; ingen tidsplan opprettes. Ingen deployhandling finnes.
JSON-resultat kontrolleres mot eksakt installert leserhash og faste filbaner.
Rapport beholdes som Actions-artefakt i sju dager.

Oppsett endrer bare dedikert nøkkelautorisasjon og egen privat driftsleser,
ikke publiseringskode eller data. Oppdatering av leseren krever kontrollert
oppdatering av denne private driftsfilen. Tilbakeføring er fjerning av akkurat
den dedikerte nøkkellinjen og deaktivering av workflow; personlig nøkkel beholdes.
Kontoen kan ha andre rettigheter, men denne nøkkelen får ingen vanlig shell-tilgang.

Ferdig når dedikert nøkkel og secret er registrert, fast kommando fungerer,
vilkårlig kommando avvises, rapportvalidering består og GitHub-jobben er prøvd.
Kode/CI alene beviser ikke at dette oppsettet er aktivert. Faktisk oppsettsstatus
rapporteres separat. Merge til main kan utløse eksisterende release-pipeline;
nyhetsinngang #69 skal ikke tas med som skjult del av driftsoppsettet.

## Verifisert oppsettsstatus 11.10.2026

Privat leser er installert, SHA256
`86944c3f1cb3b4cee34a4da93321482f04c64817dd8c44a579a126fd81cb823d`.
Den dedikerte offentlige nøkkelen er **ikke registrert** etter tilbakeføring:
fast lesekommando besto ikke tilkoblingstesten. Etterkontroll bekrefter fire
bevarte eksisterende nøkkellinjer, filrettighet 0600 og at maskinnøkkelen avvises.
Ikke registrer nøkkelen på nytt før årsaken er avklart. Secrets/Actions er ikke
aktivert. Dette er klargjort kode og delvis serveroppsett, ikke permanent drift.
