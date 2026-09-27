# RR Robot i Studio

Når Studio er aktivert med `site_mode=app` og Microsoft Entra-innlogging, finnes en intern, skrivebeskyttet visning på `/robot.php`. Den er stengt i demo-modus og mens ventesiden er aktiv.

RR Robot eksporterer formatversjon 1 med `npm run export:studio -- /sikker/sti/robot-inbox.json snapshots/kamp.json`. Overfør filen til `studio-private/config/robot-inbox.json`, utenfor webrot. Ikke legg den i Git eller `studio-public`. Eksporten er en lokal filoverføring, ikke en løpende API-kobling. Gjenta overføringen når nye kontrollerte snapshots finnes. Studio validerer skjemaet, viser maksimalt 100 utkast og gjør ingen publisering.

NFF-siden tillater ikke automatiserte roboter. Bruk kontrollerte snapshots inntil avtalt API-tilgang fra fotballdata.no kan implementeres og testes. Ingen API-nøkkel eller responsformat antas her.
