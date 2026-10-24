# Arkitekturgjennomgang for Radio Rubben

WordPress som redaksjonelt grensesnitt, Robåt som bakgrunnsmotor og Studio som lydsystem

24. oktober 2026 · Versjon 1.0 · Europe/Oslo

**Anbefaling:** Viderefør denne ansvarsdelingen, med gradvis utskifting av eksisterende koblinger. WordPress skal eie redigering og redaksjonelle beslutninger. Robåt skal utføre kildearbeid og generering på VPS. Studio skal eie lydproduksjon, bibliotek, sendekø og avvikling. Gjenbruk dagens PHP-kode og kontroller. Studio #109/L01 beholder førsteprioritet; robotflytting skal ikke bli en forutsetning for teststrømmen.

**Leveransestatus:** Arkitekturen er gjennomgått mot kode, ferske GitHub-opplysninger og dokumenterte driftskvitteringer. API-ene og migreringen nedenfor er forslag. Ingen produksjonsendring, tjenesteoppstart, publisering, modellkjøring eller merge inngår. Arbeidsområde: System & Kvalitet, med Teknisk Produksjon og IT & Utstyr.

## 1 Ansvar i målbildet

| Komponent | Skal eie | Grense mot de andre |
| --- | --- | --- |
| WordPress med redaksjonsplugin | Redigert nettsak og radiomanus, versjoner, kildevisning, redaksjonelle regler og beslutninger, publisering og rettelser | Sender bestillinger og viser jobbstatus. Utfører ikke lange modell- eller lydjobber i webforespørsler. |
| Robåt på VPS | Kildehenting, normaliserte fakta, originalutkast, kontrollrapporter, artikkel- og manusgenerering, jobbplan, omforsøk og feilkø | Leverer forslag og belegg. Har ikke myndighet til å publisere, endre en redaktørtekst eller styre sendingen. |
| Studio på VPS | TTS, uttale og stemmevalg, lydkontroll, mediebibliotek, programprofiler, musikkregler, køforekomster og sendelogg | Bruker et bestemt gyldig manusgrunnlag. Redaksjonell tekstendring går tilbake til WordPress og ny manusversjon. |
| Liquidsoap og Icecast | Programlyd og strøm, i egne prosesser under Studios avvikling | Skal kunne fortsette når redaksjonen eller Robåt er utilgjengelig. |
| OneDrive | Originalmedier, dokumenter og avtalt produksjonsarkiv | Serveren har lokale sendeklare filer. Arkivsynk skal ikke ligge i den tidskritiske avspillingskjeden. |

Det nyere oppdraget om WordPress som redaksjonelt grensesnitt presiserer retningen i grunnlag v1.3 og faktaarket fra tidligere samme dag, der Studio også var omtalt som redaksjonelt kontrollrom. Dette er en målendring, ikke en flytting som allerede er gjennomført. Eksisterende godkjenningsflater beholdes til erstatningen er testet. CRM og de øvrige Studio-funksjonene videreføres uten ombygging i denne leveransen.

Robåt beskriver her ansvaret for bakgrunnsjobber. Navnet innebærer ikke at den eksisterende TypeScript-grunnstrukturen allerede kan erstatte PHP-flytene. Behold de tre eksisterende kodebasene. Full omskriving, nytt monorepository, Docker og nytt frontendrammeverk inngår ikke.

## 2 Kartlagt eksisterende løsning

