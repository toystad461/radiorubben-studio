# L02 – teknisk sluttkontroll 06.11.2026

Arbeidsområder: Teknisk Produksjon, Musikk & Tagging, Kontrollsenter.
Eksisterende #109 og PR #115. Kode: `5b727ff7a518242321ddeaec65320100fcc061bd`.

## Resultat og avgrensning

L02s funksjonskontroll består i testmiljøet. Dette dekker ferdige lydfiler,
inn-/utpunkter, musikkoverganger, jinglegrenser, køredigering, privat lytting
og nå/neste. Mikrofonmiks/ducking tilhører L05. Programmert overgangsfelt og
vokalmarkør er fortsatt veiledende metadata; motoren bruker cue start/end og
rotasjonens valgte crossfade, slik denne kontrollen uttrykkelig tester.
Dette er ikke produksjonsutrulling eller en påstand om subjektiv lyttegodkjenning.
Ny GitHub-CI etter testoppsettrettelsen må kontrolleres før PR ferdigmeldes.

| Kontroll | Belegg | Resultat |
| --- | --- | --- |
| M06 lydtyper | Musikk, stikk, jingle, reklame, lydteppe og programopptak i eksisterende kø; kildefiler gjenbrukes. Merking beholdes i manifest, journal og nå/neste. | Bestått |
| M07 stabil liste/dialog | Eksisterende kø/polling/fokus-regresjoner, tidligere 390/1280-nettlesertest og faktisk innsetting/fjerning uten sideskifte. | Bestått |
| M08 privat preview | Icona Pop dekodet til 36,53 s under aktiv strøm; readyState 4, ingen feil; lukking fjernet lydkilden. | Bestått |
| M09 cue start/end | Lagrede kildebundne cues; fem 10-sekunders innslag; ingen lydmarkører utenfor klippet; originalhash bevart. | Bestått |
| Musikkovergang | Ekte syntetisk MP3 ved 0, 3 og 10 s. 3-s-test måler 2,96/3,00 s PCM-overlapp. | Bestått |
| Begge jinglegrenser | Isolert test måler 0,00 s PCM-overlapp inn/ut. Ett 50-ms analysevindu kan treffe hver hard grense; vedvarende blanding avvises. | Bestått |
| Faktisk strøm | 720,001 s passivt opptak; 720/720 tilkoblet, PID 773; jinglegrenser korrelert mot originaler. | Bestått |
| Stopp/frakoblet | Reelle stopp, Icecast-brudd og frosset prosess i egen loopback-test; kontrollromsprojeksjon rydder nå/progresjon. Aktiv strøm er ikke stoppet. | Bestått isolert |
| Kø under lyd | Tidligere faktisk jingle-innsetting/fjerning og ny programmerket kontrollforekomst; ingen journalstart for testforekomstene. | Bestått |
| Nå/neste/polling | Faktisk ai-studio.php viser pågående låt og neste tre; nye tidsstempler uten refresh; motorjournal avstemt. | Bestått |

Thomas sin bekreftelse av hørbar strøm og 3+1-rotasjon er brukerbelegg.
Målingene over er assistentens separate tekniske kontroll.

## Faktisk opptak

Opptak 17:37:38–17:49:38 UTC / 18:37:38–18:49:38 Europe/Oslo.
MP3 17 281 161 byte, SHA-256
`22171cd90a44a610b642aedac1fddd8a05bd6b1e0a5f3bdb21839a063fbb8c8f`.

«Someone Who Can Dance» slutter ved 350,266125 s i opptaket, «Jingle 3»
starter samme sted og slutter ved 359,093125 s; «Do You» starter ved 359,093 s.
Sammenligning på 8 kHz gir 0 og −0,000125 s mellom cue-grensene, innenfor én
analyseprøve. Kildekorrelasjon 0,990 / 0,962 / 0,987. Dette støtter hardt bytte,
ikke en musikalsk crossfade rundt jinglen. PCM-journalens rammegrenser alene
viste opptil 40 ms tilsynelatende overlapp; det er derfor opptaket også er målt.

