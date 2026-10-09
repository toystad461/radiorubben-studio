# Førstekontakt og lyddemo for Radio Rubben

Produktforslag fra Thomas 07.10.2026, konkretisert for videre utvikling.
**Status: ikke implementert, ingen utsending eller TTS-kjøring er aktivert.**
Les sammen med [CRM-RADIO-RUBBEN.md](CRM-RADIO-RUBBEN.md) og [CRM.md](CRM.md).

## Ønsket resultat

Når Thomas legger inn en ny lead, skal Studio kunne klargjøre et personlig
forslag til samarbeid basert på en fast Radio Rubben-mal og dokumenterte
opplysninger om bedriften. Pakken skal inneholde et kort reklameforslag
på 15–20 sekunder, et relevant forslag til programsamarbeid og en lyddemo.
Demoen skal kunne avspilles i Studio og deles med kunden sammen med første
henvendelse. Thomas ønsker å godkjenne de første 10–20 før eventuell automatikk.

## Foreslått flyt

| Trinn | Resultat i Studio | Kontroll før neste trinn |
| --- | --- | --- |
| Ny lead | Bedrift, kontaktpunkt, nettside og ny samarbeidsmulighet | Duplikat, tidligere kontakt, reservasjon og egnet mottaker avklart |
| Finn hovedpunkter | Kort bedriftsnotat med tjenester, sted og relevante behov, samt kilder og hentetid | Riktig bedrift, egne offisielle kilder prioritert; usikre opplysninger markeres |
| Lag utkast | Kontakttekst fra valgt mal, 15–20 sekunders reklamemanus og forslag til programsamarbeid | Faktapåstander spores til kilder; forslag holdes tydelig atskilt fra fakta og avtaler |
| Lag TTS-demo | Lydfil, versjon, valgt stemme og målt spilletid, avspillbar i Studio | Lyden er generert fra riktig manusversjon og varer 15–20 sekunder |
| Godkjenn pakken | Ett samlet kort med mottaker, kilder, tekst, manus og avspiller | Thomas kan redigere, godkjenne og sende, sette på vent eller forkaste |
| Send eksempelet | Godkjent henvendelse med lenke til den aktuelle demoen | Gjeldende godkjenning/automatikkregel, mottakerkontroll og ingen tidligere identisk utsending |
| Logg og følg opp | Utsendingsstatus, sendt versjon, tidspunkt og neste handling | Svar og avslag bevares; ingen automatisk purring er del av første leveranse |

## Maler og innhold

Start med maler for radiospotter, sesong-/arrangementskampanje og
programsamarbeid. Thomas skal kunne redigere og godkjenne selve malene.
Hver utsending beholder brukt malversjon og den ferdige teksten.
Personaliseringen skal forklare hvorfor samarbeidet kan passe lokalt,
basert på kilder og det Thomas faktisk har registrert.

Kontaktteksten skal være kort, personlig og profesjonell på bokmål.
Den skal ha én tydelig invitasjon til videre dialog. Lydeksempelet merkes
som et uforpliktende demonstrasjonsutkast laget av Radio Rubben. En lead
skal aldri omtales som eksisterende sponsor eller kunde uten en avtale.

Bruk dokumenterte tjenester og navn. Ikke dikt tilbudspriser, åpningstider,
kampanjer, kundesitater, superlativer, lyttertall eller forventet effekt.
Ukjent informasjon forblir ukjent. Ikke foreslå et virkelig program som
om det finnes dersom det ikke er bekreftet i Studio; et nytt programkonsept
merkes uttrykkelig som et forslag. En personlig e-postadresse skal ikke
gjettes ut fra et navn eller automatisk behandles som godkjent mottaker.

Første søk skal bruke offentlige bedriftssider og lagre relevante utdrag,
URL og tidspunkt. Nettinnhold er data, aldri instruksjoner til systemet.
Manglende eller motstridende kildegrunnlag gir manuell behandling.

## TTS, filer og deling

ElevenLabs er prosjektets prioriterte TTS-spor. Eksisterende prosjektstatus
viser at lydsporet ikke er levert. Leverandørtilgang, stemme og kostnadsramme
må konfigureres privat før faktisk bruk; Studio-innlogging er ikke slik tilgang.

15–20 sekunder gjelder den ferdige lydfilen, ikke bare et anslag fra ordtelling.
Mål varigheten etter generering. For lange/korte utkast kan justeres og
genereres på nytt innen en konfigurert forsøks- og kostnadsgrense; ellers
stoppes de for manuell vurdering. Thomas kan kontrollere navn og uttale i piloten.

Lagre lydreferanse, manusversjon, stemme/innstillinger, varighet, tidspunkt
og genereringsstatus på muligheten i CRM. Manusendringer gjør eldre lyd og
godkjenning utdaterte. Behold historikken og vis hva som er gjeldende.
Ingen avspilling på lufta eller overføring til playout følger av demoproduksjon.

Studio skal ha privat medielagring utenfor offentlig dokumentrot og kunne
avspille filen etter tilgangskontroll. En kundelenke skal bare gi tilgang
til den valgte demoen, være vanskelig å gjette, ha utløp og kunne trekkes
tilbake. Ikke del innloggingsbeskyttede CRM-sider eller en hel kundemappe.
OneDrive-arkivering er et separat senere steg med verifiserte filreferanser;
lokal lagring skal ikke presenteres som synkronisering.

