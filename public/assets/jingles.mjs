const cards=[...document.querySelectorAll('.jingle-card')], status=document.getElementById('jingle-listen-status');
let generation=0;
function stop(){generation++;for(const card of cards){const audio=card.querySelector('audio');audio.pause();audio.currentTime=0;card.querySelector('[data-preview]').textContent='Forhåndslytt';}status.textContent='Privat lytting er av.';}
for(const card of cards){const audio=card.querySelector('audio'),button=card.querySelector('[data-preview]');
  button.addEventListener('click',async()=>{const wasPlaying=!audio.paused;stop();if(wasPlaying)return;const current=generation;try{await audio.play();if(current!==generation)return;button.textContent='Stopp lytting';status.textContent=`Privat lytting: ${card.querySelector('h3').textContent}. Sendekøen er uendret.`;}catch{if(current!==generation)return;status.textContent='Lyden kunne ikke spilles. Oppdater siden og prøv igjen.';}});
  audio.addEventListener('ended',stop);audio.addEventListener('error',()=>{stop();status.textContent='Lydfilen kunne ikke bekreftes.';});
}
document.getElementById('jingle-category').addEventListener('change',e=>{stop();for(const card of cards)card.hidden=e.target.value!=='all'&&card.dataset.category!==e.target.value;});
document.addEventListener('visibilitychange',()=>{if(document.hidden)stop();});
window.addEventListener('pagehide',stop);
