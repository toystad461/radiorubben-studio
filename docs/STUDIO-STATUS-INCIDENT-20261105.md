# Statusavvik i Studio og offentlig spiller – 05.11.2026

## 05.11.2026 – klokkeavvik bekreftet; rettelse under kontroll

Thomas sin nettlesermåling ga HTTP 200 på 556 ms, status playing,
PC-tid 2026-11-05T14:01:44.306Z og server-tid 14:01:13Z:
31,306 sekunders forskjell (15:01 norsk tid). Begge API-er leverer gyldige
nå/neste-data. Den eksisterende klientkontrollen avviser dette: Studio ved
5 sekunders forskjell, offentlig spiller ved 30 sekunder. Tester gjenskaper
begge feiltekstene med samme avvik. Målingen fastslår relativt avvik, ikke
hvilken maskin som har korrekt absolutt tid.

Rettelsen binder hvert svar til en tilfeldig forespørsels-ID og bruker
performance.now() for svartid og lokal utløpstid. Serverens kontroll av
hjerteslag og forbindelse beholdes. Gamle eller forsinkede svar avvises,
og svar fra før et fanebytte får ikke fylle visningen etterpå.
Gamle API-kall uten requestId fungerer fortsatt. Private lytterrettigheter
og offentlig feltbegrensning beholdes. Ny klient krever ny serverrute.

Koden er klargjort i #115 og web #111; installasjon og brukeretterkontroll
gjenstår. Ingen lydmotor, sendekø, bibliotek eller WordPress-innhold endres.


Tidligere kontrollgrunnlag følger nedenfor og er historikk der det er avløst.

Arbeidsområde: Plattform / Teknisk Produksjon. Hovedspor: Studio #115/L02.
Berørt avhengighet: radiorubben-web #111. Ingen ny parallell implementering.

## Behov og avgrensning

Bevar avspilling, sendekø, bibliotek, journal og nyere selektive installasjoner.
Finn hvorfor fersk motorstatus ikke vises i Studio eller på nettsiden.
Ikke behandle «Motor ukjent» som en bekreftet tjenestestans. Samlet start/stopp-
og lyttepanel er foreløpig forslag; det er ikke installert eller ferdigmeldt.

## Bekreftet grunnlag

- Kodegrunnlag lest i Studio: 51dd5c49e1e52a18073d23dcddd832f1cdc3d096,
  PR #115 åpen/draft. Web PR #111 head: dfa2a2a723eeb04ea2dde7e7511c7cffee10fba7.
- Thomas sin VPS-avlesning: rr-studio-stream active/running, PID 773,
  Result=success, NRestarts=0, ExecMainStatus=0. Icecast tilsvarende med PID 770.
  rr-studio-rotation.timer active/waiting.
- Heartbeat: engine=liquidsoap, connected=true, position=12.72,
  pid 773, alder 0,4 sekunder. Dette gjelder tidspunktet for avlesningen.
- De siste 18 journalradene viser reserved/started/completed i playing-modus
  med connected=true, fra 13:19 til13:32:04 UTC / 14:19–14:32:04 Europe/Oslo.
  Siste started er «Paris (Radio Edit)» 13:32:02 UTC.
  Feltet lat i avlesningen viser state.current.title; ved completed kan det
  være den nye aktive låten, ikke den forekomsten som nettopp ble fullført.
- Thomas opplyser etterpå: «streamen starter - og går men det oppdateres ikke
  status på sidene som over». Dette er en subjektiv lyttebekreftelse.
- Skjermbilder rundt 14:35 viser ukjent motor, nå/neste og deaktivert lytting
  i Kontrollrom; offentlig spiller viser avspilling og manglende sendestatus.
  Studio viser samtidig at en annen Studio-serverkontroll svarer.
- Autentisert WordPress-lesing 05.11 kl.13:36:05 UTC bekrefter aktivt tema
  Radio Rubben Next. Aktive wp-content/mu-plugins/rr-public-radio.php og .js
  er lest gjennom eksplisitt wp-content-scope, ikke fra WPVibe-utkastet.
  PHP oppgir 1.0.5; JS henter https://5.249.252.78/radio-status.php.
  Kildelesingen er ikke en byteeksakt hashkontroll.

## Felles statusflyt og konkrete feilmuligheter

Studio controlroom-api.php leser music_playout_state(), heartbeat.json og
control.json under samme spillerlås. Offentlig radio-status.php bruker samme
datagrunnlag, med avgrenset offentlig feltliste og separat bildeindeks.

Studio-klienten har 4 sekunders forespørselsgrense og avviser svar hvis
generatedAt avviker mer enn 5 sekunder fra PC-klokken. Serveren krever ferskt,
samsvarende motorhjerteslag under 5 sekunder. Feil under henting, JSON-tolking
eller validering ender i samme «Ukjent»-visning; lytteadressen fjernes også.
Offentlig klient har 6 sekunders forespørselsgrense og 30 sekunders klokkegrense.

Det er dermed ikke mulig å skille tjenestestans, avlesningsfeil, klokkeavvik,
innlogging eller transportfeil fra de viste feiltekstene alene.
Begge serverruter kan dessuten returnere 503 ved feil under lesing.
Hele journalen leses fra begynnelsen under lås; eventuell voksende lesetid
eller ressursbruk må måles før den påstås å være årsak.

## Tilgangsgrense

Assistentøkten har ikke en konfigurert SSH-agent eller direkte VPS-økt.
Et ordinært HTTPS-forsøk med beholdt TLS-validering av offentlig radio-status.php
ble avvist med curl 60: certificate subject name matches ikke 5.249.252.78.
Ingen HTTP-status/body ble mottatt. Dette beskriver assistentmiljøets
forbindelsesforsøk, og fastslår ikke alene sertifikatsituasjonen i Thomas sin
nettleser eller den felles rotårsaken. Sertifikatkontroll er ikke omgått.

## Neste kontroll og ferdigkriterier

1. Hent respons og observert svartid fra /controlroom-api.php og
   /radio-status.php i brukerens eksisterende nettleser. Del bare JSON eller
   feilmelding; ingen cookies, tokens eller full HAR.
2. Ved serverfeil: avstem installerte statusfiler, leserettigheter,
   relevante PHP-feil og lesetid mot kildecommit. Ved gyldig playing-status:
   kontroller klientens klokke, nettverksfeil og JS/cache på den faktiske siden.
3. Rett bare bekreftet avvik i eksisterende spor. Selektiv pakke krever ferske
   målhasher, privat backup, tilbakeføring og kontroll av nyere arbeid.
4. Bekreft automatisk nå/neste-oppdatering i begge sider gjennom minst ett
   faktisk låtbytte, med fortsatt lyd. Kontroller gjenåpning av bakgrunnsfane
   og at en reell avlesningsfeil gir forståelig feil uten oppdiktet status.
5. Fullfør mobil/iPhone-kontroll før samlet L02-aksept.

Ingen runtimefiler, tjenester, køer, innhold eller redaksjonelle godkjenninger
er endret i denne kontrollen. Dokumentasjonsendringen trenger ingen nye
runtime-tester. Årsaken er fortsatt uavklart; ingen feilretting er ferdigmeldt.

