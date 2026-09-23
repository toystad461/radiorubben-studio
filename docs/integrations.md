# Integrasjoner og neste versjon

## Microsoft

Innloggingsgrunnlaget er implementert med NextAuth.js v4 og en tenant-spesifikk Azure AD-provider. Konfigurasjonsvalidering krever organisasjons-ID, app-ID, klienthemmelighet, sesjonshemmelighet og en gyldig base-URL. Tilgang må begrenses gjennom Entra-apptildeling. Test med både tildelt og ikke-tildelt bruker før produksjonsbruk.

Fremtidige interne sider og API-ruter må kontrollere sesjon og tillatelser på serveren hver gang. Den offentlige demonstrasjonen inneholder ingen brukerdata. Konfigurasjonsstatus er ikke en helsesjekk av Microsoft-tjenesten.

## OneDrive / Microsoft Graph

`src/lib/integrations/onedrive.ts` definerer kontrakten for et fremtidig filbibliotek. Ingen Graph-klient eller filhenting er aktivert. Avklar personlig OneDrive kontra delt SharePoint-bibliotek før implementering. Bruk minste nødvendige delegerte lesetilgang; vurder eksplisitt samtykke og `offline_access` bare hvis bakgrunnsarbeid er nødvendig. Implementer sikker serverlagring, fornyelse og tilbakekalling av tokens før bruk. Tokens skal ikke sendes til klientkomponenter eller logger.

## WordPress

`src/lib/integrations/wordpress.ts` definerer en fremtidig funksjon for artikkelutkast. `WORDPRESS_API_URL` er kun en reservert miljøvariabel og blir ikke brukt ennå. Neste steg er å avklare nettstedets REST API og autentisering. Bruk en dedikert WordPress-bruker med minimale rettigheter og et application password lagret på serveren. Første skriveoperasjon bør lage et utkast; publisering krever en egen bekreftet arbeidsflyt.

## Før lansering

- Sett opp hosting, HTTPS og DNS for studio.radiorubben.no.
- Konfigurer produksjonsmiljø og test innlogging, avvist tilgang, utlogging og utløpt sesjon.
- Kontroller serverautorisasjon på alle nye dataendepunkter.
- Legg til overvåking uten personopplysninger eller tokens i logger.
- Behold GitHub-repoet privat; en privat kodebase gjør ikke selve nettstedet privat.
