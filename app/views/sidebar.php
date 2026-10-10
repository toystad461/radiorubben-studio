<aside class="sidebar">
 <a href="/" class="brand"><img src="/assets/radio-rubben-logo.png" alt="Radio Rubben" width="2172" height="724"><small>STUDIO / ARBEIDSROM</small></a>
 <?php if (($activePage ?? '') === 'ai-studio'): ?>
 <span id="mic-live-banner" class="mic-live-banner" role="status" title="Fader 1-status fra MIDI; bekrefter ikke lydruting eller sending">MIC UKJENT</span>
 <?php endif; ?>
 <div class="studio-desktop-menu"><?php require __DIR__.'/navigation.php'; ?></div>
 <details class="studio-mobile-menu"><summary>Studio-meny</summary><?php require __DIR__.'/navigation.php'; ?></details>
 <div class="sidebar-bottom"><span class="station-dot"></span> Et sted for gode historier.<p>studio.radiorubben.no</p></div>
</aside>