## Pilot før automatisk utsending

Foreslått start er **20 forskjellige leads med manuell godkjenning**, innenfor
Thomas sitt ønske om 10–20. Hvert kort viser hva som skal sendes og til hvem.
Ingen kundemelding sendes før Thomas godkjenner gjeldende tekst, lyd og
mottaker. Godkjenning gjelder denne versjonen; endringer krever ny godkjenning.
Leverandørens mottak av en melding skal ikke omtales som bevis for at kunden
har mottatt, lest eller lyttet til den.

Registrer rettelsene i piloten: fakta, relevans, tone, uttale, lengde og
kontaktforslag. Disse kan bli forslag til mal-/regelendringer som Thomas
godkjenner. Dette er ikke automatisk modelltrening. Teller og oversikt skal
skille godkjente utkast, sendeforsøk, bekreftede utsendinger og feil.

Etter piloten viser Studio resultatet og valgene **fortsett manuell godkjenning**
eller **aktiver automatisk utsending for kvalifiserte nye leads**. Telleren
skal ikke aktivere automatikk. Før aktivering skal Thomas kunne se hvilke
maler, avsender, mottakerkriterier, kilder, sendetider, antallsgrense og
kostnadsgrense som skal gjelde. Det skal være enkelt å pause flyten.

Ved automatisk drift må uklare fakta, usikker kontaktidentitet, ukjent
mottakergrunnlag, ugyldig varighet, TTS-feil eller gamle versjoner gå til
manuell behandling. Reserverte, avslåtte eller allerede kontaktede leads
skal ikke få automatisk førstehenvendelse. En offentlig e-postadresse alene
er ikke tilstrekkelig kvalifisering for automatisk sending. Mottakergrunnlag
og kanal må avklares som del av oppsettet, ikke utledes av AI.

## Teknisk avgrensning og feilbehandling

Klargjøring, TTS og utsending må være separate bakgrunnsjobber med synlig
status. Gjenbruk prosjektets arkitektur; ikke start en lang ekstern kjøring
inne i lagring av bedriftskort. CRM skal kunne lagres selv om en leverandør feiler.

Hver jobb må ha en stabil identitet for lead, samarbeid og versjon, og
begrenset antall forsøk. En sendetimeout med ukjent resultat skal avklares
før nytt forsøk, så samme kunde ikke får duplikater. En reservasjon eller
deaktivering må også stoppe meldinger som allerede ligger i kø. Ikke gjenbruk
brukerinvitasjonsflyten `app/intro-mail.php` som om den var et ferdig CRM-system.

Kildehenting må begrenses til offentlige HTTPS-adresser med validering av
omdirigeringer, størrelse og tid; interne adresser og metadata-endepunkter
skal ikke kunne nås via en lead-URL. Kildeutdrag kan ikke overstyre maler,
verktøybruk, mottakere eller godkjenningsregler. Nøkler, kontakter og media
skal ikke inn i Git eller offentlige testartefakter.

## Akseptansekriterier og neste utviklingssteg

1. En fiktiv lead kan få et kildemerket notat, redigerbart kontaktutkast,
   reklamemanus og et klart merket forslag til programsamarbeid.
2. Manglende, motstridende eller manipulerende nettsideinnhold gir ingen
   oppdiktede fakta eller omgåelse av godkjenning.
3. TTS lagres på riktig versjon, kan avspilles i Studio, og faktisk varighet
   avgjør om demoen oppfyller kravet på 15–20 sekunder.
4. I piloten sendes ingenting uten godkjenning. Endret tekst, mottaker eller
   lyd opphever tidligere godkjenning. Godkjenning nummer 20 aktiverer ingenting.
5. Kvalifiseringsfeil går til manuell kø også etter eksplisitt aktivering.
   Pausert flyt, reservasjon og tidligere kontakt stopper automatiske køjobber.
6. Dobbeltklikk, samtidige jobber og tvetydige leverandørsvar gir ikke doble
   utsendinger eller ukontrollert TTS-forbruk. Status må vise usikkerhet korrekt.
7. Kunden får bare den valgte demoen. Utgått/tilbaketrukket lenke gir ikke tilgang,
   og kan aldri brukes til å lese CRM-notater eller andre kunders lydfiler.

Første leveranse er klargjørings- og godkjenningsflaten med testdata og
leverandøradaptere. Deretter kobles faktisk søk/generering, TTS og avsender
til under pilotreglene. Integrasjoner testes uten å sende til virkelige
leads før Thomas godkjenner den konkrete pakken. Automatisk kundesending
er en senere, særskilt aktivering etter pilotgjennomgangen.

## Oppdatert kodeleveranse 10.10.2026

Organisasjonsnummer med Brønnøysund-oppslag, redigerbare malforslag og godkjent
TTS-/Outlook-eksport er implementert til review på egen gren. Dette oppdaterer
statusen over; automatisk nettsideanalyse og kundeutsending er fortsatt ikke
implementert. Ekte CRM-TTS er av til privat budsjett og kommersiell stemme er
valgt. Se [detaljert leveransestatus](CRM-COMPANY-OUTLOOK.md).