Siste syntetiske cue-/typetest: 10,04/10/10/10/10 s i journalen,
2,00/1,96/0/0 s overlapp, ingen utenfor-cue-markører. De to blandede
50-ms-vinduene ligger nøyaktig 10 s fra hverandre, ved jinglegrensene.
Innslagstype reklame er bevart helt til fullført journalhendelse.
Tidlige prøvekjøringer av den nye testen avdekket og rettet testoppsett:
jinglebibliotekfilter, ett analysevindu per grense og en rapporteringsvariabel.
Dette var testfeil; produksjonsmotoren ble ikke endret.

## Selektiv rettelse og installasjon

Påvist avvik: lydteppe/programopptak manglet i typevalget, og eventType ble
strippet fra motorens manifest og kontrollromsprojeksjon. Rettet i samme PR.
Installert 18:12:35 UTC / 19:12:35 Europe/Oslo med førhashvern og private kopier.
Bare sju web-/metadatafiler; ingen tjenestestart, motorfil, sendekø eller config
ble endret av installasjonen. Eldre installerte klokke- og konsollvarianter ble
bevart ved å anvende bare denne rettelsen på faktisk servergrunnlag.
Hele PR-head er derfor **ikke** installert som en samlet release.

Privat backup: `/var/lib/rr-vps-deploy/l02-types-20261106-181235`.
Tilbakeføring ble faktisk prøvd i isolert kopi og alle førhasher gjenopprettet.
Ved behov på test-VPS:

```sh
sudo python3 /var/lib/rr-vps-deploy/l02-types-20261106-181235/rollback-helper.py rollback /var/lib/rr-vps-deploy/l02-types-20261106-181235
```

Hjelperen avviser nyere filendringer; den starter ingen tjenester.
Aktiv tilbakeføring var ikke nødvendig og er ikke kjørt.

| Fil | Installert SHA-256 |
| --- | --- |
| `app/controlroom.php` | `35280adeebc1c0891c7467bf0b3b88f115139fff0baeea5ecfe3368be3d627a1` |
| `app/music-live-queue.php` | `db98f5e1ee1ef65a158907d080d112425e2919a23c6b40e93401a17e32f76b4f` |
| `app/music-playout.php` | `4d5ab804df71c48be93db69cc6563b54e5ffff8f1355e3fdc41d7851a6a9daf7` |
| `public/ai-studio.php` | `942bacf44c7315585bef0461728ff0161da420ef99ad6df20f8c9bfd0106e3a3` |
| `public/music-studio.php` | `508514fb8958f0e0c0669dde8838d5aee71766b9c69f12327bc0437e48c82816` |
| `public/assets/music-studio.mjs` | `e4bf36216e847e3f5ed778c00a2060ffe0c2fd3e8096f6d077631b837f5e91f9` |
| `public/assets/ai-studio/controlroom.mjs` | `676a7896c6664a203540add344218c961c8c7b9003e668464f06128cb4d262e3` |

## Etterkontroll

18:16:10 UTC / 19:16:10 Europe/Oslo: sju installerte hasher verifisert,
alle 130 originalfiler samsvarer med sine lagrede SHA-256. Fire motorfiler
(app/music-liquidsoap.php, scripts/music-liquidsoap.php, liquidsoap-child.py,
radio.liq) er identiske med testet kode. Supervisors PID fortsatt 773.

Faktisk programmerket kontrollforekomst `6e29bf7b03af07d1aeb06b549a748993`
ble lagt sist i køen via nettleseren, vist som Programopptak, og fjernet.
Ingen reservasjon/start/fullføring finnes i journalen for denne forekomsten.
Ingen testlyd ble sendt ut. Kontrollrommet viste Capsize og neste tre,
inkludert Jingle 2, etterpå.

Opptak, kort jingleutdrag, JSON-kvitteringer og skjermbilde ligger i denne
oppgavens lokale output-mappe, ikke i offentlig GitHub eller OneDrive.
Ingen produksjons-, WordPress-, DNS- eller radio.co-endring. PR er ikke merget.
