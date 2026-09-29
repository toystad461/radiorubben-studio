# Robåten – God morgen Vestland sendeforslag v1

Bygger på PR #13, head `7f72fbf7a9e0e33b488c74d8c0251021f9bbffd7`, inkludert programregister-refaktoren. Ikke deployet. Dette er et selektivt Uniweb-tillegg, ikke en full erstatning for aktivt Studio.

## Bruk

1. Velg kildesaker i Nyhetsdesk → Sending. Tilordne riktig program. Gamle punkter uten program endres aldri automatisk.
2. Åpne **Robåt – sendeforslag** i menyen. Velg God morgen Vestland, dato og programledernavnet som skal leses på lufta.
3. Velg inntil fem kildesaker. Kryss eventuelt av for AI-manus til de sakene som ikke allerede har manus. Dette bruker eksisterende private AI-konfigurasjon og `producer_request`, ikke en ny API-nøkkel.
4. **Lag sendeforslag** oppretter 16 kvartblokker kl. 05–09, tema, programlederstikk, 52 musikkforslag og åtte reserver. Manglende nyheter/serviceinformasjon er synlige tomme plasser. Ingen datoavhengige nyheter fra chatprøven importeres.
5. AI-utkast lagres også på kildepunktene i Sending med originaltekst, profil/regler og historikk. Rett og kontroller der, og bruk eksisterende «Lær av rettelsen». Lag et nytt sendeforslag etter endringene; gamle forslag beholdes som øyeblikksbilder.
6. Vis eller last ned hele kjøreplanen. Kontroller aktualitet, musikkfiler og faktisk timing før sending.

## Programkunnskap og begrensninger

Programregister v2 / profil v2 har sendetid, tidssone, stemme, musikkretning, redaksjonelle regler og tre korte stileksempler fra prøvearbeidet. Dette følger både nyhetsgeneratoren i Sending og nye AI-manus i sendeforslaget. Bare det valgte programmets godkjente regler brukes. Eksemplene er aldri faktakilder.

Den kodeeide `templates/morning-v1.json` inneholder den første faste hverdagsmester-malen. Andre programprofiler får ikke denne malen uten eksplisitt tilordning. Skjemaet har foreløpig ikke fri temagenerering. Programlederstikk og låtvalg er faste malforslag, ikke AI-generert på nytt ved hvert trykk. Nyhetsutvalget hentes fra eksisterende Sending; det skjer ikke et nytt nettsøk. Ingen automatisk NRK-henting, vær, trafikk, lytting, lydproduksjon, OneDrive-synk eller chat-synk er lagt til.

Musikkforslagene er ikke koblet automatisk til Musikkontroll i denne versjonen. Alle filstatuser er `unknown`, spilletider `null`, og timingen er `unmeasured` til faktisk arkivkontroll/avvikling. Ikke bruk forslagets kvarter som eksakte låtstarter. Reservedata er forslag, ikke en garanti for filtilgang. Neste kobling bør bruke Musikkontrollens faktiske fil-ID, versjon og varighet, ikke artist/tittel alene.

Kildeutdragets eksisterende minimumskrav, `store:false` og prioritering av kildekrav er bevart. Modellrespons er fortsatt et utkast som krever menneskelig faktakontroll. Alle valgte saker gjentas i nyhetsblokkene som et utgangspunkt; redaktøren prioriterer og varierer dem før sending.

## Lagring og samtidighet

`broadcastDrafts` ligger i eksisterende private `sending-board.json`, under samme atomiske fillås. Hver plan bevarer dato, kildepunktenes revisjoner og fullstendige øyeblikksbilder, modellnavn ved AI-bruk, malversjon, profil og godkjente regelversjoner. Ingen API-nøkkel lagres. Eksisterende sendepunkter og regler bevares; bare valgte kildepunkter som mangler manus får nye AI-utkast.

AI-kall kjøres utenfor låsen. Før atomisk lagring sammenlignes kildepunkter og redaksjonell kontekst med opprinnelig grunnlag. Samtidige endringer avviser hele pakken. AI-feil gir heller ingen delvis lagring. Request-token hindrer doble sendeforslag. Grensen er 50 forslag; ingenting slettes automatisk. Produksjonsarkiv og filstørrelse må følges opp før denne grensen nås.

## Selektiv utrulling – må utføres separat

Bevar gjeldende produksjonskode og privat sendeliste. Avstem forskjeller, særlig menyen, som også kan inneholde senere Musikkontroll-arbeid. Denne PR-en skal ikke deployes automatisk.

- Oppdater `studio-private/app/programs.php` og `editorial-memory.php`.
- Legg til `studio-private/app/templates/morning-v1.json` og `broadcast.php`.
- Legg til `studio-public/broadcast.php`.
- Flett menyoppføringen i `studio-private/app/views/sidebar.php` med aktiv meny; ikke overskriv andre nye oppføringer.

`board.php`, `story-script.php`, `producer.php`, innlogging, helpers og gjeldende private konfigurasjon fra aktivt Studio er avhengigheter. Standardpakken/main inkluderer ikke hele den aktive Studio-utgaven. Ingen private JSON-data følger kodeendringen. Tilbakeføring fjerner lenken/siden og gjenoppretter berørte kodefiler; ikke gjenopprett eldre data over nyere redaksjonelt arbeid.

## Tester

`test-broadcast.php` dekker firetimersmal, 52+8 låtforslag, profil/regler i mockede AI-kall, programisolasjon, ukjent/legacy-program, kildevalg, original/historikk i Sending, datoer, ukjent filstatus, idempotens, manglende konfigurasjon, utilstrekkelig kilde, samtidige endringer og ingen delvis lagring.

`test-broadcast-page.php` bruker isolert bootstrap og midlertidige filer for GET, opprettelse, HTML-escaping, observatøravvisning, CSRF, nonce, ukjent program og metodeavvisning. CI kjører begge på PHP 8.2/8.4 sammen med eksisterende board-, manus-, lærings- og programregistertester. Lokalt er relevante tester kjørt med PHP WASM. Ingen ekte AI-kall, produksjonsdata eller utrulling er brukt.

Etter senere utrulling må ekte innlogging, mobilvisning, eksport og privat konfigurert AI-kall kontrolleres i Studio. Produksjonens musikkarkiv er ikke tilgjengelig i disse testene.
