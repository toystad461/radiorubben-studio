# Punktlytting og setting av cue-punkter – 14.10.2026

Behov: Operatøren skal kunne trykke på et vist cue-punkt og høre området rundt det, samt enkelt finne og sette vokal/overgang uten vilkårlig tidlig stopp.

Observasjon i innlogget Studio: Bruno Mars-låten de8bdffd245b7145310e6d2450fc610f hadde vokalpunkt16,3sek og ukjent overgang. Den gamle introprøven stoppet3sek etter vokalpunktet. Dette bekrefter konfigurert stoppgrense, ikke at vokalpunktet er korrekt i musikken.

Ny flyt: Punktknapp eller markørklikk spiller fra fem sekunder før vokal/overgang; start spilles fra start og slutt prøves fra ti sekunder før slutt. Lyttingen fortsetter til sluttpunktet eller manuell stopp. Trykk et annet sted på lydbølgen eller bruk spolefeltet og Lytt herfra. Dra en markør for å justere. Sett vokalstart her / Sett overgang her bruker aktuell lydposisjon; endringer lagres først eksplisitt. Ukjente punkter merkes og starter ikke en oppdiktet punktprøve. Avslutningsknappen bruker siste30sek når overgang mangler.

Rettelse: En forsinket pausehendelse fra tidligere avspilling skal ikke nullstille en ny aktiv cue-prøve eller play-knapp. Hendelsene kontrollerer faktisk paused-status. Stopp ved fane-/vindusbytte fra PR87 beholdes.

Avgrensning: Nettleserlytting og manuell cue-justering. Originalfiler og eksisterende lagrede punkter endres ikke ved klikking/lytting. Ingen analyse, AI-kall, sending eller automatisk lagring.

Kontroller: Ekte dempet syntetisk media, sammenhengende intro forbi tidligere kortgrense, punktknapp, markørklikk uten punktflytting, spoling/lytting valgt sted, setting fra avspillingsposisjon, ukjent punkt, vindusbytte, avbrutt lasting og eksisterende isolerte bibliotek-/kø-/cue-/mobiltester. Produksjonsutrulling krever konkret autorisasjon.

Automatisk lydbølge og skravering: Lagrede waveformPeaks vises direkte. Mangler de, hentes og SHA-kontrolleres originalen én gang når cue-canvas nærmer seg synlig område (IntersectionObserver); lyd dekodes uten avspilling. Manuell oppdatering/nytt forsøk beholdes. Lydbølgestatus er separat fra cue-lyttestatus for å unngå at automatisk lasting overskriver pågående/stoppet lytting. Intro (start–vokalstart) skraveres grønt, outro (overgang–slutt) lilla. Områdene følger viste, også ulagre­de, punkter. Ukjente eller ugyldige intervaller skraveres ikke. Ingen ny musikalsk strukturanalyse eller automatisk punktlagring.
