import { initWeather } from './services/weather.mjs';
import { AiStatus, watchStudioServer } from './services/status.mjs';
import { initMusic } from './services/music.mjs';
import { HueFollower } from './services/hue.mjs';
import { validFader, faderLive } from './services/fader.mjs';
import { cleanFeedback, rememberRejection, feedbackPrompt } from './services/feedback.mjs';
import { canAutoPlace } from './services/review.mjs';
import { StudioEvents, StudioLog } from './services/events.mjs';
import { MidiService } from './services/midi.mjs';
import { ACTIONS, PadMapping } from './services/mapping.mjs';
import { interpretRode } from './services/rodecaster.mjs';
import { emptyContext, demoContext, estimateSeconds, prepareDraft } from './services/producer.mjs';
const $ = id => document.getElementById(id);
const say = text => { $('feedback').textContent = text; };
const bus = new StudioEvents(() => say('En studiokomponent svarte ikke. Manuell drift er fortsatt tilgjengelig.'));
const log = new StudioLog(bus);
const midi = new MidiService(bus);
const mapping = new PadMapping(bus);
const key = 'rubben.ai-studio.mapping.v1';
const hue = new HueFollower($('main').dataset.csrf, message => { $('hue-status').textContent=message; $('light-status').textContent=message; });
$('hue-follow').onchange=()=>hue.setEnabled($('hue-follow').checked);
window.addEventListener('pagehide',()=>hue.close());
const faderKey = 'rubben.ai-studio.fader1.v1';
let micFader = null, faderLearning = null, faderSample = null, micLive = null;
try { const saved=JSON.parse(localStorage.getItem(faderKey) || 'null'); if (validFader(saved)) micFader=saved; } catch {}
function showMic(live) {
  hue.update(live);
  const label = live === true ? 'MIC LIVE · fader 1 oppe' : live === false ? 'MIC AV · fader 1 nede' : 'Ukjent · venter på faderbevegelse';
  $('mic-status').textContent = label;
  if (live !== micLive) {
    micLive = live;
    $('physical-status').textContent = label;
    bus.emit('MIC_FADER_CHANGED', {log: live === null ? 'Fader 1-status ukjent' : label});
  }
  $('mic-live-banner').textContent = live === true ? 'MIC LIVE' : live === false ? 'MIC AV' : 'MIC UKJENT';
  $('mic-live-banner').classList.toggle('is-live',live === true);
}
function stopFaderLearning() {
  faderLearning=null; faderSample=null;
  $('learn-mic-fader').textContent='Lær fader 1'; $('cancel-mic-fader').disabled=true;
}

