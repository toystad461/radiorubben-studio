const $ = id => document.getElementById(id), root = $('music-studio');
const control = root.dataset.control === '1', settings = root.dataset.settings === '1';
let view = null, busy = false, stale = true, proposal = null, policyTrack = null, profileDirty = false;
let refreshing = null, selectedEntry = null, rotationDirty = false;
const rowCache = new WeakMap();
let insertion = null;
const eventLabels={stikk:'Stikk',innlegg:'Innlegg',jingle:'Jingle',reklame:'Reklame',hilsen:'Hilsen',onske:'Ønske',sang:'Sang'};
let monitorContext = null, monitorSeen = new Set(), monitorSources = [], monitorNext = 0, monitorBusy = false;
let monitorAudio = null, monitorStreamUrl = null;
const outcomeLabels = {planned:'Planlagt',started:'Startet',completed:'Fullført',interrupted:'Avbrutt',failed:'Feilet'};
const monitorUnconfigured = 'Testlytting er ikke konfigurert i dette miljøet.';
const txt = (tag, value, cls) => { const n = document.createElement(tag); n.textContent = value; if (cls) n.className = cls; return n; };
const say = value => { $('studio-message').textContent = value; };
const split = value => value.split(',').map(s => s.trim()).filter(Boolean);
const title = t => t.eventType ? `${eventLabels[t.eventType] || 'Innslag'} · ${t.artist ? t.artist+' – ' : ''}${t.title}` : t.kind==='jingle' ? `Jingle · ${t.title}` : `${t.artist || 'Ukjent artist'} – ${t.title}`;
const when = iso => new Intl.DateTimeFormat('nb-NO',{timeZone:'Europe/Oslo',hour:'2-digit',minute:'2-digit',second:'2-digit'}).format(new Date(iso));
const duration = s => Number.isFinite(s) ? `${Math.floor(s/60)}:${String(Math.floor(s%60)).padStart(2,'0')}` : 'ukjent varighet';
function button(label, callback, disabled = false) { const b=txt('button',label);b.type='button';b.disabled=disabled;b.addEventListener('click',callback);return b; }
function options(select, list, selected = []) { select.replaceChildren(...list.map(([value,label])=>{const o=txt('option',label);o.value=value;o.selected=selected.includes(value);return o;})); }
async function request(body) {
  const response=await fetch('/music-studio-api.php',{method:body?'POST':'GET',cache:'no-store',signal:AbortSignal.timeout(15000),...(body?{headers:{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf},body:JSON.stringify(body)}:{})});
  const data=await response.json();if(!response.ok)throw Error(data.error||'Studio svarte ikke.');return data;
}
function renderProfile() {
  if (!view || profileDirty) return;
  const p=view.studio.profiles[$('schedule-program').value] || Object.values(view.studio.profiles)[0];
  $('profile-origin').textContent=p.proposal?'Startverdiene er forslag til utprøving, ikke tidligere vedtatte regler. Se gjennom og lagre egne regler.':'Denne programprofilen er redigert manuelt. Endringene gjelder bare dette programmet.';
  $('profile-name').value=p.name;$('profile-description').value=p.description;
  $('rule-artist').value=p.hard.artistMinutes;$('rule-track').value=p.hard.trackMinutes;$('rule-days').value=p.hard.weekdays.join(', ');
  $('pref-energy').value=p.preferences.energy;$('pref-energy-step').value=p.preferences.maxEnergyStep;$('pref-tempo-step').value=p.preferences.maxTempoStep;$('pref-key').value=p.preferences.keyWeight;$('pref-tags').value=p.preferences.tags.join(', ');$('pref-genres').value=p.preferences.genres.join(', ');
}
function heldEntry(id) {
  const p=view?.player;
  return !!p && (p.current?.id===id || Object.hasOwn(p.reserved||{},id) || Object.hasOwn(p.active||{},id));
}
function hasInflight() {
  const p=view?.player;
  return !!p && (!!p.current || Object.keys(p.reserved||{}).length>0 || Object.keys(p.active||{}).length>0);
}
// Saved occurrences own the list. A partial timing plan must never hide them.
function queueRows() {
  const planned = new Map((view.plan?.rows || []).map(e => [e.id, e]));
  let timingKnown = true;
  return view.entries.map(e => {
    const row = planned.get(e.id);
    if (row && timingKnown) return {...e, ...row};
    timingKnown = false;
    return {...e, plannedAt: null, blocked: ['Spilletid kan ikke beregnes. Kontroller fil, varighet og køen foran dette innslaget.']};
  });
}
function renderQueueStatus() {
  const rows=queueRows(), p=view.player;
  const protectedEntry=e=>!!e && (heldEntry(e.id) || !!p.outcomes?.[e.id]);
  const fixedEntry=e=>!!e && (protectedEntry(e) || e.locked);
  const byId=new Map(rows.map((e,i)=>[e.id,{e,i}]));
  for(const li of $('studio-entries').children) {
    const row=byId.get(li.dataset.entryId);if(!row)continue;
    const {e,i}=row, active=p.current?.id===e.id||Object.hasOwn(p.active||{},e.id), reserved=Object.hasOwn(p.reserved||{},e.id), outcome=p.outcomes?.[e.id];
    li.querySelector('[data-queue-status]').textContent=[duration(e.duration),e.locked?'Låst':'',active?'Aktiv i motoren':reserved?'Overgang låst av motoren':'',outcome?outcomeLabels[outcome.status]||outcome.status:''].filter(Boolean).join(' · ');
    li.dataset.playbackState=active?'active':reserved?'reserved':outcome?.status||'queued';
    const disabled=!control||busy||stale||!!p.pendingCommand||heldEntry(e.id), fixed=disabled||!!outcome||e.locked;
    for(const action of li.querySelectorAll('[data-queue-action]')) {
      const kind=action.dataset.queueAction;
      if(view.studio.rotation?.enabled&&['lock','replace','up','down'].includes(kind)){action.disabled=true;continue;}
      if(kind==='insert'){action.disabled=!insertionAllowed(e.id);action.title=action.disabled?'Plasseringen krever fersk spillerstatus og kan ikke flytte låste eller behandlede innslag.':'Sett inn lagret lyd på denne plassen';continue;}
      if(kind==='next'){action.disabled=fixed||!view.studio.rotation?.enabled||Object.keys(p.reserved||{}).length>0||!!e.hourlyHour;continue;}
      action.disabled=kind==='preview'?(!control||!e.available):kind==='lock'?disabled:fixed||(kind==='replace'&&e.kind==='jingle')||(kind==='up'&&(i===0||fixedEntry(rows[i-1])))||(kind==='down'&&(i===rows.length-1||fixedEntry(rows[i+1])))||(kind==='remove'&&(!!e.hourlyHour||rows.slice(i+1).some(protectedEntry)));
    }
  }
  if($('rotation-form'))for(const e of $('rotation-form').elements)e.disabled=!settings||busy||stale||hasInflight()||['starting','playing'].includes(p.mode);
  $('reset-queue').disabled=!!view.studio.rotation?.enabled||!control||busy||stale||!!p.pendingCommand||hasInflight()||['starting','playing'].includes(p.mode);
  for(const e of $('schedule-form').elements)e.disabled=!!view.studio.rotation?.enabled||!control||busy||stale||hasInflight();
}
function insertionAllowed(afterId) {
  if(!control||busy||stale||!view?.studio.rotation?.enabled||!view.player.online||view.player.pendingCommand)return false;
  const at=view.entries.findIndex(e=>e.id===afterId);if(at<0)return false;
  return !view.entries.slice(at+1).some(e=>heldEntry(e.id)||view.player.outcomes?.[e.id]||e.locked);
}
function openInsertion(afterId) {
  const at=view.entries.findIndex(e=>e.id===afterId);if(at<0||!insertionAllowed(afterId))return;
  insertion={afterId,beforeId:view.entries[at+1]?.id||null};
  $('insert-position').textContent=`Etter ${title(view.entries[at])}${view.entries[at+1]?' · før '+title(view.entries[at+1]):' · sist i køen'}`;
  $('insert-search').value='';$('insert-status').textContent='';renderInsertResults();$('insert-dialog').showModal();
}
function renderInsertResults() {
  if(!insertion)return;
  const type=$('insert-type').value,music=['sang','onske'].includes(type),query=$('insert-search').value.toLocaleLowerCase('nb');
  const matches=view.catalog.filter(t=>(t.kind==='music')===music&&t.available&&t.technicalReady&&!t.jingle?.disabled&&title(t).toLocaleLowerCase('nb').includes(query));
  $('insert-results').replaceChildren(...matches.map(t=>{
    const row=txt('div','','ms-track');row.append(txt('span',`${title(t)} · ${duration(t.duration)}`),button('Sett inn her',async()=>{
      const placement={...insertion};$('insert-status').textContent='Lagrer på valgt plass …';
      const ok=await mutate({action:'insert',...placement,eventType:type,trackId:t.trackId,sha256:t.sha256});
      if(ok){$('insert-dialog').close();insertion=null;}else $('insert-status').textContent=$('studio-message').textContent;
    },!insertionAllowed(insertion.afterId)));return row;
  }));
  if(!matches.length)$('insert-results').append(txt('p','Ingen klargjort lyd passer søket. Last opp eller ferdigstill lyden via lenkene nedenfor.'));
}
function renderCatalogControls() {
  for(const row of $('studio-catalog').children) {
    const buttons=row.querySelectorAll('button');
    if(buttons.length===4){const track=view.catalog.find(t=>t.trackId===row.dataset.rowKey);buttons[3].disabled=!view.studio.rotation?.enabled||!control||busy||stale||!track?.available||!track?.technicalReady||!view.player.online||view.player.pendingCommand||Object.keys(view.player.reserved||{}).length>0;buttons[1].hidden=track?.kind==='jingle';buttons[1].disabled=!!view.studio.rotation?.enabled||!settings||busy||stale;buttons[2].disabled=!!view.studio.rotation?.enabled||!control||busy||stale||!view.catalog.find(t=>t.trackId===row.dataset.rowKey)?.available;}
  }
}
function renderMonitorControl() {
  const p=view.player, active=!!monitorAudio||!!monitorContext, liquidsoap=p.engine==='liquidsoap';
  const configured=typeof p.streamUrl==='string'&&p.streamUrl.length>0;
  $('monitor').disabled=!active&&(!control||stale||!p.online||(liquidsoap&&(!configured||!p.streamConnected)));
  $('monitor').textContent=active?'Slå av monitor':'Lytt til testutgangen';
  if(liquidsoap&&!configured)$('monitor-status').textContent=monitorUnconfigured;
  else if($('monitor-status').textContent===monitorUnconfigured)$('monitor-status').textContent='Av. Start testlytting her; serveravspillingen påvirkes ikke.';
}
// PR #113: a previous terminal message can remain while the next track plays.
// Do not turn an old snapshot into a claim that playback is still running.
function playerMessage(p) {
  const previousOutcome = ['Køen er ferdig.', 'Fullført på isolert testutgang.', 'Fullført i Liquidsoaps PCM-utgang mens teststrømmen var tilkoblet.'];
  if (p.mode !== 'playing' || !p.current || !previousOutcome.includes(p.message)) return p.message;
  return !stale && p.online === true
    ? 'Avspilling pågår på testserveren.'
    : `Avspillingen kan ikke bekreftes. Sist mottatt: ${p.message}`;
}
function renderPlayer() {
  const p=view?.player;if(!p)return;
  const liquidsoap=p.engine==='liquidsoap';
  const modes={starting:liquidsoap?'Klargjør teststrøm':'Klargjør testavspilling',playing:'Spiller på testserveren',paused:'Pauset på VPS',stopped:'Stoppet',blocked:'Stoppet – må kontrolleres',unconfigured:'VPS-spiller ikke installert'};
  $('player-mode').textContent=stale?'Spillerstatus må oppdateres':p.online?(modes[p.mode]||'Ukjent spillerstatus'):(p.mode==='unconfigured'?modes[p.mode]:'Spillertjenesten svarer ikke');
  $('player-connection').textContent=stale?'Motor og utgang: status er uavklart.':`Motor: ${p.online?'svarer':'svarer ikke'}${liquidsoap?' · Icecast: '+(p.online&&p.streamConnected?'tilkoblet':'ikke bekreftet tilkoblet'):' · Utgang: privat lydmonitor'}`;
  $('player-current').textContent=p.current?`${stale||!p.online?'Sist kjent: ':'Nå: '}${title(p.current)} · ${duration(p.position)}`:'Ingen aktiv låt';
  // A reserved occurrence is ahead of the unclaimed next item in the manifest.
  const reserved=Object.values(p.reserved||{}).find(e=>e.id!==p.current?.id);
  const next=reserved||p.next;
  if($('player-next')) $('player-next').textContent=stale||!p.online?'Neste i motoren kan ikke bekreftes.':next?`${reserved?'Reservert neste':'Neste i synkronisert kø'}: ${title(next)}${p.ready===false?' · Køendringer må synkroniseres':''}`:'Ingen neste i synkronisert kø.';
  $('player-detail').textContent=[playerMessage(p),p.pendingCommand?'Venter på kommandobekreftelse':'',p.ready===false?'Køen må synkroniseres':''].filter(Boolean).join(' · ');
  for(const action of ['start','pause','stop']) $('player-'+action).disabled=!control||busy||stale||!p.online||p.pendingCommand;
  if(p.capabilities?.pause===false)$('player-pause').disabled=true;
  $('player-start').textContent=liquidsoap?'Start testavspilling':'Start / fortsett';
  $('player-control-note').textContent=p.capabilities?.pause===false?'Bruk Spill neste på et lagret innslag. Neste innslag kan endres til motoren låser overgangen. Aktiv lyd kan ikke fjernes.':'';
  $('player-output-note').textContent=view.studio?.rotation?.enabled?`Standardrotasjon: ${view.studio.rotation.crossfade} sekunder crossfade mellom sanger. Jingler spilles uten overlapp.`:liquidsoap?'Testavspillingen bruker myke overganger uten overlapp. Motorstatus og Icecast-tilkobling vises hver for seg.':'Monitoren spiller dekodet testlyd fra VPS. Lokal lytting styrer ikke serveravspillingen.';
  $('player-sync').disabled=!control||busy||stale||p.mode==='unconfigured'||p.pendingCommand;
  $('last-completed').textContent=p.lastCompleted?`${title(p.lastCompleted)} · ${when(p.lastCompleted.endedAt)}`:'Ingen fullført testavspilling';
  const outcomes=Object.values(p.outcomes||{}).slice(-8).reverse();
  $('player-events').replaceChildren(...outcomes.map(e=>txt('li',`${title(e)} · ${outcomeLabels[e.status]||e.status}${e.message?' · '+e.message:''}`)));
  if(monitorContext && (liquidsoap || !p.online || p.mode!=='playing')) void stopMonitor('Monitor stoppet; testspilleren har endret status. Serveravspillingen er uendret.');
  if(monitorAudio && (!liquidsoap || !p.online || !p.streamConnected || p.streamUrl!==monitorStreamUrl)) void stopMonitor('Testlytting stoppet; forbindelsen til den valgte teststrømmen er ikke bekreftet. Serveravspillingen er uendret.');
  renderMonitorControl();
  // Player events change seq independently of the saved queue revision. Update
  // only row status and controls so open editors and unsaved fields stay put.
  renderQueueStatus();renderCatalogControls();if($('insert-dialog').open)for(const b of $('insert-results').querySelectorAll('button'))b.disabled=!insertionAllowed(insertion?.afterId);
}
// Reuse unchanged rows, including their focus and scroll position.
function stableRows(parent, items, key, build, signature = JSON.stringify) {
  const focus = parent.contains(document.activeElement) ? document.activeElement : null;
  const scrollTop = parent.scrollTop;
  const old = new Map(Array.from(parent.children, n => [n.dataset.rowKey, n]));
  const cache = rowCache.get(parent) || new Map(); rowCache.set(parent, cache);
  const wanted = new Set();
  items.forEach((item, index) => {
    const id = key(item), stamp = signature(item); wanted.add(id);
    let node = old.get(id);
    if (!node || cache.get(id) !== stamp) {
      const fresh = build(item, index); fresh.dataset.rowKey = id;
      if (node) {
        const focused = node.contains(document.activeElement) ? document.activeElement : null;
        const action = focused?.dataset.queueAction;
        node.replaceChildren(...fresh.childNodes);
        if (action) node.querySelector(`[data-queue-action="${action}"]`)?.focus({preventScroll:true});
      } else node = fresh;
      cache.set(id, stamp);
    }
    if (parent.children[index] !== node) parent.insertBefore(node, parent.children[index] || null);
  });
  for (const node of Array.from(parent.children)) if (!wanted.has(node.dataset.rowKey)) {
    cache.delete(node.dataset.rowKey); node.remove();
  }
  if (focus?.isConnected && document.activeElement !== focus) focus.focus({preventScroll:true});
  parent.scrollTop = scrollTop;
}
function openPreview(track) {
  if (!control || !track.available || !/^[a-f0-9]{32}$/.test(track.trackId) || !/^[a-f0-9]{64}$/.test(track.sha256)) return;
  const audio = $('studio-preview-audio');
  audio.pause(); audio.src = `/music-library-audio.php?id=${track.trackId}&version=${track.sha256}`;
  $('preview-title').textContent = title(track);
  $('preview-details').href = track.kind==='jingle' ? `/jingles.php#jingle-${track.trackId}` : `/music-library.php?detail=1&track=${track.trackId}#track-detail`;
  $('preview-details').textContent=track.kind==='jingle'?'Åpne jingleinnstillinger':'Åpne låtdetaljer og cue-punkter i egen fane';
  $('preview-status').textContent = 'Privat forhåndslytting. Trykk Play for å lytte; sendekø og sending er uendret.';
  if (track.id) selectedEntry = track.id;
  for (const row of $('studio-entries').children) row.classList.toggle('is-selected', row.dataset.entryId === selectedEntry);
  $('preview-dialog').showModal();
}
function stopPreview() {
  const audio=$('studio-preview-audio'); audio.pause(); audio.removeAttribute('src'); audio.load();
}
function render() {
  if(!view)return;
  $('suggest').disabled=!!view.studio.rotation?.enabled||!control||busy||stale||!view.studio.schedule;$('refresh').disabled=busy;$('reset-queue').disabled=!!view.studio.rotation?.enabled||!control||busy||stale||!!view.player.current;
  for(const e of $('schedule-form').elements)e.disabled=!control||busy||stale;
  for(const e of $('profile-form').elements)e.disabled=!!view.studio.rotation?.enabled||!settings||busy||stale;
  const rows=queueRows();
  if(view.libraryError)say(view.libraryError);
  if($('rotation-form')) {
    const r=view.studio.rotation||{enabled:false,crossfade:3};
    if(!rotationDirty){$('rotation-enabled').checked=r.enabled;$('rotation-crossfade').value=r.crossfade;}
    for(const e of $('rotation-form').elements)e.disabled=!settings||busy||stale||hasInflight()||['starting','playing'].includes(view.player.mode);
    $('rotation-status').textContent=r.enabled?`På · ${r.crossfade} sekunder crossfade · planlagt til ${r.plannedUntil?when(r.plannedUntil):'ukjent'}${r.shortage?' · '+r.shortage:''}`:'Av. Velg standardavspilling for å planlegge neste time.';
  }
  if(view.hourBridge?.enabled){const b=view.hourBridge;$('rotation-status').textContent+=' · Timeinnslag: '+(b.libraryFull?'biblioteket er fullt – hele sanger fortsetter':b.ready?'tre musikkglimt klare':b.requestedSeconds?`${Math.round(b.requestedSeconds)} sekunder planlagt – hele sanger brukes til lyden er klar`:'venter på en luke på 18–45 sekunder');}
  $('queue-summary').textContent=`${view.entries.length} innslag · ${view.studio.schedule?view.studio.schedule.date+' · Europe/Oslo':'Velg program, dato og starttid'}`;
  stableRows($('studio-entries'),rows,e=>e.id,(e,i)=>{
    const li=document.createElement('li');li.dataset.entryId=e.id;li.classList.toggle('is-selected',e.id===selectedEntry);
    const status=txt('p','','ms-muted');status.dataset.queueStatus='';
    li.append(txt('strong',`${e.plannedAt?when(e.plannedAt)+' · ':''}${title(e)}`),status);
    if(e.reasons?.length)li.append(txt('p',e.reasons.join(' · '),'ms-reasons'));
    if(e.warnings?.length)li.append(txt('p',e.warnings.join(' · '),'ms-muted'));
    if(e.blocked?.length)li.append(txt('p','Sperret: '+e.blocked.join(' · '),'ms-warning'));
    const actions=txt('div','','ms-actions');
    const rowButton=(kind,label,callback)=>{const b=button(label,callback,true);b.dataset.queueAction=kind;return b;};
    actions.append(rowButton('preview','Forhåndslytt / detaljer',()=>openPreview(e)),rowButton('lock',e.locked?'Lås opp':'Lås',()=>mutate({action:'lock',id:e.id,locked:!e.locked})),rowButton('replace','Bytt',()=>suggest(e.id)),rowButton('up','Opp',()=>mutate({action:'move',id:e.id,beforeId:queueRows()[queueRows().findIndex(row=>row.id===e.id)-1]?.id})),rowButton('down','Ned',()=>mutate({action:'move',id:e.id,beforeId:queueRows()[queueRows().findIndex(row=>row.id===e.id)+2]?.id||null})),rowButton('next','Spill neste',()=>mutate({action:'playnext',id:e.id})),rowButton('remove','Fjern',()=>mutate({action:'remove',id:e.id})));li.append(actions);const gap=rowButton('insert','+ Legg til innslag',()=>openInsertion(e.id));gap.className='ms-insert-gap';gap.setAttribute('aria-label',`Legg til innslag etter ${title(e)}`);li.append(gap);return li;
  });
  if(!rows.length)$('studio-entries').append(txt('li','Køen er tom. Godkjenn musikk og be om forslag.'));
  if(view.plan?.missing.length)$('studio-entries').append(txt('li',view.plan.missing.map(m=>typeof m==='string'?m:JSON.stringify(m)).join(' · '),'ms-warning'));
  const query=$('catalog-search').value.toLocaleLowerCase('nb');
  stableRows($('studio-catalog'),view.catalog.filter(t=>title(t).toLocaleLowerCase('nb').includes(query)),t=>t.trackId,t=>{
    const row=txt('div','','ms-track'), info=document.createElement('div');info.append(txt('strong',title(t)),txt('p',`${duration(t.duration)} · ${t.energy?t.energy+' ('+t.sources.energy+')':'Energi ukjent'} · ${t.bpm?t.bpm+' BPM ('+t.sources.bpm+')':'Tempo ukjent'}`,'ms-muted'));
    info.append(txt('p',Object.entries(t.tags).map(([tag,source])=>`${tag} (${source})`).join(' · '),'ms-muted'));
    info.append(txt('p',t.kind==='jingle'?'Lagret jingle / innslag':t.policy?.blocked?'Manuelt sperret':t.policy?.approved?'Godkjent for: '+t.policy.programs.map(id=>view.studio.profiles[id]?.name||id).join(', '):'Mangler testgodkjenning','ms-warning'));
    const actions=txt('div','','ms-actions');actions.append(button('Forhåndslytt / detaljer',()=>openPreview(t),!control||!t.available),button('Godkjenning / rettelser',()=>editPolicy(t),!settings||busy||stale),button('Legg i kø',()=>mutate({action:'add',trackId:t.trackId,sha256:t.sha256}),!control||busy||stale||!t.available),button('Spill neste',()=>mutate({action:'playnext',trackId:t.trackId,sha256:t.sha256}),true));row.append(info,actions);return row;
  },t=>JSON.stringify([t,view.studio.profiles]));
  renderCatalogControls();
  if(!view.catalog.length)$('studio-catalog').append(txt('p','Biblioteket er tomt. Last opp og klargjør musikk i Musikkbibliotek.'));
  renderPlayer();renderProfile();
}
async function refresh(silent=false) {
  if(busy||refreshing)return;
  refreshing=(async()=>{
  try {
    const data=await request();const first=!view;const changed=first||data.revision!==view.revision||JSON.stringify(data.catalog)!==JSON.stringify(view.catalog);view=data;stale=false;
    if(first){options($('schedule-program'),Object.values(view.studio.profiles).map(p=>[p.programId,p.name]),[view.studio.schedule?.programId||'god-morgen-bomlo']);const now=new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Oslo',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());$('schedule-date').value=view.studio.schedule?.date||now;$('schedule-time').value=view.studio.schedule?.time||'07:00';$('schedule-occurrence').value=view.studio.schedule?.occurrence||'';}
    if(changed){proposal=null;$('proposals').hidden=true;}
    if(!silent)say('Lagret kø og spillerstatus er oppdatert.');
    if(changed||!silent)render();else renderPlayer();
    if(monitorContext)void monitorPump();
  } catch(e){stale=true;say(e.message+' Oppdater før du fortsetter.');render();}
  })();
  try {await refreshing;} finally {refreshing=null;}
}
async function mutate(body) {
  // A click during background polling must wait, not disappear silently.
  if(refreshing)await refreshing;
  if(!control||busy||stale)return false;
  if(body.action==='pause'&&view.player.capabilities?.pause===false)return false;
  if(body.id&&['lock','replace','move','remove','playnext'].includes(body.action)&&heldEntry(body.id)) {
    say('Innslaget er reservert eller aktivt i motoren og kan ikke endres nå.');renderQueueStatus();return false;
  }
  busy=true;render();
  try {const result=await request({...body,revision:view.revision,playerRevision:view.player.seq});if(result.proposal)return result;
    view=result;stale=false;proposal=null;$('proposals').hidden=true;say('Lagret. '+(view.player.pendingCommand?'Venter på spillerens bekreftelse.':''));return true;
  }catch(e){stale=true;say(e.message+' Oppdater før en ny handling.');return false;}
  finally{busy=false;render();}
}
async function suggest(replace=null){
  const result=await mutate({action:replace?'replace':'suggest',...(replace?{id:replace}:{}),commit:false});
  if(!result?.proposal)return;proposal={...result,replace};const p=result.proposal;
  $('proposal-entries').replaceChildren(...p.suggestions.map(t=>{const li=document.createElement('li');li.append(txt('strong',`${when(t.plannedAt)} · ${title(t)}`),txt('p',t.reasons.join(' · '),'ms-reasons'),txt('p',t.warnings.join(' · '),'ms-muted'));return li;}));
  $('deficits').replaceChildren(...p.missing.map(m=>{if(typeof m==='string')return txt('p',m,'ms-warning');const d=document.createElement('div');d.append(txt('p',`Ingen gyldig kandidat til plass ${m.slot} kl. ${when(m.plannedAt)}.`,'ms-warning'));for(const [reason,n]of Object.entries(m.excluded))d.append(txt('p',`${n} låter: ${reason}`));d.append(txt('p','Muligheter: '+m.alternatives.join(' · ')));return d;}));
  $('accept-proposals').disabled=!p.suggestions.length;$('proposals').hidden=false;say('Se gjennom begrunnelsene. Forslagene er ennå ikke lagt i kø.');
}
function editPolicy(t){
  policyTrack=t;const p=t.policy||{approved:false,blocked:false,programs:[],weekdays:[1,2,3,4,5,6,7],periods:[],fields:{}};
  $('policy-title').textContent=title(t);$('policy-approved').checked=p.approved;$('policy-blocked').checked=p.blocked;
  options($('policy-programs'),Object.values(view.studio.profiles).map(p=>[p.programId,p.name]),p.programs);
  options($('policy-periods'),['Natt','Helgemorgen','Morgen','Formiddag','Ettermiddag','Helgekveld','Kveld','Sen kveld'].map(v=>[v,v]),p.periods);
  $('policy-days').value=p.weekdays.join(', ');$('policy-energy').value=Object.hasOwn(p.fields,'energy')?(p.fields.energy||'unknown'):'inherit';$('policy-mood').value=(p.fields.mood||[]).join(', ');$('policy-mood-override').checked=Object.hasOwn(p.fields,'mood');$('policy-error').textContent='';$('policy-dialog').showModal();
}
$('schedule-form').addEventListener('submit',async e=>{e.preventDefault();await mutate({action:'schedule',schedule:{programId:$('schedule-program').value,date:$('schedule-date').value,time:$('schedule-time').value,occurrence:$('schedule-occurrence').value}});});
$('profile-form').addEventListener('input',()=>{profileDirty=true;});
$('schedule-program').addEventListener('change',()=>{profileDirty=false;renderProfile();});
$('profile-form').addEventListener('submit',async e=>{e.preventDefault();const profile={programId:$('schedule-program').value,name:$('profile-name').value,description:$('profile-description').value,hard:{artistMinutes:Number($('rule-artist').value),trackMinutes:Number($('rule-track').value),weekdays:split($('rule-days').value).map(Number)},preferences:{energy:$('pref-energy').value,maxEnergyStep:Number($('pref-energy-step').value),maxTempoStep:Number($('pref-tempo-step').value),keyWeight:Number($('pref-key').value),tags:split($('pref-tags').value),genres:split($('pref-genres').value)}};if(await mutate({action:'profile',profile})){profileDirty=false;renderProfile();}});
$('policy-form').addEventListener('submit',async e=>{e.preventDefault();const fields={};if($('policy-energy').value!=='inherit')fields.energy=$('policy-energy').value==='unknown'?null:$('policy-energy').value;if($('policy-mood-override').checked)fields.mood=split($('policy-mood').value);
 const ok=await mutate({action:'policy',trackId:policyTrack.trackId,sha256:policyTrack.sha256,policy:{approved:$('policy-approved').checked,blocked:$('policy-blocked').checked,programs:Array.from($('policy-programs').selectedOptions,o=>o.value),weekdays:split($('policy-days').value).map(Number),periods:Array.from($('policy-periods').selectedOptions,o=>o.value),fields}});if(ok)$('policy-dialog').close();else $('policy-error').textContent=$('studio-message').textContent;});
$('policy-cancel').addEventListener('click',()=>$('policy-dialog').close());
$('suggest').addEventListener('click',()=>suggest());$('refresh').addEventListener('click',()=>refresh());$('catalog-search').addEventListener('input',render);
$('cancel-proposals').addEventListener('click',()=>{proposal=null;$('proposals').hidden=true;});
$('accept-proposals').addEventListener('click',()=>{if(!proposal)return;const p=proposal;void mutate({action:p.replace?'replace':'suggest',...(p.replace?{id:p.replace}:{}),commit:true,trackIds:p.proposal.suggestions.map(t=>t.trackId)});});
for(const action of ['start','pause','stop','sync'])$('player-'+action).addEventListener('click',()=>mutate({action}));
async function stopMonitor(message='Monitor av. VPS-avspillingen fortsetter uavhengig.'){
  const context=monitorContext, audio=monitorAudio;monitorContext=null;monitorAudio=null;monitorStreamUrl=null;
  if(audio){audio.onerror=null;audio.onended=null;audio.onplaying=null;audio.pause();audio.removeAttribute('src');audio.load();}
  for(const source of monitorSources){try{source.stop();}catch{}}monitorSources=[];monitorSeen.clear();
  $('monitor-status').textContent=message;if(view)renderMonitorControl();
  if(context){try{await context.close();}catch{}}
}
async function monitorPump(){
  if(!monitorContext||monitorBusy||view.player.engine==='liquidsoap')return;monitorBusy=true;const ctx=monitorContext;
  try{for(const segment of view.player.segments||[]){if(monitorSeen.has(segment))continue;monitorSeen.add(segment);const response=await fetch('/music-monitor-audio.php?segment='+encodeURIComponent(segment),{cache:'no-store'});if(!response.ok)throw Error('Testlyden kunne ikke hentes.');const buffer=await ctx.decodeAudioData(await response.arrayBuffer());if(monitorContext!==ctx)break;const source=ctx.createBufferSource();source.buffer=buffer;source.connect(ctx.destination);if(monitorNext<ctx.currentTime+.08){monitorNext=ctx.currentTime+2.5;$('monitor-status').textContent='Bufrer privat monitorlyd. Lyttingen er forsinket; serveravspillingen fortsetter.';}source.start(monitorNext);monitorNext+=buffer.duration;monitorSources.push(source);source.onended=()=>{monitorSources=monitorSources.filter(s=>s!==source);};}if(monitorSeen.size>200)monitorSeen=new Set(view.player.segments||[]);}
  catch{if(monitorContext===ctx)await stopMonitor('Monitor feilet. VPS-avspillingen er uendret.');}finally{monitorBusy=false;}
}
$('monitor').addEventListener('click',async()=>{
  if(monitorAudio||monitorContext)return stopMonitor();
  if($('monitor').disabled||!view)return;
  if(view.player.engine==='liquidsoap') {
    const streamUrl=view.player.streamUrl;
    if(typeof streamUrl!=='string'||!streamUrl)return;
    const audio=new Audio(streamUrl);audio.autoplay=false;audio.preload='none';monitorAudio=audio;monitorStreamUrl=streamUrl;
    const failed=()=>{if(monitorAudio===audio)void stopMonitor('Testlytting feilet. Serveravspillingen er uendret.');};
    audio.onerror=failed;
    audio.onended=()=>{if(monitorAudio===audio)void stopMonitor('Teststrømmen er avsluttet. Serveravspillingen er uendret.');};
    audio.onplaying=()=>{if(monitorAudio===audio)$('monitor-status').textContent='Lytter til den faktiske Icecast-teststrømmen. Lyden kan være forsinket.';};
    $('monitor-status').textContent='Kobler til teststrømmen for lokal lytting …';renderMonitorControl();
    try{await audio.play();}catch{failed();}
    return;
  }
  const context=new AudioContext();monitorContext=context;renderMonitorControl();
  try {
    await context.resume();if(monitorContext!==context)return;
    const segments=view.player.segments||[];monitorSeen=new Set(segments.slice(0,-1));monitorNext=0;
    $('monitor-status').textContent='Lytter til faktisk dekodet testlyd fra VPS. Forsinkelse ca. 5–8 sekunder.';void monitorPump();
  }catch{if(monitorContext===context)await stopMonitor('Monitor kunne ikke startes. VPS-avspillingen er uendret.');}
});
// Closing the tab only closes the monitor. No server pause/stop request is sent.
$('preview-close').addEventListener('click',()=>$('preview-dialog').close());
$('preview-dialog').addEventListener('close',stopPreview);
$('studio-preview-audio').addEventListener('error',()=>{$('preview-status').textContent='Lydfilen kunne ikke åpnes. Sendingen er uendret.';});
document.addEventListener('visibilitychange',()=>{if(document.hidden)stopPreview();});
window.addEventListener('pagehide',()=>{stopPreview();void stopMonitor();});
void refresh();setInterval(()=>void refresh(true),1000);

$('reset-queue').addEventListener('click',()=>{if(window.confirm('Tømme køen for en ny test? Avspillingshistorikken bevares.'))void mutate({action:'reset'});});

if($('rotation-form')){
  $('rotation-form').addEventListener('input',()=>{rotationDirty=true;});
  $('rotation-form').addEventListener('submit',async e=>{e.preventDefault();if(await mutate({action:'standard',rotation:{enabled:$('rotation-enabled').checked,crossfade:Number($('rotation-crossfade').value)}})){rotationDirty=false;render();}});
}

$('insert-type').addEventListener('change',renderInsertResults);$('insert-search').addEventListener('input',renderInsertResults);$('insert-close').addEventListener('click',()=>$('insert-dialog').close());$('insert-refresh').addEventListener('click',async()=>{await refresh();renderInsertResults();});
