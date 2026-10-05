// All file access stays in the browser. No audio bytes, paths or file inventory are uploaded.
export const normalize = value => String(value).normalize('NFKC').toLocaleLowerCase('nb-NO').replace(/\s+/g,' ').trim();
export const trackKey = t => `${normalize(t.artist)}\u0000${normalize(t.title)}`;
export function catalogFiles(files) {
  return Array.from(files).filter(f=>/\.(mp3|m4a|wav|aac|ogg|flac)$/i.test(f.name)).map(file=>{
    const parts=(file.webkitRelativePath||file.name).split('/');
    const stem=file.name.replace(/\.[^.]+$/,'').replace(/^\d+[ ._-]+/,'');
    const split=stem.indexOf(' - ');
    const artist=split>0?stem.slice(0,split).trim():(parts.length>=3?parts[1]:'');
    const title=split>0?stem.slice(split+3).trim():stem.trim();
    return {artist,title,file};
  });
}
export async function checkTrack(track,catalog,connected) {
  if(!connected)return {status:'unknown',label:'Ikke kontrollert – velg arkivmappe'};
  const matches=catalog.filter(c=>trackKey(c)===trackKey(track));
  if(!matches.length)return {status:'missing',label:'Ikke funnet med denne artist-/tittelkombinasjonen'};
  if(matches.length>1)return {status:'ambiguous',label:'Flere versjoner funnet – avklar riktig fil'};
  const match=matches[0];
  try {
    if(match.file.size===0)return {status:'unavailable',label:'Filen er tom'};
    const bytes=await match.file.slice(0,64).arrayBuffer();
    if(!bytes.byteLength)throw new Error('Empty read');
    return {status:'found',label:'Fil funnet og lesbar – lytt før sending',track:match};
  }catch{return {status:'unavailable',label:'Filen kan ikke leses – kontroller lokal nedlasting/OneDrive'};}
}
export function reserveCandidates(track,catalog,planned) {
  const excluded=new Set(planned.map(trackKey));excluded.add(trackKey(track));
  const counts=new Map();for(const c of catalog)counts.set(trackKey(c),(counts.get(trackKey(c))||0)+1);
  return catalog.filter(c=>c.artist && c.file.size>0 && counts.get(trackKey(c))===1 && !excluded.has(trackKey(c)))
    .sort((a,b)=>(normalize(b.artist)===normalize(track.artist))-(normalize(a.artist)===normalize(track.artist)) || `${a.artist} ${a.title}`.localeCompare(`${b.artist} ${b.title}`,'nb')).slice(0,5);
}
export function hourPreview(plan,hour) {
  if(!/^([01][0-9]|2[0-3]):00$/.test(hour)||Number(hour.slice(0,2))%2)return [];
  return plan.filter(t=>t.slot===hour && !t.archived).slice(0,3).map(t=>({artist:t.artist,title:t.title,clipSeconds:6}));
}

if(typeof document!=='undefined' && document.querySelector('#music-check')) {
 const root=document.querySelector('#music-check');const plan=JSON.parse(document.querySelector('#music-plan-data').textContent);
 const input=document.querySelector('#archive-folder');const summary=document.querySelector('#archive-status');
 let catalog=[],connected=false,run=0,objectURL=null;
 const audio=document.querySelector('#archive-audition');
 function stopAudition(){audio.pause();audio.removeAttribute('src');if(objectURL)URL.revokeObjectURL(objectURL);objectURL=null;}
 async function checkAll(){
  const current=++run;let problems=0;
  summary.textContent=connected?'Kontrollerer filtilgang …':'Velg musikkmappen for å kontrollere planen.';
  for(const row of root.querySelectorAll('[data-track-id]')){
   const track=plan.find(t=>t.id===row.dataset.trackId);const state=await checkTrack(track,catalog,connected);
   if(current!==run)return;
   row.querySelector('[data-result]').textContent=state.label;row.querySelector('[data-result]').dataset.status=state.status;
   if(state.status!=='found')problems++;
   const listen=row.querySelector('[data-listen]');if(listen){listen.hidden=state.status!=='found';listen.onclick=()=>{stopAudition();objectURL=URL.createObjectURL(state.track.file);audio.src=objectURL;audio.play().catch(()=>{summary.textContent='Start lytting med avspillerens avspillingsknapp.';});};}
   const reserveStatus=row.querySelector('[data-reserve-status]');
   if(track.reserve){const reserve=await checkTrack(track.reserve,catalog,connected);if(current!==run)return;reserveStatus.textContent=`Reserve: ${reserve.label}`;}else reserveStatus.textContent='Ingen reserve valgt.';
   const list=row.querySelector('[data-candidates]');if(!list)continue;list.replaceChildren();
   if(!connected)continue;
   for(const candidate of reserveCandidates(track,catalog,plan.flatMap(t=>t.reserve?[t,t.reserve]:[t]))){
    const result=await checkTrack(candidate,catalog,true);if(current!==run)return;if(result.status!=='found')continue;
    const li=document.createElement('li'),button=document.createElement('button');button.type='button';button.textContent=`Velg reserve: ${candidate.artist} – ${candidate.title}`;
    button.addEventListener('click',()=>{const form=row.querySelector('[data-reserve-form]');form.elements.reserveArtist.value=candidate.artist;form.elements.reserveTitle.value=candidate.title;form.requestSubmit();});li.append(button);list.append(li);
   }
   if(!list.children.length){const li=document.createElement('li');li.textContent='Ingen entydige, lesbare reservekandidater funnet.';list.append(li);}
  }
  summary.textContent=connected?`${catalog.length} lydfiler i valgt mappe. ${problems} planlagte spor trenger avklaring. Kontrollert ${new Date().toLocaleTimeString('nb-NO')}.`:'Arkivet er ikke tilkoblet. Status er ukjent, ikke «fil mangler».';
 }
 input.addEventListener('change',()=>{stopAudition();catalog=catalogFiles(input.files);connected=input.files.length>0;checkAll();});
 document.querySelector('#check-again').addEventListener('click',checkAll);
 window.addEventListener('pagehide',stopAudition);
 const select=document.querySelector('#preview-hour');
 select.addEventListener('change',()=>{const tracks=hourPreview(plan,select.value);const out=document.querySelector('#hour-preview');out.replaceChildren();const intro=document.createElement('p');intro.textContent=tracks.length===3?'Dette får du den neste timen:':'Det trengs tre planlagte sanger i denne timen.';out.append(intro);for(const t of tracks){const line=document.createElement('p');line.textContent=`${t.artist} – ${t.title} · planlagt klipp ${t.clipSeconds} sek`;out.append(line);}});
 checkAll();
}