let context = emptyContext(), monitor = true, messages = [], learn = null, suggestion = null, renderPending = false;
let confirmedPort = '', previousPort = '';
let generating = false, revision = 0, suggestionOrigin = 'Lokal mal';
let undoDraft = null, lastAction = 'new', styleFeedback = [];
const feedbackKey = 'rubben.ai-studio.feedback.v1';
try { styleFeedback = cleanFeedback(JSON.parse(localStorage.getItem(feedbackKey) || '[]')); } catch {}
const aiReady = $('main').dataset.aiReady === 'true';
const aiStatus = new AiStatus(text => { $('ai-status').textContent=text; }, aiReady);
bus.on('AI_STATUS', ({payload:s}) => aiStatus.update(s.source,s.busy,s.message));
watchStudioServer($('site-status'));
try { const saved = localStorage.getItem(key); if (saved) mapping.set(JSON.parse(saved)); }
catch { say('Lagret mapping kunne ikke leses. Alle fysiske pads er deaktivert til ny mapping lagres.'); }
const clock = iso => new Date(iso).toLocaleTimeString('nb-NO', {hour12:false});
function scheduleRender() {
  if (renderPending) return; renderPending = true;
  setTimeout(() => { renderPending = false; renderMonitor(); renderLog(); }, 80);
}
function renderLog() {
  $('studio-log').replaceChildren(...log.rows.slice(0,30).map(row => {
    const li = document.createElement('li'); const time = document.createElement('time');
    time.textContent = clock(row.timestamp); time.dateTime = row.timestamp;
    li.append(time, document.createElement('br'), row.type.replaceAll('_',' '));
    if (row.detail) li.append(document.createElement('br'), row.detail);
    return li;
  }));
}
function renderMonitor() {
  $('midi-rows').replaceChildren(...messages.slice(0,100).map(row => {
    const tr = document.createElement('tr');
    for (const value of [row.timestamp,row.channel ?? '—',row.type,row.number ?? '—',row.value ?? '—',row.function]) {
      const td = document.createElement('td'); td.textContent = String(value); tr.append(td);
    }
    return tr;
  }));
  $('monitor-status').textContent = `${monitor ? 'Monitor kjører' : 'Monitor pauset'} · ${messages.length} events (viser siste 100)`;
}
function estimate() { $('estimate').textContent = `Estimert: ${estimateSeconds($('draft').value)} sek`; }
function resetSuggestion() { revision++; suggestion = null; $('suggestion-preview').hidden = true; $('use-suggestion').disabled = true; $('suggestion-status').textContent = $('auto-prepare').checked ? 'Venter på nytt låtbytte. Manuset ditt beholdes.' : 'Automatisk klargjøring er av. Manuset ditt beholdes.'; }
function updateContext(next) {
  const changed = context.nowPlaying !== next.nowPlaying;
  context = next; resetSuggestion();
  $('program-title').textContent = context.program ?? 'Velg programkontekst';
  $('context-source').textContent = context.source === 'demo' ? 'TESTDATA · Ingen faktisk sendestatus' : context.source === 'local' ? 'Lokalt musikkbibliotek · sendestatus ukjent' : 'Ingen sendekilde tilkoblet';
  $('now-playing').textContent = context.nowPlaying ?? 'Venter på musikkdata';
  $('next-track').textContent = context.next ?? 'Ikke tilgjengelig';
  if (changed && context.nowPlaying) bus.emit('SONG_START', {log:context.source === 'demo' ? 'Testdata · simulert låtbytte' : 'Kontekst oppdatert'});
}
function stageSuggestion(text, origin) {
  suggestion = text; suggestionOrigin = origin;
  $('suggestion-preview').textContent = text; $('suggestion-preview').hidden = false;
  $('use-suggestion').disabled = false;
  $('suggestion-status').textContent = `${origin} · ca. ${estimateSeconds(text)} sek. Les gjennom før du bruker forslaget.`;
  bus.emit('BREAK_PREPARED', {log:`${origin} · venter på godkjenning`});
}
async function action(action, local = false) {
  if (action === 'lightsOff') { $('hue-follow').checked=false; await hue.allOff(); return; }
  if (action === 'open') { showView(false); history.replaceState(null, '', location.pathname); $('producer-title').scrollIntoView({behavior:'smooth'}); return; }
  if (generating) { say('AI arbeider allerede med et forslag.'); return; }
  lastAction = action;
  if (local || !aiReady) {
    stageSuggestion(prepareDraft(action,context,$('draft').value), 'Lokal mal · ikke AI-generert');
    say('Lokal mal er klar for gjennomlesing.'); return;
  }
  resetSuggestion(); const version = revision; const autoReview = $('auto-review').checked;
  generating = true; $('reject-regenerate').disabled = true; aiStatus.update('producer',true,'Skriver og kontrollerer stikk …');
  $('suggestion-status').textContent = autoReview ? 'AI skriver og kontrollerer stikket. Du kan fortsatt redigere manuset.' : 'AI skriver. Du kan fortsatt redigere manuset.';
  const controller = new AbortController(); const timeout = setTimeout(()=>controller.abort(), 75000);
  try {
    const response = await fetch('/producer-api.php', {method:'POST', credentials:'same-origin', signal:controller.signal,
      headers:{'Content-Type':'application/json', 'X-CSRF-Token':$('main').dataset.csrf},
      body:JSON.stringify({action, autoReview, feedback:feedbackPrompt(styleFeedback), notes:$('producer-notes').value, draft:$('draft').value,
        program:context.program ?? '', nowPlaying:context.nowPlaying ?? '', next:context.next ?? ''})});
    const result = await response.json();
    if (!response.ok || typeof result.text !== 'string') throw new Error(result.error || 'AI kunne ikke lage et forslag.');
    aiStatus.update('producer',false,'Stikk mottatt · '+clock(new Date()));
    if (version !== revision) { say('Manus eller kontekst ble endret. Be om et nytt forslag når du er klar.'); return; }
    stageSuggestion(result.text, 'AI-generert');
    if (canAutoPlace({enabled:$('auto-review').checked, requested:autoReview, review:result.review, unchanged:version === revision})) {
      applySuggestion(true);
    } else if (autoReview) {
      const reasons = Array.isArray(result.review?.reasons) ? result.review.reasons.join(' ') : 'Kontrollen ga ikke en godkjenning.';
      $('suggestion-status').textContent = `Til gjennomlesing: ${reasons || 'Automatisk innsetting er slått av.'}`;
      say('Forslaget er klart til manuell gjennomlesing. Manuset er beholdt.');
      bus.emit('BREAK_REVIEW_HELD', {log:'Automatisk kontroll · manuell gjennomlesing nødvendig'});
    } else { say('AI-forslaget er klart. Les gjennom og velg om du vil bruke det.'); }
  } catch (error) {
    aiStatus.update('producer',false,'Stikk feilet · manuell drift tilgjengelig');
    $('suggestion-status').textContent = 'Ingen nytt AI-forslag. Manuset er beholdt.';
    say(error.name === 'AbortError' ? 'AI brukte for lang tid. Prøv igjen eller velg lokal mal.' : error instanceof SyntaxError ? 'AI svarte ikke som forventet. Bruk lokal mal eller prøv igjen.' : error.message);
    bus.emit('AI_ERROR', {log:'Generering feilet · manuell drift tilgjengelig'});
  } finally { clearTimeout(timeout); generating = false; $('reject-regenerate').disabled = false; }
}
function renderPads() {
  $('pad-board').replaceChildren(...mapping.rows.map(row => {
    const button = document.createElement('button'); button.type = 'button';
    const label = document.createElement('small'); label.textContent = `PAD ${row.pad} · ${row.enabled ? 'MIDI aktiv' : 'skjermknapp'}`;
    button.append(label,ACTIONS[row.action]); button.onclick = () => bus.emit('PRODUCER_ACTION',{action:row.action,log:`Skjerm · ${ACTIONS[row.action]}`});
    return button;
  }));
}
function field(tag, row, name, value, options) {
  const el = document.createElement(tag); el.dataset.field = name; el.setAttribute('aria-label',`PAD ${row.pad}: ${name}`);
  if (tag === 'select') for (const [v,label] of Object.entries(options)) el.add(new Option(label,v));
  else { el.type = name === 'enabled' ? 'checkbox' : 'number'; el.min = name === 'channel' ? '1':'0'; el.max = name === 'channel' ? '16':'127'; el.step='1'; }
  if (name === 'enabled') el.checked = value; else el.value = value;
  return el;
}
function renderMapping() {
  $('mapping-rows').replaceChildren(...mapping.rows.map(row => {
    const tr = document.createElement('tr'); tr.dataset.pad = row.pad;
    for (const name of ['enabled','channel','type','number','value','action']) {
      const td = document.createElement('td');
      td.append(field(['type','action'].includes(name) ? 'select':'input',row,name,row[name],name === 'type' ? {cc:'CC',noteon:'Note On'} : ACTIONS));
      if (name === 'enabled') td.append(` PAD ${row.pad}`); tr.append(td);
    }
    const td = document.createElement('td'), button = document.createElement('button');
    button.type = 'button'; button.textContent = 'Lær'; button.onclick = () => {
      learn = row.pad; $('cancel-learn').disabled = false;
      $('mapping-status').textContent = `Lærer PAD ${learn}: trykk pad-en. Andre MIDI-handlinger er pauset mens innlæring pågår.`;
    }; td.append(button); tr.append(td); return tr;
  }));
}
function cancelLearn() { learn=null; $('cancel-learn').disabled=true; }
bus.on('MIDI_MESSAGE', ({payload:{message,portId}}) => {
  let faderState = null;
  if (faderLearning) {
    if (message.type === 'cc') faderSample={...message,portId};
  } else {
    faderState=faderLive(micFader,message,portId); if (faderState !== null) {
      showMic(faderState);
      $('physical-status').textContent = `Fader 1 ${faderState ? 'oppe' : 'nede'} · verdi ${message.value}`;
    }
  }
  const pad = mapping.match(message);
  const interpreted = confirmedPort === portId ? interpretRode(message) : null;
  const row = {...message,function:faderState !== null ? `Fader 1 ${faderState ? 'oppe' : 'nede'}` : pad ? `PAD ${pad.pad} · ${ACTIONS[pad.action]}` : interpreted?.label ?? 'Ukjent / ikke mappet'};
  $('last-midi').textContent = `Siste event: ${row.timestamp} · ${row.type} · kanal ${row.channel ?? '—'}`;
  if (monitor) { messages.unshift(row); messages.length = Math.min(messages.length,300); }
  if (learn !== null) {
    if (['cc','noteon'].includes(message.type)) {
      const tr = document.querySelector(`[data-pad="${learn}"]`);
      for (const name of ['channel','type','number','value']) tr.querySelector(`[data-field="${name}"]`).value = message[name];
      $('mapping-status').textContent = `PAD ${learn} innlært. Kontroller verdiene, huk av Aktiv og lagre.`;
      cancelLearn();
    }
  } else {
    if (interpreted && !pad && faderState === null) {
      $('physical-status').textContent = interpreted.label;
      bus.emit(interpreted.type,{channel:message.channel,log:interpreted.label});
    }
    if (!faderLearning) {
      if (pad) $('physical-status').textContent = `PAD ${pad.pad} · ${ACTIONS[pad.action]}`;
      mapping.handle(message);
    }
  }
  scheduleRender();
});
bus.on('MIDI_STATUS', ({payload:s}) => {
  const connectedId = s.connected ? s.inputId : '';
  if (!s.connected || connectedId !== previousPort) { showMic(null); stopFaderLearning(); }
  if (connectedId !== previousPort) {
    $('rode-confirm').checked = false; confirmedPort=''; previousPort = connectedId;
    $('physical-status').textContent = 'Ingen bekreftet tilstand'; cancelLearn();
  }
  $('output-status').textContent = s.outputConnected ? `Tilkoblet · ${s.output}` : 'Ikke valgt · mottak av MIDI fungerer uten';
  $('midi-status').textContent = s.connected ? `Tilkoblet · ${s.input}` : 'Frakoblet';
  for (const kind of ['input','output']) {
    const select = $(`midi-${kind}`);
    select.replaceChildren(new Option('Ingen valgt',''), ...s[`${kind}s`].map(p => new Option(`${p.name}${p.manufacturer ? ` · ${p.manufacturer}`:''}`,p.id)));
    if (s[`${kind}Id`] && !s[`${kind}s`].some(p=>p.id===s[`${kind}Id`])) select.add(new Option('Valgt port · frakoblet',s[`${kind}Id`]));
    select.value = s[`${kind}Id`]; select.disabled = !midi.access || midi.stopped;
  }
  $('rode-confirm').disabled = !s.connected;
  $('disconnect-midi').disabled = !midi.access || midi.stopped;
  if (s.error) say(s.error); else if (!s.connected) say('Velg MIDI-inngang når enheten er tilgjengelig. Manuell produsent er klar.');
});
bus.on('PRODUCER_ACTION', ({payload}) => action(payload.action));
bus.on('SONG_START', () => {
  if (!$('auto-prepare').checked) return;
  void action('new');
});
bus.on('*',scheduleRender);
for (const button of document.querySelectorAll('[data-action]')) button.onclick = () => bus.emit('PRODUCER_ACTION',{action:button.dataset.action,log:ACTIONS[button.dataset.action]});
$('draft').oninput = () => { revision++; undoDraft=null; $('undo-draft').disabled=true; $('draft-origin').textContent='Manuelt redigert'; estimate(); };
$('producer-notes').oninput = () => { revision++; };
$('local-template').onclick = () => { void action('new', true); };
function applySuggestion(automatic = false) {
  if (suggestion === null) return;
  undoDraft = {text:$('draft').value, origin:$('draft-origin').textContent};
  $('undo-draft').disabled = false;
  $('draft').value = suggestion; const origin = suggestionOrigin;
  resetSuggestion(); estimate();
  $('draft-origin').textContent = automatic ? 'AI-generert · automatisk kontrollert' : origin;
  if (automatic) {
    $('suggestion-status').textContent = 'Kontroll bestått · stikket er satt inn i manusfeltet. Du kan angre.';
    say('Stikket er automatisk kontrollert og satt inn i manusfeltet.');
  }
  bus.emit('BREAK_DISPLAYED', {log:automatic ? 'Automatisk kontroll bestått · manus oppdatert' : `Operatør godkjente ${origin}`});
}
$('use-suggestion').onclick = () => applySuggestion();
$('undo-draft').onclick = () => {
  if (!undoDraft) return;
  $('draft').value = undoDraft.text; $('draft-origin').textContent = undoDraft.origin;
  undoDraft = null; $('undo-draft').disabled = true; resetSuggestion(); estimate();
  say('Forrige manus er gjenopprettet.'); bus.emit('BREAK_UNDONE', {log:'Forrige manus gjenopprettet'});
};
$('auto-review').onchange = () => {
  revision++;
  $('approval-mode').textContent = $('auto-review').checked ? 'Automatisk kontroll og innsetting er på i denne fanen.' : 'Manuell godkjenning er på.';
};
$('demo-data').onchange = () => updateContext($('demo-data').checked ? demoContext():emptyContext());
$('auto-prepare').onchange = () => { resetSuggestion(); if (!$('auto-prepare').checked) $('suggestion-status').textContent = 'Automatisk klargjøring er av.'; };
$('connect-midi').onclick = async () => {
  if ($('main').dataset.midiEnabled !== 'true') return;
  if (!window.isSecureContext || !navigator.requestMIDIAccess) { say('USB MIDI krever en nettleser med Web MIDI på HTTPS eller localhost. Prøv Chrome eller Edge.'); return; }
  $('connect-midi').disabled=true;
  try { await midi.start(); } finally { $('connect-midi').disabled=false; }
};
$('disconnect-midi').onclick = () => { void midi.stop(); };
for (const kind of ['input','output']) $(`midi-${kind}`).onchange = e => { void midi.select(kind,e.target.value); };
$('rode-confirm').onchange = () => { confirmedPort = $('rode-confirm').checked ? midi.inputId : ''; $('physical-status').textContent='Ingen bekreftet tilstand'; };
$('monitor-toggle').onclick = () => { monitor=!monitor; $('monitor-toggle').textContent=monitor ? 'Stop Monitor':'Start Monitor'; renderMonitor(); };
$('monitor-clear').onclick = () => { messages=[]; renderMonitor(); };
$('monitor-copy').onclick = async () => {
  try { await navigator.clipboard.writeText(['timestamp\tchannel\ttype\tCC/note\tvalue\tfunction',...messages.slice().reverse().map(r=>[r.timestamp,r.channel,r.type,r.number,r.value,r.function].join('\t'))].join('\n')); say('MIDI-loggen er kopiert.'); }
  catch { say('Nettleseren tillot ikke kopiering. Marker tabellen og kopier manuelt.'); }
};
$('save-mapping').onclick = () => {
  try {
    const rows = [...$('mapping-rows').children].map(tr => {
      const row={pad:Number(tr.dataset.pad)};
      for (const input of tr.querySelectorAll('[data-field]')) row[input.dataset.field] = input.type === 'checkbox' ? input.checked : input.type === 'number' ? (input.value === '' ? NaN : Number(input.value)) : input.value;
      return row;
    });
    mapping.set(rows); cancelLearn(); renderPads();
    try { localStorage.setItem(key,JSON.stringify(mapping.rows)); $('mapping-status').textContent='Mapping lagret i denne nettleseren.'; }
    catch { $('mapping-status').textContent='Mapping er aktiv i denne fanen, men nettleseren tillater ikke varig lagring.'; }
    bus.emit('MAPPING_UPDATED',{log:'Pad-konfigurasjon oppdatert'});
  } catch (error) { $('mapping-status').textContent=error.message; }
};
$('cancel-learn').onclick = () => { cancelLearn(); $('mapping-status').textContent='Innlæring avbrutt.'; };
$('clear-log').onclick = () => { log.clear(); renderLog(); };
$('export-log').onclick = () => {
  const url=URL.createObjectURL(new Blob([log.export()],{type:'text/plain;charset=utf-8'}));
  const a=document.createElement('a'); a.href=url;a.download=`radio-rubben-studiologg-${new Date().toISOString().slice(0,10)}.txt`;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
};
window.addEventListener('pagehide',()=>{ void midi.stop(); });
const tick = () => { const now=new Date(); $('studio-clock').textContent = now.toLocaleTimeString('nb-NO',{timeZone:'Europe/Oslo',hour12:false}); $('studio-date').textContent=now.toLocaleDateString('nb-NO',{timeZone:'Europe/Oslo',weekday:'long',day:'numeric',month:'long'}); }; tick(); setInterval(tick,1000);
renderMapping(); renderPads(); renderMonitor(); bus.emit('STUDIO_READY',{log:'Manuell drift · ingen AI kreves'});

