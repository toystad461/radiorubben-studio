# AI-policy – implementeringsstatus for Studio

## Denne PR-en

Base: Studio #55, `feature/ai-journalist-2`,
`6b6b768` (RR Audio), som igjen bygger på #51. Første kartlegging brukte
`1a2cfb85030ee8db1473814d80cb97deb68cf84b`; nyere base ble tatt inn uten force-push.
PR #55 gir allerede kildeproveniens, KI-merking og kontrollert læring; denne PR-en
utvider dette avgrenset, uten en konkurrerende lærings-/godkjenningsmotor.

Implementert:
- Felles `AI-POLICY.md` v1.0 og tillegg til eksisterende `AGENTS.md`.
- Ny generering registrerer `aiPolicyVersion=1.0.0` for både radio og nett.
- Nettgodkjenningen bindes til hele genereringsposten, inkludert modell,
  kildehash/-URL/-tidspunkt og AI-status. Endring eller fjerning krever ny godkjenning.
  Tidligere leveringshasher uten policyfelt er uendret; ingen historikk migreres.
- Ny integrasjonstest bruker den faktiske `studio_web_publish`-grensen og en
  injisert WordPress-transport: AI-kontroll alene, feil rolle, endret tekst,
  kilde eller proveniens og utløpt kontroll gir null transportkall. Gyldig
  menneskelig godkjenning leverer kildeattribusjon og synlig KI-merking én gang.
- TTS-posten registrerer policyversjon og eksplisitt syntetisk opphav. Lydens
  eksisterende godkjennings-/køgrense avviser manglende eller endrede verdier.
  Målrettede tester utvider #55s eksisterende lydkjede; ingen ny lydmotor bygges.
- Testen oppdages automatisk av `tests/run.php` i eksisterende PHP 8.2/8.4-CI.
  #55s tester av separat regelgodkjenning og kildeisolasjon beholdes.

Validering: full lokal PHP 8.4.25-testpakke og pakking besto, inkludert ny
policytest, eksisterende læringstester og deploysperre. JavaScript-saksflyt besto.
Eksisterende OpenID-bibliotek gir PHP 8.4-deprecationmeldinger; ingen nye
avhengigheter er lagt til. PHP 8.2/8.4 og mobiltest kontrolleres også i CI.

RR Audio ble kommittert i #55 mens denne oppgaven pågikk. Den nye basecommiten
er tatt inn; statusnotatene er bevart og Stylebook 1.1.0 beholdt. Diffen mot #55
inneholder bare policytilleggene, ikke den andre oppgavens lydimplementasjon.
Ingen ukommitterte filer fra den arbeidskopien er tatt med. TTS er fortsatt av.

## Kartlagt før endring – 2026-10-08

Egne kloner og grenen `policy/ai-v1` brukes fordi prosjektmappen ikke er et Git-repo
(managed-worktree svarte «Not a git repository»). Andre grener og lokale endringer
ble ikke skrevet til. Åpne PR-er og alle fjernreferanser ble lest fra GitHub.

| Repo | main ved kartlegging | Åpne PR-er | Relevant arbeid |
| --- | --- | --- | --- |
| Robot | `e9fcdab676c58fbf4bd017e1a1beace64011e197` | 2 | #1 import; #3 språk-/kvalitetskontroll |
| Studio | `1aff7adeb2b90c9576265c70f06bab141a1519a0` | 11 | #51 læring → #55 AI-journalist; #53 direkte regler overlapper; #52 produksjonsavstemming; #47/#48 app/CRM; #21 Render-test |
| Web | `9006e82e1ebc91e2a128cf2d3e263470e16db8e0` | 30 | #63 historisk 0.10.4 → #30 0.10.5; #67 avstemming → #68–71 nettside/quiz; #72 blokktema; #29–49 historisk fotballkjede |

