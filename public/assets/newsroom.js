(()=>{'use strict';let busy=false;const progress=document.querySelector('#newsroom-progress');
document.querySelectorAll('form[data-newsroom]').forEach(form=>form.addEventListener('submit',async event=>{
 event.preventDefault();if(busy)return;const data=new FormData(form);data.set('response','json');busy=true;
 const buttons=[...document.querySelectorAll('button')];const previous=buttons.map(b=>b.disabled);buttons.forEach(b=>b.disabled=true);
 progress.hidden=false;progress.textContent=data.get('action')==='approve'?'Publiserer den godkjente teksten …':'Robåt arbeider. Originalen leses før skriving og kontroll …';
 try{const response=await fetch('/newsdesk.php',{method:'POST',body:data,credentials:'same-origin'});let result;
  try{result=await response.json();}catch(e){throw new Error('Forbindelsen ble avbrutt. Last køen på nytt før nytt forsøk. Ingen handling gjentas automatisk.');}
  if(!response.ok||!result.ok)throw new Error(result.error||'Handlingen kunne ikke bekreftes.');
  const url=new URL(result.url,location.origin);if(url.origin!==location.origin||url.pathname!=='/newsdesk.php')throw new Error('Ugyldig svar fra desken.');location.assign(url.href);
 }catch(e){progress.textContent=e.message;progress.setAttribute('role','alert');const link=document.createElement('a');link.href='/newsdesk.php';link.textContent=' Last køen på nytt';progress.append(link);}
 finally{busy=false;buttons.forEach((b,i)=>b.disabled=previous[i]);}
}));window.addEventListener('beforeunload',event=>{if(busy){event.preventDefault();event.returnValue='';}});
})();
