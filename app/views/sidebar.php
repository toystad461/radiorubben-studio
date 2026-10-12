<aside class="sidebar">
 <a href="/" class="brand"><img src="/assets/radio-rubben-logo.png" alt="Radio Rubben" width="2172" height="724"><small>STUDIO / ARBEIDSROM</small></a>
 <?php if (($activePage ?? '') === 'ai-studio'): ?>
 <span id="mic-live-banner" class="mic-live-banner" role="status" title="Fader 1-status fra MIDI; bekrefter ikke lydruting eller sending">MIC UKJENT</span>
 <section class="studio-player" aria-labelledby="studio-player-title">
  <div class="studio-player-heading"><h2 id="studio-player-title">Lytt i studio</h2><span aria-hidden="true">♫</span></div>
  <label for="studio-player-source">Lydkilde</label>
  <select id="studio-player-source" <?= studio_can($user ?? null, 'produce') ? '' : 'disabled' ?>><option value="broadcast">Radiosending</option><option value="local">Lokal musikk</option></select>
  <p id="studio-player-state" role="status">Radiosending er ikke tilkoblet.</p>
  <div class="studio-player-controls"><button type="button" id="studio-player-toggle" disabled aria-label="Spill av valgt lydkilde">Spill</button><label for="studio-player-volume">Volum<input type="range" id="studio-player-volume" min="0" max="100" value="100" disabled></label></div>
  <p class="studio-player-note">Lytting i denne fanen. Bekrefter ikke sendestatus.</p>
 </section>
 <?php endif; ?>
 <div class="studio-desktop-menu"><?php require __DIR__.'/navigation.php'; ?></div>
 <details class="studio-mobile-menu"><summary>Studio-meny</summary><?php require __DIR__.'/navigation.php'; ?></details>
 <div class="sidebar-bottom"><span class="station-dot"></span> Et sted for gode historier.<p>studio.radiorubben.no</p></div>
</aside>
