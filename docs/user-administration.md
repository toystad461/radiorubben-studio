# Brukeradministrasjon i Studio

Studio bruker Microsoft Entra som identitetskilde. Administratorrollen er knyttet til Thomas Magne Sellevold-Øystads stabile Entra object ID `41b640dc-f49b-47fc-87ef-85f7ba90c3d2`. En vanlig Studio-pålogging ber bare om OIDC-profil, og ingen andre får tilgang til `/admin/users.php` eller `/admin/connect.php`.

## Opprette og tildele

Siden **Brukere og tilgang** kan opprette nye interne `@radiorubben.no`-kontoer med et tilfeldig midlertidig passord og påtvunget passordbytte ved første innlogging. Passordet vises én gang til administrator og logges ikke. Administrasjonen kan også slå opp en eksisterende intern konto og tildele den Studio Enterprise Application med standardrollen. **Assignment required** skal fortsatt være aktiv i Entra. Tildeling av appen gir medarbeiderrolle i Studio; bare Thomas' object ID er administrator.

Etter at Graph-samtykke er gitt, starter administrator en egen kortvarig autorisasjon via `/admin/connect.php`. ID-tokenet må gjelde samme Entra object ID som den aktive Studio-sesjonen. Graph access token lagres bare i serverbasert sesjon i høyst 45 minutter, og vanlig innlogging ber ikke om Graph-rettigheter.

Hvis en konto opprettes, men app-tildelingen feiler, viser siden at kontoen finnes og lar administrator forsøke tildelingen på nytt. Den samme kontoen må ikke opprettes på nytt. Før brukeropplysninger gis til noen, velg en sikker kanal for midlertidig passord. Brukersiden sender ingen e-post.

## Entra-oppsett før drift

Denne flyten krever Microsoft Graph **delegated** permissions `User.Create`, `User.ReadBasic.All`, `Application.Read.All` og `AppRoleAssignment.ReadWrite.All`, med administrator-samtykke og en Entra-administratorrolle som tillater brukertildeling. Registrer disse på **Radio Rubben Studio**-appen i Entra, kontroller samtykketeksten og godkjenn dem først etter egen sikkerhetsvurdering. Disse tillatelsene er bredere enn Studio alene; applikasjonskoden begrenser handlingene til `radiorubben.no` og Studio Enterprise Application, men Entra-tokenet er ikke ressursbegrenset til bare denne appen. Ikke legg til application permissions eller slå av **Assignment required**.

Kjør følgende driftskontroller etter publisering: Thomas ser brukersiden, en annen tildelt bruker får 403, en ikke-tildelt konto stanses av Entra, opprettelse med midlertidig passord og app-tildeling fungerer, og mislykket tildeling kan gjentas uten duplikat. Ingen ekte kontoer opprettes i automatiserte tester.
