# Cue-punkter i editor og kontrollrom

Sak RR-CUE-DISPLAY-20261106. Behov presisert av Thomas 06.11.2026:
cue-punktene skal vises i sangeditoren og grafisk i kontrollrommets avspiller.
TTS over intro, ferdig før vokal, er sekundært og ikke del av denne leveransen.

Base: PR #115, `8ad018f364e3af0736509e8f214db2e10f5401fe`, ny avgrenset
gren `feat/cue-tts-intro`. Denne basen inneholder den aktive VPS-flyten.
Den er ikke identisk med hele den selektivt installerte v0.2.0-utgaven.

## Endring

- En nyere, filbundet vokalanalyse vises fremfor eldre start/slutt fra
  stillhetsanalysen. Manuelle cue-punkter, også manuell ukjent vokal, beholdes.
  Visning godkjenner eller skriver ikke om lagrede punkter.
- Editor får direkte start av eksisterende analysekø. Køen finnes også i
  detaljvisningen. Når den aktuelle analysen fullføres, hentes punktene på nytt;
  pågående lytting og ulagrede rettelser hindrer automatisk sideoppdatering.
- Kontrollrommet viser samme punktgrunnlag: start, vokalstart, overgang, slutt,
  intro-/outroområder, tilgjengelig lydbølge og målt avspillingsposisjon.
  Analyseforslag merkes som ikke godkjent. Ukjente punkter navngis som ukjente.
- Motorens posisjon er relativ til det avspilte utsnittet. Markøren bruker
  faktisk cue-in + målt posisjon på originalens tidslinje. Faktisk
  avspillingsvindu vises separat fra senere redigerte biblioteksmarkører.
- Bibliotekdata bindes til motorens trackId og SHA256. Ingen private filbaner
  eller hasher returneres i grafikkdata. Biblioteklås tas etter at spillerlåsen
  er sluppet. Feil i biblioteklesing fjerner cue-visningen, ikke motorstatus.
- Stopp, frakobling, foreldet status og ny fil uten punkter fjerner gammel grafikk.

## Kontroller og ferdigkriterier

Lokalt bestått: PHP cue-display, controlroom, controlroom-api og
controlroom-audio-types; eksisterende JS-controlroom; cue-lagring og analysekø
med rolle/CSRF/revisjon/filversjon og bevarte manuelle rettelser.
Isolert faktisk nettleser med syntetiske data: 390/1280 px, grafikk/markørens
pixelposisjon, synlig vokalstart i editor, ukjent vokal, stopp/låtbytte og
ulagret redigering under analysefullføring. Ingen lyd eller produksjonsdata
brukes i denne grafikkprøven. Nye tester inngår i PHP-/nettleser-CI.

Ferdig i drift krever selektiv installasjon, kontroll av faktisk filsett,
privat backup/rollback og etterkontroll med en ekte låt med kjente punkter.
Dette er ikke utført eller autorisert av selve utviklingsbestillingen.

## Avgrensning

Eksisterende serveranalyse finner bare stillhet ved start/slutt. Den gjenbrukte
YAMNet-analysen finner vokalforslag mens Studio er åpent. Den er et estimat,
ikke garanti for riktig sangstart, og kan fortsatt returnere ukjent. Denne
endringen senker ingen terskler og dikter ikke opp punkter. En permanent
serverbasert vokalanalyse og validering på representative ekte låter gjenstår.
Ingen TTS-generering, stemmemiks, køendring, lydmotorendring eller sending.
Ingen merge eller produksjonsinstallasjon er utført.

Neste anbefaling: kontroller utviklingsgrenen og installer avgrenset etter
konkret utrullingsgodkjenning; sammenlign funnet vokalstart med gjennomlytting
av faktiske låter før videre TTS-arbeid.
