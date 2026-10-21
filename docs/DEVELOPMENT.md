# Studio – startpunkt for utvikling

Kontrollgrunnlag 21.10.2026: main `96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60`.
Dette er et datert kildebilde. Hent fersk GitHub-status før hver oppgave.
Arbeidsområde: Teknisk Produksjon / IT & Utstyr / System & Kvalitet.

## Hva dette repoet eier

Studio er en PHP/Composer-applikasjon med nettleser-JavaScript. Node brukes også
til tester; Node på VPS betyr ikke at Studio er skrevet om til Node.
`app/` har serverlogikk, `public/` er webrot, `config/example.php` dokumenterer
innstillinger. `config/local.php`, media, brukere og driftsdata er private.
`snapshots/` og daterte kvitteringer er historiske bevis, ikke aktiv kode.
WordPress har eget repo. Det nye playout-løpet skal kunne fungere uten radio.co.

## Kilde, miljø og driftsbevis

| Område | Kontrollert grunnlag | Betydning |
| --- | --- | --- |
| main | `96ff332d` | Kodegrunnlag; ikke full kopi av dagens servere |
| Uniweb-produksjon | `studio-release.yml`, `STUDIO-DEPLOY.md` | Selektiv deploy kan utløses ved main-push; aktive variabler er ikke lest i denne kontrollen |
| VPS-test | `deploy/vps-test` ved `3eefe528` | Separat gateway og miljø `studio-vps-test`; ikke produksjon |
| Siste leste VPS-kvittering | [Actions 39642309764](https://github.com/toystad461/radiorubben-studio/actions/runs/39642309764) | `deployed`, åtte filer fra `5d995a78` / PR #106; ingen fersk serverlesing i audit |

VPS-workflowen finnes på deploygrenen og i PR #105, ikke på kontrollert main.
Workflow-commit og kildecommit er forskjellige: filmanifestet peker på den
reviewede kildeversjonen. Kvitteringen dokumenterer bare det selektive filsettet.
PR #106 har en feilet full mobiljobb (`fixture server starts`) på samme head.
VPS-kjøringen tester PHP og syntaks, men ikke denne mobiljobben. Utrulling er
derfor ikke bevis på at alle produktkontroller har bestått.

## Før en kodeendring

1. Les AGENTS.md, fersk main/head, relevante PR-er og aktuelle områdedokumenter.
2. Skriv ett mål og et observerbart ferdigkriterium i oppgaven/PR-en.
3. Velg main for uavhengig arbeid. Bruk annen base bare ved dokumentert avhengighet.
4. Ikke bland ny funksjon med avstemming av eldre produksjonsavvik.
5. Bevar andre arbeidskopier, private data og historiske manifest. Ingen force-push.

Aktive kjeder ved kontrollen: #98 → #99 (kø/avvikling), #103 → #104 og #106
(analyse/cover), #105 (VPS-deploy). #96/#97 gjelder testmiljø/WordPress-mål.
Gamle #13/#15/#16/#21 må avstemmes før integrasjon, ikke massemerges.

## Kontroller

Kommandoene kjøres fra repoets rot på en utviklingsmaskin eller isolert CI-runner,
aldri som uannonsert aktivitet på produksjons-VPS. På Windows brukes Bash/WSL
for shellskriptene; på Mac brukes terminal med PHP/Composer og Node installert.
CI er den versjonerte referansen for nødvendige verktøy og PHP 8.2/8.4.

```sh
composer install --no-dev --prefer-dist --no-interaction
composer validate --strict
composer audit
composer test
bash tests/test-javascript-check.sh
bash scripts/check-javascript.sh
node tests/recovered/test-case-flow.mjs
python3 tests/test-audio-key.py
bash tests/test-deploy-block.sh
```

PHP/Composer er nødvendig for full funksjonskontroll. Rapporter manglende runtime
som ikke kjørt, aldri som bestått. UI-endringer trenger relevante Playwright-tester
fra `.github/workflows/php.yml`, inklusive faktisk musikkbibliotek ved musikkendringer.
Bruk syntetiske data; ikke start ekte AI-kall, lydproduksjon eller kundesending som test.

`php.yml` og `studio-release.yml` bruker begge jobbnavnene `verify (8.2)`,
`verify (8.4)` og `mobile`. Kontroller workflow og jobb-ID, ikke bare navnet.
Navnene bør senere skilles sammen med en koordinert oppdatering av ruleset
24734566. Ingen beskyttelsesregel endres i denne dokumentasjons-/CI-rettelsen.

## Gjeldende produktavklaringer

- Nyhetsartikler: kildebelegg og manuell redaksjonell sluttgodkjenning.
- TTS/sendekø: mål om automatisk produksjon og kølegging uten obligatorisk
  godkjenning av hvert innslag, avklart 19.10.2026. Historisk policy v1.0 og
  implementasjonsnotater beskriver dagens eldre sperrer; de er ikke et permanent
  produktkrav. Dokumentasjonsendringen aktiverer ingen automatikk.
- Kølagt, lydprodusert og faktisk avspilt er separate statuser. Ingen falsk sendt-status.
- OneDrive er ønsket dokument-/mediearkiv; kode og teknisk versjonshistorikk er i GitHub.
- ElevenLabs er prioritert TTS-leverandør. Les gjeldende offisiell dokumentasjon
  for den faktisk konfigurerte modellen ved implementasjon.

## Levering og kontekst mellom verktøy

Chat/Work avklarer oppgaven og redaksjonelle krav. Codex arbeider i denne koden.
Overlever PR/issue, repo, base/head, målmiljø, ferdigkriterier og kjente avvik.
Ikke anta at ChatGPT-prosjektinstruksjoner eller andre samtaler er lest av Codex.

En leveringskvittering skal skille mellom endret, testet, merget, utrullet og
etterkontrollert. Bevar allerede gitt autorisasjon innenfor samme leveranse.
Main/deploygren kan utløse skriving: avstem mål, filsett og faktisk deployatferd
før integrasjon. Utrulling trenger backup, før/etter-hasher og tilbakeføringsplan.

`STUDIO-STATUS.md` er historisk journal. Nye oppgaver og fersk CI hører hjemme i
GitHub; oppdater dette startpunktet når arbeidsmåte eller miljø faktisk endres.
