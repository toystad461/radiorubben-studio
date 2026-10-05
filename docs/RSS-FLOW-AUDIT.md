# RSS-flyten – kodekontroll 5. oktober 2026

## Deployment-kontroll 5. oktober 2026 – viktig avgrensning

Denne rapporten beskriver **main**, ikke hele aktivt Uniweb-Studio. Ved deployment-forberedelse ble [PR #19](https://github.com/toystad461/radiorubben-studio/pull/19) funnet med konsolidert produksjonsgrunnlag på commit `049b42591e525055232503e4710787ad09b62139`. Dens kildeinnsamling dokumenterer nyere manus-, kildekontroll- og nettpubliseringsmoduler på serveren. Påstandene nedenfor om manglende funksjonalitet må derfor ikke brukes som konklusjon om hele produksjonen.

**Ikke merge/deploy denne PR-en som produksjonsrettelse før avstemming mot ferske serverfiler og PR #19.** main eller en full standardpakke kan overskrive nyere aktive funksjoner. Første audit undersøkte ikke dette produksjonsgrunnlaget; denne begrensningen korrigeres her.

Deployment-forberedelse ble startet med eksisterende workflow på main, `mode=dry-run`: [kjøring 37286799867](https://github.com/toystad461/radiorubben-studio/actions/runs/37286799867). PHP 8.2/8.4-kontrollene og pakking bestod. Serverkontrollen/utrullingen ble hoppet over fordi `STUDIO_DEPLOY_SSH_KEY` og `STUDIO_DEPLOY_KNOWN_HOSTS` mangler og `STUDIO_DEPLOY_ENABLED=false`. Ingen nettstedfiler ble endret. Eksisterende v0.0.2-releaseutkast ble gjenoppbygget av workflowen for uendret main.

En isolert test med ekte Bømlo-RSS svarte HTTP 200 og aksepterte 5 saker. Robåtens parser, merge og eksport bevarte ID-er; gjentatt import ga 0 nye saker. Testen brukte private lokale testfiler, uten overføring til Studio eller publisering. Kontrollstatus forble `unverified`. Dette verifiserer RSS → hendelse → eksport; ikke innlogget Studio, AI-kontroller eller WordPress.

## Konklusjon for kontrollert main

Målflyten er ikke implementert ende-til-ende. Det som finnes er RSS-innlesing, lagring av kildehendelser og visning av kildekort. Det finnes ingen ferdig webartikkel eller Studio-produksjon fra denne kjeden.

Kontrollert kodegrunnlag:
- Studio: `5b87408b0e413770e1486029a18e0edabfcf0ebe`
- Robåten: `e9fcdab676c58fbf4bd017e1a1beace64011e197`
- Web: `bbcc9e76f844a40961e5a81bdf7b94295eaf22cb`

Dette er kontroll av GitHub-koden. Serverens installerte versjon, private eksportfiler, eventuelle eksterne cron-jobber, WordPress-database og innlogging i produksjon er ikke verifisert. Ingen deploy, publisering eller live RSS-import er utført.

## Faktisk flyt og konkrete avvik

| Trinn | Kodebevis | Resultat / avvik |
| --- | --- | --- |
| Ny RSS-sak | Robåtens `src/sources/rss/bomlo.ts`, `src/jobs/ingest-bomlo.ts` | Bømlo RSS normaliseres med stabil `news:bomlo-kommune:<sha256 av GUID, 24 tegn>`. `mergeNews` dedupliserer eksisterende ID-er. Kommandoen må kjøres separat. |
| Løpende drift | Robåtens `package.json`, `src/index.ts`, `BOMLO_RSS.md` | Ingest/export er kommandolinjejobber. `start` logger bare oppstart; ingen arbeidende RSS-worker eller tidsstyring i arkivet. Ekstern tidsstyring kan finnes, men er ikke kontrollert. |
| Kildeinnhold | `fetchBomloRss` og `parseBomloRss` | Kun RSS hentes. Originalartikkelen følges ikke. URL-/formatkontroll er teknisk innlesingskontroll, ikke faktakontroll. `verificationStatus=unverified`. |
| Artikkel | Robåtens `src/jobs/export-bomlo.ts` | `draft.body` kopieres fra RSS-sammendraget. `source-card:<event.id>` er kildekort, ikke generert artikkel. |
| Språkvask / kvalitet / fakta | Hele Robåtens kildefiltre og Studio-integrasjonene | Ingen implementerte jobber eller obligatoriske kontrollporter. Status `review` alene dokumenterer ingen utført kontroll. |
| To produksjoner | `export-bomlo.ts`, Studio `RobotInbox.php` | Ett `event` og ett kildekort-`draft`. Ingen web-/studio-produksjoner, versjoner eller produksjonsstatuser. |
| Felles identitet | Ingest → export → Studio | `draft.eventId=event.id` bevares i kildekortflyten. Dette beviser kun delt identitet mellom hendelse og kildekort. Det beviser ikke felles ID for web og Studio-stikk som ennå mangler. |
| Studio arbeidsflate | Studio `public/index.php`, `public/robot.php` | Før rettelsen finnes kildevisningen bare på robot.php; musikk-/sendingsproduksjon og stikk er ikke implementert her. |
| Manuell godkjenning | Studio `app/integrations/WordPress.php`, auth | WordPress er kun et grensesnitt for `createDraft`, uten implementasjon eller publisering. Ingen Thomas-spesifikk godkjenning, revisjonslogg eller serverkontroll for publisering. Fravær av publisering er ikke en implementert godkjenningsflyt. |
| Parallelle veier | Studio `MunicipalityRss.php`; Web `RR_News/rr_news.php` og temaets `front-page.php` | Studio hadde direkte RSS-fallback ved tom/ugyldig eksport. Web henter RSS separat og viser offentlige lenkekort, med URL som dedupliseringsnøkkel og uten Robåt-ID. Temaets forside bruker denne shortcoden. Kortene er ikke egne genererte artikler. |

## Avgrenset rettelse i denne PR-en

- Kildekort vises i den innloggede Studio-oversikten på `/#produksjon`, med synlig sak-ID og ærlig status om manglende produksjonsfunksjoner.
- `robot.php` beholder adgangskontroll og sender innloggede brukere til oversikten. Venteside og demo forblir stengt.
- Sidevisning leser kun eksisterende eksportfiler. Den direkte RSS-fallbacken er frakoblet.
- Gyldige kort fra begge eksportfiler samles med deduplisering på `event.id`; nyhetseksporten har prioritet.
- Regresjonstester dekker deduplisering, ID i visningen, escaping, manglende eksport og skjuling i demo.
- Webs offentlige kildekort er ikke fjernet; de er dokumentert som en separat kildevisning. Det finnes ingen erstatning med godkjente Robåt-artikler ennå.

## Nødvendig videre implementasjon

1. En driftet, idempotent RSS-worker med varig sakslager og eksplisitt behandling av kildeoppdateringer (dagens merge ignorerer endringer på kjent GUID).
2. Hent og lagre originalkilden med URL, tidspunkt og innholdshash; håndter utilgjengelig kilde og kildekonflikter uten å godkjenne automatisk.
3. Generer én redaksjonell grunnsak og lagre resultater fra språkvask, kvalitetskontroll og faktakontroll. Feil eller manglende resultat må blokkere videre produksjon.
4. Lag webartikkel og Studio-stikk under samme sak-ID, kildeversjon og faktagrunnlag, med separate kanalstatuser.
5. Vis begge produksjoner i Studio. Implementer Thomas sin identitet som servervalidert godkjenner, CSRF-beskyttet godkjenning av eksakt webversjon og varig revisjonslogg. Endring etter godkjenning må kreve ny godkjenning.
6. Implementer WordPress-overføring med felles sak-ID og idempotent oppdatering. Publisering må avvise manglende/utdatert godkjenning og kontrollresultat.
7. Test hele kjeden med deterministiske kilder og generator, inkludert gjentatt ingest, endret kilde, kontrollfeil, delvis produksjonsfeil og forsøk på publisering uten Thomas sin godkjenning. Bekreft deretter installert versjon og driftsjobber uten å publisere testinnhold.

Denne PR-en erstatter ikke disse trinnene og skal ikke omtales som en ferdig RSS-produksjonsflyt.
