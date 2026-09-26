# Desken – første programmerte versjon

Ny side: `/desk.php`. Bruker eksisterende innlogging og venteside. Ingen endring av site_mode, innlogging eller produksjonsoppsett.

## Arbeidsflyt

Legg inn tittel, kategori, kilde, lenke og manus → lagre utkast → godkjenn → legg i sendingskø → marker «Lest på lufta». Endring av tekst opphever godkjenning og tar saken ut av køen. Leste saker beholdes som historikk og kan ikke overskrives i redigeringsbildet. Køen følger tidspunktet sakene ble lagt inn; ta ut og legg inn igjen for å flytte en sak sist.

Alle innloggede medarbeidere deler desken. Ingen automatisk publisering eller sending. Kildelenker åpnes i egen fane, men innhold lastes ikke ned av serveren. Tidspunkt i grensesnittet er Europe/Oslo og angir siste redigering, ikke publiseringstid hos kilden.

## Lagring og drift

Administrator må opprette en privat skrivbar katalog utenfor både dokumentrot og releasekatalog, med rettigheter 0700, og sette `STUDIO_DESK_STORAGE_DIR` eller `desk_storage_dir` i eksisterende private config/local.php. Bruk for eksempel en dedikert katalog under kontoens private hjemmemappe, med bekreftet absolutt serversti. Ikke pek mot webrot, en delt mappe eller OneDrive-synk.

Saker lagres i desk.json med 0600, separat fil-lås og atomisk rename. Revisjonskontroll avviser utdaterte skjemaer fra andre faner/medarbeidere. Ved lagringsfeil vises 503 og ingen vellykket lagring bekreftes. Ta egen backup av datakatalogen; eksisterende kodebackup inkluderer den ikke. Inntil arkivering innføres er grensen 500 saker.

OneDrive: godkjente manus hører under `Radio Rubben / Manus & Stikk`, og sendingsgrunnlag under `Radio Rubben / Sendeplan`. Dette er foreslått plassering; denne versjonen oppretter eller synkroniserer ingen OneDrive-filer.

## Neste integrasjonstrinn

RSS/API-innboks med kilde, publiseringstid, hentetid, original lenke og geografisk filter Bømlo/Stord/Sunnhordland. Kontroller endepunkter og bruksrett før aktivering. Hentefrekvens planlagt 15 minutter med cache og tydelig feilstatus. Innhentede saker skal ikke overskrive manus eller automatisk bli godkjent.

Planlagte adaptere: lokale RSS-kilder, MET vær/varsler, Vegvesen DATEX, Entur, Politiloggen og eventuell avtale med NTB. API-nøkler oppbevares på serveren. «Lag morgenbrief» skal gi utkast med kildehenvisninger, etterfulgt av manuell godkjenning. Ingen av disse adapterne er implementert eller tilkoblet i denne første versjonen.
