# Studio – felles status og arbeidsliste

Sist kontrollert: 30.09.2026, Europe/Oslo.
Eier: Thomas Magne Sellevold-Øystad.
Repo: https://github.com/toystad461/radiorubben-studio

## Bruk denne siden
Dette er inngangen til videre Codex-arbeid. GitHub inneholder kode og tekniske
beslutninger, Studio er den daglige arbeidsflaten, og OneDrive er ønsket
innholdsarkiv. Nye samtalebeslutninger føres hit eller i lenket fagdoc.
Samtaler alene er ikke en deploylogg. Hent ferske PR-heads før videre arbeid.

## Kontrollert grunnlag
GitHub lesing og skrivetilgang er tilgjengelig i etableringsøkten.
Main har en eldre README og mangler overlays-kjeden. Produksjon er derfor
ikke lik main. Ingen fersk innlogget Uniweb-kontroll eller serverfilavstemming
er utført i denne økten. Tabellen nedenfor skiller GitHub-observasjon fra
tidligere dokumentert publisering; PR-beskrivelser er ikke dagens serverbevis.

| Område | GitHub / dokumentert status | Neste kontroll |
| --- | --- | --- |
| Læring og programregister, [PR #13](https://github.com/toystad461/radiorubben-studio/pull/13) | Åpen draft, head 7f72fbf7a9e0e33b488c74d8c0251021f9bbffd7. GitHub rapporterte mergeable=false. Tidligere læring selektivt publisert 28.09; senere programregisterarbeid er ikke bevist publisert. | Avstem publiserte filer og undersøk basekonflikt før integrering. |
| Musikkontroll, [PR #14](https://github.com/toystad461/radiorubben-studio/pull/14) | PR-beskrivelsen dokumenterer selektiv publisering 28.09. Virkelig arkivmappe/reserveflyt gjenstår. | Test på studiomaskinen med brukerens arkiv. |
| Sendeforslag, [PR #15](https://github.com/toystad461/radiorubben-studio/pull/15) | Dokumentert som ikke deployet; fast mal og profil v2. | Avstem programregister og meny før senere utrulling. |
| Væroversikt, [PR #16](https://github.com/toystad461/radiorubben-studio/pull/16) | Åpen draft, head 9b19cdfb4d9d6f83b8c5fe831c1cf9ad15123c7e. Beskrivelsen sier ikke publisert. | Bekreft om senere serverendringer finnes; ekte MET/cache og visuell kontroll. |
| Kontrollsenter, [PR #17](https://github.com/toystad461/radiorubben-studio/pull/17) | Åpen draft, head d753535963d9d1dbf20d104a8b23478547f75f36. To filer dokumentert publisert 29.09. | Innlogget PC/iPad/mobil-kontroll og ferske filhasher. |
| Nyhetsmanus, [PR #18](https://github.com/toystad461/radiorubben-studio/pull/18) | Åpen draft. Ny head 4fe4eb110b66752e281e4e23f460bf9a4d72df9d, 25 endrede filer mot base; tidligere head med åtte filer er ikke lenger hele PR-en. Separat review-only-patch finnes. Ingen verifisert deploy. | Avstem eksisterende patch mot aktiv kode; ikke bygg samme leveranse på nytt uten grunn. |
| WordPress / nettpublisering | Nyere samtalearbeid omtaler tilkobling, men denne kartleggingen har ikke bekreftet aktiv kode eller ende-til-ende-flyt. | Undersøk faktisk implementasjon og manuell sluttgodkjenning. |
| OneDrive | Ingen aktiv synk verifisert i denne økten. | Finn eksisterende mapper og ønsket eksportretning før integrasjon. |
| ElevenLabs / radio.co | Bruker prioriterer ElevenLabs for TTS. Aktiv lyd-/playout-integrasjon er ikke verifisert her. | Kartlegg separat etter produksjonsgrunnlaget. |

## Viktigste tekniske funn
PR #18 bygger på #17 → #16 → #15 → #13, med tidligere avhengigheter.
Ikke behandle denne kjeden som én godkjent produksjonspakke.

Gjeldende [NEWS-RELEASE.md](https://github.com/toystad461/radiorubben-studio/blob/4fe4eb110b66752e281e4e23f460bf9a4d72df9d/overlays/uniweb/NEWS-RELEASE.md)
beskriver en review-only-patch for board.php, sending.php og news-script.php.
Programregister og editorial-memory er forutsetninger, ikke inkluderte leveranser.
producer.php / producer_request må avstemmes mot aktiv implementasjon.
Eksakte hasher for aktiv kode mangler. En grønn preflight mot GitHub-referansen
bekrefter ikke kompatibilitet med hele driftsmiljøet.

## Prioritert arbeidsliste
| Prioritet | Oppgave | Ferdig når |
| --- | --- | --- |
| 1 | Etabler en fersk, privat lesekopi av relevant Uniweb-kode og et filkart. | Berørte kodefiler har kilde, tidspunkt, hash og avklart GitHub-motstykke; hemmeligheter og redaksjonelle data er utelatt. |
| 2 | Avstem eksisterende PR #18-patch og programavhengigheter mot denne kopien. | Minimal endring er testet isolert, med tilbakeføringsplan og tydelige gjenstående live-kontroller. |
| 3 | Planlegg konsolidering av selektivt publisert kode og PR-kjeden. | En gjennomgåbar integreringsrekkefølge og base finnes; ingen blind merge eller full deploy. |
| 4 | Samle automatisk klargjøring og manuell sluttgodkjenning i én Studio-flyt. | Kilder, avvik, manus/artikkel og neste handling er samlet; bare eksplisitt godkjenning åpner publisering. |
| 5 | Koble dokumenter til faktiske OneDrive-mapper og bygg ElevenLabs-sporet. | Verifiserte filreferanser, klare feilstatuser og testet eksport/lydproduksjon; playout behandles separat. |

Neste konkrete oppgave: Prioritet 1. Manglende forutsetning er en fersk kodekopi
eller autorisert tilgang til Uniwebs aktive kode; GitHub alene kan ikke bevise
innholdet på serveren. Ikke be brukeren lime inn passord eller private config-filer.

## Beslutninger 30.09.2026
- Codex brukes som fast utviklingsverktøy for Studio.
- Repoet skal bære tekniske regler og status, slik at neste oppgave ikke krever
  leting i gamle samtaler.
- Arbeid utføres som avgrensede endringer; denne etableringen endrer bare
  dokumentasjon og setter ikke produksjon eller integrasjoner i drift.
- Automatisering frem til manuell sluttgodkjenning er ønsket produktretning.
- OneDrive-lenker skal peke til virkelige, identifiserte filer.

## Oppdatering ved hver levering
Noter dato, oppgave/PR og eksakt head, endrede filer, relevante testresultater,
uprøvde integrasjoner, publiseringsbevis eller «ikke publisert», samt neste steg.
Dokumenter serverstatus med filhash og kontrolltidspunkt når tilgjengelig.
Ikke kopier hele logger eller hemmeligheter inn i denne oversikten.
