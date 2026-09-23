# Videre integrasjoner

## Microsoft Entra ID

PHP bruker `jumbojett/openid-connect-php` via Composer. Klienten validerer signaturen og standard OIDC-claims; Studio krever i tillegg nonce, gyldig exp, sub og riktig tenant. PKCE bruker S256. Callback-state kontrolleres før tokenutveksling. Bruker-ID og navn hentes bare fra verifiserte claims. Nye interne sider/API-er må laste bootstrap og kreve current_user() før de behandler data. Ingen interne data må være tilgjengelige i demo-modus.

## OneDrive

Kontrakten er `app/integrations/OneDrive.php`. Avklar personlig OneDrive eller delt SharePoint-bibliotek. Innlogging ber bare om openid/profile/email og gir ikke filtilgang. Implementer minste nødvendige Graph-tilgang, samtykke og sikker serverlagring/fornyelse av tokens før filhenting.

## WordPress

Kontrakten er `app/integrations/WordPress.php`. Avklar REST API og en dedikert bruker med minst nødvendige rettigheter. Application password skal lagres utenfor public/ og Git. Start med artikkelutkast; publisering trenger egen arbeidsflyt.

## Før produksjon

Bekreft separat dokumentrot hos Uniweb, PHP-versjon, curl/openssl og sesjonslagring. Aktiver HTTPS før Entra-modus. Test vellykket og avvist innlogging med ekte tenant. DNS står urørt til dette er avklart. GitHub Actions tester syntaks, konfigurasjonsgrenser og HTTP-svar, men erstatter ikke test på Uniweb eller ekte Microsoft-innlogging.
