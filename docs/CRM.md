# Samarbeid / CRM

Første versjon for partnerarbeidet i Radio Rubben. Egen gren fra main
`81c1423c69d596531018e24d5475f5ad84b28b9c` (07.10.2026), uavhengig av
den åpne iPhone-PR-en #47 og de eldre Render-/programgrenene.

**Førende krav:** CRM-et skal tilpasses Radio Rubbens partnerarbeid.
Se [produktkrav og videre leveranser](CRM-RADIO-RUBBEN.md). Denne PR-en
dekker kontaktgrunnlaget; strukturerte avtaler, kampanjer, produksjonsstatus
og fornyelser er ennå ikke implementert.
Thomas sitt forslag om maler, nettsøk og 15–20 sekunders TTS-demo før
førstekontakt er konkretisert i [CRM-OUTREACH.md](CRM-OUTREACH.md).
Kundekontakt og lydproduksjon er fortsatt ikke aktivert i CRM-et.

## Bruk

Administrator åpner **Samarbeid / CRM** i Studio-menyen (`/crm.php`).
Opprett en bedrift, registrer kontaktperson og samarbeidsidé, og sett neste
steg og eventuell oppfølgingsdato. Dagens og tidligere frister samles øverst.
Datoene vurderes etter Europe/Oslo. På vent, avslåtte og arkiverte kort er
unntatt fra oppfølgingslisten, men kan fortsatt finnes via statusfilteret.

Telefon, møte, e-post, melding og internt notat registreres med faktisk dato.
Notatet sender ingen melding og endrer ikke salgsstatus automatisk.
Internnotater teller ikke som kontakt med bedriften. Historikken beholder
tidspunkt, aktør og før/etter-verdier ved endring. Kort kan arkiveres og
gjenåpnes med statusvalget; historikken slettes ikke.

Startlisten kan legges til med en eksplisitt knapp. Den inneholder fem
bedrifter med offentlige opplysninger sjekket i dialogen 07.10.2026:
Kulleseidkanalen Gjestehamn, Bømlo Storsenter, MEKK Bømlo, Bømlo Hotell og
Finnås Kraftlag. Hver rad har sin offentlige kilde i nettsidefeltet.
Tidligere kontakt, interesse og budsjett er uavklart. Ingen oppfølgingsdato,
kontaktperson eller avtale diktes opp. Gjentatt import bevarer eksisterende
kort og legger bare til manglende bedriftsnavn. Bilhuset er ikke i startlisten.
Private e-poster, privattelefoner og tidligere avslag er ikke kodet i Git.

OneDrive-feltet tar en eksisterende HTTPS-lenke til en fil eller mappe.
Det gjør ingen filoverføring, opplasting, deling eller synkronisering.
«Åpne e-post» åpner brukerens e-postprogram; Studio sender ikke e-post.
CRM har ingen kobling til redaksjonell publisering eller sendelisten.

## Tilgang og lagring

- Eksisterende bootstrap, innlogging og `current_user()` brukes. Rollen
  bestemmes fortsatt på serveren. Bare `admin` har lese- og skrivetilgang;
  produsent, programleder og observatør avvises også på direkte URL.
- Alle mutasjoner krever POST og gyldig CSRF. GET oppretter aldri data.
- Privat lagring i `config/crm.private.json`, utenfor dokumentroten, med
  separate låsefiler, filmodus 0600 og atomisk rename. Eksisterende config-
  ekskluderinger holder register, lenker og notater ute av Git og pakkene.
- Endringer og historikk lagres under samme lås. Fersk revisjon kreves ved
  både endring og kontaktlogg, så gamle faner og dobbeltinnsending avvises.
  Feilskjema beholder innskrevet tekst og gammel revisjon.
- Ugyldig JSON feiler lukket. Ukjent skjema overskrives ikke. Validering av
  lengder, datoer, typer, telefon, e-post og URL; escaped visning; ingen
  uthenting av brukerangitte lenker. OneDrive-lenker godtar kjente Microsoft-
  domener, ikke lookalike-domener. Ingen nye avhengigheter.

