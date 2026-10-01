# Entra – klargjøring for test, 01.10.2026

## Grunnlag og avgrensning
Bygger på konsolidert Studio i PR #19, commit f7ce90ce393a625ba4b9ffc4cf494457dbb04244.
Denne testendringen skal sammenlignes mot PR #19, ikke lastes opp som en hel gren.
Ingen produksjon, Entra-innstilling, gjesteinvitasjon eller temafil er endret.
Eksisterende OIDC-klient bruker tenant-spesifikk innlogging, PKCE, state, nonce
og bibliotekets tokenvalidering. Entra-koden finnes allerede.

## Faktiske roller i dagens kode
Entra-eier gjenkjennes med eksisterende OID-regel og får admin.
Andre innloggede Entra-brukere får observer, også inviterte gjester.
De fem foreslåtte rollene fra samtalen er ikke implementert for Entra.
Dagens øvrige roller er admin, producer, presenter og observer.
Ikke lov en gjest redaktør-/publiseringstilgang før egen rolleimplementasjon er testet.
En lokal administratorkonto er heller ikke dokumentert støttet: lokal
brukeroppretting avviser admin. Lokal reservekonto gir kun sin eksisterende rolle.
Kontroller derfor Entra-eiertilgangen separat før noen overgang.

## Testoppsett
1. Bruk et isolert HTTPS-testnettsted med syntetiske data og separat privat config.
   Testvert er ennå ikke valgt/opprettet. Ikke bruk produksjonsdata eller flytt domenet.
2. Bruk en separat Web-appregistrering i Radio Rubbens tenant, single tenant.
   Registrer nøyaktig https://<bekreftet-testvert>/auth/callback.php.
   Ikke registrer plassholderen som virkelig adresse.
3. Sett Assignment required = Yes i Enterprise Application. Tildel testbrukerne
   enkeltvis: én intern bruker og én invitert B2B-gjest etter eksplisitt invitasjonsbestilling.
   Ekstern bruker logger inn med sin egen konto, men er gjest i Radio Rubbens tenant.
4. Kun innlogging: openid/profile/email. Ikke gi Graph-filtilgang i denne testen.
5. Sett tenant_id, client_id og client_secret privat på testverten. Hemmeligheten
   skal aldri inn i chat, Git eller testlogg. Bruk auth_mode=entra,
   local_users_enabled=true og testvertens HTTPS-base_url.
   Bevar eksisterende reservekonto; ikke kopier produksjonens brukerregister.
6. Kjør composer install --no-dev og composer test på testkopien.
   Kjør php scripts/entra-preflight.php før testnettstedet åpnes.
   Skriptet er CLI-only, gjør ingen nettverkskall og viser bare PASS/BLOCKED.
   Det beviser ikke gyldig secret, app-tildeling, eksisterende reservekonto eller webserveroppsett.
7. Aktiver site_mode=app kun på testkopien etter forhåndskontroll.

## Manuell testprotokoll – alle punkter er uprøvd
| Test | Forventet |
| --- | --- |
| Tildelt intern bruker | Innlogging; observer med mindre eksisterende eier-OID matcher |
| Tildelt invitert gjest | Egen Microsoft-konto; observer; ingen administrasjon eller produksjon |
| Ikke-tildelt bruker i tenant | Avvist av Entra |
| Ekstern bruker uten invitasjon | Avvist |
| Observer åpner administrative handlinger direkte | Avvist på serveren |
| Forfalsket state / feil callback / utløpt forsøk | Avvist; ingen innlogget sesjon |
| Lokal reservekonto | Innlogging med eksisterende begrenset rolle |
| Utlogging og utløpt sesjon | Intern visning krever ny innlogging |
| Fjernet app-tildeling | Ny innlogging avvist; eksisterende Studio-sesjon kan vare til utløp |
| Hemmeligheter og private filer | Ikke tilgjengelig via HTTP; ingen tokens i logger |

Registrer dato, kildecommit, testvert, testtype og PASS/FAIL uten personopplysninger
eller tokens. Behold manuell status uprøvd til handlingen faktisk er gjennomført.
Ingen automatisk OneDrive-tilgang eller synk inngår.
Ved feil stenges testkopien med site_mode=coming-soon; produksjon berøres ikke.

## Videreføring
Før produksjon: avstem ferske serverhasher, test OIDC mot riktig tenant,
bekreft eiertilgang og reservevei, og følg STUDIO-DEPLOY.md.
Testpakken er ikke en godkjenning av hele PR #19 for publisering.

Kilder:
- https://learn.microsoft.com/en-us/entra/identity-platform/single-and-multi-tenant-apps
- https://learn.microsoft.com/en-us/entra/external-id/what-is-b2b
