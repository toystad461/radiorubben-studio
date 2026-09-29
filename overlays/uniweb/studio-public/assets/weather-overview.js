'use strict';
const clock = document.getElementById('weather-clock');
const clockFormat = new Intl.DateTimeFormat('nb-NO', {timeZone:'Europe/Oslo',hour:'2-digit',minute:'2-digit'});
function tick() {
  if (clock) clock.textContent = clockFormat.format(new Date());
  const loaded = Number(document.getElementById('main')?.dataset.loadedAt || 0);
  const warning = document.getElementById('weather-age-warning');
  if (warning && loaded) warning.hidden = Date.now()/1000 - loaded <= 900;
}
tick(); setInterval(tick, 15000);
const screenButton = document.getElementById('weather-fullscreen');
const page = document.getElementById('main');
if (document.fullscreenEnabled && page?.requestFullscreen && screenButton) {
  screenButton.hidden = false;
  screenButton.addEventListener('click', async () => {
    try { if (document.fullscreenElement) await document.exitFullscreen(); else await page.requestFullscreen(); }
    catch { screenButton.textContent = 'Fullskjerm er ikke tilgjengelig'; }
  });
  document.addEventListener('fullscreenchange', () => {screenButton.textContent = document.fullscreenElement ? 'Avslutt fullskjerm' : 'Fullskjerm';});
}
const copyButton = document.getElementById('weather-copy');
if (copyButton) {
  copyButton.hidden = false;
  copyButton.addEventListener('click', async () => {
    const field = document.getElementById('weather-script-text');
    const status = document.getElementById('weather-copy-status');
    try { await navigator.clipboard.writeText(field.value); status.textContent = 'Værstikket er kopiert.'; }
    catch { field.focus(); field.select(); status.textContent = 'Teksten er markert. Bruk Kopier på enheten din.'; }
  });
}
