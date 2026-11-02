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
ekte isolert Liquidsoap/Icecast, kontrollert varighet og to målte 880 Hz-pulser.
Windows-fixtures med atomisk rename/begrenset tempmappe feilet lokalt; de samme
Linux-kontrakttestene bestod på PHP8.2/8.4. Den første nye transporttesten hadde
en deaktivert automatisk timemarkør i den manuelle testkøen; dette ble rettet
før installasjon. Rettet isolert ekte lydtest bestod også direkte på VPS-en.

Rollback: deaktiver framtidige timesignal i jinglebiblioteket; allerede reservert
lyd får fullføres. Gjenopprett kun de eksakte kodefilene fra privat backup.
Bevar signaloriginal, eksisterende bibliotek, kø, journal og øvrige produksjonsdata.
Ikke rull hele grenen over serveren og ikke start lydmotoren på nytt.

## Installert og kontrollert 02.11.2026, 11:36 Europe/Oslo

Sju runtime-filer matcher aac974a etter tre selektive installasjoner:
app/music-hourly.php, app/music-rotation.php, app/music-studio.php,
app/music-playout.php, app/jingles.php, public/jingles.php og
public/assets/jingles.css. Generator og tester er versjonert i samme PR115.

Aktivert 11:32:22. Første timesignal er beregnet til omtrent 12:01:42,
etter musikkinnslaget som starter omtrent 11:58:11. Korte tidskorrigeringer
følger faktisk lydstart. Signalet har stabil forekomst-ID gjennom påfyll.
Det er ennå ikke sendt ved kontrollen 11:36; funksjon i isolert lydkjede,
installasjon, aktiv konfigurasjon og faktisk timekø er verifisert separat.

102 eksisterende biblioteksinnslag og originalhasher er bevart; én ny fil
utvider biblioteket til 103 innslag. Privat kode- og databackup finnes under
/var/lib/rr-vps-deploy/hourly-a4tkcvvq. Mobilrettelsen har backup hourly-q05t_uay,
og rettelsen mot unødvendige kørevisjoner har backup hourly-kdhpswl7.
Motor-PID770 er uendret. Ingen motorrestart. Journalens eksisterende prefix,
120/121 beskyttede filer og ordinær stream er bekreftet bevart.

Innlogget Chrome bekrefter aktivt timesignal og privat avspilling med varighet
1,2 sekunder og uten mediefeil. Desktop og 390 CSS-piksler er kontrollert;
statusmeldingen er flyttet til normal dokumentflyt og dekker ikke knappene.
Ti sekunder av offentlig stream er dekodet uten feil etter aktivering.

Tilbakeføring: bruk «Slå av timesignal» i jinglebiblioteket. Et allerede
klargjort innslag får fullføres. Kjør deretter ved behov rollback.py fra
hourly-kdhpswl7, hourly-q05t_uay og hourly-a4tkcvvq i den rekkefølgen.
Skriptene kontrollerer forventet etter-hash før kode erstattes. Ikke gjenopprett
en gammel kø eller journal over nyere avspillingsdata. Selve kode-rollbacken
er klargjort, men ikke kjørt på den aktive sendingen.

Se [presis installasjons- og aktiveringskvittering](STUDIO-HOURLY-DEPLOY-20261102.json).

Køvisningen er også åpnet innlogget: «12:01:41 · Jingle · Timesignal – dobbel pip».
Kørevisjon 652 var uendret gjennom flere timerkontroller 11:36–11:37;
ny sang og videre køpåfyll fungerer fortsatt uten nettleserstyring.

## Endelig CI

Alle tre workflows på aac974a, som matcher installert runtime, er grønne:

- [PHP, Composer og nettlesertester](https://github.com/toystad461/radiorubben-studio/actions/runs/41430078231): success.
- [Liquidsoap, MP3, dobbel pip og omstart](https://github.com/toystad461/radiorubben-studio/actions/runs/41430078259): success.
- [Release-verifikasjon](https://github.com/toystad461/radiorubben-studio/actions/runs/41430078289): success.

Automatisk Uniweb-deploy er ikke utført. Etterfølgende dokumentasjonscommit
endrer ingen runtime-filer og krever ingen ny kodeinstallasjon.