// Switch views in the same document: keep MIDI, manuscript and session log alive.
function showView(settings) {
  $('producer-view').hidden = settings;
  $('settings-view').hidden = !settings;
  $('studio-page-title').textContent = settings ? 'Innstillinger' : 'AI Studio';
  document.title = `${settings ? 'Innstillinger' : 'AI Studio'} – Radio Rubben Studio`;
  for (const link of document.querySelectorAll('[data-studio-nav]')) {
    const active = link.dataset.studioNav === ({'#innstillinger':'settings','#rode':'rode','#music-title':'music','#producer-title':'scripts'}[location.hash] || 'board');
    link.classList.toggle('active', active);
    if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
  }
}
function routeView() {
  if ($('main').dataset.settingsAllowed === 'false' && ['#innstillinger','#rode'].includes(location.hash)) history.replaceState(null,'',location.pathname);
  showView(['#innstillinger', '#rode'].includes(location.hash));
  const target = {'#music-title':'music-title','#producer-title':'producer-title','#rode':'settings'}[location.hash];
  if (target) document.getElementById(target)?.scrollIntoView({block:'start'}); else window.scrollTo(0, 0);
}
for (const link of document.querySelectorAll('[data-studio-nav]')) link.addEventListener('click', event => {
  if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  event.preventDefault();
  history.pushState(null, '', new URL(link.href).pathname + new URL(link.href).hash);
  routeView();
});
window.addEventListener('hashchange', routeView);
window.addEventListener('popstate', routeView);
routeView();

