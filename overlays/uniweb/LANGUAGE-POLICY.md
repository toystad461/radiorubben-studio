# Bokmål og språkvask i Studio

Radio Rubbens genererte nyhets- og trafikkmanus skal være på bokmål. Nynorsk i kilden er korrekt målform, ikke en skrivefeil. Rett sikre språkfeil ved omskriving, men bevar navn, tall, datoer, mening og forbehold. Ikke gjett ved tvetydighet. Originaltekst og kildebelegg lagres urørt.

Nyhetskontrollen vurderer også rettskriving, grammatikk og målform. Merknader hindrer godkjent AI-kontroll. Ny policy krever ny kontroll av gamle manus. AI-språkkontroll er ikke en garanti.

Læring bruker eksisterende Robåt – læring: rett manus, kildekontroller og godkjenn det, foreslå en generell språkregel via «Lær av rettelsen», og godkjenn regelen som administrator. Bare godkjente regler brukes i fremtidige manus for samme program. Ikke lær nye fakta eller navnerettelser automatisk fra RSS. Direkte språkregler kan også foreslås uten manus. Ingen modelltrening eller automatisk import av kildenes skrivefeil.

Validering: 49 nyhetskontroller, sidekontroller og eksisterende manus/læringstester passerer på PHP WASM 8.2/8.4. Ekte modelltest gjenstår. Endringen er foreløpig ikke publisert: nettleserøkten utløp 30.09.2026. Selektiv utrulling gjelder news-script.php og promptendringen i aktiv story-script.php; ikke last opp hele grenen. Ingen endring i WordPress-generator eller RSS-originalene.
