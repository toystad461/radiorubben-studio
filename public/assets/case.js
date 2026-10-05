(()=>{
const settings=document.querySelector('#case-controller').dataset;
const state={id:settings.item,revision:Number(settings.revision),csrf:settings.csrf};
const progress=document.querySelector('#case-progress');let busy=false;const dirty=new Set();
const say=text=>{progress.textContent=text;};
const lock=value=>{busy=value;document.querySelectorAll('button,select,input[type=checkbox]').forEach(b=>b.disabled=value);document.querySelectorAll('textarea,input:not([type=hidden]):not([type=checkbox])').forEach(e=>e.readOnly=value);};
async function step(action,form){
 const data=form?new FormData(form):new FormData();
 // Selects are disabled while a request runs; include the editor's selected placement explicitly.
 if(action==='save_web' && document.querySelector('#news-scope'))data.set('news_scope',document.querySelector('#news-scope').value);
 for(const [k,v] of Object.entries({...state,action,response:'json'}))data.set(k,String(v));
 const res=await fetch('/case.php',{method:'POST',body:data,credentials:'same-origin'});
 let result;try{result=await res.json();}catch(e){throw new Error('Kontakten ble avbrutt eller innloggingen utløp. Last saken på nytt før du fortsetter. Ingen automatisk gjentakelse.');}
 if(!res.ok||!result.ok)throw new Error(result.error||'Handlingen kunne ikke fullføres.');
 state.revision=result.revision;document.querySelectorAll('input[name="revision"]').forEach(e=>e.value=state.revision);
 return result;
}
async function run(work){if(busy)return;lock(true);try{await work();busy=false;location.replace('/case.php?item='+encodeURIComponent(state.id));}catch(e){say(e.message+' Lagrede delresultater er bevart.');lock(false);document.querySelectorAll('[data-final] button').forEach(b=>b.disabled=true);}}
document.querySelector('#prepare-all').addEventListener('click',()=>{
 if(dirty.size){say('Lagre rettelsene før du lager nye utkast.');return;}
 run(async()=>{say('1 av 2: Henter originalen, lager radiomanus og kontrollerer språk og kilder …');await step('prepare_radio');say('2 av 2: Lager nettsak og kontrollerer språk og kilder …');await step('prepare_web');});
});
const newsScope=document.querySelector('#news-scope');
if(newsScope)newsScope.addEventListener('change',()=>{const form=document.querySelector('#web-editor');dirty.add(form);document.querySelectorAll('[data-final] button').forEach(b=>b.disabled=true);say('Nyhetskategorien er endret. Lagre og kontroller nettsaken før sluttgodkjenning.');});
document.querySelectorAll('[data-save]').forEach(form=>{
 form.addEventListener('input',()=>{dirty.add(form);document.querySelectorAll('[data-final] button').forEach(b=>b.disabled=true);});
 form.addEventListener('submit',e=>{e.preventDefault();const kind=form.dataset.save;if([...dirty].some(f=>f!==form)){say('Du har rettelser i begge tekster. Bruk knappen nedenfor for å lagre og kontrollere begge.');document.querySelector('#save-both').hidden=false;return;}
 run(async()=>{say('Lagrer rettelser og kontrollerer mot originalkilden …');await step('save_'+kind,form);dirty.delete(form);await step('check_'+kind);});});
});
const saveBoth=document.createElement('button');saveBoth.id='save-both';saveBoth.type='button';saveBoth.hidden=true;saveBoth.textContent='Lagre og kontroller begge tekster';progress.after(saveBoth);
saveBoth.addEventListener('click',()=>run(async()=>{for(const form of [...dirty]){const kind=form.dataset.save;say('Lagrer og kontrollerer '+(kind==='radio'?'radiomanus':'nettsak')+' …');await step('save_'+kind,form);dirty.delete(form);await step('check_'+kind);}}));
document.querySelectorAll('[data-step]').forEach(form=>form.addEventListener('submit',e=>{e.preventDefault();if(dirty.size){say('Lagre rettelsene først.');return;}run(async()=>{say('Robåt arbeider med saken …');await step(form.dataset.step);});}));
document.querySelectorAll('[data-final]').forEach(form=>form.addEventListener('submit',e=>{if(busy||dirty.size){e.preventDefault();say('Lagre og kontroller rettelsene før sluttgodkjenning.');}}));
window.addEventListener('beforeunload',e=>{if(busy||dirty.size){e.preventDefault();e.returnValue='';}});
if(settings.autoPrepare==='1')document.querySelector('#prepare-all').click();
})();
