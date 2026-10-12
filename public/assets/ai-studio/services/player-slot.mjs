// Uses the existing local audio element. No new stream or playback on load.
export function initPlayerSlot() {
  const source=document.getElementById('studio-player-source');
  if(!source)return;
  const audio=document.getElementById('music-audio'),button=document.getElementById('studio-player-toggle'),volume=document.getElementById('studio-player-volume'),state=document.getElementById('studio-player-state');
  const local=()=>source.value==='local';
  function render(){
    const ready=local()&&!!audio.getAttribute('src');
    button.disabled=!ready;volume.disabled=!local();
    button.textContent=ready&&!audio.paused?'Pause':'Spill';
    button.setAttribute('aria-label',ready&&!audio.paused?'Pause lokal musikk':'Spill av valgt lydkilde');
    volume.value=String(Math.round(audio.volume*100));
    state.textContent=!local()?'Radiosending er ikke tilkoblet.'+(!audio.paused&&audio.getAttribute('src')?' Lokal musikk spiller fortsatt.':''):!ready?'Ingen lokal låt er lastet.':audio.error?'Lokal lyd kunne ikke spilles.':!audio.paused?'Spiller lokal musikk i denne fanen.':'Lokal musikk er pauset eller stoppet.';
  }
  source.addEventListener('change',render);
  button.addEventListener('click',async()=>{
    if(!local()||!audio.getAttribute('src'))return;
    if(!audio.paused){audio.pause();return;}
    try{await audio.play();render();}catch{state.textContent='Kunne ikke starte lokal musikk. Kontroller musikkspiller og lydutgang.';}
  });
  volume.addEventListener('input',()=>{if(local())audio.volume=Number(volume.value)/100;});
  for(const event of ['playing','pause','ended','error','volumechange','emptied','loadedmetadata'])audio.addEventListener(event,render);
  const observer=new MutationObserver(render);observer.observe(audio,{attributes:true,attributeFilter:['src']});
  window.addEventListener('pagehide',()=>observer.disconnect(),{once:true});
  render();
}
