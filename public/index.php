<?php
declare(strict_types=1);
if (!in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/', '/index.php'], true)) {
    http_response_code(404);
    exit('Siden finnes ikke.');
}
require dirname(__DIR__) . '/app/bootstrap.php';
$user = current_user();
$ready = $config['auth_mode'] === 'entra';
if ($ready && !$user) redirect('/login.php');
$integrations = require dirname(__DIR__) . '/app/integrations/catalog.php';
require dirname(__DIR__) . '/app/views/head.php';
?>
    <div class="shell">
      <aside class="sidebar">
        <a href="/" class="brand">
          <img src="/assets/radio-rubben-logo.png" alt="Radio Rubben" width="2172" height="724">
          <small>STUDIO / ARBEIDSROM</small>
        </a>
        <div class="nav-label">ARBEIDSROM</div>
        <nav aria-label="Hovedmeny">
          <a href="/" class="nav-item active" aria-current="page">
            <?= icon('LayoutDashboard', 18) ?>
            Oversikt
          </a>
          <?php if ($user): ?><a href="/robot.php" class="nav-item"><?= icon('FileText', 18) ?> Redaksjonell innboks</a><?php endif; ?>
          <?php if (studio_is_admin($user)): ?><a href="/admin/users.php" class="nav-item"><?= icon('ShieldCheck', 18) ?> Brukere og tilgang</a><?php endif; ?>
          <a href="#integrasjoner" class="nav-item">
            <?= icon('SlidersHorizontal', 18) ?>
            Integrasjoner
          </a>
          <a href="#kom-i-gang" class="nav-item">
            <?= icon('ShieldCheck', 18) ?>
            Kom i gang
          </a>
        </nav>
        <div class="sidebar-bottom">
          <span class="station-dot" /> Et sted for gode historier.
          <p>studio.radiorubben.no</p>
        </div>
      </aside>
      <div class="workspace">
        <header class="topbar">
          <span>
            Arbeidsrom <span class="slash">/</span> 
            <strong>Oversikt</strong>
          </span>
          <span class="version">
            STUDIO <b>01</b>
          </span>
        </header>
        <main id="main">
          <div class="heading-row">
            <div>
              <p class="eyebrow">RADIO RUBBEN STUDIO</p>
              <h1>God radio starter her.</h1>
              <p class="intro">
                Ditt samlingspunkt for innhold, samarbeid og gode sendinger.
              </p>
            </div>
            <span class="mode">
              <span></span>
              <?= $user ? "Innlogget" : "Demonstrasjon" ?>
            </span>
          </div>
          <section class="hero" aria-labelledby="hero-title">
            <div class="hero-copy">
              <span class="hero-label">
                <span></span> DITT DIGITALE KONTROLLROM
              </span>
              <h2 id="hero-title">
                Mer tid til radio.
                <br />
                <em>Alt samlet på ett sted.</em>
              </h2>
              <p>
                Fra den første ideen til historien som når ut.
                <br />
                Her bygger vi arbeidsrommet til Radio Rubben.
              </p>
              <a href="#integrasjoner" class="button light">
                Utforsk arbeidsrommet <?= icon('ArrowRight', 17) ?>
              </a>
            </div>
            <div class="radio-art" aria-hidden="true">
              <div class="orbit orbit-one" ></div>
              <div class="orbit orbit-two" ></div>
              <div class="orbit orbit-three" ></div>
              <div class="radio-center">
                <img src="/assets/radio-rubben-logo.png" alt="" width="2172" height="724">
              </div>
              <span class="frequency">RADIO RUBBEN — NÆR DEG</span>
            </div>
          </section>
          <section id="integrasjoner" class="section">
            <div class="section-heading">
              <div>
                <p class="eyebrow">VERKTØYENE DINE</p>
                <h2>Ett studio. Flere muligheter.</h2>
              </div>
              <span class="small">Vi bygger videre, steg for steg.</span>
            </div>
            <div class="cards">
              <?php foreach ($integrations as $item): ?>
              <article class="integration-card">
                <div class="card-top"><span class="tool-icon <?= escape($item['id']) ?>"><?= icon($item['icon'], 26) ?></span><span class="badge">Ikke tilkoblet</span></div>
                <p class="eyebrow"><?= escape($item['category']) ?></p><h3><?= escape($item['title']) ?></h3>
                <p><?= escape($item['description']) ?></p>
                <details><summary>Se hva som kommer <?= icon('ArrowUpRight') ?></summary><p><?= escape($item['planned']) ?>. Integrasjonen er planlagt og henter ingen data ennå.</p></details>
              </article>
              <?php endforeach; ?>
              <article class="integration-card">
                <div class="card-top">
                  <span class="tool-icon microsoft">
                    <?= icon('ShieldCheck', 26) ?>
                  </span>
                  <span class="badge">
                    <?= $ready ? "Konfigurert" : "Klargjort" ?>
                  </span>
                </div>
                <p class="eyebrow">TILGANG OG SAMARBEID</p>
                <h3>Microsoft 365</h3>
                <p>
                  <?= $user ? "Du er innlogget som " . escape($user["name"]) . "." : "Én arbeidskonto. En felles inngang for medarbeiderne våre." ?>
                </p>
                <div class="card-action">
                  <?php if ($user): ?>
                  <form method="post" action="/logout.php"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><button class="button secondary">Logg ut</button></form>
                  <?php else: ?><a href="/login.php">Om Microsoft-innlogging <?= icon('ArrowUpRight') ?></a><?php endif; ?>
                </div>
              </article>
            </div>
          </section>
          <section id="kom-i-gang" class="getting-started">
            <div>
              <p class="eyebrow">NESTE STEG</p>
              <h2>Et godt grunnlag er på plass.</h2>
              <p>
                Studioet er klart for videre utvikling. Slik tar vi det i bruk.
              </p>
            </div>
            <ol>
              <li>
                <span>01</span>
                <div>
                  <strong>Gi medarbeiderne tilgang</strong>
                  <p>Konfigurer Microsoft Entra ID for organisasjonen.</p>
                </div>
              </li>
              <li>
                <span>02</span>
                <div>
                  <strong>Koble til ressursene</strong>
                  <p>Bygg filbiblioteket med OneDrive og Microsoft Graph.</p>
                </div>
              </li>
              <li>
                <span>03</span>
                <div>
                  <strong>Gjør veien til publisering kortere</strong>
                  <p>Koble WordPress til studioets arbeidsflyt.</p>
                </div>
              </li>
            </ol>
          </section>
          <footer>
            <span>
              Radio Rubben <b>Studio</b>
            </span>
            <span>
              <?= $ready ? "Internt arbeidsrom" : "PHP-versjon · Ingen eksterne tjenester er tilkoblet" ?>
            </span>
          </footer>
        </main>
      </div>
    </div>

</body></html>
