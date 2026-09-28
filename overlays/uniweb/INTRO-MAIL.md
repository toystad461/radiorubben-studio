# Intro-e-post ved ny Studio-konto

Den aktive Entra/Studio-kontoflyten i `/users.php` oppretter brukeren først. Etterpå forsøker den å sende en tekstbasert velkomstmelding med innloggingslenke, rolle og kort veiledning til Kontrollsenter, Nyhetsdesk, Sending og OneDrive-arbeidsområdet **Manus & Stikk**. Det midlertidige passordet sendes aldri i denne e-posten; administrator deler det separat gjennom en sikker kanal. Ved feil beholdes kontoen, feilen vises tydelig, og administrator kan bruke **Send intro-e-post** i brukerlisten senere. E-postserverens aksept er ikke bekreftelse på levering til innboksen.

Filer:

| Lokal fil | Uniweb-mål |
| --- | --- |
| `app/intro-mail.php` | `studio-private/app/intro-mail.php` |
| `app/auth/StudioLocalUsers.php` | `studio-private/app/auth/StudioLocalUsers.php` |
| `public/local/admin-panel.php` | `studio-public/local/admin-panel.php` |

Sett `intro_from_email` til en eksisterende, autentisert avsender på `radiorubben.no` i privat `studio-private/config/local.php` (for eksempel `'intro_from_email' => 'studio@radiorubben.no',` **bare dersom den kontoen faktisk finnes og er godkjent som avsender**). Uniweb krever en autentisert avsender for utsending fra webhotellet. Den 28.09.2026 viste Uniweb-panelet ingen e-postkonto på verken `radiorubben.no` eller `studio.radiorubben.no` i dette webhotellet; derfor er avsender ikke konfigurert eller testet i produksjon. Ikke legg passord i repoet. PHP `mail()` brukes først når gyldig avsender er satt, og bør prøves med en kontrollert testkonto før reell bruk.

Kjør PHP 8.2/8.4 syntakskontroll og `php test-intro-mail.php`. Ta backup av de to eksisterende filene før utrulling. Oppdater først privat app, deretter admin-panelet. Test en ny testbruker, kontroller at e-post tas imot og at første innlogging krever nytt passord. Test også at misligholdt utsending viser feil og at manuell sending fungerer. Ikke opprett en ekte medarbeider bare for test.
