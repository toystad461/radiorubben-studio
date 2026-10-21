# Albumcover og låtvalg

Cover hentes automatisk fra innebygd MP3-bilde ved opplasting. JPEG/PNG kan velges manuelt (2 MiB, opptil 4096×4096 og 16 millioner piksler). Manglende cover gir Radio Rubben-plassholder. Private bildefiler følger låtens originalhash og egen revisjon; manuelle valg beholdes. Original, cue-punkter og AI-resultater endres ikke.

⋯ ved hver låt åpner redigering, cue-punkter, cover og detaljer. «Legg sist i køen» bruker eksisterende music-studio-api.php med produsentrolle, CSRF og fersk køversjon. Program/dato må være valgt først. Ingen startkommando sendes. Ved usikkert svar skal operatøren kontrollere køen før nytt forsøk; klienten gjentar aldri POST automatisk. Menyen fungerer med tastatur og Escape.

Testet køkontrakt samsvarer med VPS-snapshot: music-studio.php SHA256 50b2cf650bf6d12b03885d7964499cfe7e326feb34bb1adc599fdb089b4c399e; music-studio-api.php SHA256 f89b4ea7753f9e4fd3d627a0db99b744744c5305e882993ad63a0f86d33a4093. Disse runtime-filene skal ikke erstattes av denne leveransen.

Validering: PHP coverregresjon via tests/run.php; node tests/recovered/test-music-track-actions.mjs. Gateway skal kontrollere nøyaktige før/etter-hasher før selektiv utrulling. Nettbasert coveroppslag er ikke med.
