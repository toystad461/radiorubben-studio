const $ = id => document.getElementById(id);
const root = $('main'), audio = $('queue-audio');
const canControl = root.dataset.canControl === '1';
let snapshot = JSON.parse($('queue-initial').textContent), busy = false, stale = !snapshot;
let current = null, history = [], finished = new Set(), playVersion = 0, advancing = false;
audio.volume = 0.8;
const label = entry => `${entry.artist} – ${entry.title}`;
const time = seconds => Number.isFinite(seconds) ? `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}` : '—';
const say = message => { $('queue-message').textContent = message; };
const next = () => snapshot?.entries.find(entry => !finished.has(entry.id) && entry.id !== current?.id);
const text = (tag, value, className) => { const node = document.createElement(tag); node.textContent = value; if (className) node.className = className; return node; };
function button(title, action, key, disabled = false) {
  const node = text('button', title); node.type = 'button'; node.disabled = disabled;
  node.dataset.focusKey = key; node.addEventListener('click', action); return node;
}
function controls() {
  $('queue-play').disabled = !canControl || busy || stale || advancing || !next();
  $('queue-pause').disabled = !canControl || !current || !!audio.error;
  $('queue-pause').textContent = audio.paused ? 'Fortsett' : 'Pause';
  $('queue-stop').disabled = !canControl || !current;
  $('queue-clear').disabled = !canControl || busy || stale || !snapshot?.entries.length;
  $('queue-refresh').disabled = busy;
  $('queue-restart').disabled = !canControl || busy || advancing;
  $('queue-next').textContent = next() ? label(next()) : 'Ingen flere låter i denne prøvelyttingen';
}
function render() {
  const focusKey = document.activeElement?.dataset.focusKey;
  const catalog = $('queue-catalog'), list = $('queue-entries'); catalog.replaceChildren(); list.replaceChildren();
  const query = $('queue-search').value.trim().toLocaleLowerCase('nb');
  const matches = (snapshot?.catalog || []).filter(entry => label(entry).toLocaleLowerCase('nb').includes(query));
  $('queue-library-count').textContent = `${matches.length} låter`;
  for (const entry of matches) {
    const row = document.createElement('li'), info = document.createElement('div');
    info.append(text('strong', entry.title), text('span', entry.artist), text('small', `${time(entry.duration)}${entry.available ? '' : ' · Fil mangler'}`));
    const add = button('Legg til', () => change({action:'add', trackId:entry.trackId, sha256:entry.sha256}), `add-${entry.trackId}`, !canControl || busy || stale || !entry.available || snapshot.entries.length >= 100);
    add.setAttribute('aria-label', `Legg ${label(entry)} i kø`); row.append(info, add); catalog.append(row);
  }
  if (!matches.length) catalog.append(text('li', snapshot?.catalog.length ? 'Ingen treff.' : 'Biblioteket er tomt. Åpne musikkbiblioteket og last opp MP3-filer.'));
  let total = 0, unknown = 0;
  for (const [index, entry] of (snapshot?.entries || []).entries()) {
    if (Number.isFinite(entry.duration)) total += entry.duration; else unknown++;
    const row = document.createElement('li'); row.dataset.id = entry.id;
    row.draggable = canControl && !busy && !stale;
    if (entry.id === current?.id) row.classList.add('queue-current-row');
    if (finished.has(entry.id)) row.classList.add('queue-finished-row');
    const info = document.createElement('div'); info.className = 'queue-entry-info';
    const status = entry.id === current?.id ? ' · Valgt i spilleren' : finished.has(entry.id) ? ' · Behandlet i denne fanen' : '';
    info.append(text('strong', entry.title), text('span', entry.artist), text('small', `${time(entry.duration)}${status}${entry.available ? '' : ' · Fil mangler eller er endret'}`));
    const actions = document.createElement('div'); actions.className = 'queue-entry-actions';
    const disabled = !canControl || busy || stale;
    const up = button('Opp', () => change({action:'move', id:entry.id, beforeId:snapshot.entries[index-1].id}), `up-${entry.id}`, disabled || index === 0);
    const down = button('Ned', () => change({action:'move', id:entry.id, beforeId:snapshot.entries[index+2]?.id || null}), `down-${entry.id}`, disabled || index === snapshot.entries.length-1);
    const remove = button('Fjern', () => change({action:'remove', id:entry.id}), `remove-${entry.id}`, disabled);
    up.setAttribute('aria-label', `Flytt ${entry.title} opp`); down.setAttribute('aria-label', `Flytt ${entry.title} ned`); remove.setAttribute('aria-label', `Fjern ${entry.title} fra kø`);
    actions.append(up, down, remove); row.append(text('span', String(index+1), 'queue-position'), info, actions);
    row.addEventListener('dragstart', event => { if (!row.draggable) return event.preventDefault(); event.dataTransfer.setData('text/plain', entry.id); event.dataTransfer.effectAllowed = 'move'; });
    row.addEventListener('dragover', event => { if (row.draggable) event.preventDefault(); });
    row.addEventListener('drop', event => {
      event.preventDefault(); const id = event.dataTransfer.getData('text/plain');
      if (!disabled && id !== entry.id && snapshot.entries.some(item => item.id === id)) void change({action:'move', id, beforeId:entry.id});
    });
    list.append(row);
  }
  if (!snapshot?.entries.length) list.append(text('li', 'Køen er tom. Velg låter fra biblioteket.', 'queue-empty'));
  $('queue-total').textContent = snapshot ? `${snapshot.entries.length} innslag · ${time(total)}${unknown ? ` + ${unknown} med ukjent varighet` : ''}` : 'Køen er utilgjengelig';
  $('queue-current').textContent = current ? label(current) : 'Ingen låt valgt';
  $('queue-history').replaceChildren(...history.slice(-8).reverse().map(item => text('li', `${label(item)} · ${item.outcome}`)));
  controls();
  if (focusKey) document.querySelector(`[data-focus-key="${CSS.escape(focusKey)}"]`)?.focus({preventScroll:true});
}
async function request(body) {
  const response = await fetch('/music-queue-api.php', {
    method: body ? 'POST' : 'GET', cache:'no-store', signal:AbortSignal.timeout(12000),
    ...(body ? {headers:{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf}, body:JSON.stringify(body)} : {}),
  });
  const data = await response.json();
  if (!response.ok) throw Error(data.error || 'Køen kunne ikke bekreftes.');
  if (!snapshot || data.revision >= snapshot.revision) snapshot = data;
  stale = false;
}
async function refresh() {
  if (busy) return;
  busy = true; render();
  try { await request(); say('Køen er oppdatert. Avspillingen i denne fanen er uendret.'); }
  catch (error) { stale = true; $('queue-autoplay').checked = false; say(`${error.message} Automatisk fortsettelse er av.`); }
  finally { busy = false; render(); }
}
async function change(body) {
  if (!canControl || busy || stale) return;
  busy = true; render();
  try { await request({...body, revision:snapshot.revision}); say('Køen er lagret.'); }
  catch (error) {
    // Never replay a timed-out mutation: it may already have committed.
    stale = true; $('queue-autoplay').checked = false;
    say(`${error.message} Trykk Oppdater for å se lagret kø før du gjør flere endringer.`);
  } finally { busy = false; render(); }
}
function record(outcome) {
  if (!current) return;
  finished.add(current.id); history.push({...current, outcome}); history = history.slice(-100);
}
function fail(message) {
  $('queue-autoplay').checked = false; audio.pause(); $('queue-player-state').textContent = 'Avspilling stoppet'; say(message); controls();
}
async function playNext(automatic = false) {
  if (!canControl || advancing || busy || stale) return;
  advancing = true; const version = ++playVersion; controls();
  try {
    await request();
    if (version !== playVersion || (automatic && !$('queue-autoplay').checked)) return;
    let entry = next(); const skipped = [];
    while (entry && !entry.available) { finished.add(entry.id); skipped.push(entry.title); entry = next(); }
    if (!entry) {
      if (skipped.length) say(`Hoppet over utilgjengelige filer: ${skipped.join(', ')}. Ingen neste låt klar.`);
      else say('Prøvelyttingen er ferdig. Legg til flere låter eller begynn køen på nytt.');
      return;
    }
    if (current && !audio.ended) record('Avbrutt');
    audio.pause(); current = {...entry}; audio.src = entry.audioUrl;
    $('queue-player-state').textContent = 'Laster lyd …'; render();
    await audio.play();
    if (version !== playVersion) return;
    say(skipped.length ? `Spiller neste. Hoppet over utilgjengelige filer: ${skipped.join(', ')}.` : 'Prøvelytter via enhetens valgte lydutgang.');
  } catch (error) {
    if (version !== playVersion) return;
    stale = true; fail(`${error.message} Trykk Oppdater før neste forsøk.`);
  } finally { if (version === playVersion) advancing = false; render(); }
}
function stop() {
  playVersion++; advancing = false; $('queue-autoplay').checked = false;
  audio.pause(); audio.removeAttribute('src'); audio.load(); current = null;
  $('queue-player-state').textContent = 'Stoppet'; $('queue-progress').textContent = '—'; render();
}
$('queue-search').addEventListener('input', render);
$('queue-refresh').addEventListener('click', refresh);
$('queue-clear').addEventListener('click', () => {
  if (window.confirm('Tømme den lagrede køen? Låten som prøvelyttes nå, fortsetter.')) void change({action:'clear'});
});
$('queue-play').addEventListener('click', () => void playNext());
$('queue-pause').addEventListener('click', async () => {
  if (!canControl || !current) return;
  if (!audio.paused) audio.pause();
  else { try { await audio.play(); } catch { fail('Kunne ikke fortsette. Kontroller lydfil og lydutgang.'); } }
});
$('queue-stop').addEventListener('click', () => { stop(); say('Prøvelyttingen er stoppet. Lagret kø er uendret.'); });
$('queue-restart').addEventListener('click', () => { if (!canControl) return; stop(); history = []; finished.clear(); render(); say('Klar fra første låt. Trykk Spill neste når du vil begynne.'); });
$('queue-volume').addEventListener('input', () => { if (canControl) audio.volume = Number($('queue-volume').value) / 100; });
$('queue-autoplay').addEventListener('change', () => say($('queue-autoplay').checked ? 'Fortsetter etter at låten er ferdig. Start første låt med Spill neste.' : 'Neste låt startes manuelt.'));
audio.addEventListener('playing', () => { $('queue-player-state').textContent = 'Spiller i denne fanen'; controls(); });
audio.addEventListener('pause', () => { if (current && !audio.ended) $('queue-player-state').textContent = 'Pauset'; controls(); });
audio.addEventListener('waiting', () => { if (current) $('queue-player-state').textContent = 'Laster lyd …'; });
audio.addEventListener('timeupdate', () => { $('queue-progress').textContent = current ? `${time(audio.currentTime)} / ${time(audio.duration)} · ${time(Math.max(0,audio.duration-audio.currentTime))} igjen` : '—'; });
audio.addEventListener('error', () => { if (current) fail('Lydfilen kunne ikke spilles. Automatisk fortsettelse er stoppet. Velg neste låt eller kontroller filen.'); });
audio.addEventListener('ended', () => {
  record('Ferdigspilt'); current = null; $('queue-player-state').textContent = 'Ferdig'; render();
  if ($('queue-autoplay').checked) void playNext(true);
});
window.addEventListener('pagehide', stop, {once:true});
render();
