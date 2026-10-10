# Lokal relevans før RSS-produksjon

## Kartlagt eksisterende flyt

Studio henter de tre faste feedene i app/integrations/NewsDesk.php, validerer
URL-er/XML, mellomlagrer og samler NRK-peker på kanonisk identitet.
Nyhetsdesk (public/newsdesk.php) bruker app/newsroom.php for inntak og klargjøring.
Originalen hentes og identitetskontrolleres i app/news-script.php; eksisterende
radio- og nettgeneratorer og uavhengig kildekontroll brukes videre.
app/case-workflow.php lagrer nettutkast. app/board.php bevarer manus/historikk.
Menneskelig godkjenning kommer før app/web-publish.php sender til WordPress.
app/audio-workflow.php og app/bulletin.php har separate manus-, lyd- og sendekrav.

WordPress-køen vises gjennom app/newsroom-wordpress.php og den autentiserte
rr-fotballrobot/v1/newsroom-broen. Dokumentert WordPress-cron starter Studios
newsroom-worker, og går dermed gjennom samme utvalgssteg. Den lokale web-kopien
har RR_News som viser RSS-lenkekort uten å lagre innlegg; dette er ikke
artikkelgenerering. Gjeldende web-main ble lest via GitHub, men inneholder ikke
Fotballrobotens aktive newsroom-plugin. Det er ikke gjort endringer i WordPress,
robot-repo eller produksjon. Revisjon av all aktiv WordPress-plugin-kode gjenstår
før eventuell utrulling på server; historisk driftsdokumentasjon er ikke bevis
for at det ikke finnes andre aktive produksjonsinnganger.

## Nye regler og sperrer

app/news-relevance.php er utvalgssteget i eksisterende nyhetsflyt, versjon bomlo-1.
Det bruker eksisterende producer_request, modell og sikkert originaluttrekk.
Dette innebærer ett modellkall for utvalg før eventuelle separate skrive- og
kontrollkall. Ingen ny nyhetsmotor eller automatisk publiseringsgodkjenning.

- Bømlo prioriteres også for små kultur-, frivillighets- og hverdagssaker.
  Køen leser kommunekilder og Bømlo-omtale først, uten å godkjenne dem av den grunn.
- Modellen må beskrive lokal tilknytning, konkret virkning, verdi, aktualitet,
  kildekvalitet og begge kanaler. Ingen totalscore.
- Serveren henter belegg fra oppgitte avsnittsindekser. Manglende eller ugyldig
  belegg stopper. Automatisk positivt utvalg krever uttrykkelig Bømlo/bømling
  i lokalt belegg samt belegg for betydningen. Andre lokale stedsnavn alene
  venter på redaksjonell avklaring. Dette er bevisst konservativt.
- Radio, nett og begge vurderes separat; utilstrekkelig kilde stopper begge.
- Hendelsesdato krever kildebelegg. Publiseringsdato er et annet felt.
  Eldre artikler kan kvalifisere for kommende aktiviteter eller dokumenterte
  løpende tilbud. Ukjent tid i eldre hendelsessaker krever avklaring.
- Original, kontrollsum, policy, modell, tidspunkt, begrunnelser og prior-sak
  lagres. Vurderingen utløper etter ett døgn; endret original/metadata krever ny
  vurdering. Kildekontrollens strengere éntimesgrense beholdes.
- Eksakt kildeidentitet gjelder hele historikken, også arkiverte saker.
  Identisk originaltekst kan ikke erklæres ny av modellen. Semantisk sammenlikning
  skjer mot de siste 60 tidligere RSS-sakene med korte utdrag/faktapunkter.
  Dette er ikke et fullstendig semantisk søk i hele arkivet: eldre saker eller
  opplysninger utenfor utdraget kan kreve manuell arkivsjekk.
- Samme hendelse uten nye fakta avvises og lenkes til tidligere sak.
  Vesentlig oppdatering krever nytt kildebelegg. Publiserte saker kan ikke
  regenereres via denne flyten; de må redigeres manuelt.
- Direkte radio/nett-generering, TTS og samlet sending kontrollerer utvalget.
  Eksisterende kilde-, rolle-, CSRF-, revisjons- og menneskelige godkjenninger
  beholdes. En overstyring nullstiller godkjenning, endrer kanalvalget og
  lagres med aktør/tid/årsak. Den lærer ingen fast regel og kan ikke oppheve
  mangelfulle kilder eller uavklarte dubletter.
- Avviste automatiske kandidater lagres i innboksen uten å bruke en produksjonsplass.
  Ett utvalg per worker-kjøring. Uendrede avvisninger/feil prøves ikke automatisk
  igjen; redaktøren kan hente original og revurdere. Maks åtte produksjoner er
  en øvre grense, aldri en kvote som fylles.
- Feed-pekere beholdes inntil 30 dager. Dette er et avgrenset søkevindu, ikke en
  garanti for alle fremtidige arrangementer publisert tidligere.

## Justering og verifisering

Redaksjonelle kriterier, strukturert svar og uavhengige sperrer:
app/news-relevance.php. Endre policyversjon ved vesentlige regelendringer.
Retensjonsvindu: app/integrations/NewsDesk.php og STUDIO_RELEVANCE_LOOKBACK.
Automatisk utvalg/prioritering: studio_newsroom_tick i app/newsroom.php.
Overstyring for enkeltsak: Redaksjonelt utvalg i eksisterende Nyhetsdesk.

tests/recovered/test-news-relevance.php tester grenseverdier med eksplisitt
syntetiske modellsvar. Det verifiserer kontrollflyt og sperrer, ikke modellens
forståelse eller sannheten i virkelige artikler. Tidligere skrive-, kilde-, kanal-,
lyd-, bulletin- og side-tester beholdes med egne utvalgsfixturer.
Mobilfixturen har bare oppdiktet belegg.

scripts/rss-relevance-preview.php henter offentlige RSS/originaler, og kjører
bare vurdering hvis modellkonfigurasjon finnes. Den skriver aldri til board,
publiserer aldri og lager ikke manus eller TTS. --source-text er kun for privat
lokal kontroll; rå originaler skal ikke legges i offentlig PR.
Se RSS-RELEVANCE-PREVIEW-2026-10-10.md for faktisk prøve og åpne verifikasjonspunkter.

Ingen produksjonsutrulling er autorisert i denne bestillingen.

## Redaksjonell tilbakemelding og ny vurdering

«Forkast forslag» åpner nå et felt med påkrevd begrunnelse (10–1000 tegn).
Studio lagrer begrunnelse, aktør, tidspunkt og versjon sammen med arkiveringen.
Siste 60 sammenligningssaker kan gi forkastingsgrunner som eksempler i samme
program. Det er ikke modelltrening og oppretter ingen godkjent generell regel.

«Vurder saken på nytt» leser originalen og inkluderer siste 20 saksbundne
kommentarer fra læringsflaten og tidligere vurderinger, avklaringsgrunnene,
forrige vurdering og en eventuell ny kommentar (maks 2000 tegn). Deaktiverte
eller avviste læringskommentarer tas ikke med. Nytt vurderingsgrunnlag vises
med forrige begrunnelse; tidligere vurderinger bevares i historikken.
Kommentarer er undersøkelsespunkter, aldri dokumentasjon som erstatter originalen.
Handlingen produserer ingen artikkel, TTS eller publisering. Godkjenninger
nullstilles. Tekniske leveringsavvik må løses separat.
