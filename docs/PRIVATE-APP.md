# Privat Radio Rubben-app – første testversjon

Dato: 7. oktober 2026. Base: main `81c1423c69d596531018e24d5475f5ad84b28b9c`.
Dette er en avgrenset videreutvikling av eksisterende PHP-Studio, ikke et nytt
publiseringssystem. Ingen produksjonsutrulling, Entra-endring eller publisering
av innhold er utført. Privatmodus er av som standard.

## Levert i koden

- `/mobil.php`: mobilinngang som leser den eksisterende arbeidslisten og åpner
  samme `/case.php` som Studio bruker. Ingen kopier av saker eller ny godkjenningsmotor.
- Hjem, Saker, Radio og Mer i mobilmenyen; direkte innganger til Radioliste,
  Nyhetsdesk, Studio og Læring. Eksisterende rettighets-, CSRF-, revisjons- og
  kildekontroller beholdes.
- Manifest med standalone-visning og eget PNG-ikon, avledet fra eksisterende
  Radio Rubben-logo. Innloggingens retur til `/` fører til mobilinngangen når
  privatmodus er aktiv. Ingen appbutikk eller administrasjonsprofil.
- Enkel nettstatusmelding. Ingen service worker, frakoblet lagring, offline-kø,
  bakgrunnspublisering eller klientlagring av tilgangsnøkler. Eksisterende
  `Cache-Control: no-store`, sesjonsutløp og CSP beholdes.

## Privat tilgang – av som standard

Etter godkjent test av eierens Microsoft-innlogging kan denne innstillingen
legges i eksisterende `config/local.php` utenfor dokumentroten:

```php
'private_app_enabled' => true,
```

Bruk en boolsk verdi, ikke strengen `"true"`. Manglende nøkkel eller `false`
beholder dagens Studio-tilgang, men gir HTTP 503 på mobilinngang og manifest.
Feil type eller annen innloggingsmodus enn Entra gir HTTP 503 i bootstrap;
ingen tilbakefall til demo, Vipps eller lokal konto.

**Når dette aktiveres, begrenses hele Studio-verten til den eksisterende
Entra-eieren**, ikke bare mobilforsiden. `current_user()` filtrerer bruker etter
ordinær kontroll av sesjon, utløp og kontostatus. Identiteten kontrolleres mot
Studio sin eksisterende `studio_entra_is_admin()`/eier-OID, ikke navn, e-post eller
en klientoppgitt administratorrolle. Lokale administratorkontoer og andre
Entra-brukere får ikke tilgang i privatmodus. Maskin-til-maskin-integrasjoner
får ingen nye rettigheter og bruker fortsatt sine eksisterende mekanismer.

Ingen ny eieridentitet er hardkodet i dette tillegget. Bekreft at den eksisterende
eiermappingen faktisk er Thomas sin ønskede Microsoft-konto før aktivering.
Ikke aktiver på en delt Studio-vert uten å akseptere at andre brukere stenges ute.
Ikonet og manifestet inneholder bare merkevare, ikke private saks- eller brukerdata.

## Testbevis og tydelige grenser

`php tests/private-app.php` består lokalt med 51 kontroller på PHP 8.4.23.
Testene bruker ekte PHP-HTTP-innganger i en isolert kopi av app/public og
kunstige serverlagrede sesjoner. Ingen ekte config, redaksjonsdata eller nøkler
kopieres. Kontroller dekker feil bruker, forfalsket rolle, annen leverandør,
utløpt sesjon, direkte saks-/API-innganger, utlogging/CSRF, uendret arbeidsliste,
manifest, ikon, deaktivert app og ugyldig konfigurasjon.

Lokal layoutkontroll av HTTP-generert HTML med de faktiske stilarkene besto
393×852, 320×568, 852×393 og 1280×800 uten horisontal overflyt eller JavaScript-feil.
HTML ble rendret i Chromium via set_content fordi miljøets nettleserpolicy
blokkerer direkte localhost-navigering. Det er en layoutkontroll, ikke en
Safari-/iPhone-test eller full klikkgjennomgang. Nettstatushendelser er simulert.
Forhåndsvisning bruker tydelig merkede testutkast, ikke faktiske Radio Rubben-saker.

Nye tester er lagt til `composer test`. Eksisterende PHP 8.2/8.4-, mobil- og
pakkekontroller skal også være grønne på PR-head før merge vurderes.
Fersk CI-status føres i PR-en; lokal test erstatter ikke hele testpakken.

## Før aktivering

1. Review og grønn CI på eksakt commit. Main har automatisk selektiv deploy;
   ikke merge denne PR-en som en ren dokumentgodkjenning.
2. Autorisert selektiv utrulling med ferske før/etter-hasher, privat backup og
   tilbakeføring. Ikke bruk en full opplastingspakke over dagens Studio.
3. På skjermet testadresse: bekreft ekte Microsoft-innlogging med riktig eier,
   avvisning av en annen konto, utløpt sesjon og utlogging. Ingen ekte sak godkjennes
   eller publiseres bare for å teste appinnlogging.
4. På iPhone 15 Pro: Safari → Del → Legg til på Hjem-skjerm → Åpne som nettapp.
   Test innlogging fra hjemskjerm, gjenåpning etter hvile, tastatur, større tekst,
   rotering, tilbakeknapp og bevaring av ulagret tekst før redaksjonell bruk.

Innstillingen settes tilbake til `false` for å slå av privatmodus og mobilappen;
det gjenoppretter ordinær Studio-tilgang. Kode tilbakeføres med deploy-backup.
Ingen sak, fil eller bruker skal slettes ved tilbakeføring.

## Ikke levert i denne første versjonen

Pushvarsler, lydopptak, Face ID/passkeys, RRLive-integrasjon, direkte opplasting
og mappevisning fra OneDrive, offlinearbeid og fysisk iPhone-test. OneDrive er
fortsatt ønsket filarkiv; ingen mapper flyttes og ingen lenker eller synkstatus
konstrueres. Eksisterende saksbehandling beholdes; tillegget omskriver ikke hele
mobilredigeringen til én ny skjerm.

Referanser:
- Apple: https://support.apple.com/no-no/guide/iphone/iphea86e5236/ios
- MDN: https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/How_to/Create_a_standalone_app