| Del | Kontrollert kode og dokumentasjon | Betydning for videre arbeid |
| --- | --- | --- |
| WordPress Fotballrobot | Webs selektive releasegrunnlag har kildeadaptere, FactStore, MatchJobs, MatchWork, Writer, EditorialQuality, PublicationGate, ReviewDesk, spillersaker og ukesdekning. [K2, K4] | Mye av motoren finnes allerede i PHP, men bruker WordPress-funksjoner og lagring. Den kan ikke bare kopieres ut og kjøres selvstendig. |
| Redaksjonell grense | ReviewDesk kontrollerer bruker, teksttoken og godkjenning. PublicationGate binder kvalitetskontroll til tekst, faktagrunnlag og regelversjon. [K4] | Bevar sperrene på serversiden når ny transport innføres. En vellykket AI-kontroll gir ikke publiseringstillatelse. |
| Samlet nyhetsdesk | Studio leser og avgjør WordPress-forslag via `/wp-json/rr-fotballrobot/v1/newsroom`. WordPress ReviewDigest laster samtidig Studios PHP-moduler fra lokal disk. [K5, K6] | Det finnes både API-kobling og direkte filavhengighet. De må skilles før Studio og redaksjonen kan frikobles. |
| RSS og manus i Studio | NewsDesk, newsroom, news-input, news-script og web-publish håndterer innhenting, relevans, originalkilde, nett-/radioutkast, godkjenning og levering. [K1, K6] | Gjenbruk funksjonene og kontrollene som grunnlag for Robåt. Beskytt eksisterende sakshistorikk. |
| Regler og lagring | Studio bruker `sending-board.json` med lås, revisjoner og atomisk erstatning. WordPress SharedRules leser godkjente fellesregler fra samme lokale fil. [K5, K6] | Delt filsystem er en reell driftsavhengighet. Senere regeloverføring må være versjonert og inkludere tilbakekalling. |
| TypeScript Robot | main har RSS-import/eksport, hendelser og en teknisk kvalitetsport. `src/index.ts` logger bare oppstart; kvalitetsporten gir alltid `publishable: false`. [K3] | Ingen varig motor er etablert av `npm start`. Repoet kan overta avgrensede oppgaver etter dokumentert integrasjon. |
| RR Audio | PHP-kode har stemmerettigheter, budsjett, uttale, hashbundet manus, TTS-reservasjon, lydbehandling og tilstand for ukjent leverandørutfall. [K7] | Verdifullt grunnlag for L04. Dagens kode har fortsatt eksplisitt lyttegodkjenning. |
| Musikk og avvikling | #98/#99 gir lagret kø og PHP/FFmpeg-spiller. #111 kobler samme kø til Liquidsoap/Icecast. Bibliotek, analyser, cue-punkter og programregler finnes fra før. [K8, K9] | Behold én sendekø. L01 skal ferdigstilles uten å vente på redaksjonsmigrering. |

Studio har flere ulike køer: redaksjonell sendeliste, analysejobber, RR Audio-markeringen `audioQueue`, musikkens faktiske avspillingskø og motorens manifest/journal. De representerer forskjellige oppgaver. `audioQueue` er ikke dokumentasjon på at lyd er lagt inn i L01-køen eller sendt. I #111-kontrakten kan bare musikk opprettes og avvikles i denne leveransen, selv om skjemaet kjenner flere typer.

OneDrive-grensesnittet som ble lest i Studio er en kontrakt uten implementert Graph-tilgang. Det beviser ikke automatisk arkivering fra Studio. Dokumenter som er manuelt arkivert, og en ferdig produksjonsintegrasjon, må rapporteres hver for seg. [K7]

## 3 Funn som styrer arkitekturen

**A1 Direkte avhengighet av Studio-filer.** `ReviewDigest::worker()` bruker `ABSPATH.'studio-private'`, laster `app/newsroom.php` og kaller `studio_newsroom_tick()`. `SharedRules::current()` leser `studio-private/config/sending-board.json`. En flytting av Studio uten å erstatte disse koblingene kan stoppe klargjøring, samlevarsel eller regelinnhenting. Erstatt med versjonerte grensesnitt én flyt om gangen. [K5]

**A2 Flere identiteter for samme kilde.** Studio normaliserer NRK-ID og kilde-URL. Robot lager Bømlo-ID fra RSS-GUID med prefikset `news:bomlo-kommune:`. WordPress bruker blant annet FIKS-ID, innleggs-ID og egne metadata. Behold disse som eksterne referanser i en eksplisitt koblingstabell. Ikke konverter dem ved antakelse eller tittelmatching. [K3, K6]

**A3 Eksisterende API er for bredt for en robotbruker.** `Robot::allowed()` krever `manage_options` og `edit_posts`. Nyhetsdeskens POST kan utløse godkjenning/publisering med ytterligere publiseringskontroll. Dette er en operatørbro, ikke et egnet innleveringspunkt for en begrenset bakgrunnsmotor. Nye maskinrettigheter må skilles fra menneskelig redaksjonsmyndighet. [K4, K5]

**A4 Automatisk TTS er et mål, ikke dagens fullførte flyt.** `rr_audio_ready()` krever `heardDisclosure` og lydgodkjenningshash. `rr_audio_approve_or_queue()` krever lyttebekreftelse. Bevar dagens runtime under gjennomgangen; erstatt dette kontrollert i L04 med avtalt innholdspolicy, stemmerettigheter og automatisk lydkontroll. Nettpublisering beholder menneskelig godkjenning. CRM-TTS beholder sine særvilkår. [K7, K8]

