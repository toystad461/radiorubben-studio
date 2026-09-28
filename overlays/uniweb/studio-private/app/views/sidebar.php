      <aside class="sidebar">
        <a href="/" class="brand">
          <img src="/assets/radio-rubben-logo.png" alt="Radio Rubben" width="2172" height="724">
          <small>STUDIO / ARBEIDSROM</small>
        </a>
        <?php if (($activePage ?? "") === "ai-studio"): ?>
        <span id="mic-live-banner" class="mic-live-banner" role="status" title="Fader 1-status fra MIDI; bekrefter ikke lydruting eller sending">MIC UKJENT</span>
        <?php endif; ?>
        <div class="nav-label">ARBEIDSROM</div>
        <nav aria-label="Hovedmeny">
<?php
$menu = [
 ['overview','/control.php','LayoutDashboard','Kontrollsenter',''],
 ['sending','/sending.php','FileText','Sending',''],
 ['newsdesk','/newsdesk.php','FileText','Nyhetsdesk',''],
 ['ai-studio','/ai-studio.php','AudioLines','AI Studio','board'],
 ['music','/ai-studio.php#music-title','AudioLines','Musikk & lyd','music'],
 ['articles','/robot.php','FileText','Artikkelutkast',''],
 ['social','/workspace.php?section=social','Cloud','Sosiale medier',''],
 ['rode','/ai-studio.php#rode','AudioLines','Studio (RØDE)','rode'],
 ['settings','/ai-studio.php#innstillinger','SlidersHorizontal','Innstillinger','settings'],
];
foreach ($menu as [$key,$href,$symbol,$label,$target]):
if (in_array($key,['rode','settings'],true) && $user && !studio_can($user,'settings')) continue; ?>
<a href="<?= escape($href) ?>" class="nav-item <?= ($activePage ?? '') === $key ? 'active' : '' ?>"<?= $target ? ' data-studio-nav="'.escape($target).'"' : '' ?>><?= icon($symbol,18) ?> <?= escape($label) ?></a>
<?php endforeach; ?>
<?php if (studio_can($user ?? null, 'users')): ?>
<a href="/users.php" class="nav-item <?= ($activePage ?? '') === 'users' ? 'active' : '' ?>"><?= icon('ShieldCheck',18) ?> Brukere</a>
<?php endif; ?>
        </nav>
        <div class="sidebar-bottom">
          <span class="station-dot"></span> Et sted for gode historier.
          <p>studio.radiorubben.no</p>
        </div>
      </aside>
