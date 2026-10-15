# Radio Rubben – felles Studio/web-grunnmur
Status: utviklingsspesifikasjon, 2026-10-15. Arbeids-ID RR-RADIO-PLATFORM-001. Relatert: RR-STUDIO-HOME-001 / PR #95.

## Mål og grenser
Radio Rubben skal gradvis erstatte Radio.co med en egen radioplattform. Radio.co er bare midlertidig driftsleverandør. Vi bygger først administrasjon, redaksjon, musikk, radiotid, produksjonskø, statistikkgrensesnitt og web-kobling. Playout, live-inngang og streamserver kommer senere.
Denne PR-en er en isolert visningspilot. Den er ikke en release av plattformen og har ikke egen streaming eller lytterdata.

## Originaler og eksisterende funksjoner
- Studio: PHP, nåværende autentisering/roller, redaksjonell board, RR Audio, sending, musikkbibliotek og musikkplan. Kontroller faktisk serverruntime mot main og selektivt publiserte filer før endring.
- Web: WordPress i toystad461/radiorubben-web; bevar Next, Fotballrobot, artikler, autentisering og publisering. Ikke flytt offentlig visning inn i Studio.
- Robot: toystad461/RadioRubben-robot er en separat TypeScript-hendelsesmodell i tidlig fase; ikke anta at den er produksjonskilde.
- OneDrive: godkjente originalmedier, avtaler og versjonerte eksportkopier. Serverdatabase/jobber skal ikke ligge i OneDrive-synk.
- Radio.co: eksisterende drift, ikke ny funksjonell avhengighet.

## Foreslått delt datakontrakt v0 (ikke implementert)
Alle API-svar må ha schemaVersion, generatedAt UTC, provenance, status (available/stale/unavailable) og tydelig scope (stationId/channelId).
Stabile ID-er: stationId, channelId, programId, scheduleSlotId, mediaAssetId, productionJobId, editorialCaseId og playoutEventId.
Innhold: contentType (music/news/weather/sport/promo/ident/program), title, mediaAssetId, editorialApproval (separat), technicalValidation, plannedStart, plannedEnd, sourceHash, revision.
Plan og sending må ha forskjellige statuser: draft -> approved -> scheduled -> queued -> handed_off -> playing -> played | failed | cancelled. Det er aldri lov å beregne played fra scheduled.
Ingen live-status uten kildekvittering med tidsstempel.
Stats må skille concurrentListeners, sessions, estimatedUniqueListeners, listeningSeconds og peakConcurrent; hver verdi har measurementWindow, sampledAt, aggregationMethod og source. Manglende data gir null/unavailable, ikke 0.
Lytteridentifikatorer/IP-er skal ikke eksporteres til dashboards; rapporter aggregert med tilgangsstyring og oppbevaringstid.

## Foreslått ansvarsdeling
- Studio: autentisering, redaksjonell godkjenning, metadata, programregister, kalender, musikkkatalog, produktjonsjobber og admin-statistikk.
- Intern API-tjeneste på VPS: signerte forespørsler, jobbkø, health, audit, målepunkter og senere playout-adapter. Ingen publiserte hemmeligheter i WP/JS.
- Web/WordPress: offentlig sendeplan, redaksjonelle saker, nå/neste og avspillingsknapp (senere). Bare eksplisitt offentliggjorte, aggregerte data over en read-only API, med cache/timeout/fallback.
- Eventuell streamserver senere: Icecast/Liquidsoap/AzuraCast vurderes etter måling og lisenskontroll, ikke vedtatt.

## Kvalitetskrav / ferdigkriterier for grunnmur
1. Les faktisk kode, aktive PR-er, servermanifest og produksjonsversjoner for begge repo før inngrep.
2. Utarbeid datamodell og API-kontrakter med skjemaendringer, versjonering, rollegrenser og sporbare korrelasjons-ID-er.
3. Implementer én kjørbar vertikal test: godkjent program/slot i Studio -> read-only API -> offentlig web-visning i isolert testmiljø. Ingen reell avspilling.
4. Negativtester: anonym tilgang til private data, stale data, timeout, feil på kilde, duplikatjobber, ugyldig statusovergang, endret manus/fil og sikker degradert web-visning.
5. CI: PHP 8.2/8.4, WordPress/PHP, JS, mobilbredder 320/390/768/1280 og faktisk browserkontroll. Ikke påstå at generell mobile CI dekker ny side uten spesifikt testcase.
6. Releasekrav: sammenlign aktive produksjonsfiler, ta privat backup, avstem endringer, verifiser mot signerte kvitteringer, dokumenter rollback. Merge og deploy autoriseres separat fra innholdsgodkjenning.
7. Før første ekte test med lytterdata: autentisert statistikkilde og personvernvurdering; gamle Radio.co-tall må merkes som historisk import.

## Prioritert utviklingsløp
A. PR #95: startside og lytterstatistikk som isolert read-only forhåndsvisning. Gjennomfør autentisert PHP- og browser-test, juster UI og avklar primærnavigasjon.
B. Verifiser eksisterende musikk/plan/board og lag felles ID- og statusmodell uten å migrere data.
C. Studio + web testintegrasjon med samme kontrakt; ingen WordPress-produksjonsendring før test i staging.
D. VPS jobber, helsesjekk, eksport/import, backup/restore og teknisk overvåking.
E. Playout/streaming gjennom separat prosjekt når ovenstående fungerer.

## Avklaringer før produksjonsomlegging
Eksisterende datalagring og mediefilvolum, primær-ID-er, faktisk WP-tema/pluginruntime, sendingsrettigheter, radiostrømmens publikumskapasitet, VPS-budsjett, backup/RPO/RTO og autentisering mellom Studio/VPS/web.