**A5 Tidsstyring har et kjent separat avvik.** Den leste host-runneren mangler `rrfr_club_results`. Web #106 retter dette, men er åpen og oppgis ikke utrullet. PR-en dokumenterer også observerte startintervaller på 2 t 23 min–4 t 54 min. Rettelsen løser ikke intervallforsinkelsen. Bruk én tidsplanlegger per jobb; en VPS-utløst WordPress-jobb er fortsatt en WordPress-avhengig jobb. [K10, E1]

**A6 main er ikke installasjonsfasit.** Web main mangler hele Fotballrobot-grunnlaget. Dokumenterte selektive utrullinger finnes i åpne PR-er, blant annet Web #109 og Studio #110. Hver fremtidig flytting må begynne med installert filmanifest og fersk kontroll av miljøet. Ikke deploy hele main eller en hel PR-kjede. [K1, K2, K11]

**A7 L01-kontrollen må bruke riktige tjenestenavn.** #109 har dokumentert kontroll av `rr-music-playout`, og gatewayen undersøker `rr-playout`/`rr-stream`. #111s faktiske maler heter `rr-studio-stream.service` og `rr-studio-icecast.service`; `rr-playout` og `rr-stream` er også brukernavn. `not-found` for de gamle tjenestenavnene beviser derfor ikke fravær av de nye. Avstem mal, installert unit, prosess og strømmeadresse ved neste L01-kontroll. [K8, K9]

## 4 Dataeierskap og versjoner

WordPress er autoritativt for lagret redaktørtekst og redaksjonelle avgjørelser. Robåt er autoritativt for kildeobservasjoner, genererte forslag og sine jobber. Studio er autoritativt for lydfiler, køforekomster og avspillingshendelser. Kopier i andre systemer er visninger eller versjonerte grunnlag, ikke konkurrerende originaler.

| Foreslått felt | Betydning og regel |
| --- | --- |
| `case_id`, `external_refs` | Stabil intern sak-ID; kobling til eksisterende Studio-ID, Robot-hendelse, FIKS-ID og WP-innlegg. Gamle referanser bevares. |
| `source_id`, `source_revision`, `source_hash` | Kildeidentitet skilt fra innholdsversjon. URL, utgiver, publiseringstid, hentetid og belegg følger med. Samme innhold ved ny henting gir ikke automatisk ny sak. |
| `article_revision`, `script_revision` | Separate versjoner for nettartikkel og radiomanus. Begge peker til sitt faktagrunnlag. Nettartikkelens prosa er ikke automatisk radiomanus. |
| `rules_version`, `policy_version`, `generation` | Eksakt regelsett, innholdspolicy og modell-/promptversjon brukt ved generering og kontroll. En språkregel er ikke en faktakilde. |
| `approval_id`, `approval_scope`, `content_hash` | Godkjenning knyttet til eksakt innhold og kanal. Endrede kilder, tekst eller relevante medier krever ny vurdering etter policy. |
| `job_id`, `idempotency_key`, `attempt` | En identitet for oppdraget og en nøkkel som hindrer at gjentatte leveringer skaper flere resultater. |
| `asset_id`, `asset_sha256`, `queue_entry_id` | Lydfil og filversjon er skilt fra forekomsten i køen. Samme låt kan ha flere legitime køforekomster. |
| `valid_until`, `environment`, `occurred_at` | Utløp for tidskritisk innhold, miljø og hendelsestid. Lagre entydige UTC-tider; vis Europe/Oslo og håndter tidsskifte. |

Bruk serverkontrollerte versjoner og sammenligning mot forventet revisjon ved skriving. En redaktørendring mens Robåt arbeider gjør resultatet foreldet: lagre det som forslag, ikke overskriv. WordPress må beregne egne hasher etter validering og sanitering; ikke stole på en innsendt verdi som `approved: true` eller `publishable: true`.

Kildedata og fakta som genererte en tekst skal kunne gjenfinnes uendret. Robåt kan lagre nyere observasjoner uten å erstatte det gamle bevisgrunnlaget. Bevar WordPress-artikkelens faktakopi, Studio-manushistorikk, regelhistorikk og manuelle overstyringer ved migrering.

## 5 Foreslått API og tilgang

