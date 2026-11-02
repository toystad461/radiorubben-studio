# Innsetting mellom køinnslag – 02.11.2026

Arbeidsområder: Sendeplan, Teknisk Produksjon, Kontrollsenter. Eksisterende PR115.

Et plusstegn etter hver køforekomst åpner Stikk, Innlegg, Jingle, Reklame,
Hilsen, Ønske og Sang. Typen merker køforekomsten, ikke originalfilen.
Sang og Ønske velger musikk; de øvrige velger lagret innslagslyd fra jinglebiblioteket.
Ingen tomme plassholdere sendes. Manusarbeid og opplasting åpnes via eksisterende sider.
Første versjon krever aktiv standardrotasjon og fersk motorstatus.

Serveren kontrollerer rettighet, CSRF, køversjon, tilstøtende forekomst-ID-er,
lydhash, teknisk klargjøring og programregler. Ingen automatisk omplassering ved
konflikt. Aktiv/reservert eller operatørlåst lyd kan ikke flyttes. Det er mulig
å sette inn etter en reservert forekomst; den reserverte lyden beholdes.

Testet lokalt: eksakt plassering, sju typer, endret kilde, endret plassering,
vern av aktiv/reservert/låst lyd og uendret rekkefølge på eksisterende innslag.
CI tester dialogen ved390/1280px og spiller en hel syntetisk sang før et innskutt
stikk, med PCM-/MP3-kontroll og uendret bibliotek.

Installert 02.11.2026 kl.14:32:36 Europe/Oslo (13:32:36 UTC), fem filer fra
a0643c8bc10f372e80d87aec305d15ca345cabe3. Privat backup/rollback:
/var/lib/rr-vps-deploy/queue-insert-emcbfwjx. Stream-PID770 uendret,
8 802 389 byte eksisterende journal og111 beskyttede filer uendret.
Faktisk VPS-dialog kontrollert desktop og390px; ingen ekstra innslag sendt.
Isolert full lydtest på VPS: første sang26sek, innsatt stikk på valgt plass,
0,0sekunders PCM-gap og fjernet forekomst aldri startet. GitHub PHP41453001002,
release41453001157 og kø-/UI-deljobbene i41453001338 er grønne. Sistnevntes
lydjobb ventet på apt-installering ved deploy; ikke rapportert som full grønn CI.
