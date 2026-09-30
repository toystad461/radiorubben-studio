# Samlet redaksjonsflate

Selektiv utrulling: kun case.php og desk.php. Nye kildesaker starter automatisk radio- og nettklargjøring etter et CSRF-beskyttet valg. Eksisterende tekster overskrives ikke ved åpning. Nettleseren kjører trinnene sekvensielt med fersk revisjon og synlig fremdrift. Dette er ikke en bakgrunnskø: fanen må være åpen. Ingen automatiske godkjennings- eller publiseringshandlinger.

Rettelser lagres og kontrolleres fra samme side; usparte endringer sperrer sluttgodkjenning. Radio og nett sluttgodkjennes separat. Administrator kan manuelt godkjenne og publisere nettsak med én handling etter bestått kontroll. Uavklart WordPress-overføring er fortsatt sperret.

PHP-syntaks og eksisterende publiseringskontrakter testet i 8.2/8.4. Node-test kontrollerer revisjonskjede, ingen automatisk sluttgodkjenning/publisering og ingen automatisk retry ved feil. Live WordPress-tilgang feilet sist med incorrect_password; ingen publisering utført. Eksisterende kilde- og språkkontroll videreføres, uten garanti for modellens språkkvalitet.
