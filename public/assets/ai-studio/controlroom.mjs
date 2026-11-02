const $=id=>document.getElementById(id);
const labels={playing:'Spiller på VPS',starting:'Starter',stopped:'Stoppet',paused:'Pauset',blocked:'Stoppet · må kontrolleres',disconnected:'Frakoblet · lydutgang ikke bekreftet',unknown:'Ukjent · ingen fersk bekreftelse',unconfigured:'Ikke konfigurert'};
const title=t=>t.kind==='jingle'?`Jingle · ${t.title||'Ukjent tittel'}`:`${t.artist||'Ukjent artist'} – ${t.title||'Ukjent tittel'}`;
const time=n=>Number.isFinite(n)&&n>=0?`${Math.floor(n/60)}:${String(Math.floor(n%60)).padStart(2,'0')}`:'ukjent';
let busy=false,lastGood=0,streamUrl=null;
function render(v){
  const status=Object.hasOwn(labels,v.status)?v.status:'unknown';
  $('server-air-status').textContent=labels[status];$('server-air-status').dataset.state=status;
  $('server-connection').textContent=`Motor: ${v.engineOnline===true?'svarer':'ukjent'} · Strøm: ${v.streamConnected===true?'tilkoblet':'ikke bekreftet'}`;
  const current=status==='playing'&&v.now;
  $('server-now').textContent=current?title(v.now):status==='stopped'?'Ingen avspilling':labels[status];
  const measured=current&&Number.isFinite(v.position)&&Number.isFinite(v.now.duration)&&v.now.duration>0&&v.position>=0&&v.position<=v.now.duration;
  $('server-time').textContent=measured?`${time(v.position)} / ${time(v.now.duration)} · sist målt i motoren`:current?`Varighet: ${time(v.now.duration)} · progresjon ukjent`:'Progresjon ukjent';
  const progress=$('server-progress');progress.hidden=!measured;if(measured){progress.max=v.now.duration;progress.value=v.position;}
  const items=v.queueKnown===true&&Array.isArray(v.next)?v.next.slice(0,3):[];
  $('server-next').replaceChildren(...(items.length?items.map(t=>`${title(t)} · ${time(t.duration)}`):[v.queueKnown?'Ingen flere bekreftede innslag':'Køstatus ukjent']).map(text=>{const li=document.createElement('li');li.textContent=text;return li;}));
  const timestamp=Date.parse(v.generatedAt);
  $('server-updated').textContent=Number.isFinite(timestamp)?`Sist hentet ${new Date(timestamp).toLocaleTimeString('nb-NO',{timeZone:'Europe/Oslo'})} · norsk tid`:'Ingen fersk serverstatus';
  streamUrl=v.streamUrl==='/music-test-stream/'?v.streamUrl:null;
  $('server-listen').disabled=!streamUrl&&$('server-audio').paused;
  if(!streamUrl&&$('server-audio').paused)$('server-listening').textContent='Privat lytting er ikke tilgjengelig for denne rollen eller forbindelsen.';
}
async function refresh(){
  if(busy)return;busy=true;
  try{
    const response=await fetch('/controlroom-api.php',{cache:'no-store',redirect:'error',signal:AbortSignal.timeout(4000)});
    if(!response.ok)throw Error();const v=await response.json();
    const age=Date.now()-Date.parse(v.generatedAt);
    if(v.schemaVersion!==1||!Number.isFinite(age)||age < -5000||age>5000)throw Error();
    lastGood=Date.now();render(v);
  }catch{lastGood=0;render({status:'unknown'});}finally{busy=false;}
}
if($('server-now')){
  const audio=$('server-audio'),listen=$('server-listen'),message=$('server-listening');
  listen.addEventListener('click',async()=>{
    if(!audio.paused){audio.pause();audio.removeAttribute('src');audio.load();return;}
    if(!streamUrl)return;
    audio.src=streamUrl;message.textContent='Kobler til teststream …';
    try{await audio.play();}catch{message.textContent='Lytting kunne ikke starte. Prøv igjen.';}
  });
  audio.addEventListener('playing',()=>{listen.textContent='Stopp lytting';message.textContent='Du lytter til teststreamen. Lyd kan være forsinket i forhold til motorstatus.';});
  audio.addEventListener('pause',()=>{listen.textContent='Lytt til teststream';message.textContent='Lytting er av. Sendingen fortsetter på serveren.';});
  audio.addEventListener('error',()=>{listen.textContent='Lytt til teststream';message.textContent='Lytteforbindelsen ble brutt. Serverstatus vises separat.';});
  audio.addEventListener('waiting',()=>{message.textContent='Lytteavspilleren venter på lyd …';});
  void refresh();const poll=setInterval(refresh,2000);
  const expiry=setInterval(()=>{if(lastGood&&Date.now()-lastGood>5000){lastGood=0;render({status:'unknown'});}},500);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden){render({status:'unknown'});void refresh();}});
  window.addEventListener('pagehide',()=>{clearInterval(poll);clearInterval(expiry);audio.pause();},{once:true});
}