function renderFeedbackMemory(saved = true) {
  $('feedback-memory').replaceChildren(...styleFeedback.map(row => {
    const li = document.createElement('li'); li.textContent = `${row.reason} — «${row.excerpt}»`; return li;
  }));
  $('feedback-memory-status').textContent = `${styleFeedback.length} av 3 tilbakemeldinger. ${saved ? 'Lagres lokalt i denne nettleseren.' : 'Nettleseren tillot ikke lagring. Endringen gjelder bare denne fanen.'}`;
}
function saveFeedbackMemory() {
  let saved = true;
  try { if (styleFeedback.length) localStorage.setItem(feedbackKey,JSON.stringify(styleFeedback)); else localStorage.removeItem(feedbackKey); } catch { saved = false; }
  renderFeedbackMemory(saved);
}
$('reject-regenerate').onclick = () => {
  if (generating) return;
  const rejected = suggestion ?? $('draft').value;
  if (!rejected.trim()) { say('Lag et stikk først, så kan du avvise det og be om et nytt.'); return; }
  styleFeedback = rememberRejection(styleFeedback, rejected, $('rejection-reason').value);
  saveFeedbackMemory(); $('rejection-reason').value = '';
  // Remove an explicitly rejected active script before requesting its replacement.
  // A rejected pending suggestion never changes the active manuscript.
  if (suggestion === null) {
    $('draft').value = undoDraft?.text ?? '';
    $('draft-origin').textContent = undoDraft?.origin ?? 'Venter på nytt stikk';
    undoDraft = null; $('undo-draft').disabled = true; estimate();
  }
  resetSuggestion(); bus.emit('BREAK_REJECTED', {log:'Stikk avvist · tilbakemelding brukes i neste forslag'});
  void action(lastAction);
};
$('forget-feedback').onclick = () => { styleFeedback = []; revision++; saveFeedbackMemory(); say('Tilbakemeldinger er fjernet fra denne fanen. Lagringsstatus vises under Rubbens stil.'); };
renderFeedbackMemory();