## Tester og utrulling

`php tests/crm.php` tester lagring, roller, revisjoner, validering, historikk,
oppfølgingsdato, arkiv, duplikater, idempotent startliste og korrupt register.
`php tests/crm-page.php <mode>` tester controller med isolerte testbrukere og
data: GET, liste, nytt kort, gjest, tre avviste roller, metode, CSRF, lagring,
notat, ugyldig skjema, konflikt og ukjent handling. Testene ligger i composer
test og kjøres derfor i eksisterende PHP 8.2/8.4-CI. Mobiljobben tester
liste, kort og nytt skjema på 320, 390, 800 og 1280 piksler.

Lokalt besto 78 domenekontroller og 14 isolerte rutemoduser på PHP 8.5.10
(WASM). GitHub-kjøring 37686489844 besto PHP 8.2/8.4 og mobiljobben,
inkludert CRM-layoutkontrollen, på runtime-commit
`fdc0a38b83f5262f0049a38df07c098e913002ff`. Senere produktkrav er
dokumentasjonsendringer. Fysisk iPhone og ekte Microsoft-innlogging er ikke testet.

Berørte runtime-filer: `app/crm.php`, `app/crm-candidates.php`,
`app/views/sidebar.php`, `public/crm.php`, `public/assets/crm.css`.
Tillegg: tre testfiler, composer-skript og dokumentasjon. Eksisterende bootstrap,
auth, konfigurasjon, redaksjon, deployskript og iPhone-gren er uendret.

Dette er kode og testgrunnlag, ikke produksjonsaktivering. Før autorisert
utrulling: avstem fersk main/produksjonsruntime, kontroller CI på eksakt head,
bruk etablert selektiv deploy med før/etter-hasher og tilbakeføring. Ikke
last opp en fullpakke. Privat CRM-register skal alltid bevares. Se STUDIO-DEPLOY.md.

## Foreslått Microsoft-flyt – ikke implementert eller aktivert

Anbefalt ansvarsdeling:

| System | Ansvar |
| --- | --- |
| Studio | Bedrift, status, kontaktlogg, neste steg og dato |
| Teams | Samlet varsel med lenker tilbake til Studio |
| OneDrive | Tilbud, avtaler, logoer og andre filer |
| OneNote, valgfritt senere | Lengre møtenotater og idéarbeid |

Første integrasjon bør være én samlet oppfølgingsmelding, ikke en melding
for hver lagring. Studio velger forfalte aktive oppfølginger og sender
bedriftsnavn, neste steg og innloggingsbeskyttet kortlenke til en avtalt
Teams-chat/kanal via **Workflows**. En serverjobb må settes opp for tidsstyring;
denne PR-en oppretter ingen jobb. Brukeren velger mottaker, tidspunkt og konto
før integrasjonen aktiveres. Del bare nødvendig innhold i varslet.

Varsling må ha privat konfigurasjon, av som standard, eksplisitt autentisering,
timeout, kvitteringsstatus og kontroll mot doble utsendinger. CRM-lagring skal
fungere selv om Teams er utilgjengelig. Workflows eies av brukerkontoer; sørg
for eierskap/medeiere slik at flyten ikke blir foreldreløs. Ikke anta at en
vellykket Microsoft-innlogging i Studio allerede gir Graph-skrivetilgang.

Microsoft dokumenterer at Teams sine webhook-maler ikke krever premiumlisens.
Faktisk tilgang til Teams/Workflows og andre nødvendige steg må likevel avklares
for Radio Rubbens konto. OneNote Graph krever delegerte tillatelser og støtter
ikke app-only, så toveis automatisk OneNote-synk bør ikke være første leveranse.

Kilder kontrollert 07.10.2026:
- https://support.microsoft.com/en-us/workflows/send-messages-in-teams-using-incoming-webhooks
- https://learn.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/connectors-using
- https://learn.microsoft.com/en-us/graph/api/resources/onenote-api-overview?view=graph-rest-1.0
- https://learn.microsoft.com/en-us/power-platform/admin/power-automate-licensing/faqs
