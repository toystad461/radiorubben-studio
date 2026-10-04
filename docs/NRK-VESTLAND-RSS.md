# NRK Vestland i Studio

Første utviklingstrinn bestilt 3. oktober 2026: de to oppgitte feedene skal være kilder i eksisterende Nyhetsdesk/Robåten.

| Kilde | Feed |
| --- | --- |
| NRK Vestland – Toppsaker | https://www.nrk.no/vestland/toppsaker.rss |
| NRK Vestland – Siste nytt | https://www.nrk.no/vestland/siste.rss |

## Brukerflyt

Nyhetsdesken samler NRK-sakene i én Vestland-seksjon. Toppsaker står først. En sak i begge feeder vises bare én gang, og hver feeds status vises separat. Bømlo kommune, trafikk og vær beholdes. Den nasjonale NRK-feeden hentes ikke av denne løsningen.

RSS gir tittel, kort kildeomtale, original lenke og tidspunkt. Innholdet er kildeinngang, ikke bekreftede fakta eller en ferdig Radio Rubben-artikkel. Valgte saker går til eksisterende sendeliste og nyhetsmanusflyt med originalkildekontroll og manuell sluttgodkjenning. Ingen ny publiseringsmotor, nettsideimport, AI-jobb eller planlagt oppgave innføres.

## Identitet og historikk

- NRKs artikkel-ID fra den offentlige lenken brukes på tvers av begge feeder og endret tittel/URL-sti. Uten slik ID brukes normalisert lenke.
- Sporingsparametere og fragmenter tas bort. Andre parametere beholdes; ukjente fakta eller artikkeladresser konstrueres ikke.
- Eksisterende sendepunkter gjenkjennes også fra gamle kilde-ID-er ved å sammenligne original lenke/artikkel-ID inne i sendelistens lås.
- Allerede redigert manus, godkjenning, notater og historikk erstattes ikke av en oppdatert RSS-tittel. Ingen migrering eller nullstilling av gamle kilder eller private filer.
- Feeds har separate cachefiler og fem minutters intervall ved besøk på desken. Dette er ikke en bakgrunnsjobb. Henting ved feil respekterer samme intervall; sist vellykkede innhold merkes som eldre data, og skjules etter seks timer. Gyldig tom feed skilles fra en tilgangsfeil.

## Avhengigheter og base

Bygger på konsolidert Studio-kode i PR #19, låst til `049b42591e525055232503e4710787ad09b62139`. Denne basen inneholder den aktive arkitekturen for `NewsDesk`, `board` og `news-script`. De eldre PR #5/#9-grenene er ikke et komplett grunnlag for dagens Studio. Render- og Entra-testgrenene endres ikke.

Runtime-endringer: `app/source-identity.php` (ny), `app/integrations/NewsDesk.php`, `app/board.php` og `public/newsdesk.php`. Sider som bruker `board.php` får kun den felles, rene identitetsfunksjonen som ny avhengighet. Ingen endring av `news-script.php`, innlogging, generering eller publiseringsport.

## Verifisert og uprøvd ved utvikling 03.10.2026

Testene bruker konstruert RSS uten kopiert NRK-innhold. De dekker de to eksakte URL-ene, ingen nasjonal feed, dubletter, ulike saker med lik tittel, endret URL, oppdateringer, delvis feil, cache, fremtidsdatoer, ekstern XML, lenkegrenser og bevaring av menneskelig redigering.

Direkte lesing av NRKs sider/feeder gjennom søkeverktøyet ble blokkert 3. oktober 2026. Blokkeringen er ikke omgått. En vellykket, autorisert respons fra de faktiske feedene er derfor ikke bekreftet i dette arbeidet; syntetiske tester er ikke bevis for live RSS-innhold.

Ved første utviklingsleveranse ikke produksjonsaktivert. Før selektiv aktivering må de fire berørte serverfilene/avhengighetene avstemmes mot ferske råbyte-hasher, Studio-deploytilgang bekreftes, og privat backup og tilbakeføring klargjøres. Ikke last opp hele PR-grenen eller standardpakken over aktivt Studio. Se `AGENTS.md` og `docs/STUDIO-DEPLOY.md`.

OneDrive er fortsatt ønsket arkiv for ferdige manus, lyd og driftsdokumenter. Dette trinnet bruker Studio sin private kildecache; ingen OneDrive-overføring eller oppdiktet filkobling er lagt til.

## Aktivert 04.10.2026

De fire runtime-filene er selektivt aktivert sammen med nyhetspubliseringsprofilen. Innlogget Studio viste faktisk oppdaterte data fra både Toppsaker og Siste nytt, med samlet Vestland-seksjon. Bømlo kommune, trafikk, vær og eksisterende sendeliste er bevart. Dette er bevis fra autorisert Studio-UI og serverhenting, ikke en omgåelse av søkeverktøyets tidligere begrensning. Se [aktiveringsrapport](NEWS-ACTIVATION-20261004.md) for eksakt kilde, filer, CI, serverhasher og backup. Ingen offentlig artikkel er publisert.
