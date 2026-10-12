export function initBreakApproval(getSnapshot,onApproved){
 const $=id=>document.getElementById(id);let busy=false,approved=null;
 const label=()=>{$('approve-break').textContent=$('break-destination').value==='tts'?'Godkjenn og åpne TTS':'Godkjenn stikk';};
 function invalidate(){approved=null;$('break-tts-link').hidden=true;$('break-approval-status').textContent='Stikket eller grunnlaget er endret. Les og godkjenn på nytt.';}
 $('draft').addEventListener('input',invalidate);$('producer-notes').addEventListener('input',invalidate);$('break-destination').addEventListener('change',label);label();
 $('approve-break').onclick=async()=>{
  if(busy)return;const snapshot=getSnapshot(),signature=JSON.stringify(snapshot),destination=$('break-destination').value;
  if(!snapshot.text.trim()){ $('break-approval-status').textContent='Skriv eller generer et stikk først.';return; }
  busy=true;$('approve-break').disabled=true;$('break-approval-status').textContent='Lagrer din manusgodkjenning …';
  try{const response=await fetch('/studio-break-api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({...snapshot,action:'approve',confirmed:'1',csrf:$('main').dataset.csrf}),signal:AbortSignal.timeout(15000)});
   const result=await response.json();if(!response.ok||result.approved!==true||!/^\/case\.php\?item=[a-f0-9]{16}#audio-material$/.test(result.url||''))throw Error(result.error||'Godkjenningen kunne ikke bekreftes.');
   if(JSON.stringify(getSnapshot())!==signature){invalidate();$('break-approval-status').textContent='Teksten eller konteksten endret seg under lagring. Godkjenn den nye versjonen.';return;}
   onApproved(snapshot.text,snapshot.aiAssisted);approved={signature:JSON.stringify(getSnapshot()),url:result.url};
   $('break-tts-link').href=result.url;$('break-tts-link').hidden=false;$('break-approval-status').textContent='Manus godkjent og lagret. Klart til manuell opplesning. Lyd krever separat generering og gjennomlytting.';
   if(destination==='tts')location.assign(result.url);
  }catch(e){$('break-approval-status').textContent=e.name==='TimeoutError'?'Ingen bekreftelse mottatt. Prøv samme godkjenning igjen; identiske stikk gjenbrukes.':e.message;}
  finally{busy=false;$('approve-break').disabled=false;}
 };
 return {invalidate};
}
