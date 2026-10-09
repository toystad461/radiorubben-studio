# Radio Rubben Stylebook 1.1.0

Status: implementert i utviklingsbranch; ikke utrullet. Erstatter 1.0.0 for nye genereringer. Alle regler for språk, kildebelegg, presseetikk, lokal relevans, åpenhet og redaksjonell læring fra [1.0.0](STYLEBOOK-1.0.0.md) beholdes. Dette er et tillegg for RR Audio; faktakravene er ikke svekket.

## RR-A01 – selvstendige radiomanus

Nettartikkel og radiomanus skal bruke det samme kontrollerte originalgrunnlaget. Radiomanuset skrives direkte fra kilden, med korte muntlige setninger, tydelig attribusjon og én forståelig hovedvinkel. Nettartikkelens ordlyd er ikke en ny kilde. Ingen sceneanvisninger, SSML eller oppdiktet sendestatus i teksten som leses opp.

Profiler: nyhetsstikk 15–20, 30–45 og 60–90 sekunder; programlederstikk; sportsoppdateringer; lokale arrangementer; teasere; overganger. Programprofilen bestemmer tone, aldri fakta eller etikk. Ved for lite kildegrunnlag velges et kortere format. Tidsmål er ikke minstemål for tekstproduksjon. En lydfil utenfor valgt tidsprofil må korrigeres eller få en passende profil før sending; ingen automatisk fylltekst.

Ordtakene i generatorinstruksjonen er profilavhengige og fortsatt begrenset av kildelengden. Vanlig radio uten RR Audio-profil beholder den konservative grensen på 75 ord. Utvidet nyhetsstikk kan be om inntil 200 ord bare når originalgrunnlaget tillater det.

## RR-A02 – stemme, uttale og åpenhet

Bruk bare stemmer redaksjonen har godkjent for programmet og bekreftet bruksrettigheter til. En KI-stemme må ikke presenteres som et ekte intervju, tilstedeværelse eller et uredigert opptak av en person. Studio viser at talen er KI-generert; redaksjonen må avtale passende lytterinformasjon før innføring på lufta. Det er ikke lagt inn en oppdiktet påstand om at automatisk lyttermerking allerede finnes.

Lokale navn i uttaleordboken er forslag inntil manuelt godkjent. Rubbestadneset, Mosterhamn, Svortland og Bremnes er registrert uten oppdiktede fonetiske uttaler. Godkjente aliaser brukes bare i TTS-transportteksten; originalmanus og faktakilder beholdes. Hver aktiv endring versjoneres og sperrer tidligere lyd til ny generering og gjennomlytting.

## RR-A03 – to menneskelige godkjenninger

1. Redaktør leser et ferskt kildekontrollert manus og sluttgodkjenner teksten.
2. TTS genererer en privat forhåndslytting. Automatisk lydkontroll må passere godkjent stasjonsprofil.
3. Redaktør lytter gjennom, kontrollerer særlig navn, tall, negasjoner, betoning og unaturlige pauser, og sluttgodkjenner lydfilen.
4. Redaktør legger den godkjente filen i eksisterende sendeliste.

Endret manus, kildegrunnlag, program, aktiv uttaleordbok, stemmegodkjenning eller lydprofil kan gjøre lyden ugyldig. Utløpt kildekontroll og endret fil sperrer også nedlasting av sendefilen. Godkjenning av én variant gjelder ikke andre varianter. Tidligere godkjenning gjenopplives ikke ved å skrive tilbake gammel manusordlyd.

## Fag- og teknikkgrunnlag

Journalistiske primærkilder og deres anvendelse står i [Stylebook 1.0.0](STYLEBOOK-1.0.0.md). [ElevenLabs TTS](https://elevenlabs.io/docs/api-reference/text-to-speech/convert) beskriver stemmevalg, modell og utdataformater. [Uttaleordbøker](https://elevenlabs.io/docs/eleven-api/guides/how-to/text-to-speech/pronunciation-dictionaries) beskriver modellavhengige begrensninger; første implementasjon bruker godkjente lokale tekstaliaser og lover ikke universell fonemstøtte.