Dette er en kontrakt til senere implementasjon. Endepunktene under finnes ikke som samlet løsning i den kontrollerte koden. Gjenbruk eksisterende transport der den passer, men ikke gi den nye arbeideren tilgang til dagens godkjennings-POST.

En enkel første transport er at VPS-arbeiderne henter bestillinger fra en varig utboks i WordPress og leverer resultat med samme jobb-ID. Da trenger ikke WordPress å starte eller vente på en tung jobb. Robåt beholder sin egen jobblogg og kildeplan. Bruk HTTPS, eget Application Password per maskinintegrasjon og en egendefinert rolle med minste nødvendige rettigheter. Application Password arver brukerens rettigheter; det er ikke i seg selv en avgrenset rettighetspakke. [E2, E3]

| Foreslått grensesnitt | Bruker og resultat |
| --- | --- |
| `POST /rr-editorial/v1/jobs/claim` | Robåt eller Studio reserverer bare tillatte jobbtyper. Returnerer jobb, lease, forsøksnummer og forventede versjoner. |
| `POST /rr-editorial/v1/jobs/{id}/heartbeat` | Samme arbeider fornyer egen reservasjon. Et gammelt lease-token skal ikke kunne gjenopplive en overtatt jobb. |
| `POST /rr-editorial/v1/jobs/{id}/result` | Leverer forslag, kildeversjoner og kontrollrapport, eller lydstatus. Serveren kontrollerer jobbtype, avsender, lease og revisjon før resultatet tas inn. |
| `GET /rr-editorial/v1/jobs/{id}` | Avklarer faktisk mottak etter tidsavbrudd. En identisk ny innlevering returnerer samme kvittering; ulikt innhold under samme nøkkel avvises. |
| `POST /rr-editorial/v1/proposals` | Robåt leverer selvoppdaget RSS-sak med stabil kilde-ID og faktagrunnlag. Oppretter bare et forslag, med unik identitetskobling. |
| `GET /rr-editorial/v1/cases/{id}/versions/{revision}` | Leser eksakt manus-/saksgrunnlag med kanal, gyldighet, rettigheter og gjeldende autorisasjon. Historisk godkjenning alene gir ikke ny sendetillatelse. |
| `POST /rr-editorial/v1/cases/{id}/decisions` | Bare autentisert redaktør, med nonce/CSRF, rettighet og forventet revisjon. Maskinbrukerne skal få avslag. |

Alle navn er forslag. Namespace og felter fastlåses i en egen kontraktleveranse etter L01. Serveren avviser feil miljø, ukjent skjema, feil objektrettighet, for store svar og gamle revisjoner. Bruk 401/403 for tilgang, 409 for revisjonskonflikt, 422 for ugyldig innhold og 429 med kontrollert venting. GET skal ikke starte generering eller endre godkjenning.

Robåt får ikke `publish_posts` eller `manage_options`; Studio-integrasjonen får bare lese autorisert manus og levere lydstatus. En egen operatørrolle styrer avspilling. Behold WordPress-cookie/nonce for mennesker og dagens Studio-roller. Avsenderidentiteten kommer fra autentisering, ikke fra et fritt `actor`-felt. Logg jobb-ID, versjon og resultat uten hemmeligheter eller komplette persondata.

Resultat og dedupliseringskvittering må lagres atomisk på mottakersiden. Utboks og beslutning må også være atomisk koblet, eller ha en dokumentert gjenopprettingskontroll. En låst fil kan fungere for en avgrenset lokal pilot, men delte JSON-filer over nettverk skal ikke være integrasjonsmekanismen. Valg av lagring for Robåt fastsettes etter pilotens behov; ingen ny databaseplattform er vedtatt her.

## 6 Tilstander og feilbehandling

| Objekt | Foreslåtte tilstander | Hvem avgjør neste steg |
| --- | --- | --- |
| Kilde og fakta | oppdaget, hentet, kontrollert, konflikt, utløpt | Robåt registrerer belegg og kontroll. Konflikt stopper avledet produksjon. |
| Nettartikkel | utkast, trenger kontroll, klar for vurdering, godkjent versjon, publisert, rettelse venter, trukket | WordPress/redaktør. Faktisk WP-status bekrefter publisering. |
| Radiomanus | utkast, kontrollert, autorisert for lyd, foreldet, utløpt | WordPress håndhever avtalt innholdspolicy og versjon. Nettpublisering er ikke en forutsetning for et radioinnslag. |
| Lydjobb | venter, produserer, ukjent utfall, lydkontroll, klar, feilet, foreldet | Studio. Ukjent betalt TTS-utfall avklares før nytt leverandørkall. |
| Køforekomst | planlagt, reservert, startet, fullført, avbrutt, feilet, trukket før start | Studio og faktisk avspillingsmotor. «Klar lyd» betyr ikke «sendt». |
| Bakgrunnsjobb | venter, reservert, kjører, nytt forsøk, blokkert, fullført, feilkø | Eier av jobbtypen, med lease, tidsgrense og begrensede forsøk. |