Studio bruker PHP på Uniweb. Siste dokumenterte deploykjøring
[37739713565](https://github.com/toystad461/radiorubben-studio/actions/runs/37739713565)
ble lest på nytt: success, main `1aff7ad`, 2026-10-08 06:50 UTC.
PR #55 dokumenterer kontroll av 491 faktiske filer kl. 14:36 UTC og ni bevarte
kilde/runtime-forskjeller. Denne oppgaven har ikke gjentatt hele serverhashkontrollen.
`studio-release.yml` kan deploye fra main. Render-PR-en er ikke produksjonsbevis.

WordPress ble lest via autentisert GET `/wp/v2/plugins`: Fotballrobot **0.10.5**
er aktiv. Spillerwidget er 1.3.0 og Site Functions 1.0.0-rc.4. Dette bekrefter
versjoner/aktiv status, ikke byteidentitet av alle filer. Derfor brukes Web #30
som fotballbase, ikke #67s eldre 0.10.4-kandidat. TypeScript-Robot er ikke påvist
som aktiv produksjonsjournalist; Studio har sin egen RSS-flyt.

Ved første kartlegging ga GitHub rulesets-lesing **403 med krav om GitHub Pro
eller offentlig repo**. Etter brukerens uttrykkelige bestilling ble alle tre
repoene gjort offentlige. Blokkeringen er løst, og følgende regler er lest tilbake
som aktive på `refs/heads/main`:

| Repo | Påkrevde kontroller | Ruleset |
| --- | --- | --- |
| Robot | `test` | 24734546 |
| Studio | `verify (8.2)`, `verify (8.4)`, `mobile` | 24734566 |
| Web | `kontroller` | 24734571 |

Alle tre krever PR og avklarte review-tråder. Ingen bypass-aktører er lagt inn.
Det kreves ikke en ekstra godkjenner; eieren kan selv behandle egne PR-er.
Reglene gjelder hovedgrenen, ikke eksisterende utviklingsgrener. Web-kontrollen
`kontroller` er den generelle test-/byggejobben; Fotballrobotens målrettede
PHP-kontroll kjører separat og er ikke påkrevd globalt fordi den har stiavgrensning.
Denne leveransens målrettede kontroller må derfor også være grønne før integrasjon.
Grønne GitHub-kontroller gir fortsatt ingen tillatelse til produksjonsdeploy.

## Dokumentert krav, ikke ferdig implementert

- Komplett mediekontrakt for syntetisk tale og AI-bilder, med filhash, korrekt
  merking i hver kanal, godkjenning av ferdig medium og negative publiseringstester.
  Policyfeltet alene dekker ikke dette. #55s lydarbeid er nå base og bevarer separat
  manus-/lydgodkjenning. Hørbar lytterinformasjon og AI-bildeflyten gjenstår.
- Full produksjonsavstemming, ende-til-ende-test mot autentisering/WordPress,
  reelle modellsvar og redaksjonelt review av kvalitet. Mockede modellsvar måler
  sperrer, ikke modellens sannhetsgehalt.
- Publikumsversjonen på «Om oss». Denne PR-en publiserer ingen nettsidetekst.

## Utrullingsgrense

Ingen merge til hovedgren, deploy, workflow-dispatch, artikkelpublisering, læringsaktivering
eller endring av produksjonsdata. En separat godkjent utrulling må avstemme ferske
serverhasher, eksakt filsett, avhengigheter, backup og tilbakeføring. Ikke last opp
en hel gren. Historiske release-manifester skal ikke omskrives til den nye koden.

## Hørbar merking – utrullet 08.10.2026

RR Audio legger nå «Denne stemmen er KI-generert.» først i hver TTS-forespørsel,
etter at uttaleregler er brukt på nyhetsmanuset. Merkingen inngår i tegnbudsjett
og ferdig varighet, og kan ikke fjernes via manus eller uttaleordbok.
Versjon, tekst og hash av faktisk talestreng bindes til manus-, lyd- og
sendegodkjenning. Sluttgodkjenning krever egen bekreftelse på at opplysningen
høres i den behandlede lyden. Gamle lydfiler uten denne bindingen må genereres
og godkjennes på nytt. Det er ingen automatisk ny belastning eller migrering.

Tester bruker syntetisk PCM og kontrollerer faktisk leverandørpayload, budsjett,
manipulert/manglende metadata, lyttebekreftelse og tilgang til sendefilen.
Eksisterende testmodus forblir sperret mot sending. Dette aktiverer ingen
lydarbeider eller radio.co-integrasjon. Kodecommit `28df44f035cfad383e3cf15ade182ad2ba0bfd94`
er utrullet etter eksplisitt godkjenning. Fem filer ble oppdatert med privat
sikkerhetskopi; HTTPS-innlogging, offentlig versjonsmarkør og talestreng bestod
kontroll. Alle 501 registrerte filer er avstemt uten drift. Menneskelig høreprøve
med ekte TTS og faktisk utsending gjenstår.