$('learn-mic-fader').onclick = () => {
  if (!midi.input || midi.stopped) { say('Koble til MIDI før du lærer fader 1.'); return; }
  if (!faderLearning) {
    faderLearning={step:'down'}; faderSample=null; showMic(null);
    $('learn-mic-fader').textContent='Lagre nedeposisjon'; $('cancel-mic-fader').disabled=false;
    $('mic-fader-status').textContent='Flytt kun fader 1 helt ned. Trykk deretter «Lagre nedeposisjon». Pad-handlinger er pauset under innlæring.'; return;
  }
  if (!faderSample) { $('mic-fader-status').textContent='Venter på bevegelse fra fader 1. Flytt faderen før du lagrer.'; return; }
  if (faderLearning.step === 'down') {
    faderLearning={step:'up',down:faderSample}; faderSample=null;
    $('learn-mic-fader').textContent='Lagre oppeposisjon';
    $('mic-fader-status').textContent='Flytt kun fader 1 opp til normal taleposisjon. Trykk deretter «Lagre oppeposisjon».'; return;
  }
  const down=faderLearning.down, up=faderSample;
  if (down.portId !== up.portId || down.channel !== up.channel || down.number !== up.number || down.value === up.value) {
    $('mic-fader-status').textContent='Posisjonene må komme fra samme fader og ha ulike verdier. Avbryt og prøv igjen med kun fader 1.'; return;
  }
  micFader={portId:up.portId,channel:up.channel,number:up.number,down:down.value,up:up.value};
  let saved=true; try {localStorage.setItem(faderKey,JSON.stringify(micFader));} catch {saved=false;}
  stopFaderLearning(); showMic(faderLive(micFader,up,up.portId));
  $('mic-fader-status').textContent=`Fader 1 innlært · kanal ${up.channel}, CC ${up.number}. ${saved ? 'Lagret i denne nettleseren.' : 'Kun aktiv i denne fanen.'}`;
};
$('cancel-mic-fader').onclick=()=>{stopFaderLearning();showMic(null);$('mic-fader-status').textContent='Innlæring avbrutt. Flytt tidligere konfigurert fader for å oppdatere indikatoren.';};
if (micFader) $('mic-fader-status').textContent='Fader 1 er konfigurert. Flytt faderen etter tilkobling for å vise aktuell posisjon.';

initMusic(bus, updateContext, emptyContext);

initWeather();
