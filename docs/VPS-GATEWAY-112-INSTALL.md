# PR #112 – kontrollert oppgradering av test-VPS-gateway

Kontrollert 24.10.2026 kl. 11:29 Europe/Oslo. Hovedsak #109.
Arbeidsgren: `feat/vps-readonly-status-20261024`.
Dette er en klargjort prosedyre, ikke en utført installasjon eller installasjonsautorisasjon.

## Fastlåst filsett

Eneste serverfil som skal erstattes er `/usr/local/libexec/rr-vps-deploy.py`.
Kilde: `scripts/vps-deploy-gateway.py` fra commit
`e3cda33b39b77abc7cf06ee4488ee5f7bb8ac4c0`.
Kandidat SHA256: `8e59a4527f348e1caaa0a99d92a94df54c05bdc8985e832cb4641858cbdfd146`.
Git blob: `2a8d78b13364fc3c010455f00974f5b0c1ea73fa`.
PR-base: `28a2bc12776d45cb8c867fb65cf9be4de99ed2dc`.
Forventet baseline SHA256: `9c80aac6c61594dbf34c247ca0c7fca3a1f1bdcf12dcf525501572385dfe544e`.

Baseline er hentet fra GitHub og er IKKE bekreftet mot aktiv server.
Stopp ved annen aktiv hash; avstem avvik før installasjon.

## Kontrollbelegg og begrensninger

- Eksakt kandidat er kontrollert med Git blob-hash etter uthenting.
- Ni gatewaytester består lokalt, inklusive backup/rollback, drift, samtidige endringer og filavgrensning.
- Python-syntaks og Bash-syntaks for eksisterende bootstrap består.
- Isolert pakkekontroll for snapshot/status består; ekstra kommando-/enhetsfelt fjernes.
- GitHub-kontroller på kildecommiten er grønne. transfer/deploy er skipped.
- Lesende SSH-forsøk: alias rubben-test kunne ikke løses i denne økten.
  Eksplisitt IP med BatchMode/StrictHostKeyChecking ga Network is unreachable.
- Ingen aktive serverfiler, rettigheter, backup eller tjenester er kontrollert i denne runden.
- Ingen nøkler, administratorrettigheter, deploymanifest eller serverfiler er endret.
- Status har seks faste enheter og tre filtrerte tilstandsfelt; det er ikke full tjenesteoversikt eller lyd-/HTTP-helsetest.
- Timeout eller feil ved start av systemctl avbryter forespørselen gjennom eksisterende feilhåndtering.
  En slik feil skal ikke regnes som frisk drift.

## Før installasjon – eksisterende administratorøkt på test-VPS

Bruk Windows PowerShell eller Mac Terminal: `ssh rubben-test`.
Bruk etablert ubuntu-konto og eksisterende sudo, aldri deploykontoen som generell administrator.
Ikke kjør bootstrap på nytt. Ingen endring i sudoers, authorized_keys, SSH, pakker eller tjenester.

1. Bekreft hostname vps-5363103 / IP 5.249.252.78 og faktisk Studio-symlink.
   Kontroller rot-eid, ikke gruppe-/verdensskrivbar ancestry for gateway og aktiv release.
2. Kontroller gateway med stat, namei og sha256sum: ordinær fil, ingen symlink,
   én hardlink, root:root og 0644 som i eksisterende bootstrap. Stopp ved avvik;
   ikke reparer rettigheter automatisk. Kontroller også eksisterende sudoers 0440,
   root-eid authorized_keys og privat gateway-state 0700 uten å publisere innhold.
3. Kontroller at ingen deploy kjører og at pending.json ikke finnes.
   Avtal et kort installasjonsvindu. Ta eksklusiv lås på
   /var/lib/rr-vps-deploy/lock under backup/erstatning; ingen parallelle apply-jobber.
4. Eksporter kandidat og baseline fra fastlåste commits i eksisterende rr-build-checkout.
   Verifiser begge SHA256 over faktiske bytes. Les eksakt diff. Ikke hent branch-tip
   som kandidat, og ikke last ned/kjør ubekreftet kode direkte som root.
