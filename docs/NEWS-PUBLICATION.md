# Nyhetssaker fra Studio til WordPress

## Omfang – 04.10.2026

Dette trinnet kobler eksisterende saksside til nyhetsbildet og kategoriene
på radiorubben.no. Separat nettleverings-PR basert på NRK Vestland-PR #22,
låst til `c96b27af7ab184f2e407fc5b3e2b83b68671ac11`.
Render-, Entra- og Fotballrobot-grenene er uendret.

Flyt: velg RSS-sak → hent originalkilden → lag radiomanus og nettutkast →
separat kilde-/språkkontroll → rediger og kontroller igjen → manuell
sluttgodkjenning. Ingen ny bakgrunnsjobb eller automatisk publisering.

## Bilde og kategorier

Autentisert, lesende WordPress-kontroll 04.10.2026 bekreftet disse objektene:

| Objekt | ID | Bruk |
| --- | --- | --- |
| Radio Rubben – Nyheter | 1079 | Standard illustrasjon for nye nyhetssaker |
| Nyheter (`latest-updates`) | 8 | Nye støttede nyhetssaker |
| Lokale Nyheter (`lokale_nyheter`) | 27 | Kommunesaker som standard; redaktøren kan velge den for andre Bømlo-saker |

Bildet vises før første overføring og merkes i Studio som KI-generert
illustrasjon, ikke bilde av hendelsen. NRK-saker blir ikke automatisk lokale.
Kategorivalget kan endres før innlegget opprettes. Profilen gjelder støttede
kildebaserte nyhetssaker, ikke fotball, vær eller manuelle sendepunkter.

Profilen lagres med nettutkastet; POST-data kan ikke angi vilkårlige bilde- eller
kategori-ID-er. Ved opprettelse sendes `featured_media` og `categories` sammen
med innholdet. Nyhetsprofilen har forrang over generell `category_id`, som
fortsatt gjelder andre typer nettsaker.

Ved oppdatering av eksisterende nyhetsinnlegg utelates bilde og kategorier fra
forespørselen, slik at WordPress-redaktørens valg beholdes. Studio viser da
redigeringslenken og forklarer dette; det hevder ikke å vise fersk fjernmetadata.
Eldre innlegg migreres ikke. Eldre nettsaker uten WordPress-ID må lagres for
å få nyhetsprofilen før overføring. Tidligere godkjenninger må gis på nytt
fordi godkjenningsformatet nå inkluderer kildeattribusjon og profil.

## Godkjenning og gjenbruk

Godkjennings- og leveringshash binder tittel, ingress, brødtekst, kildens navn
og URL og lagret profil. Tekst-/kategoriendring nullstiller godkjenningen.
Utdatert kildekontroll, feil rolle, gammel revisjon og uavklart nettverksresultat
stopper fortsatt publisering. Ingen blind gjentakelse ved timeout.

WordPress-kladden gjenbrukes ved senere godkjenning. Bekreftet publisering får
riktig status; endret tekst krever ny kontroll. Originalkilden lenkes i teksten.
Sakssiden gjenkjenner også eksisterende redigert NRK-sak ved endret feed-ID
eller URL-tittel, via samme kildeidentitet som sendelisten. Manus bevares.

## Kontroll ved første utvikling

Lokalt besto 33 nye nyhets-/publiseringskontroller, 10 eksisterende
publiseringskontroller og åtte sakssidekontroller i PHP-WASM.
JavaScript-simuleringene dekker automatisk klargjøring uten publisering,
avbrudd uten gjentakelse og lagring av kategorivalg mens kontrollene er låst.
CI kjører full eksisterende suite på native PHP 8.2/8.4 samt Composer og pakking.
Eksakt testet kodecommit og CI-resultat dokumenteres på PR-en og i statusfilen.

Syntetiske kilder, falske credentials og simulert WordPress-transport brukes
i testene. Ingen betalte AI-kall, e-poster eller ekte innlegg er brukt som test.
Live RSS/originalhenting, innlogget Studio-UI og faktisk utkastoverføring er
fortsatt egne integrasjonskontroller.

Ved første utviklingsleveranse var løsningen ikke aktivert. Fire runtime-filer: `app/news-publication.php` (ny),
`app/web-publish.php`, `app/case-workflow.php` og `public/case.php`.
Disse og PR #22-avhengighetene må avstemmes mot ferske serverhasher før
selektiv deploy, med privat backup og tilbakeføring etter `AGENTS.md` og
`STUDIO-DEPLOY.md`. Ikke last opp hele grenen/standardpakken over aktivt Studio.
WordPress-tema, RR_News og eksisterende innlegg/mediefiler er uendret.

OneDrive er fortsatt ønsket arkiv. Denne endringen bruker eksisterende privat
Studio-sendeliste. Ingen OneDrive-overføring eller filreferanse er opprettet.

## Aktivering 04.10.2026

Selektivt aktivert sammen med PR #22-avhengighetene, etterfulgt av rettelse for eksisterende CSP. Sakssiden laster nå egne JavaScript-/CSS-filer og en lokal kopi av nyhetsbildet; sikkerhetsheaderne er uendret. Endelig runtime-kilde `f0cbc59f348978daf1532fed93ad8deee8b15979` har grønn full CI. Innlogget klargjøring av radio- og nettutkast er prøvd med faktisk kilde og AI-kontroll.

WordPress-overføring er fortsatt blokkert: Studios tilkoblingskontroll gir HTTP 401 / `incorrect_password`. Ett kladdforsøk står uavklart; det er ikke gjentatt. Ingen publisering. Se [aktiveringsrapport](NEWS-ACTIVATION-20261004.md) for før/etter-hasher, backup, prøvesak, gjenstående steg og tilbakeføring.
