# Automatisk lydbølge ved åpning – 14.10.2026

Lydbølgen lastes straks en tilgjengelig låt med varighet åpnes. Lagrede bølgedata gjenbrukes; ellers hentes og SHA-kontrolleres originalen og dekodes uten avspilling. Ingen synlig lasteknapp ved normal åpning eller suksess. En Prøv igjen-knapp vises bare ved feil. Cue-markører og intro/outro-skravering virker som før.

Dette erstatter synlighetsutløst lasting fra PR88, som kunne gi inntrykk av at et klikk var nødvendig. Bølge- og avspillingsstatus holdes adskilt.

Kontrollert med faktisk dempet syntetisk media, automatisk bølge uten klikk/scroll eller audio-src, skjult knapp, punktlytting/spoling/lagring, vindusbytte, analyse/kø og mobilstørrelser. Produksjonsinventar viste seks eldre låter uten fullAudioProfile, tre med lagrede manuelle punkter, fem ikke lagt i kø og ett feilet forsøk. Nytt forsøk på én eksisterende låt er igangsatt som del av feilsøking av manglende cue-forslag; originaler/manuelle punkter er kontrollert separat med SHA256. Nye opplastinger går gjennom eksisterende analysekø med avkrysset automatisk analyse; eldre låter må inn i denne samme køen før forslag finnes.