5. Ta et ferskt snapshot via eksisterende forced-command og lagre kvitteringen privat.
   Kontroller recoveryRequired=false. Dette er førbelegg, ikke installasjonsbevis.

## Privat backup og atomisk erstatning – krever særskilt autorisasjon

Foreslått, ikke opprettet mappe:
`/var/lib/rr-vps-deploy/gateway-upgrades/112-<unik-tid-UTC>`.
Rot-eid overmappe og undermappe 0700, umask 077. Ingen backup under webrot eller i GitHub.

1. Kopier aktiv gateway til backup/gateway.before.py; backupfil 0600 root:root.
   Lagre original eier, gruppe, modus, baseline-hash, kandidat-hash, UTC/norsk tid og
   kildecommit i privat manifest 0600. Les backup tilbake og sammenlign hash.
2. Registrer kun hash/metadata for eksisterende SSH-/sudoers-filer, ikke hemmeligheter.
   Bevar privat før-snapshot. Ikke kopier runtime-data eller nøkler inn i leveransen.
3. Opprett kandidat som midlertidig ordinær fil i /usr/local/libexec på samme filsystem.
   Sett root:root/0644 før erstatning. Verifiser kandidat-hash og kompiler teksten med
   Python compile() uten import/utførelse og uten å lage pycache i systemkatalogen.
4. Kontroller aktiv baseline-hash på nytt under låsen; stopp ved samtidige endringer.
   Flush/fsync kandidat, erstatt med os.replace(), og fsync foreldrekatalogen.
   Ikke trunker aktiv fil. Behold privat backup. Ingen tjenesteomstart er nødvendig.
5. Kontroller installert SHA256, root:root/0644, én hardlink og uendrede SSH-/sudoers-hasher.
   Frigi låsen før gatewayforespørslene slik at de ikke avvises av samme lås.

## Etterkontroll gjennom eksisterende forced-command

Send `{"mode":"status"}` og deretter `{"mode":"snapshot"}` gjennom eksisterende
rr-deploy-nøkkel og vertsverifisering. Nøkkelen skal forbli i dagens hemmelighetslager.
Ikke kopier privat GitHub-nøkkel ut til administratorøkten.

Ny pakkebygger krever senere autorisert promotering til deploy/vps-test for Actions-bruk;
PR-jobben alene sender ingenting. Endring av manifest eller promotering til deploygrenen
inngår ikke i denne klargjøringen. Ikke bruk apply til å installere gatewayen.

Krev status=service-status, nøyaktig seks forhåndsvalgte tjenester og bare load/active/sub
med filtrerte verdier. Manglende tjenester er ikke et installasjonsavvik alene.
Krev gyldig snapshot, recoveryRequired=false, samme aktive release og identiske app/public-hasher
som før. Avvik må forklares før ferdigmelding. Registrer faktisk SSH-/Actions-kvittering,
installert filhash, tidspunkt og utfall i PR112 og #109. Ingen ekte apply-test inngår.

## Dokumentert tilbakeføring

Ved feil etter erstatning: stopp videre jobber. Ta samme eksklusive gatewaylås.
Kontroller at aktiv hash fortsatt er kandidat-hashen og backup er baseline-hashen.
Ved ukjent aktiv hash: stopp, ikke overskriv annen operatørs endring.

Gjenopprett backup via ny midlertidig fil i /usr/local/libexec, bruk original
root:root/0644 fra privat manifest, fsync fil, os.replace og fsync katalog.
Backupfilen forblir privat 0600. Ikke gjenopprett nøkler, sudoers, runtime-data eller hele release.
Frigi låsen; verifiser baseline-hash/rettigheter og snapshot gjennom eksisterende gateway.
Den gamle gatewayen skal avvise status; dette er forventet etter tilbakeføring.
Bevar backup og kvitteringer. Ingen produksjonsendring.

## Status og neste handling

Kode: testet lokalt og i GitHub. Installasjon på test-VPS: ikke utført.
Verifisert drift av ny statusfunksjon: ikke utført.
Neste konkrete handling: eksisterende administratorøkt utfører lesende førkontroll
av aktiv gateway-hash, ancestry/rettigheter, release og pending-status.
Deretter kan den fastlåste installasjonen vurderes for særskilt autorisasjon.
