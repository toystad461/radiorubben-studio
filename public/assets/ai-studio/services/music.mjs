import { Rotation } from './rotation.mjs';
export function initMusic(bus, updateContext, emptyContext) {
  const $=id=>document.getElementById(id), audio=$('music-audio');
  let tracks=[], breaks=[], token='', queued=null, current=null, loaded=null, recent=[], generation=0;
  let choosing=null, playVersion=0, lastPlayedVersion=0, pendingAdvance=false;
  const rotation=new Rotation();
  const say=text=>{$('music-status').textContent=text;};
  const label=t=>`${t.artist} – ${t.title}`;
  async function api(body){
    const r=await fetch('/music-api.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':$('main').dataset.csrf},body:JSON.stringify(body),signal:AbortSignal.timeout(40000)});
    const data=await r.json();if(!r.ok)throw Error(data.error||'Musikkbiblioteket svarte ikke.');return data;
  }
  function showMood(s){
    if(s)$('music-mood').textContent=`${s.day} kl. ${s.time} · ${s.period} · ${s.mood} (norsk tid)`;
  }
  let moodBusy=false;
  async function refreshMood(){if(moodBusy)return;moodBusy=true;try{showMood((await api({action:'mood'})).schedule);}catch{$('music-mood').textContent='Tid og stemning hentes på nytt ved neste AI-valg.';}finally{moodBusy=false;}}
  void refreshMood();const moodTimer=setInterval(refreshMood,60000);
  window.addEventListener('pagehide',()=>clearInterval(moodTimer),{once:true});
  function context(){
    $('demo-data').checked=false;
    updateContext({...emptyContext(),source:'local',program:'Radio Rubben · lokalt musikkbibliotek',nowPlaying:current?label(current):null,next:queued?label(queued):null});
  }
  function queue(track){generation++;queued=track;$('music-selection').value=track?.id||'';context();$('music-play').disabled=!track||!$('music-output-confirmed').checked;}
  $('music-load').onclick=async()=>{
    $('music-load').disabled=true;
    try{const result=await api({action:'catalog'});tracks=result.tracks.filter(t=>t.kind!=='break');breaks=result.tracks.filter(t=>t.kind==='break');token=result.token;showMood(result.schedule);
      $('music-break-status').textContent=`${result.breakStatus} · ${breaks.length} lydstikk. Stikk rullerer i rekkefølge.`;
      $('music-selection').replaceChildren(new Option('Velg neste låt',''),...tracks.map(t=>new Option(label(t)+(t.eligible?'':' · manuelt valg'),t.id)));
      $('music-ai').disabled=!tracks.some(t=>t.eligible);queue(null);say(`${tracks.length} lydfiler funnet. AI velger blant ${tracks.filter(t=>t.eligible).length} med kjent artist. Ingen lydfiler lastes opp.`);
    }catch(e){say(e.message);}finally{$('music-load').disabled=false;}
  };
  $('music-selection').onchange=e=>{queue(tracks.find(t=>t.id===e.target.value)||null);say('Neste låt er valgt manuelt. Trykk Spill neste når du er klar.');};
  function fallback(){
    const eligible=tracks.filter(t=>t.eligible&&t.id!==loaded?.id);
    return eligible.find(t=>!recent.includes(t.id)) || [...eligible].sort((a,b)=>recent.indexOf(a.id)-recent.indexOf(b.id))[0] || tracks.find(t=>t.eligible) || null;
  }
  function chooseNext(automatic=false){
    if(choosing)return choosing;
    const version=++generation;$('music-ai').disabled=true;
    say('AI velger neste låt …');bus.emit('AI_STATUS',{source:'music',busy:true,message:'Velger neste låt …'});
    choosing=(async()=>{
      try{
        const result=await api({action:'choose',recent,wish:$('music-wish').value});
        bus.emit('AI_STATUS',{source:'music',busy:false,message:'Låtvalg mottatt · '+new Date().toLocaleTimeString('nb-NO')});
        if(version!==generation)return;showMood(result.schedule);
        const track=tracks.find(t=>t.id===result.id);if(!track)throw Error('Låtvalget finnes ikke.');
        queue(track);say(`AI valgte ${label(track)}. ${result.reason}`);
      }catch(e){
        bus.emit('AI_STATUS',{source:'music',busy:false,message:'AI-låtvalg utilgjengelig'});
        if(version!==generation)return;
        const track=automatic?fallback():null;
        if(track){queue(track);say(`Lokalt reservevalg: ${label(track)}. AI svarte ikke.`);bus.emit('MUSIC_FALLBACK',{log:label(track)});}
        else say(e.message+' Du kan fortsatt velge og spille manuelt.');
      }finally{choosing=null;$('music-ai').disabled=!tracks.some(t=>t.eligible);}
    })();return choosing;
  }
  $('music-ai').onclick=()=>void chooseNext();
  function preselect(){if($('music-auto-pick').checked&&!queued&&!choosing)void chooseNext(true);}
  function disableAuto(){
    $('music-auto-pick').checked=false;$('music-auto-play').checked=false;rotation.setEnabled(false);generation++;pendingAdvance=false;
  }
  async function advance(){
    if(!rotation.enabled||!$('music-output-confirmed').checked)return;
    const version=playVersion;
    pendingAdvance=true;
    if(!queued)await chooseNext(true);
    if(!pendingAdvance||version!==playVersion||!rotation.enabled)return;
    pendingAdvance=false;
    if(queued)void playTrack(queued);else say('Ingen neste låt klar. Velg en låt manuelt.');
  }
  $('music-auto-pick').onchange=()=>{generation++;if($('music-auto-pick').checked)preselect();else{$('music-auto-play').checked=false;rotation.setEnabled(false);pendingAdvance=false;}};
  $('music-auto-play').onchange=()=>{
    rotation.setEnabled($('music-auto-play').checked);
    if(rotation.enabled){$('music-auto-pick').checked=true;preselect();say('Automatikk på. Start første låt med Spill neste.');}
    else pendingAdvance=false;
  };
  $('music-break-interval').onchange=e=>rotation.setInterval(e.target.value);
  async function playTrack(track){
    if(!track||!$('music-output-confirmed').checked)return;
    const version=++playVersion;loaded=null;audio.pause();loaded=track;current=null;
    if(queued?.id===track.id)queue(null);
    audio.src=`/music-api.php?action=stream&id=${encodeURIComponent(track.id)}&token=${encodeURIComponent(token)}`;
    try{await audio.play();}
    catch(e){if(version!==playVersion)return;disableAuto();audio.pause();current=null;context();$('player-status').textContent='Avspilling feilet';say('Kunne ikke spille filen. Automatikk stoppet. Kontroller filformat og lydutgang.');}
  }
  $('music-play').onclick=()=>{pendingAdvance=false;void playTrack(queued);};
  $('music-stop').onclick=()=>{disableAuto();playVersion++;loaded=null;audio.pause();audio.removeAttribute('src');audio.load();current=null;context();$('remaining').textContent='ukjent';$('player-status').textContent='Stoppet';say('Avspilling stoppet.');bus.emit('MUSIC_STOPPED',{log:'Operatør stoppet avspilling'});};
  audio.addEventListener('playing',()=>{
    if(!loaded)return;
    if(!$('music-output-confirmed').checked){disableAuto();audio.pause();return;}
    current=loaded;const first=lastPlayedVersion!==playVersion;lastPlayedVersion=playVersion;
    if(first&&current.kind!=='break')recent=[...recent.filter(id=>id!==current.id),current.id].slice(-20);context();
    $('player-status').textContent=`Spiller · ${label(current)}`;
    say(`Spiller ${label(current)} via valgt lydutgang.`);bus.emit('MUSIC_PLAYING',{log:label(current)});if(first)preselect();
  });
  audio.addEventListener('pause',()=>{if(!loaded||audio.ended||!audio.paused)return;pendingAdvance=false;current=null;context();$('player-status').textContent=`Pauset · ${label(loaded)}`;bus.emit('MUSIC_PAUSED',{log:label(loaded)});});
  audio.addEventListener('waiting',()=>{if(loaded&&!audio.paused)$('player-status').textContent=`Laster lyd · ${label(loaded)}`;});
  audio.addEventListener('ended',()=>{const finished=loaded;current=null;context();$('player-status').textContent='Ferdig';bus.emit('MUSIC_ENDED',{log:finished?label(finished):''});
    if(!rotation.enabled){say('Ferdig. Neste innslag startes manuelt.');return;}
    const nextBreak=rotation.ended(finished,breaks);if(nextBreak)void playTrack(nextBreak);else void advance();
  });
  audio.addEventListener('error',()=>{if(!loaded)return;disableAuto();current=null;context();$('player-status').textContent='Avspilling feilet';say('Filen kunne ikke spilles. Prøv en annen lydfil.');});
  audio.addEventListener('timeupdate',()=>{$('remaining').textContent=Number.isFinite(audio.duration)?`${Math.max(0,Math.ceil(audio.duration-audio.currentTime))} sek`:'ukjent';});
  $('music-output-confirmed').onchange=()=>{audio.controls=$('music-output-confirmed').checked;$('music-play').disabled=!queued||!$('music-output-confirmed').checked;if(!$('music-output-confirmed').checked){disableAuto();audio.pause();say('Avspilling pauset til lydutgangen er bekreftet.');}};
  $('music-output').onclick=async()=>{
    try{
      if(!navigator.mediaDevices?.selectAudioOutput || !audio.setSinkId){say('Velg RØDECaster som lydutgang i Macens lydinnstillinger, og bekreft med avkrysningsboksen.');return;}
      const device=await navigator.mediaDevices.selectAudioOutput();await audio.setSinkId(device.deviceId);say(`Lydutgang valgt: ${device.label}. Bekreft at dette er ønsket RØDECaster-utgang.`);
    }catch{say('Lydutgangen ble ikke endret. Velg RØDECaster i Macens lydinnstillinger.');}
  };
  $('music-clear-history').onclick=()=>{recent=[];generation++;say('Spillehistorikk tømt for denne fanen.');};
}
