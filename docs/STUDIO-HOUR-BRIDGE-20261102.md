# Kort timeinnslag med tre musikkglimt – 02.11.2026

Arbeidsområder: Sendeplan, Musikk & Tagging, Teknisk Produksjon. PR115.
Brukeren valgte å fylle korte luker før hel time med et timeinnslag.

Ved18–45sekunder igjen ved et naturlig låtskifte brukes tre like lange klipp
fra de neste tre faktiske musikkforekomstene. Hele sangene beholdes etter
pipet. Klippene bruker registrert vokalstart eller et beregnet punkt i sangen;
de er ikke redaksjonelt valgte refrenger. Dette er musikkglimt uten TTS.

Klargjøring skjer lokalt tidligst fem minutter før timen og minst30sekunder
før planlagt innslag. Hele filer kontrolleres mot kildehash, klippene fades,
nivåtilpasses og dekodes i sin helhet før bibliotekregistrering. Ingen lyd
lastes opp til en ekstern tjeneste. Køen låses ikke mens lyd rendres.

En ferdig fil gjelder bare de tre kildeversjonene, køforekomstene, timen og
varigheten den ble laget for. Ved endring, manglende lyd, for kort/lang luke
eller full lagringskvote fortsetter vanlig musikk og pipet flyttes til et
naturlig skifte. Aktiv/reservert lyd avbrytes aldri. Maks to nye montasjer per
time ved endret kø. Eksisterende prøvebibliotekgrense200 filer/2GiB beholdes;
ved fullt bibliotek viser Studio at hele sanger fortsetter. Ingen arkivfiler
eller historikk slettes automatisk. Kapasiteten må utvides før varig stor drift.

Tester: planlagt timegrense, uendret hel musikkrekkefølge, kilde-/forekomstbytte,
JSON-roundtrip, av/på og varighetsvindu. Faktisk21sekunders lydfil med tre
syntetiske frekvenser i rett rekkefølge, avvist kildeendring, bibliotekimport
og gjentatt klargjøring uten duplikat er kontrollert isolert på VPS.
Faktisk isolert Liquidsoap/Icecast-avspilling av den importerte montasjen:
18sek hel sang,21sek montage,18sek hel sang; begge PCM-gap0,0sek. MP3 dekodet.

Installert02.11.2026 kl.14:38:27 Europe/Oslo fra
b65ae91b47eeb9becc11d59a174fe484d9235928: ni web-/rotasjonsfiler.
Aktivert14:38:28, kørevisjon769. Privat backup:
/var/lib/rr-vps-deploy/hour-bridge-ux_e0yc9. Stream-PID770 uendret,
8 864 471 byte journal og111 beskyttede filer bevart. Stream, Icecast, AI-relé
og rotasjonstimer aktive; rotasjonstjenesten rapporterte success/0.
Spillerens separate app/music-playout.php er eldre; forskjellen gjelder kun
publiseringsfunksjonen som ikke brukes av spilleren. Den ble ikke overskrevet.

Faktisk Studio viser «Timeinnslag: venter på en luke på18–45sekunder» og
«Motor: svarer · Icecast: tilkoblet». Ingen kvalifisert luke var tilgjengelig
ved verifikasjonen. Automatisk avvikling på den aktive streamen er derfor
ikke bekreftet; lydkjeden er verifisert med syntetisk lyd i isolert test.

Tilbakeføring: kjør activate-hour.php disable som rr-studio, kjør
scripts/music-rotation.php for å fjerne framtidige automatiske montasjer, og
bruk deretter privat backups rollback.py. Aktiv lyd skal fullføre først.
Bibliotekets genererte filer og journal skal ikke slettes ved kodetilbakeføring.

CI41454428947: køkontrakterPHP8.2/8.4 og mobil/desktop success; lydjobb ventet
på testverktøyinstallasjon. Release41454428743 success. Ikke full grønn CI
påstått mens resterende jobber kjører. Se kvittering vedlagt i samme mappe.
