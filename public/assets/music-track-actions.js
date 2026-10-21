(() => {
  const menus = [...document.querySelectorAll('.track-actions')];
  for (const menu of menus) menu.addEventListener('toggle', () => {
    if (menu.open) for (const other of menus) if (other !== menu) other.open = false;
  });
  document.addEventListener('click', event => {
    for (const menu of menus) if (!menu.contains(event.target)) menu.open = false;
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    for (const menu of menus) if (menu.open) {
      menu.open = false;
      menu.querySelector('summary').focus();
    }
  });
  function revealEditor() {
    const id = location.hash.slice(1);
    if (!['edit-track', 'cue-points', 'album-cover'].includes(id)) return;
    const target = document.getElementById(id);
    if (target?.tagName === 'DETAILS') target.open = true;
    target?.scrollIntoView({block: 'start'});
  }
  window.addEventListener('hashchange', revealEditor);
  revealEditor();
  const status = document.getElementById('track-action-status');
  const buttons = [...document.querySelectorAll('[data-track-queue]')];
  let busy = false;
  async function request(options = {}) {
    const response = await fetch('/music-studio-api.php', {
      credentials: 'same-origin', cache: 'no-store', ...options
    });
    let data;
    try { data = await response.json(); } catch { throw Error('Køen kunne ikke bekreftes. Åpne musikkøen før du prøver igjen.'); }
    if (!response.ok) throw Error(data.error || 'Køen kunne ikke bekreftes. Åpne musikkøen før du prøver igjen.');
    return data;
  }
  for (const button of buttons) button.addEventListener('click', async () => {
    if (busy) return;
    busy = true;
    buttons.forEach(b => { b.disabled = true; });
    status.textContent = 'Kontrollerer køen …';
    let submitted = false;
    try {
      const before = await request();
      if (!Number.isSafeInteger(before.revision) || !Array.isArray(before.entries)) throw Error('Køstatus mangler. Åpne musikkøen.');
      if (!before.studio?.schedule) throw Error('Velg program og dato i musikkøen først. Låten er ikke lagt til.');
      const trackId = button.dataset.trackQueue, sha256 = button.dataset.trackVersion;
      const csrf = document.querySelector('[data-queue-csrf]').dataset.queueCsrf;
      submitted = true;
      const after = await request({method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
        body: JSON.stringify({action: 'add', revision: before.revision, trackId, sha256})});
      const oldIds = new Set(before.entries.map(e => e.id));
      if (!Array.isArray(after.entries) || !after.entries.some(e => !oldIds.has(e.id) && e.trackId === trackId && e.sha256 === sha256)) {
        throw Error('Køendringen kunne ikke bekreftes.');
      }
      status.textContent = 'Låten er lagt sist i VPS-køen.';
      button.closest('details')?.removeAttribute('open');
    } catch (error) {
      status.textContent = (error.message || 'Køen svarte ikke.') + (submitted ? ' Kontroller musikkøen før nytt forsøk; endringen kan være lagret.' : '');
    } finally {
      busy = false;
      buttons.forEach(b => { b.disabled = false; });
    }
  });
})();
