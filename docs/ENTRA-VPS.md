# Entra-innlogging på test-VPS 5363103

Arbeidsområde: IT & Utstyr / Kontrollsenter. Klargjøring 21.10.2026.
Dette er en kontrollert oppsettsplan, ikke en kvittering på aktiv innlogging.

## Bekreftet på VPS

- Studio: https://5.249.252.78, callback: https://5.249.252.78/auth/callback.php.
- Aktiv app: /srv/radiorubben-test/studio. Den inneholder app/, config/, public/
  og vendor/, men ikke scripts/. Kjør derfor kontrollskript fra kildekopi med
  `--app-root=/srv/radiorubben-test/studio`.
- Privat konfigurasjon har site_mode=app, auth_mode=entra og local_users_enabled=true.
  Tenant/client er syntetiske nullbaserte UUID-er. Secret har plassholdermarkør.
  Ingen hemmelighetsverdi er vist eller kopiert.
- php8.3-fpm har PrivateNetwork=yes og RestrictAddressFamilies=AF_UNIX,
  NoNewPrivileges=yes og PrivateTmp=yes. Både rr-studio og rr-wordpress bruker
  denne tjenesten, med hver sin Unix-socket.
- Entra finnes allerede i app/auth/EntraClient.php: tenant-spesifikk OIDC,
  PKCE S256 og eksisterende tokenkontroll. Ingen ny innloggingsmotor trengs.
- Nettleseren i arbeidsmiljøet har tidligere avvist IP-adressen med
  ERR_CERT_COMMON_NAME_INVALID. VPS-ens egen sertifikatkontroll besto 21.10.
  Avviket er uavklart; ingen sertifikatvalidering skal skrus av.

## Oppsett som skal fylles inn privat

Bruk en separat testregistrering i **Radio Rubbens** tenant. Kartlegg eksisterende
registrering først; ikke erstatt eller endre produksjonsapp som bivirkning.

| Felt | Verdi / handling |
| --- | --- |
| Plattform | Web, single tenant |
| Foreslått navn | Radio Rubben Studio – VPS test |
| Redirect URI | https://5.249.252.78/auth/callback.php |
| Base URL i Studio | https://5.249.252.78 |
| Assignment required | Yes |
| Første bruker | Thomas, etter kontroll av riktig tenant og brukerobjekt |
| Scopes | openid, profile, email |
| tenant_id / client_id | Fra bekreftet testregistrering |
| client_secret | Legges direkte i privat serverkonfigurasjon, aldri chat/Git/logger |

HTTPS og eksakt samsvar mellom registrert callback og Studio er nødvendig.
IP-adressen er akseptert av Studios lokale URL-kontroll. Registreringens aksept
og faktisk retur fra Microsoft til denne IP-adressen er **ikke testet**.
Ingen DNS-endring inngår. Ikke bytt til HTTP eller omgå nettleservarsler.
Ingen Graph-/OneDrive-/postrettigheter eller gjesteinvitasjoner inngår.

## Nettverk – konkret avklaring før aktivering

Dagens PHP-FPM kan ikke kontakte Microsoft. Å bare fylle inn klientverdiene
er utilstrekkelig. **Ikke fjern isolasjonen fra den delte php8.3-fpm-tjenesten**:
det ville samtidig åpnet WordPress og alle Studio-integrasjonene for nettverk.

Anbefalt neste implementering er en separat FPM-tjeneste for bare
/auth/start.php og /auth/callback.php, med samme private konfigurasjon,
sesjonsnavn og sesjonslager som Studio. Før implementering må de faktiske
Nginx-rutene og pool-/sesjonsinnstillingene avstemmes; start-ruten verifiseres
mot kildekoden. Gi bare nødvendig HTTPS-utgang til Microsofts verifiserte
OIDC-discovery-, token- og signeringsnøkkelendepunkter. Begrens utgangen med
en eksplisitt kontrollert proxy/egress-regel; en egen prosess alene er ikke
destinasjonsbegrensning. Ikke hardkod kortlivede Microsoft-IP-er.
Ingen generell Graph-tilgang, shell-/exec-åpning eller nettverk til spilleren.

Dette er et arkitekturvalg som må implementeres og testes, ikke en levert
tjeneste. Konkrete konfigurasjonsfiler, egress-regler, backup og tilbakeføring
skal gjennomgås før sikkerhetsendringen aktiveres.

## Kontroller før ferdigmelding

1. Kjør `php scripts/entra-preflight.php --app-root=/srv/radiorubben-test/studio`
   fra kontrollert kildekopi med lesetilgang til den aktive appens config.
   Skriptet gjør ingen nettverkskall eller endringer og skriver ingen verdier.
   Det avviser nå kjente nullbaserte plassholder-ID-er.
2. Kontroller i den faktiske FPM-tjenesten at cURL/TLS og nødvendig utgang virker.
   CLI-PASS beviser ikke FPM-nettverk, gyldig secret eller app-tildeling.
3. Kontroller sertifikat, callback og sesjonscookie i en nettleser uten omgåelser.
4. Thomas logger inn; verifisert OID må gi eksisterende eierrolle. Andre
   Entra-brukere blir observer i dagens kode. Ikke gi automatisk admin til alle.
5. Test avvist ikke-tildelt bruker, feil state, utløpt forsøk, utlogging og
   eksisterende lokal reservekonto. Lokal reservekonto er ikke admin.
6. Bekreft at WordPress, øvrige integrasjoner og rr-music-playout fortsatt
   mangler nettverk, og at musikkø/data er bevart.
7. Dokumenter eksakt kodecommit, berørte filer, før/etter-hasher og faktisk
   testresultat. Først da kan Entra meldes aktiv på VPS.

Ved feil tilbakeføres bare den avgrensede endringen fra privat backup.
Ikke kopier produksjonsbrukere, ikke slett testdata, ikke erstatt aktiv release
med hele main eller PR-kjeden.

Kilder:
- https://learn.microsoft.com/en-us/entra/identity-platform/reply-url
- https://learn.microsoft.com/en-us/entra/identity-platform/v2-protocols-oidc
- Eksisterende docs/ENTRA-TEST.md og app/auth/EntraClient.php.
