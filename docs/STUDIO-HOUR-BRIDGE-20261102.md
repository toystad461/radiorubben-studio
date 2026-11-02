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
Installert/aktivert status føres etter deploykvittering.
