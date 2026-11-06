const $=id=>document.getElementById(id);
const labels={playing:'Spiller på VPS',starting:'Starter',stopped:'Stoppet',paused:'Pauset',blocked:'Stoppet · må kontrolleres',disconnected:'Frakoblet · lydutgang ikke bekreftet',unknown:'Ukjent · ingen fersk bekreftelse',unconfigured:'Ikke konfigurert'};
const eventLabels={stikk:'Stikk',innlegg:'Innlegg',jingle:'Jingle',reklame:'Reklame',lydteppe:'Lydteppe',program:'Programopptak',hilsen:'Hilsen',onske:'Ønske',sang:'Sang'};
const title=t=>Object.hasOwn(eventLabels,t.eventType)?`${eventLabels[t.eventType]} · ${t.title||'Ukjent tittel'}`:t.kind==='jingle'?`Jingle · ${t.title||'Ukjent tittel'}`:`${t.artist||'Ukjent artist'} – ${t.title||'Ukjent tittel'}`;
const time=n=>Number.isFinite(n)&&n>=0?`${Math.floor(n/60)}:${String(Math.floor(n%60)).padStart(2,'0')}`:'ukjent';
let busy=false,lastGood=null,streamUrl=null,visibilityGeneration=0;
function renderCues(current,position){
  const canvas=$('server-cues'),summary=$('server-cue-summary');if(!canvas||!summary)return;
  const cues=current?.cues,keys=['start','vocalStart','transition','end'],names=['Start','Vokalstart','Overgang','Slutt'];
  const valid=cues&&Number.isFinite(cues.duration)&&cues.duration>0&&keys.every(k=>cues.points?.[k]===null||Number.isFinite(cues.points?.[k])&&cues.points[k]>=0&&cues.points[k]<=cues.duration);
  canvas.hidden=!valid;
  summary.textContent=!current?'Cue-punkter vises når en låt spiller.':!valid?'Cue-punkter er ikke tilgjengelige for denne lydfilen.':
    keys.map((k,i)=>names[i]+': '+(cues.points[k]===null?'ukjent':time(cues.points[k])+' · '+cues.points[k].toFixed(1)+' s')).join(' / ')+
    (cues.source==='proposal'?' · Analyseforslag, ikke godkjent.':cues.source==='silence'?' · Automatisk start/slutt; vokal ikke funnet.':' · Lagrede punkter.')+
    ' Avspilling: '+time(cues.playbackIn)+'–'+time(cues.playbackOut)+'.';
  const c=canvas.getContext?.('2d');if(!c)return;const width=Math.max(260,canvas.clientWidth||800);canvas.width=width;canvas.height=150;c.clearRect(0,0,width,150);if(!valid)return;
  const x=n=>Math.max(0,Math.min(width,n/cues.duration*width)),p=cues.points;
  c.fillStyle='#081b2b';c.fillRect(0,0,width,150);
  for(const [a,b,color]of [[p.start,p.vocalStart,'#174b42'],[p.transition,p.end,'#423251']])if(a!==null&&b!==null&&b>a){c.fillStyle=color;c.fillRect(x(a),0,x(b)-x(a),150);}
  if(Array.isArray(cues.waveformPeaks)&&cues.waveformPeaks.length===256){c.strokeStyle='#729eb6';c.beginPath();cues.waveformPeaks.forEach((peak,i)=>{if(!Number.isFinite(peak))return;const a=Math.max(0,Math.min(1,peak))*42;c.moveTo(i*width/256,93-a);c.lineTo(i*width/256,93+a);});c.stroke();}
  keys.forEach((k,i)=>{if(p[k]===null)return;c.strokeStyle=['#69e2b2','#ffc86a','#cb9dff','#ff8d9e'][i];c.fillStyle=c.strokeStyle;c.lineWidth=2;c.beginPath();c.moveTo(x(p[k]),5);c.lineTo(x(p[k]),150);c.stroke();c.font='13px system-ui';const label=names[i];c.fillText(label,Math.max(4,Math.min(width-4-c.measureText(label).width,x(p[k])+5)),17+i*17);});
  // The motor reports elapsed time in the trimmed occurrence, not in the original file.
  if(Number.isFinite(position)&&Number.isFinite(cues.playbackIn)){c.strokeStyle='#fff';c.lineWidth=3;c.beginPath();c.moveTo(x(cues.playbackIn+position),75);c.lineTo(x(cues.playbackIn+position),150);c.stroke();}
}
function render(v){
  const status=Object.hasOwn(labels,v.status)?v.status:'unknown';
  $('server-air-status').textContent=labels[status];$('server-air-status').dataset.state=status;
  $('server-connection').textContent=`Motor: ${v.engineOnline===true?'svarer':'ukjent'} · Strøm: ${v.streamConnected===true?'tilkoblet':'ikke bekreftet'}`;
  const current=status==='playing'&&v.now;
  $('server-now').textContent=current?title(v.now):status==='stopped'?'Ingen avspilling':labels[status];
  const measured=current&&Number.isFinite(v.position)&&Number.isFinite(v.now.duration)&&v.now.duration>0&&v.position>=0&&v.position<=v.now.duration;
  $('server-time').textContent=measured?`${time(v.position)} / ${time(v.now.duration)} · sist målt i motoren`:current?`Varighet: ${time(v.now.duration)} · progresjon ukjent`:'Progresjon ukjent';
  const progress=$('server-progress');progress.hidden=!measured;if(measured){progress.max=v.now.duration;progress.value=v.position;}
  renderCues(current?v.now:null,measured?v.position:null);
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
    // A matching request ID proves this reply was generated for this poll.
    // Heartbeat freshness remains a server check; PC wall-clock time is irrelevant.
    const requestId=Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('');
    const started=performance.now();
    const generation=visibilityGeneration;
    const response=await fetch('/controlroom-api.php?requestId='+requestId,{cache:'no-store',redirect:'error',signal:AbortSignal.timeout(4000)});
    if(!response.ok)throw Error();const v=await response.json();
    if(document.hidden||generation!==visibilityGeneration||v.schemaVersion!==1||v.requestId!==requestId||!Number.isFinite(Date.parse(v.generatedAt))||performance.now()-started>=4000)throw Error();
    lastGood=performance.now();render(v);
  }catch{lastGood=null;render({status:'unknown'});}finally{busy=false;}
}
if($('server-now')){
  const audio=$('server-audio'),listen=$('server-listen'),message=$('server-listening');
  listen.addEventListener('click',async()=>{
    if(!audio.paused){audio.pause();audio.removeAttribute('src');audio.load();return;}
    if(!streamUrl)return;
    audio.src=streamUrl;message.textContent='Kobler til sendingen …';
    try{await audio.play();}catch{message.textContent='Lytting kunne ikke starte. Prøv igjen.';}
  });
  audio.addEventListener('playing',()=>{listen.textContent='Stopp lytting';message.textContent='Du lytter til sendingen. Lyd kan være forsinket i forhold til motorstatus.';});
  audio.addEventListener('pause',()=>{listen.textContent='Lytt til sendingen';message.textContent='Lytting er av. Sendingen fortsetter på serveren.';});
  audio.addEventListener('error',()=>{listen.textContent='Lytt til sendingen';message.textContent='Lytteforbindelsen ble brutt. Serverstatus vises separat.';});
  audio.addEventListener('waiting',()=>{message.textContent='Lytteavspilleren venter på lyd …';});
  void refresh();const poll=setInterval(refresh,2000);
  const expiry=setInterval(()=>{if(lastGood!==null&&performance.now()-lastGood>5000){lastGood=null;render({status:'unknown'});}},500);
  document.addEventListener('visibilitychange',()=>{visibilityGeneration++;lastGood=null;render({status:'unknown'});if(!document.hidden)void refresh();});
  window.addEventListener('pagehide',()=>{clearInterval(poll);clearInterval(expiry);audio.pause();},{once:true});
}

