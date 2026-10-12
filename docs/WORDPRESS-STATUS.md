# Avklaring av WordPress-overføring

Behov: Studio må kunne avklare et lagringsforsøk som WordPress mottok uten
at Studio fikk en gyldig bekreftelse. En ny overføring kan gi duplikater.

Administrator velger **Kontroller WordPress-status** på saken eller i
Studiooversikten. Begge steder viser samme resultat og lenke. Kontrollen
leser WordPress med eksisterende tilgang; bare Studio-kvitteringen lagres.

- Ett identifisert innlegg, samme overskrift/ingress/artikkel, leveringshash
  og bestilt status: eksisterende overføring bekreftes som publisert eller kladd.
- Innlegg med avvik: faktisk WordPress-status og lenke vises; ny overføring
  og redigering av nettsaken venter på avklaring.
- Ingen/flere treff: ingen konklusjon om at overføringen feilet. Sperren beholdes.
- Feilet oppslag: sist lagrede status beholdes og brukeren får en konkret beskjed.

Kjent WordPress-ID brukes fremfor slug; ellers brukes `studio-<sak-ID>`.
HTTP er GET mot fast radiorubben.no-adresse, uten omdirigering. Legitimasjon,
originaltekst og API-svar sendes ikke til nettleseren eller diagnostikk.
Administrator, CSRF, saksrevisjon og uendret overføringspost kontrolleres.
Overføring som fortsatt pågår, venter minst to minutter. Det gis ingen
innholds-, manus- eller lydgodkjenning. Ingen automatisk ny publisering,
gjenoppretting eller sletting. Tidligere kvittering bevares i historikk.

Ferdigkriterier: samsvar gir bekreftet status/lenke på begge flater; avvik,
feil, endrede data og feil tilgang kan ikke gi ny godkjenning eller tillate
ny overføring. Reelle saker brukes bare til lesing i utviklingskontroller.
Kategorier/bilder sammenlignes ikke separat; dette er en kontroll av
innleggsidentitet, tekst, leveringshash og publiseringsstatus. Oppslag er
ikke kontinuerlig overvåking. Etter manuelle endringer må status kontrolleres igjen.
