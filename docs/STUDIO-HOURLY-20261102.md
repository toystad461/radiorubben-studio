# Timeskille – 02.11.2026

Arbeidsområder: Teknisk Produksjon, Kontrollsenter, System & Kvalitet.
Viderefører #109/L02 og PR115. Brukeren valgte første naturlige låtskifte etter
hel time. Ingen sang avbrytes. Første aktivering gjelder neste hele time.

Timesignal er en kildebundet jingle i kategorien «Timesignal», utenfor den
vanlige tre-musikk/én-stasjonsjingle-rotasjonen. To 160 ms pip ved 880 Hz,
−18 dBFS og korte inn-/utfadinger genereres lokalt av make-hour-signal.py.
Ingen stemme, eksternt API eller radio.co. Originalfilene endres ikke.

Påfyll bruker målt start på aktiv lyd og verifiserte cue-lengder. Bare framtidige,
ureserverte køinnslag kan flyttes. Motorens aktive/reserverte innslag bevares.
Signalsløyfen bruker UTC-time som identitet, slik at begge klokken 02 ved
vintertid er forskjellige timer. Journalførte signaler spilles ikke på nytt.
Manglende/uklargjort signal gir ordinær musikk videre. Av/på er rollebegrenset,
CSRF-beskyttet og revisjonskontrollert i eksisterende jinglebibliotek.
Hvis motoren allerede har klargjort neste låt ved aktivering eller tidskorrigering,
beholdes denne; signalet kan da komme én overgang senere. Normal aktivering skjer
en time i forkant. Internettavspillerens buffer kan gi ytterligere lytterforsinkelse.

`music_hourly_preview` lager et kildebundet grunnlag med lokal time,
kørevisjon og de tre første planlagte musikkforekomstene etter signalet.
Timestikk og musikkklipp er senere arbeid og merkes ikke klare nå. Før senere
lydproduksjon må køidentitet, filhash, faktisk valgte klipppunkter og lydnivå
valideres på nytt; endret kø må ugyldiggjøre tidligere generert materiale.

Tester: music-hourly.php dekker naturlig avslutning, ingen overlapp, stabile
identiteter, aktive/reserverte innslag, omstart, manglende lyd og begge DST-skifter.
test-studio-hourly-stream.py prøver generert pipelyd mellom hele sanger gjennom
ekte isolert Liquidsoap/Icecast, kontrollert varighet og journalidentitet.
Windows-fixtures med atomisk rename/begrenset tempmappe feiler lokalt; Linux CI
må bestå før installasjon. Installert status og kvittering føres etter faktisk deploy.

Rollback: deaktiver framtidige timesignal i jinglebiblioteket; allerede reservert
lyd får fullføres. Gjenopprett kun de eksakte kodefilene fra privat backup.
Bevar signaloriginal, eksisterende bibliotek, kø, journal og øvrige produksjonsdata.
Ikke rull hele grenen over serveren og ikke start lydmotoren på nytt.