For radio kan en uttrykkelig avtalt innholdspolicy gi autorisasjon automatisk. Manuell godkjenning av hver lydfil skal ikke bygges inn som permanent krav. Den tekniske lydkontrollen skal blant annet dekke format, dekoding, varighet, stillhet, nivå, stemmerettighet, uttalegrunnlag og KI-merking. Gjeldende runtime-sperrer endres først gjennom L04 med egne tester.

Ved faktarettelse bevarer WordPress publisert tekst og oppretter et kontrollert endringsforslag. Robåt markerer avhengige forslag; Studio invaliderer berørte manus-/lydversjoner og stopper framtidige køforekomster. Ny versjon får ny autorisasjon etter policy. Allerede sendte innslag beholdes i loggen. Håndtering av et innslag som allerede spiller, må defineres særskilt; en vanlig tekstendring skal ikke kutte radiosendingen vilkårlig.

En varig endringslogg med sekvensnummer, kvittering og periodisk avstemming må gjøre at en mistet melding ikke skjuler tilbakekalling. Før nytt innslag klargjøres og før det reserveres, kontrolleres gyldig versjon/autorisasjon og utløp. Ved frakoblet redaksjon kan gyldig, allerede klargjort musikk fortsette. Tidskritiske nyhetsinnslag uten tilstrekkelig fersk autorisasjon holdes tilbake. En buffer som allerede er reservert, må kunne trekkes kontrollert av motoren.

Robåt, TTS-/FFmpeg-arbeider og avspiller skal ha ulike prosesser, brukere, skriveområder og ressursgrenser på VPS. En køtopp eller modellfeil skal ikke sulte lydmotoren. Mål prosessor, minne og disk under lytting før konkrete grenser fastsettes. Tilgang til leverandører gis bare arbeideren som trenger den; avspilleren får lokale, kontrollerte medier.

## 7 Migrering med Studio først

| Trinn | Avgrensning | Ferdigkriterium og tilbakeføring |
| --- | --- | --- |
| 0 Fullfør L01 | Fortsett #109/#111 og avstem #113. Kontroller faktisk installasjon og privat strøm med egne låter. | Tre låter, korrekt rekkefølge og overgang, annen lytteklient, lukket/gjenåpnet Studio, tom kø, filfeil og omstart. Bevar originaler og journal. Ingen Robåt-avhengighet. |
| 1 Fastlås integrasjonsgrunnlaget | Kartlegg installerte filer, gamle ID-er, regler og godkjenninger. Skill Web #106 som driftsrettelse fra arkitekturflytting. | Signert/hashet releasegrunnlag per miljø, én eier per jobb og kontrollert eksport/import av syntetiske saker. Ingen jobbeier endres ennå. |
| 2 Test én Robåt-jobb | Bømlo-RSS er første kandidat. Gjenbruk PHP-normalisering og kontroll; sammenlign med TypeScript-import i isolert test. | Samme kilde gir én sak etter gjentakelse/omstart. Endret kilde gir ny versjon. Først sammenligning uten skriving til aktiv redaksjon og uten publisering. |
| 3 Flytt én redaksjonell flyt | Vis kilde, nettutkast og radiomanus i WordPress gjennom nytt adapterlag. Bevar alle versjoner og bildevalg. | Redigering, rettelse, rolleavslag, utløp og ny godkjenning består. Studio viser lenke/status for flyttet sak og kan ikke samtidig redigere den som original. |
| 4 Bytt jobbeier kontrollert | Etter egen utrullingsbeslutning: stopp gammel utfører for akkurat den flyten, tøm/avstem aktive jobber, og aktiver VPS-arbeider. | Ingen dobbeltkjøring. Nye resultater avstemmes mot kvittering. Tilbakeføring stopper ny skriver og gjenopptar gammel med oppdatert kontrollpunkt. Ingen tilbakerulling av hele databasen. |
| 5 Koble lyd i L04 | Robåt lager radiomanus fra samme fakta; Studio produserer, kontrollerer og legger lyd én gang i eksisterende kø. | Ingen obligatorisk lyttegodkjenning per innslag. Utløp/rettelse og ukjent TTS-utfall håndteres. CRM-særvilkår bevares. |

