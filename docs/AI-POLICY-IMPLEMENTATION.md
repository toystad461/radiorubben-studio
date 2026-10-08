# AI-policy – implementeringsstatus for Studio

## Denne PR-en

Base: Studio #55, `feature/ai-journalist-2`,
`1a2cfb85030ee8db1473814d80cb97deb68cf84b`, som igjen bygger på #51.
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
- Testen oppdages automatisk av `tests/run.php` i eksisterende PHP 8.2/8.4-CI.
  #55s tester av separat regelgodkjenning og kildeisolasjon beholdes.

Validering: full lokal PHP 8.4.25-testpakke og pakking besto, inkludert ny
policytest, eksisterende læringstester og deploysperre. JavaScript-saksflyt besto.
Eksisterende OpenID-bibliotek gir PHP 8.4-deprecationmeldinger; ingen nye
avhengigheter er lagt til. PHP 8.2/8.4 og mobiltest kontrolleres også i CI.

Endringer i `app/stylebook.php` må avstemmes mot pågående lydarbeid i #55 før
integrasjon. Ingen ukommitterte filer fra den arbeidskopien er tatt med.
Dette er nye metadata og regresjonssperrer, ikke påstand om ny mediepublisering.

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

GitHub rulesets-lesing ga **403 med krav om GitHub Pro eller offentlig repo** i
alle tre repoene. CI-tester er implementert, men nye obligatoriske merge-sperrer
er ikke konfigurert eller påstått. Ingen eksisterende innstillinger er endret.

## Dokumentert krav, ikke ferdig implementert

- Komplett mediekontrakt for syntetisk tale og AI-bilder, med filhash, korrekt
  merking i hver kanal, godkjenning av ferdig medium og negative publiseringstester.
  Policyfeltet alene dekker ikke dette. Studio har parallelt, ukommittert lydarbeid;
  det er ikke tatt inn eller redigert i denne leveransen.
- Full produksjonsavstemming, ende-til-ende-test mot autentisering/WordPress,
  reelle modellsvar og redaksjonelt review av kvalitet. Mockede modellsvar måler
  sperrer, ikke modellens sannhetsgehalt.
- Publikumsversjonen på «Om oss». Denne PR-en publiserer ingen nettsidetekst.
- Påkrevde GitHub-kontroller må avklares med tilgjengelig abonnement og regler.

## Utrullingsgrense

Ingen merge, deploy, workflow-dispatch, artikkelpublisering, læringsaktivering
eller endring av produksjonsdata. En separat godkjent utrulling må avstemme ferske
serverhasher, eksakt filsett, avhengigheter, backup og tilbakeføring. Ikke last opp
en hel gren. Historiske release-manifester skal ikke omskrives til den nye koden.
