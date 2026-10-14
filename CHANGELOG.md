# Endringslogg – Radio Rubben Studio

## Under utvikling – nyhetsbilder v1.2

- Semantisk tema og tre verifiserte formatvarianter for nye nyhetsutkast.
- Forhåndsvisning, KI-merking, begrunnelse, manuell overstyring/lås og synlige avvik i dagens godkjenningsflate.
- Bilde og tekst bindes til samme sluttgodkjenning; godkjente originaler kan lastes ned for deling.
- Krever sammenhørende WordPress-utvidelse og kontrollert aktivering; flagget er av som standard.

Se [implementasjon, tester og innføring](docs/NEWS-IMAGES.md). Ikke produksjonsaktivert.

## Under utvikling – NRK Vestland RSS

- NRK Vestland Toppsaker og Siste nytt erstatter nasjonal NRK-feed i Nyhetsdesken.
- Felles kildeoversikt med toppsakmerking, dublettvern på NRK-artikkel-ID og synlig status per feed.
- Gamle sendepunkter gjenkjennes uten å overskrive manus, godkjenning eller historikk.
- Cache skiller en gyldig tom feed fra tilgangsfeil. Ingen automatisk publisering.

Se [RSS-grunnlag og aktiveringsstatus](docs/NRK-VESTLAND-RSS.md). Ikke produksjonsaktivert.

## 0.0.1 – 2026-09-24

Første nummererte webrelease fra det eksisterende PHP-prosjektet.

- Versjon lagres i `VERSION`; releasepakken knyttes til eksakt Git-commit.
- ZIP for Uniweb med adskilte `studio-public` og `studio-private`, samt SHA-256-kontrollsum.
- GitHub-kontroller på PHP 8.2 og 8.4, releaseutkast og separat, kontrollert SSH-publisering.
- Studio-spesifikk mappekontroll, HTTPS-sjekk, prøvekjøring, privat kodebackup og tilbakeføringsforsøk.
- Eksisterende kontrollsenter, venteside og Microsoft Entra-innlogging beholdes.

Dette etablerer releaseflyten, ikke nye AI-, MIDI-, OneDrive- eller Radio.co-funksjoner.
GitHub-utkast, opplastingspakke og publisert nettsted er ulike statuser. Se kjøringsloggen
for faktisk publiseringsstatus; et grønt bygg alene betyr ikke at nettstedet er oppdatert.
