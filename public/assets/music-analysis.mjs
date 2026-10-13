/* AGPL-3.0-or-later. Separate browser analysis component; no playout. */
const button=document.querySelector('[data-music-analyze]');
if(button)button.addEventListener('click',async()=>{
  const status=document.querySelector('#music-analysis-status');
  button.disabled=true;let context,worker;
  try{
    status.textContent='Henter den private originalen for analyse …';
    const response=await fetch(button.dataset.audioUrl,{credentials:'same-origin',cache:'no-store'});
    if(!response.ok||!response.headers.get('content-type')?.startsWith('audio/'))throw Error('Originalen kunne ikke hentes.');
    const bytes=await response.arrayBuffer();if(bytes.byteLength>20*1024*1024)throw Error('Filen er for stor.');
    const hash=Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',bytes)),b=>b.toString(16).padStart(2,'0')).join('');
    if(hash!==button.dataset.version)throw Error('Originalen samsvarer ikke med låtreferansen.');
    context=new AudioContext();const audio=await context.decodeAudioData(bytes);
    if(audio.duration<10||audio.duration>900)throw Error('Analysen støtter låter mellom 10 sekunder og 15 minutter.');
    const durationSeconds=Math.min(90,audio.duration),startSeconds=Math.max(0,(audio.duration-durationSeconds)/2);
    const offline=new OfflineAudioContext(1,Math.ceil(durationSeconds*44100),44100);
    const source=offline.createBufferSource();source.buffer=audio;source.connect(offline.destination);source.start(0,startSeconds,durationSeconds);
    const samples=(await offline.startRendering()).getChannelData(0);
    let power=0;for(const s of samples)power+=s*s;if(power/samples.length<1e-10)throw Error('Utsnittet er stille.');
    status.textContent='Musikkprodusenten får BPM og toneart. Beregner …';
    worker=new Worker('/assets/music-analysis-worker.js');
    const measurements=await new Promise((resolve,reject)=>{
      const timer=setTimeout(()=>reject(Error('Analysen tok for lang tid. Prøv igjen.')),120000);
      worker.onmessage=({data})=>{clearTimeout(timer);data.error?reject(Error(data.error)):resolve(data);};
      worker.onerror=()=>{clearTimeout(timer);reject(Error('Analyseverktøyet kunne ikke lastes.'));};
      worker.postMessage({samples},[samples.buffer]);
    });
    const saved=await fetch('/music-library-analysis.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:button.dataset.id,version:hash,revision:Number(button.dataset.revision),csrf:button.dataset.csrf,values:{engine:'essentia.js-0.1.3',...measurements,startSeconds,durationSeconds}})});
    const payload=await saved.json();if(!saved.ok||payload.saved!==true)throw Error(payload.error||'Lagringen kunne ikke bekreftes.');
    status.textContent='Analysen er lagret. Oppdaterer låten …';location.reload();
  }catch(error){status.textContent=error.message||'Analysen kunne ikke fullføres.';}
  finally{worker?.terminate();if(context)await context.close();button.disabled=false;}
});
