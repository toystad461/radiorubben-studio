# RR Robot i Studio

Når Studio er aktivert med `site_mode=app` og Microsoft Entra-innlogging, vises kildeinnboksen i oversikten på `/#produksjon`. Den vises ikke i demo eller på ventesiden. `/robot.php` er en kompatibilitetsadresse som sender innloggede brukere til samme oversikt.

Studio leser formatversjon 1 fra `studio-private/config/robot-news.json` og `robot-inbox.json`, utenfor webrot. Eksportene valideres og saker med samme `event.id` vises én gang; nyhetseksporten har prioritet. Ingen nettverkskall eller direkte RSS-henting skjer ved sidevisning. Manglende, ugyldig eller tom eksport gir tom innboks.

Bømlo kommunes RSS-saker eksporteres med `npm run export:bomlo -- data/bomlo-news.json privat/robot-news.json`. Filen må fortsatt overføres separat til Studio. Dette er ingen løpende API-kobling eller planlagt bakgrunnsjobb.

Kommunesakene er **kildekort**, med RSS-tittel og sammendrag, status `unverified` og lenke til originalen. De er ikke genererte Radio Rubben-artikler eller speakerstikk. Den tidligere PHP-leseren i `MunicipalityRss.php` brukes bare av parser-testene og er ikke koblet til noen offentlig inngang.

Webartikkelgenerering, uthenting av originalartikkel, språkvask, kvalitetskontroll, faktakontroll, kanalproduksjoner og Thomas sin godkjenning er ikke implementert. WordPress-kontrakten er et grensesnitt uten implementasjon. Se `docs/RSS-FLOW-AUDIT.md` for avvik og nødvendige neste steg.

NFF-siden tillater ikke automatiserte roboter. Bruk kontrollerte snapshots inntil avtalt API-tilgang fra fotballdata.no kan implementeres og testes.
