# Kontrollsenter og Sending – aktiv Uniweb-utgave

Dette tillegget bygger videre på den aktive Studio-utgaven og nyhetsdesken. Det overskriver ikke AI Studio, robotinnboksen eller konfigurasjon/brukerdata.

| Lokal fil | Uniweb-mål |
| --- | --- |
| `public/index.php` | `studio-public/index.php` |
| `public/control.php` | `studio-public/control.php` |
| `public/sending.php` | `studio-public/sending.php` |
| `public/newsdesk.php` | `studio-public/newsdesk.php` |
| `public/assets/control.css` | `studio-public/assets/control.css` |
| `app/board.php` | `studio-private/app/board.php` |
| `app/views/sidebar.php` | `studio-private/app/views/sidebar.php` |

Deploy bare disse filene. Behold eksisterende `studio-private/config/local.php`, cache og brukerdatabaser. PHP-prosessen må kunne skrive `studio-private/config/sending-board.json` og `.lock`; filene opprettes med 0600. Ikke legg dem i dokumentroten. Ta backup av `index.php`, `sidebar.php` og `newsdesk.php` før overskriving. Rekkefølge: privat `board.php`, CSS og nye sider, deretter `newsdesk.php`, `sidebar.php`, `index.php` sist.

Den gamle sendelisten i PHP-sesjonen migreres ved første besøk i Nyhetsdesk. Nye punkter lagres felles. Produsent, programleder og administrator kan redigere; observatør kan lese. `Klar` krever lagret manus og bekreftet innholdskontroll. Ingen lyd sendes på lufta, og ingen artikkel publiseres av dette tillegget. Statusen «På lufta» er eksplisitt ukjent til en faktisk avspillingsintegrasjon er tilkoblet.

I Sending kan en medarbeider generere et kort manusutkast per sak fra lagret tittel, kildeomtale og publiseringstid. Dette er hele faktagrunnlaget; full artikkel hentes ikke automatisk. For korte omtaler avvises, og medarbeideren må skrive manuelt etter å ha lest originalen. Generering krever `openai_api_key` og `openai_model` i eksisterende privat Studio-konfigurasjon. API-kallet bruker `store=false`. Ny generering erstatter manusfeltet på punktet, men lagrer alltid som utkast med kildekontroll avslått. Samtidige redigeringer avvises ved revisjonsnummer. Kjør `php test-story-script.php` med samme overlay-struktur som CI før utrulling.

Kontroller etter utrulling: `/` videresender til Kontrollsenter; `Nyhetsdesk → Legg i sendeliste → Sending` bevarer punktet etter ny innlogging; lagre manus, merk klart, flytt, arkiver og last ned tekst. Sjekk på Mac/PC, iPad liggende/stående og mobil. Rollene skal håndheves på serveren, også ved direkte POST.