L02 og L03 beholder sin plass i Studio-planen. Trinnene over er avhengigheter for robotflytting, ikke en ny tidsplan som går foran Studio. Fotballmotoren flyttes etter at den enklere kilde- og utkastflyten er dokumentert. WordPress kan bruke en VPS-basert utløser i overgangsperioden, men den gamle og den nye skriveren skal aldri eie samme jobb samtidig. Ikke deaktiver all WP-Cron når bare én jobbfamilie er overtatt; øvrige planlagte WordPress-funksjoner må fortsatt få kjøre. [E1]

Følgende kontrollscenarier skal inngå i senere integrasjonsarbeid: samtidig redaktørendring og jobbresultat; gjentatt levering etter tapt HTTP-svar; omstart under TTS; kildeendring mens lyd er reservert; mistet tilbakekallingsmelding; feil miljø og rolle; publiseringsforsøk fra robotbruker; samme låt flere ganger i kø; utløpt nyhetsmanus; nettbrudd uten stans av gyldig musikk; overgang mellom sommer- og vintertid. Dette er planlagte tester, ikke tester kjørt i gjennomgangen.

## 8 Kontrollbelegg og neste handling

Kodekontrollen er utført i isolerte lokale kopier. Ingen ny direkte serverkontroll eller fysisk lytting er utført i denne arkitekturgjennomgangen. Driftsopplysninger nedenfor er hentet fra daterte kvitteringer og observasjoner i GitHub, ikke utledet fra at en PR er åpen eller merget.

| Kontrollert grunnlag | Identitet og status ved gjennomgangen |
| --- | --- |
| Studio main | `96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60` |
| Web main | `5169765446b75f5c983695940241985beda2f7ad` |
| Robot main | `33cefc2849e415e2c6bd2f7335e73cae6baef9e5` |
| Studio #111 | Åpent utkast, head `a34d50bc1e38dee4567bcdfb5ebdd9abbdd59e0d`, base #99. |
| Studio #113 | Åpent utkast, head `ad87e842fcd075ca4b77b7303558827edb552fec`. Retter statusmelding, ikke dokumentert lydhakking. |
| Web #109 | Åpen release-PR, head `6a247e122998544a0ff0d477844d27db1c5bc439`. Kvitteringen oppgir selektiv installasjon og kontroll av 49 filhasher 24.10. |
| Web #106 | Åpen PR, head `6951cd4e40d8fb1557c80c881061cf8183c1d994`. Ikke dokumentert utrullet. |
| Test-VPS i #109 | Sist dokumenterte release `studio-music-fad12a2320de0ff0a036854c414accee794143bb`. Senere nettleserobservasjon viser tre køinnslag og motorfremdrift. Bruker har meldt hakkete lyd. |

Det er ikke kjørt runtime-tester på ny arkitektur, siden leveransen bare endrer dokumentasjon. Kildebaner, commitreferanser, ansvarsgrenser og forskjellen mellom kode og driftsbevis er kontrollert. Dokumentet er et beslutnings- og implementasjonsgrunnlag, ikke en ferdigmelding for Robåt eller L01.

**Anbefalt neste handling:** Fortsett i Studio #109/L01. Avstem den aktive testreleasens filer mot #111 og eventuelle selektive endringer; kontroller de faktiske tjenestenavnene og kartlegg hakkingen i dagens monitor før lydtesten godkjennes. Deretter fullføres det allerede avgrensede teststrømløpet innenfor konkret autorisasjon. Hold robotmigreringen som etterfølgende arbeid.

## 9 Kilder

