# Albumcover i Studio

Henter støttet innebygd JPEG/PNG ved MP3-opplasting, inkludert gjenbruk av en identisk fil når cover mangler. Eksisterende låter får knappen Hent cover fra MP3. Admin/produsent kan laste opp eget bilde eller velge plassholder. Manuelle bilder og eksplisitt plassholder overskrives aldri automatisk.

Bilder lagres privat som atomiske JSON-filer i musikkbibliotekets covers-mappe, kildebundet til originalens SHA256. Egen revisjon/CSRF/rolle og lås beskytter endring. Bibliotekets delte lås hindrer samtidig sletting under skriving. Originalen, bibliotekregisteret, cue-punktene og AI-vurderingen endres ikke. Sletting av låt forsøker også å rydde coverfiler. Ingen eksterne oppslag eller publisering.

Kun JPEG/PNG, inntil 2 MiB, 4096 per side og 16 millioner piksler. Bildedata leveres gjennom innlogget endepunkt med fast MIME, nosniff, sandbox og private/no-store. Filnavn fra bruker brukes aldri i lagringssti. SVG-opplasting er avvist; plassholderen er fast servergenerert grafikk. Manglende/skadet cover gir plassholder og stopper ikke lydanalyse.

Visning: biblioteksrad, låtdetaljer og bibliotekets forhåndslytter. Ingen endring i separat VPS-playout-grensesnitt. Internt i Studio; ingen automatisk distribusjon av grafikk til nettside eller strøm.

Base er PR103s selektivt installerte 8ace4d83. Kun public/music-library.php endres blant eksisterende kjørefiler; førhash i VPS-snapshot 39631001793 er c0ed0a0df93e6e90638463973efc0722671fa7ec1788622186780019c3256507, lik valgt base. Nye filer går i allerede eksisterende app/views og public/assets. Ikke publiser hele grenen, da aktiv VPS inneholder andre selektive rettelser.
