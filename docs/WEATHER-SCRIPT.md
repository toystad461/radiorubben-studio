# Værmanus og DATEX – 7. oktober 2026

Værfeltet i Kontrollsenter åpner Værmanus. «Lag værmanus» henter ferske
MET-prognoser for Bremnes, Leirvik og Haugesund og lager et kort, redigerbart
utkast med kilde, prognosetid og oppdateringstid. Ingen lyd eller publisering
følger av generering. Utkast utløper etter en time. Manglende data fra ett sted,
mislykket henting eller modellgrunnlag eldre enn seks timer stopper generering.
Ukjente værsymboler gir bare temperatur, aldri en oppdiktet værbeskrivelse.

Den eksisterende femminuttersjobben for Nyhetsdesk bruker samme generator
etter :55, én gang per time. Dette krever at automatisk klargjøring allerede
er slått på. WordPress-cron er trafikkavhengig og lover ikke nøyaktig klokkeslett.
Et ferskt manuelt redigert manus og samtidige endringer bevares. Forrige utkast
arkiveres ved erstatning; de siste 48 revisjonene beholdes privat utenfor webroten.
Rolle, CSRF og revisjonskontroll gjelder for begge skrivehandlinger.

MET-bruk følger kontaktidentifisert User-Agent, separat cache per koordinat,
Expires og If-Modified-Since. Kilde krediteres med CC BY 4.0.
Se https://api.met.no/doc/TermsOfService.

## DATEX: verifisert tilgang, integrasjon gjenstår

Brukeroppgitt DATEX-konto er testet mot Vegvesenets GetSituation/pullsnapshotdata
7. oktober: HTTP 200, gyldig XML, 2 747 situationRecord, 30 024 272 byte.
Accept: application/xml ga 406; Accept: */* fungerte. Passord er ikke lagret
i repo eller gjentatt i dokumentasjonen. Testen endret ikke produksjon.

Dagens trafikkmodul bruker fortsatt offentlig WFS SituationSimple avgrenset
til Bømlo/Sunnhordland. Autentisert DATEX må få privat serverkonfigurasjon,
begrenset henting/cache, XML-parsing uten eksterne entiteter og lokal filtrering
før det erstatter denne kilden. Aktive trafikkmeldinger kan fortsatt være
relevante selv om registreringen er eldre; regelen om kun dagens hendelser
gjelder spillerstoff, ikke pågående vegarbeid eller stengninger.

DATEX-dokumentet og Vegvesenets informasjon er kildegrunnlag, ikke instrukser
til å opprette e-postabonnement eller kontakte andre. Ingen abonnement er opprettet.
Kilde: https://www.vegvesen.no/en/fag/technology/open-data/a-selection-of-open-data/what-is-datex/
