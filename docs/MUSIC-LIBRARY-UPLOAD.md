# Privat musikkbibliotek – opplasting v1, 13.10.2026

Behov: Last opp prøvepakken med MP3 til Radio Rubbens eget nettbibliotek og
se hvilke originaler som er lagret. Base er utrullet main e8cdf532 (PR78).

## Bruk og avgrensning

Etter autorisert utrulling åpnes Musikkbibliotek i Studio-menyen, eller
`/music-library.php`. Administrator og produsent kan velge én til ti filer.
Programleder/observatør kan lese biblioteket, men ikke laste opp. Gjester
sendes til innlogging. CSRF kontrolleres før filbehandling. Faktiske
`multipart/form-data`-opplastinger kreves; vilkårlige filbaner kan ikke brukes.

Originalene lagres uten endring i `studio-private/media/music/originals/`.
Register og lås ligger i `studio-private/media/music/`. Mappene opprettes ved
første gyldige opplasting, ikke ved lesing eller kodeutrulling. Filene får
tilfeldig låt-ID og privat filnavn, med SHA256, størrelse, tidspunkt og aktør
i registeret. Ingen offentlig URL eller nedlasting er innført.

V1-grenser er 20 MiB per fil, 64 MiB per innsending, ti filer per innsending
og 100 filer / 512 MiB i prøvebiblioteket. Dette er produktgrenser, ikke
bevis på webhotellets lagringskvote. Siden viser de faktiske PHP-webgrensene;
lavere server/proxy-grenser kan hindre innsending. Start med færre filer.
`fileinfo` kreves; opplasting er deaktivert hvis den mangler.

MP3 kontrolleres med MIME og tre sammenhengende MPEG Layer III-rammer etter
eventuell ID3-header. Dette er begrenset formatkontroll, ikke full dekoding,
virusgaranti eller kvalitetskontroll. Friformat-MP3 og filer med uvanlige
data før lydrammene kan bli avvist. Originalene skal ikke kopieres manuelt
inn i lageret: filer uten registeroppføring sperrer videre opplasting.
Identiske filer gir samme låt-ID og ingen ny original eller registerskriving.

Registeret skrives atomisk under eksklusiv lås. Ugyldig register beholdes
og sperrer skriving. Filfeil rydder bare den nye originalen og midlertidige
registerfilen. Etter avbrudd kan en uregistrert original kreve separat
avstemming; systemet sletter ikke historiske filer eller gjetter koblingen.
Manglende original vises i listen og sperrer ny opplasting.

## Ikke levert i v1

Artist/tittel fra ID3, varighet, nettoppslag, automatisk tagging, programvalg,
forhåndslytting og køkobling er neste trinn. Filnavn er bare originalreferanse.
Status er venter på klargjøring; ingen robotjobb startes ennå. Ingen ny
programprofil, leverandør, VPS eller FFmpeg-avhengighet er opprettet.
Ingen lyd, innholdspublisering eller redaksjonell godkjenning skjer ved upload.

## Kontroller og ferdigkriterier

PHP 8.2/8.4: roller/CSRF, ekte opplastingskrav, traversering, forkledd PHP,
delvis upload, størrelsesavvik, originalbevaring, deduplisering, ID3-prefiks,
flyttefeil, uregistrert fil, skadet register og manglende original.
Nettleser med ekte PHP og multipart: gyldig/ugyldig fil i samme innsending,
originalens byteinnhold, duplikat, offentlig 404, observer-POST og CSRF-POST.
Syntetisk visning på 320/390/768/1280 uten horisontal overflyt eller JS-feil.

Brukerens ti originalfiler behandles først når funksjonen er utrullet og
brukeren laster dem opp. Ingen produksjonsmappe eller ekte musikkfil er
opprettet under utvikling/test. Kodebackup og etterkontroll følger konkret
merge-/utrullingsautorisasjon; biblioteksdata skal aldri inkluderes i kodepakken.
