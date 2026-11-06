# Manusknapper på VPS – 02.11.2026

Arbeidsområder: Manus & Stikk, Teknisk Produksjon, Kontrollsenter.
Eksisterende PR115. Brukerens innmeldte feil er reprodusert innlogget på VPS.

Tre årsaker: knappene brukte lokal kontekst, openai_model var tom, og PHP-FPM
har PrivateNetwork=yes og RestrictAddressFamilies=AF_UNIX. Eksisterende API-nøkkel
ble kontrollert med GET /v1/models og ga200 uten eksponering av nøkkelen.

Rettelsen henter fersk VPS-kontekst ved hver manusbestilling. Serveren overstyrer
nettleserens låtdata med journal/manifest og avviser nytt musikkstikk dersom
køen skifter under generering. Aktiv sang omtales ikke som allerede fullført.
Feil vises også ved manusfeltet. Lokal mal er merket som mal.

Forbindelsen bruker en privat Unix-socket som kun samme Linux-bruker kan nå.
PHP-FPM beholder nettverksisolasjonen. Reléet tillater bare POST /v1/responses
til https://api.openai.com, TLS-verifisering, fast modell, store=false, tekst,
begrenset forespørsel/svar og tokenantall. Ingen proxy til vilkårlige adresser,
HTTP-redirect, verktøy eller offentlig port. Nøkkel og tekst logges ikke.
Modell for denne avgrensede funksjonen: gpt-5.4-mini-2026-03-17.
Offisiell modellreferanse: https://developers.openai.com/api/docs/models/gpt-5.4-mini.
Privat transportfil inneholder bare socketsti og modell, ingen nøkkel.

Testet: stale/disconnected/ukjent kø, aktuell sang-formulering, privat relégrense,
feil modell, verktøy, lagring, tokens og vilkårlig URL. Faktisk AI-generering er kontrollert innlogget på VPS, på desktop og 390 px.
Neste låt ga aktuell Kygo/Selena Gomez og neste Echosmith fra serverkøen;
Kortere ga et nytt, kortere forslag. Ingen TTS eller køpublisering ble utført.

Brukeren godkjente eksplisitt den avgrensede AI-forbindelsen. Ni mål fra
c08801b68f5b873bfd14378203ad65b6e2cd6a6c ble installert 02.11.2026
kl.14:20:39 Europe/Oslo (13:20:39 UTC). Alle kildehasher er kontrollert.
Privat backup: /var/lib/rr-vps-deploy/producer-vps-svjpqvgh.
Streamens PID770 var uendret; ny relétjeneste og begge lydtjenester var aktive.
CI41450955287, 41450955261 og41450955377 var grønne.
Denne rettelsen produserer manus; den oppretter ikke TTS eller sender lyd.
