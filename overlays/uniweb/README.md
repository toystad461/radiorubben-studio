# Studio brukertilgang – tillegg til aktiv Uniweb-versjon

Denne mappen inneholder bare filene som trengs for brukertilgang i den aktive Uniweb-utgaven per 28.09.2026. Den er **ikke** en full Studio-utgivelse. Ikke erstatt hele `studio-public` eller `studio-private` med en pakke fra repoets `main`; aktiv Uniweb har AI Studio og flere filer som ikke finnes der.

Overfør filene til tilsvarende `studio-public` og `studio-private` mapper etter sikkerhetskopi. Behold `studio-private/config/local.php` og alle eksisterende private data. Bekreft at serverens PHP-prosess kan opprette `studio-users.json` og `studio-login-attempts.json` i privat config-mappe.

I `studio-private/config/local.php` settes `'local_users_enabled' => true,` når påloggingen skal åpnes. Før dette er Studio-kontoer avstengt. Dette tillegget endrer ikke Microsoft-appen eller Graph-tillatelser. Thomas må logge ut og inn igjen med Microsoft for å få en ny økt med bekreftet Entra objekt-ID og administratorrolle.

Verifiser etter aktivering:

1. Microsoft-innlogging viser Thomas som Administrator og menypunktet Brukere.
2. En annen Microsoft-konto får ikke administratorrolle.
3. Opprett en testkonto med rollen Observatør i `/users.php`. Det midlertidige passordet vises én gang.
4. Logg inn med testkonto i egen nettleserøkt, bytt passord og bekreft at Brukere er utilgjengelig.
5. Deaktiver testkontoen og bekreft at gammel økt ikke får tilgang.
6. Kontroller at AI Studio, eksisterende menyer og felles Studio-stil fortsatt virker.

Den eksisterende Vipps-baserte brukersiden er bevart når `auth_mode=vipps`. Ved `auth_mode=entra` brukes den nye lokale kontosiden med samme `head.php`, `sidebar.php`, `account.php`, `studio.css` og `users.css` som resten av Arbeidsrommet.
