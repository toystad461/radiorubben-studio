# Lytt i studio – første spillerplass, 12.10.2026

Behov: Fast plass for den kommende avspilleren under mikrofonstatus i Studio.
På mobil er den utenfor den lukkede menyen. Base er utrullet PR77, 918b6b5e.

Lydkilde velges mellom Radiosending og Lokal musikk. Ingen radiosendings-URL
er konfigurert/verifisert i Studio; dette valget viser ikke tilkoblet og har
deaktiverte avspillings-/volumkontroller. Ingen ekstern spiller eller leverandør
er koblet til. Neste integrasjonssteg trenger en verifisert lyttekilde.

Lokal musikk bruker eksisterende music-audio, ikke en ny spiller. Spill/pause
krever at en lokal låt er lastet, og volum gjelder samme element. Det lokale
musikkbiblioteket er fortsatt avhengig av eksisterende lokalt spiller-API;
denne leveransen aktiverer ikke musikkopplasting på Uniweb.

Ingen lyd starter ved sidelasting eller bytte av lydkilde. Kildebytte stopper
ikke eksisterende lokal avspilling; status sier fra hvis lokal musikk spiller
mens Radiosending er valgt. Avspilling er ikke bevis på sending. Lytting til
radiosending, lokal musikk og faktisk sending skal fortsatt være adskilt.
Forhåndslytting av stikk/TTS er ikke koblet til denne plassen i første versjon.

Observatører beholder deaktivert betjening og ingen Studio-JS. Andre sider
får ikke spillerfeltet. Ingen endring i godkjenning, TTS, publisering, MIDI,
køstyring, automatikk, tilgang eller private data.

Kontroller: faktisk PHP-visning og Studio-JS med syntetiske API-er ved
320/390/768/1280/1440 px, plassering under mikrofon, synlighet med lukket
mobilmeny, ukjent radiosending, sperret avspilling uten lokal lyd, kildevalg
og volum på eksisterende element. Eksisterende kø og manusgodkjenning testes
videre. Ingen ekte lydproduksjon/avspilling eller innholdsgodkjenning.
