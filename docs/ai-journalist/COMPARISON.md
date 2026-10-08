# Før/etter: to publiserte artikler

Offentlige WordPress-artikler og lenkede originaler hentet 8. oktober 2026. Originalene kan ha blitt endret siden publisering. Et forsøk på å lese historiske kildeøyeblikksbilder fra privat produksjonsliste ble avvist av automatisk sikkerhetskontroll; slike data ble ikke eksportert. Sammenligningen bruker derfor offentlige, avgrensede kildeutdrag.

Ettertekstene er redaksjonelle forslag skrevet i utviklingsoppgaven etter stylebooken. De er **ikke** innspilte svar fra produksjonens modell. Dette er kvalitativ demonstrasjon og regresjonsgrunnlag, ikke en måling av modellforbedring. Artiklene er ikke endret eller republisert.

| Artikkel | Ord før → etter¹ | Redaksjonell endring |
| --- | --- | --- |
| [Mulig slagsmål, 1283](https://www.radiorubben.no/studio-881809fd2615496a/) | 105 → 37 | Bevarer «mulig» og «skal ha», fjerner gjentakelser og unødvendig bloddetalj. Ingen påstand om fortsatt tilstedeværelse for videre avklaring. |
| [Budsjett, 1271](https://www.radiorubben.no/studio-63afd93367530283/) | 121 → 40 | Prioriterer forslag, behandling og høring. Fjerner gjentatt framleggingsdato og generell bakgrunn. Planlagte informasjonsmøter fremstilles ikke som gjennomført. |

¹Tittel, ingress og brødtekst, uten kildefot. Lavere ordantall er ikke i seg selv bedre journalistikk. Omtrentlig LIX ligger i JSON-resultatet, men brukes ikke til godkjenning; blant annet datoer gjør enkel setningstelling upresis.

## Nytt forslag: mulig slagsmål

**Politiet undersøker mulig slagsmål ved Krohnengen skole**

Politiet rykket ut etter en melding om mulig slagsmål ved skolen, melder NRK.

Ifølge NRK skal politiet ha kontroll på én mistenkt og to fornærmede. Bakgrunnen for hendelsen er ukjent.

Kilde: [NRKs original](https://www.nrk.no/vestland/slagsmal-pa-krohnengen-skule-1.18048789). Skolen som stedsangivelse gir ikke grunnlag for å anta at de involverte er elever eller mindreårige. Kandidaten legger ikke til alder.

## Nytt forslag: budsjettprosessen

**Bømlo legger fram budsjettforslaget 26. oktober**

Kommunedirektør Kjetil Aga Gjøsæter presenterer forslaget til budsjett for 2027 og økonomiplan for 2027–2030, opplyser Bømlo kommune.

Formannskapet behandler forslaget 26. november og legger det ut til offentlig høring. Kommunestyrets budsjettmøte er 14. desember.

Kilde: [Bømlo kommunes budsjettinformasjon](https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/oktober-til-desember-er-budsjettid-i-kommunen.20968.aspx). Tidene er framlagt som prosess/plan, ikke et påstått allerede vedtatt budsjett.

## Reproduserbar kontroll

Kjør `php scripts/compare-journalist.php` for JSON-rapport fra låste testdata. `tests/recovered/test-journalist-comparison.php` kontrollerer kildeutdragets hash, tekstformat, talltoken og bevarte forbehold. En riktig talltoken er ikke bevis på riktig betydning eller tilknytning. Semantikk og etikk må fortsatt vurderes.

En tredje offentlig sak (1244, arealplan) ble undersøkt, men originalen ga ikke et entydig tekstuttrekk gjennom produksjonsparseren. Den er ikke omskrevet på grunnlag av Radio Rubbens egen gamle tekst. Dette er et konkret eksempel på at kildefeil skal gi stopp, ikke en oppdiktet ny versjon.

## Før målt modellforbedring kan hevdes

Kjør gamle og nye genereringsinstrukser med samme modellinnstilling på samme låste, tillatte originalutvalg. Lagre råutkast, modellnavn, tidspunkt og prompt-/kildehash uten hemmeligheter. La redaktøren vurdere parene i blindet rekkefølge: fakta/forbehold først, deretter etikk, lokal relevans, språk, repetisjon og åpenhet. Ingen høy stilscore kan kompensere for en faktisk feil. Gjenta på flere typer saker; dette utvalget på to er ikke representativt. Produksjonsaktivering krever separat godkjenning.
