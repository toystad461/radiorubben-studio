# Samme test-VPS fra Windows og MacBook

Thomas bruker begge maskinene og ønsker samme arbeidsmåte. Avtalt 19.10.2026:
samme alias `rubben-test`, samme server og samme lagrede Studio-data, med en egen
personlig SSH-nøkkel på hver maskin. Dette er en oppskrift, ikke en kvittering på
at klientene eller nøklene allerede er satt opp.

## Verifisert server og gjenstående tilgang

| Felt | Testmiljø |
| --- | --- |
| Uniweb-server | 5363103 |
| Linux-vertsnavn | vps-5363103 |
| IP / SSH-port | 5.249.252.78 / 22 |
| Eksisterende administratorkonto | ubuntu |
| Foreslått felles klientalias | rubben-test |
| Studio | https://5.249.252.78/ |
| Aktiv app | /srv/radiorubben-test/studio |

Kontrollert på VPS 19.10 kl. 13:09 Europe/Oslo: SSH er aktiv, lytter på IPv4/IPv6
port 22, og UFW tillater OpenSSH. Kontoen ubuntu har et satt passord;
ingen passordverdi er lest. Effektiv sshd-standard tillater passord og offentlige
nøkler; root-passordinnlogging er sperret. Faktisk SSH-innlogging fra Windows
og MacBook gjenstår. Ingen nøkkel, konto eller SSH-/brannmurinnstilling er endret.

Agentens arbeidsmiljø svarer `Network is unreachable` ved direkte tilkobling.
At Thomas får SSH på sin PC gir ikke automatisk agenten samme nettverkstilgang.
Det må finnes en verifisert, autorisert kjøreflate før utrulling flyttes dit.
Produksjonswebhotellets SSH-konto og nøkler skal ikke gjenbrukes for denne VPS-en.

## Første tilkobling fra hver maskin

Åpne PowerShell/Windows Terminal på Windows eller Terminal på MacBook:

```sh
ssh ubuntu@5.249.252.78
```

Ved første forbindelse sammenlignes serverens vertsnøkkelfingeravtrykk med
output fra denne lesende kommandoen i den innloggede Uniweb-konsollen:

```sh
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
```

Velg/verifiser samme nøkkeltype i klienten. Ikke omgå vertsverifikasjon ved et
avvik. Passordet skrives av Thomas direkte i terminalen, aldri i chat.
Etter innlogging skal `hostname` vise `vps-5363103`, og `whoami` vise `ubuntu`.

## Ett alias på begge maskiner

Legg denne avgrensede blokken til eksisterende klientkonfigurasjon. Bevar andre
vertsoppføringer; filen skal hete `config`, uten `.txt`-endelse.

| Maskin | Konfigurasjonsfil |
| --- | --- |
| Windows | `%USERPROFILE%\.ssh\config` |
| MacBook | `~/.ssh/config` |

```sshconfig
Host rubben-test
    HostName 5.249.252.78
    Port 22
    User ubuntu
    ForwardAgent no
    StrictHostKeyChecking ask
```

Da blir daglig tilkobling lik på begge maskiner:

```sh
ssh rubben-test
```

`ssh -G rubben-test` viser effektiv klientkonfigurasjon uten innlogging.
Kontroller hostname, user og port før første forbindelse. Denne aliasblokken
endrer bare lokal klientkonfigurasjon; den gir ingen ny tilgang på serveren.

## Egen nøkkel per maskin – neste oppsettsteg

Opprett en passfrasebeskyttet Ed25519-nøkkel lokalt på hver maskin etter å ha
kontrollert at valgt filnavn er ledig. Bruk samme lokale filnavn
`radiorubben_test_ed25519`, men forskjellige nøkkelpar og kommentarer, for
eksempel `thomas-windows-rubben-test` og `thomas-macbook-rubben-test`.
Thomas skriver passfrasen direkte i klienten. Bare `.pub`-delen registreres
på VPS-en, med dokumentert maskintilhørighet. Privatnøkkelen blir på maskinen.

Når den aktuelle maskinens offentlige nøkkel er registrert og verifisert,
utvides akkurat `Host rubben-test` med:

```sshconfig
    IdentityFile ~/.ssh/radiorubben_test_ed25519
    IdentitiesOnly yes
```

Prøv nøkkelinnlogging fra begge maskiner i nye forbindelser før en eventuell
endring av passordinnlogging. Opplåsing kan håndteres av maskinens SSH-agent;
ikke fjern passfrasen eller kopier privatnøkkelen mellom maskinene for enkelhets skyld.
En mistet/utskiftet maskin kan da få sin offentlige nøkkel tilbakekalt separat.
Ingen slik tilgangsendring er utført som del av denne dokumentasjonen.

## Bytte maskin uten å miste arbeid

Studio, musikkø, profiler, lydfiler og spillerstatus ligger på VPS-en. Begge
nettlesere åpner samme testadresse, men har hver sin innloggingsøkt. Lukk gammel
fane eller hent fersk status før videre redigering; revisjonssperrer beskytter
mot at en gammel fane overskriver nye køendringer. Den vedvarende avspilleren
kjører som en server-tjeneste uavhengig av SSH-vindu og nettleser.

Kildekode og dokumentasjon har GitHub som felles utgangspunkt. Kontroller gren,
commit og ukommittert arbeid ved maskinbytte. En terminaløkt eller pågående
utrulling flyttes ikke automatisk mellom maskinene. Videre utrulling skal ha
én aktiv operasjon, fastlåst commit, tilbakeføringsgrunnlag og felles kvittering.
En begrenset utrullingsbruker er fortsatt planlagt; `ubuntu` er dagens
administrator og skal ikke beskrives som en allerede avgrenset deploykonto.

Microsoft-innlogging i Studio er en separat, foreløpig utsatt oppgave.
SSH-aliaset krever ingen Microsoft-/Entra-endring.

Offisiell referanse: [OpenSSH klientkonfigurasjon](https://man.openbsd.org/ssh_config),
[Windows klientkonfigurasjon](https://learn.microsoft.com/en-us/windows-server/administration/openssh/openssh-server-configuration)
og [Windows nøkkelhåndtering](https://learn.microsoft.com/en-us/windows-server/administration/openssh/openssh_keymanagement).
