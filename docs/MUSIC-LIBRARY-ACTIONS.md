# Musikkbibliotek – handlinger, redigering og merkelapper

Behov13.10.2026: flere tags, flater i oversikten, samlet Actions-meny og enkel
forhåndslytting. Base: utrullet PR81, main3d238dc7a5a03328c7fad13de89796904839dba3.

## Utviklet

- Handlinger i valgt låt: rediger opplysninger/tags, gå til cue-seksjonen,
  slettehandling og tydelig avslått køvalg. Nettbiblioteket er ikke koblet til
  den lokale Studio-køen; ingen falsk enqueue eller sending.
- Rediger navn, artist, album, sjanger og år. Rettelser lagres separat fra
  filmetadata. Tomme felt bruker originalens opplysninger igjen. Inntil20 egne
  tags, opptil80 tegn hver, med komma/linjeskift som separator. Roller, CSRF,
  originalhash og revisjon kontrolleres; originalfilen beholdes. Endringer
  ugyldiggjør gamle produsentforslag gjennom eksisterende grunnlagshash.
- Flater-kolonne viser kun gjeldende AI-perioder som forslag, med «Ikke vurdert»
  når forslag mangler. Flater er søkbare og kan ikke forveksles med aktiv rotasjon.
- Produsentskjemaet får inntil8 tags fra begrenset norsk stemnings-/flateordliste.
  Forslag merkes AI. Ta med forslag-knappen fyller kun editoren; operatøren
  lagrer selv. Tidligere forslag uten tags kan fortsatt leses. Ingen nye
  språk-, BPM- eller utgivelsesfakta oppdiktes som tags.
- Én play-knapp i hver rad og ved valgt låt, uten avkryssing. Ett privat audio-
  element per side, ingen src før operatørens klikk, play/pause og ingen kø- eller
  sendekobling. Operatøren har uttrykkelig bedt om denne forenklingen.
- Eksakt SHA256-dublett gjenbrukes som før. Etter automatisk filklargjøring
  varsles mulig duplikat hvis effektiv artist/tittel samsvarer og varigheten
  avviker maks3sek. Ulike filer beholdes. Manglende opplysninger gir ikke
  gjetting fra filnavn. Dette er ikke lydfingerprinting eller bevis på samme miks.

## Ferdigkriterier og kontroll

Målrettet PHP8.2/8.4: urettmessig rolle, CSRF, stale versjon, lengdegrenser,
bevarte originaler/kildemetadata, tom overstyring, mulige duplikater og tagskjema.
Ekte syntetisk multipartnettleser: rediger/lagre, ettklikk-play/pause uten lydsrc
ved GET, faktisk mulig-duplikatvarsel for endrede MP3-bytes og bevaring av begge
utgaver. Avspilling simuleres for å unngå faktisk lyd; bytelevering testes separat.
320/390/768/1280px liste og detalj, eksisterende rolle-/lyd-/slettekontroller.

Gjenstår: faktisk nettbibliotekkø, cue-redigering/lydbølge og cue i avspilleren,
rotasjonsgodkjenning. Nett-/AI-bakgrunnsjobb er fortsatt avslått. Ingen nye
produksjonstags, modellkall, avspilling, merge eller utrulling gjort i utviklingen.
