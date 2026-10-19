const $ = id => document.getElementById(id), root = $('music-studio');
const control = root.dataset.control === '1', settings = root.dataset.settings === '1';
let view = null, busy = false, stale = true, proposal = null, policyTrack = null, profileDirty = false;
let monitorContext = null, monitorSeen = new Set(), monitorSources = [], monitorNext = 0, monitorBusy = false;
const txt = (tag, value, cls) => { const n = document.createElement(tag); n.textContent = value; if (cls) n.className = cls; return n; };
const say = value => { $('studio-message').textContent = value; };
const split = value => value.split(',').map(s => s.trim()).filter(Boolean);
const title = t => `${t.artist || 'Ukjent artist'} – ${t.title}`;
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
function renderPlayer() {
  const p=view?.player;if(!p)return;
  const modes={playing:'Spiller på VPS',paused:'Pauset på VPS',stopped:'Stoppet',blocked:'Stoppet – må kontrolleres',unconfigured:'VPS-spiller ikke installert'};
  $('player-mode').textContent=p.online?modes[p.mode]:(p.mode==='unconfigured'?modes[p.mode]:'Spillertjenesten svarer ikke');
  $('player-current').textContent=p.current?`${title(p.current)} · ${duration(p.position)}`:'Ingen aktiv låt';
  $('player-detail').textContent=[p.message,p.pendingCommand?'Venter på kommandobekreftelse':'',p.ready===false?'Køen må synkroniseres':''].filter(Boolean).join(' · ');
  for(const action of ['start','pause','stop']) $('player-'+action).disabled=!control||busy||stale||!p.online||p.pendingCommand;
  $('player-sync').disabled=!control||busy||stale||p.mode==='unconfigured'||p.pendingCommand;
  $('monitor').disabled=!control||!p.online;
  $('last-completed').textContent=p.lastCompleted?`${title(p.lastCompleted)} · ${when(p.lastCompleted.endedAt)}`:'Ingen fullført testavspilling';
  const labels={planned:'Planlagt',started:'Startet',completed:'Fullført',interrupted:'Avbrutt',failed:'Feilet'};
  const outcomes=Object.values(p.outcomes||{}).slice(-8).reverse();
  $('player-events').replaceChildren(...outcomes.map(e=>txt('li',`${title(e)} · ${labels[e.status]||e.status}${e.message?' · '+e.message:''}`)));
  if(monitorContext && (!p.online || p.mode!=='playing')) void stopMonitor('Monitor stoppet; testspilleren er '+(p.online?'pauset eller stoppet.':'utilgjengelig.'));
}
function render() {
  if(!view)return;
  $('suggest').disabled=!control||busy||stale||!view.studio.schedule;$('refresh').disabled=busy;$('reset-queue').disabled=!control||busy||stale||!!view.player.current;
  for(const e of $('schedule-form').elements)e.disabled=!control||busy||stale;
  for(const e of $('profile-form').elements)e.disabled=!settings||busy||stale;
  const rows=view.plan?.rows||view.entries;
  if(view.libraryError)say(view.libraryError);
  $('queue-summary').textContent=`${view.entries.length} innslag · ${view.studio.schedule?view.studio.schedule.date+' · Europe/Oslo':'Velg program, dato og starttid'}`;
  $('studio-entries').replaceChildren(...rows.map((e,i)=>{
    const li=document.createElement('li');const outcome=view.player.outcomes?.[e.id];const current=view.player.current?.id===e.id;
    li.append(txt('strong',`${e.plannedAt?when(e.plannedAt)+' · ':''}${title(e)}`),txt('p',`${duration(e.duration)}${e.locked?' · Låst':''}${current?' · Aktiv på VPS':''}${outcome?' · '+outcome.status:''}`,'ms-muted'));
    if(e.reasons?.length)li.append(txt('p',e.reasons.join(' · '),'ms-reasons'));
    if(e.warnings?.length)li.append(txt('p',e.warnings.join(' · '),'ms-muted'));
    if(e.blocked?.length)li.append(txt('p','Sperret: '+e.blocked.join(' · '),'ms-warning'));
    const disabled=!control||busy||stale||current, fixed=disabled||!!outcome||e.locked;
    const actions=txt('div','','ms-actions');
    actions.append(button(e.locked?'Lås opp':'Lås',()=>mutate({action:'lock',id:e.id,locked:!e.locked}),disabled),button('Bytt',()=>suggest(e.id),fixed),button('Opp',()=>mutate({action:'move',id:e.id,beforeId:rows[i-1].id}),fixed||i===0),button('Ned',()=>mutate({action:'move',id:e.id,beforeId:rows[i+2]?.id||null}),fixed||i===rows.length-1),button('Fjern',()=>mutate({action:'remove',id:e.id}),fixed));li.append(actions);return li;
  }));
  if(!rows.length)$('studio-entries').append(txt('li','Køen er tom. Godkjenn musikk og be om forslag.'));
  if(view.plan?.missing.length)$('studio-entries').append(txt('li',view.plan.missing.map(m=>typeof m==='string'?m:JSON.stringify(m)).join(' · '),'ms-warning'));
  const query=$('catalog-search').value.toLocaleLowerCase('nb');
  $('studio-catalog').replaceChildren(...view.catalog.filter(t=>title(t).toLocaleLowerCase('nb').includes(query)).map(t=>{
    const row=txt('div','','ms-track'), info=document.createElement('div');info.append(txt('strong',title(t)),txt('p',`${duration(t.duration)} · ${t.energy?t.energy+' ('+t.sources.energy+')':'Energi ukjent'} · ${t.bpm?t.bpm+' BPM ('+t.sources.bpm+')':'Tempo ukjent'}`,'ms-muted'));
    info.append(txt('p',Object.entries(t.tags).map(([tag,source])=>`${tag} (${source})`).join(' · '),'ms-muted'));
    info.append(txt('p',t.policy?.blocked?'Manuelt sperret':t.policy?.approved?'Godkjent for: '+t.policy.programs.map(id=>view.studio.profiles[id]?.name||id).join(', '):'Mangler testgodkjenning','ms-warning'));
    const actions=txt('div','','ms-actions');actions.append(button('Godkjenning / rettelser',()=>editPolicy(t),!settings||busy||stale),button('Legg i kø',()=>mutate({action:'add',trackId:t.trackId,sha256:t.sha256}),!control||busy||stale||!t.available));row.append(info,actions);return row;
  }));
  if(!view.catalog.length)$('studio-catalog').append(txt('p','Biblioteket er tomt. Last opp og klargjør musikk i Musikkbibliotek.'));
  renderPlayer();renderProfile();
}
async function refresh(silent=false) {
  if(busy)return;busy=true;
  try {
    const data=await request();const first=!view;const changed=first||data.revision!==view.revision;view=data;stale=false;
    if(first){options($('schedule-program'),Object.values(view.studio.profiles).map(p=>[p.programId,p.name]),[view.studio.schedule?.programId||'god-morgen-bomlo']);const now=new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Oslo',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());$('schedule-date').value=view.studio.schedule?.date||now;$('schedule-time').value=view.studio.schedule?.time||'07:00';$('schedule-occurrence').value=view.studio.schedule?.occurrence||'';}
    if(changed){proposal=null;$('proposals').hidden=true;}
    if(!silent)say('Lagret kø og spillerstatus er oppdatert.');
    busy=false;if(changed||!silent)render();else renderPlayer();
    if(monitorContext)void monitorPump();
  } catch(e){stale=true;say(e.message+' Oppdater før du fortsetter.');busy=false;renderPlayer();}
}
async function mutate(body) {
  if(!control||busy||stale)return false;busy=true;render();
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
  const context=monitorContext;monitorContext=null;for(const source of monitorSources){try{source.stop();}catch{}}monitorSources=[];monitorSeen.clear();if(context)await context.close();$('monitor').textContent='Lytt til testutgangen';$('monitor-status').textContent=message;
}
async function monitorPump(){
  if(!monitorContext||monitorBusy)return;monitorBusy=true;const ctx=monitorContext;
  try{for(const segment of view.player.segments||[]){if(monitorSeen.has(segment))continue;monitorSeen.add(segment);const response=await fetch('/music-monitor-audio.php?segment='+encodeURIComponent(segment),{cache:'no-store'});if(!response.ok)throw Error('Testlyden kunne ikke hentes.');const buffer=await ctx.decodeAudioData(await response.arrayBuffer());if(monitorContext!==ctx)break;const source=ctx.createBufferSource();source.buffer=buffer;source.connect(ctx.destination);monitorNext=Math.max(monitorNext,ctx.currentTime+.08);source.start(monitorNext);monitorNext+=buffer.duration;monitorSources.push(source);source.onended=()=>{monitorSources=monitorSources.filter(s=>s!==source);};}if(monitorSeen.size>200)monitorSeen=new Set(view.player.segments||[]);}
  catch{if(monitorContext===ctx)await stopMonitor('Monitor feilet. VPS-avspillingen er uendret.');}finally{monitorBusy=false;}
}
$('monitor').addEventListener('click',async()=>{if(monitorContext)return stopMonitor();monitorContext=new AudioContext();await monitorContext.resume();const segments=view.player.segments||[];monitorSeen=new Set(segments.slice(0,-1));monitorNext=0;$('monitor').textContent='Slå av monitor';$('monitor-status').textContent='Lytter til faktisk dekodet testlyd fra VPS. Forsinkelse ca. 2–5 sekunder.';void monitorPump();});
// Closing the tab only closes the monitor. No server pause/stop request is sent.
window.addEventListener('pagehide',()=>{void stopMonitor();});
void refresh();setInterval(()=>void refresh(true),1000);

$('reset-queue').addEventListener('click',()=>{if(window.confirm('Tømme køen for en ny test? Avspillingshistorikken bevares.'))void mutate({action:'reset'});});
