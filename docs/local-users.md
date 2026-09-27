# Studio-kontoer

Administrator er Thomas sin Microsoft Entra-konto (objekt-ID `41b640dc-f49b-47fc-87ef-85f7ba90c3d2`). Administrator åpner **Brukere og tilgang** fra Studio og kan opprette medarbeidere, deaktivere/aktivere kontoer og tilbakestille passord. Lokale kontoer får bare medarbeidertilgang. Ingen Microsoft Graph-tillatelser eller Microsoft 365-brukere opprettes.

## Aktivering på Uniweb

1. Publiser pakken til de samme `studio-public` og `studio-private` mappene som før. Behold eksisterende `studio-private/config/local.php`; den inneholder Entra-oppsettet.
2. Sørg for at PHP-prosessen kan opprette filer i `studio-private/config`, og at mappen ikke kan nås fra web. Kontoene lagres i `studio-users.json` med passordhash; innloggingsforsøk i `studio-login-attempts.json`. Filene opprettes ved bruk og settes til modus 0600 der verten støtter det. Ta sikkerhetskopi av brukerfilen sammen med den private konfigurasjonen.
3. Legg til `'local_users_enabled' => true,` i arrayet i privat `config/local.php`. Standard er `false`. Microsoft-innloggingen for administrator fungerer uavhengig av dette valget.
4. Åpne `/admin/users.php` med Thomas sin Microsoft-innlogging og opprett første medarbeider. Kopier det midlertidige passordet når det vises; det vises bare i svaret på opprettelse eller tilbakestilling.
5. Medarbeideren velger «Logg inn med Studio-konto» på innloggingssiden og må velge nytt passord før Studio åpnes.

Passord må ha minst 14 tegn. Innlogging sperres etter fem feil per e-postadresse og IP-adresse i 15 minutter. Ved deaktivering eller passordreset økes kontoversjonen, slik at gamle økter avsluttes ved neste forespørsel. Hvis brukerfilen ikke kan leses, gis ingen lokal tilgang.

Administratoridentiteten er bundet til en bestemt Entra objekt-ID. Ved skifte av administrator må denne verdien endres i kildekoden og publiseres på nytt.
