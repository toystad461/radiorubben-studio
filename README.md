# Radio Rubben Studio

Internt arbeidsrom for **studio.radiorubben.no**, bygget med Next.js App Router, React og TypeScript. Første versjon har et responsivt norsk kontrollsenter og en klargjort Microsoft Entra ID-innlogging. OneDrive og WordPress er planlagte integrasjoner uten aktive API-kall.

## Kom i gang på Mac

Installer **Node.js 24 LTS** fra [nodejs.org](https://nodejs.org/) hvis `node --version` ikke virker. Installasjonen inkluderer npm. Installer deretter prosjektets pakkebehandler:

```sh
npm install --global pnpm@11.25.0
cd ~/RadioRubben/radiorubben-studio
pnpm install --frozen-lockfile
pnpm dev
```

Åpne [localhost:3000](http://localhost:3000). Du trenger ingen nøkler eller miljøfil for demonstrasjonen. Node 22 eller nyere er minimum for dette prosjektet; `.nvmrc` velger 24 ved bruk av nvm.

**Uten komplett innloggingsoppsett er startsiden en offentlig demonstrasjon med bare statisk innhold.** Ikke legg inn interne data før autentisering og tilgang er satt opp. Statusmerkene beskriver konfigurasjon, ikke bekreftet forbindelse til eksterne tjenester.

## Microsoft Entra ID

Integrasjonen bruker NextAuth.js v4 og provider `azure-ad` (bibliotekets navn på Microsoft Entra ID). Hemmeligheter leses bare på serveren. Opprett ingen hemmeligheter i kildekoden.

1. Registrer en **single-tenant** webapp i organisasjonens Entra ID.
2. Registrer Web redirect URI-er:
   - Lokalt: `http://localhost:3000/api/auth/callback/azure-ad`
   - Produksjon: `https://studio.radiorubben.no/api/auth/callback/azure-ad`
3. Aktiver **Assignment required** for Enterprise Application og tildel bare aktuelle medarbeidere/grupper. Uten dette kan øvrige brukere i organisasjonen også få tilgang.
4. Kopier eksempelfilen lokalt:

```sh
cp .env.example .env.local
```

5. Fyll inn `AZURE_AD_TENANT_ID`, `AZURE_AD_CLIENT_ID` og `AZURE_AD_CLIENT_SECRET` fra din appregistrering. Tenant og client ID må være UUID-er. Sett `NEXTAUTH_SECRET` til en tilfeldig hemmelighet på minst 32 tegn (for eksempel generert lokalt med `openssl rand -base64 32`). Ikke del eller commit verdiene.
6. Behold `NEXTAUTH_URL=http://localhost:3000` lokalt. Start utviklingsserveren på nytt. Besøk `/login`.

Appen ber bare om `openid profile email`. Den lagrer en kryptert sesjonscookie med åtte timers levetid og eksponerer ikke Graph-tokens til klienten. Når oppsettet er komplett, krever `/` en gyldig serverkontrollert sesjon. Delvis eller manglende oppsett deaktiverer auth-endepunktene med HTTP 503. Utlogging avslutter Studio-sesjonen, ikke hele Microsoft-kontoens nettleserøkt.

Reell Microsoft-innlogging må verifiseres i organisasjonens tenant etter at legitimasjon er konfigurert. Ingen ekte credentials følger med prosjektet.

## Kontroller og produksjonsbygg

```sh
pnpm lint
pnpm typecheck
pnpm test
pnpm build
pnpm test:smoke
pnpm start
```

`pnpm start` krever et ferdig bygg. Produksjonsinnlogging krever HTTPS i `NEXTAUTH_URL`; sett den til `https://studio.radiorubben.no`. Sett miljøverdiene i hostingplattformens hemmelighetshåndtering. Appen trenger Node-server eller Next.js-kompatibel hosting, ikke statisk eksport. Domene, DNS, hosting og TLS er ikke satt opp av denne første versjonen.

## Struktur

```text
src/app/                     Kontrollsenter, layout og innloggingsside
src/app/api/auth/            Innloggings- og callback-endepunkter
src/components/              Gjenbrukbare UI-komponenter
src/lib/auth/                Validering og serverkonfigurasjon for Entra ID
src/lib/integrations/        OneDrive-/WordPress-grensesnitt og visningsdata
tests/                       Kontroller av innloggingskonfigurasjon
docs/integrations.md         Videre plan for integrasjoner
```

## GitHub er kode-master

Privat repo: [toystad461/radiorubben-studio](https://github.com/toystad461/radiorubben-studio). Behold synligheten **Private**. `private: true` i package.json hindrer utilsiktet pakkepublisering; GitHub-synlighet styres separat på GitHub.

Hent siste versjon før videre arbeid med `git pull --ff-only`. Bruk egne grener og pull requests for videre endringer. Commit kildekode, dokumentasjon og `pnpm-lock.yaml`; ikke `.env.local`, `.next` eller `node_modules`. Bruk pnpm konsekvent, slik at prosjektet har én låsefil.

## Referanser

- [Next.js – installasjon](https://nextjs.org/docs/app/getting-started/installation)
- [NextAuth.js – Microsoft Entra / Azure AD](https://next-auth.js.org/providers/azure-ad)
- [NextAuth.js – App Router](https://next-auth.js.org/configuration/initialization#route-handlers-app)