- **K1** [Studio main ved kontroll](https://github.com/toystad461/radiorubben-studio/tree/96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60). Lest: AGENTS.md, docs/STUDIO-STATUS.md og docs/RSS-FLOW-AUDIT.md.
- **K2** [Web main ved kontroll](https://github.com/toystad461/radiorubben-web/tree/5169765446b75f5c983695940241985beda2f7ad). Lest: AGENTS.md og docs/DEVELOPMENT.md.
- **K3** [Robot main ved kontroll](https://github.com/toystad461/RadioRubben-robot/tree/33cefc2849e415e2c6bd2f7335e73cae6baef9e5). Lest: package.json, src/index.ts, src/core/events.ts, src/sources/rss/bomlo.ts, src/editorial/quality-gate.ts og docs/DEVELOPMENT.md.
- **K4** [Fotballrobotens gjennomgåtte PHP-kode](https://github.com/toystad461/radiorubben-web/tree/6a247e122998544a0ff0d477844d27db1c5bc439/wordpress/wp-content/plugins/radio-rubben-fotballrobot/includes). Lest blant annet robot.php, review-desk.php, publication-gate.php, fact-store.php og match-jobs.php. Dette er et releasegrunnlag; filenes installasjonsstatus følger egne kvitteringer.
- **K5** [Eksisterende kobling til Studio](https://github.com/toystad461/radiorubben-web/blob/6a247e122998544a0ff0d477844d27db1c5bc439/wordpress/wp-content/plugins/radio-rubben-fotballrobot/includes/review-digest.php). Lest også shared-rules.php og newsroom.php i samme katalog/commit.
- **K6** [Studios redaksjonsmoduler](https://github.com/toystad461/radiorubben-studio/tree/96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60/app). Lest: board.php, source-identity.php, newsroom.php, newsroom-worker.php, newsroom-wordpress.php og news-publication.php.
- **K7** [Eksisterende lydflyt](https://github.com/toystad461/radiorubben-studio/blob/96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60/app/audio-workflow.php). Lest også audio-processing.php, audio-storage.php og integrations/OneDrive.php.
- **K8** [Studio hovedsak 109](https://github.com/toystad461/radiorubben-studio/issues/109). Sak og ti kommentarer lest, inklusive runtime-, nettleser- og lyttefunn 24.10.2026. [PR 113](https://github.com/toystad461/radiorubben-studio/pull/113) er kontrollert separat.
- **K9** [L01 kontrakt og driftsplan ved eksakt head](https://github.com/toystad461/radiorubben-studio/blob/a34d50bc1e38dee4567bcdfb5ebdd9abbdd59e0d/docs/STUDIO-LIQUIDSOAP.md). Lest også app/music-play-queue.php, app/music-studio.php, app/music-liquidsoap.php og tjenestemalene i infra/studio-stream. [PR 111](https://github.com/toystad461/radiorubben-studio/pull/111).
- **K10** [Web PR 106 om scheduler](https://github.com/toystad461/radiorubben-web/pull/106). Nåværende åpne rettelse og dokumenterte driftsintervaller. Gammel tillatelsesliste kontrollert i scripts/fotballrobot-cron.php i Webs releasegrunnlag.
- **K11** [Produksjonskvittering for Sport](https://github.com/toystad461/radiorubben-web/blob/6a247e122998544a0ff0d477844d27db1c5bc439/docs/fotballrobot/SPORT-DEPLOYMENT-20261024.md). [Studio PR 110](https://github.com/toystad461/radiorubben-studio/pull/110) dokumenterer separat, selektiv bildekontraktrettelse.
- **K12** [Gjeldende målgrunnlag v1.3 i OneDrive](https://radiorubben-my.sharepoint.com/personal/thomas_sellevold-oystad_radiorubben_no/Documents/Radio%20Rubben/07%20Plattform%20og%20driftsdokumentasjon/Gjeldende%20prosjektgrunnlag/01-Radio-Rubben-Plattform-grunnlag.txt?web=1). Lest 24.10.2026, sammenholdt med lokalt v1.2, dagens oppdrag og overleveringen. Faktaark v1.0 fra tidligere 24.10 er historisk sammenligningsgrunnlag.
- **E1** [WordPress om systemstyrt WP-Cron](https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/). Kontrollert 24.10.2026.
- **E2** [WordPress om Application Passwords](https://developer.wordpress.org/advanced-administration/security/application-passwords/). Kontrollert 24.10.2026.
- **E3** [WordPress om egendefinerte REST-endepunkter](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/). Kontrollert 24.10.2026.

Retning og kontraktvalg i dette dokumentet er anbefalinger basert på kartleggingen. Tekniske kildehenvisninger dokumenterer eksisterende mekanismer; de skal ikke leses som bevis på at den foreslåtte arkitekturen er implementert.
