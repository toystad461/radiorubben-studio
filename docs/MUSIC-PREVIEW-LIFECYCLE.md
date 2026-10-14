# Forhåndslytting og cue-avspilling – feilretting 14.10.2026

Behov: Lyd skal stoppe ved vindus-/fanebytte, play/pause skal vise faktisk avspilling, og cue-knapper skal fungere på en nylig åpnet låt.

Funnet: Eksisterende forhåndslytting lyttet bare på pagehide. Fane- og vindusbytte utløser normalt visibilitychange/blur. Cue-lytting ventet på loadedmetadata uten å starte lasting i audio med preload=none. Tidligere avspillingstester brukte simulert play og readyState og kunne derfor ikke fange dette.

Rettelse: Stopp og nullstill kontrollene ved blur, skjult dokument og pagehide. Ingen automatisk gjenstart ved retur. Play/pause-knappen følger native mediahendelser og aktiv kilde, også når cue-knapper eller avspillerens egne kontroller brukes. Cue-lytting laster metadata eksplisitt før posisjon settes. Ventende metadata avbrytes ved kontekstbytte; forsinket svar får ikke starte lyd. Segmentgrenser og manuelle cue-punkter beholdes.

Avgrensning: Nettleserbasert forhåndslytting. Ingen endring av lydoriginaler, cue-lagring, analysealgoritme, rotasjon eller sending.

Kontroller: Ekte HTMLMediaElement-avspilling med dempet syntetisk PCM, ny preload=none-spiller, intro/avslutning, startposisjon/sluttgrense, native play/pause, vindusblur, faktisk fanebytte/retur og forsinket metadata etter avbrudd. Eksisterende isolerte bibliotektester dekker import, dubletter, tilgang/CSRF, analyse/kø, cue-lagring og mobilbredder. Ingen ekte produksjonsavspilling utført.

Ferdigkriterier: Ikke lyd etter kontekstbytte; knapper viser faktisk avspilling; cue-knapper virker uten at ordinær play startes først; ingen sen gjenstart etter avbrudd. Produksjonsutrulling og operatørens lyttetest er separate fra lokal kontroll.
