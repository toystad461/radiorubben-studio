(()=>{'use strict';
 let busy=false,dirty=false,uncertain=false;
 const progress=document.querySelector('#newsroom-progress');
 const main=()=>document.querySelector('#main');
 const mobile=()=>window.matchMedia('(max-width: 800px)').matches;
 function init(){
  const queue=document.querySelector('#nr-queue');if(queue)queue.open=!mobile();
  document.querySelectorAll('.nr-approve').forEach(form=>{form.querySelector('button.nr-primary').disabled=!form.querySelector('[name=confirmed]').checked;});
 }
 function lock(value){document.querySelectorAll('#main button,#main input[type=checkbox]').forEach(el=>{
  if(value){el.dataset.wasDisabled=el.disabled?'1':'0';el.disabled=true;}
  else if(el.dataset.wasDisabled!==undefined){el.disabled=el.dataset.wasDisabled==='1';delete el.dataset.wasDisabled;}
 });}
 function message(text,error=false){progress.hidden=false;progress.setAttribute('role',error?'alert':'status');progress.textContent=text;}
 function safeUrl(value){const url=new URL(value,location.origin);if(url.origin!==location.origin||url.pathname!=='/newsdesk.php')throw new Error('Ugyldig svar fra desken.');return url;}
 function retryLink(){const button=document.createElement('button');button.type='button';button.dataset.newsroomReload='1';button.textContent='Kontroller status';progress.append(' ',button);}
 async function load(url,options={},mode='read'){
  if(busy)return;busy=true;lock(true);
  message(options.method==='POST'?(mode==='approve'?'Lagrer godkjenningen …':mode==='reject'?'Forkaster forslaget og åpner neste sak …':mode==='revise'?'Bearbeider og kontrollerer teksten. Du blir her …':'Lagrer …'):'Henter saken …');
  const scrollY=window.scrollY;
  try{
   const response=await fetch(url,{credentials:'same-origin',...options});
   if(response.redirected&&new URL(response.url).pathname==='/login.php'){
    uncertain=options.method==='POST';message('Innloggingen har utløpt. Teksten du skrev er fortsatt her.',true);
    const link=document.createElement('a');link.href='/login.php';link.target='_blank';link.rel='noopener';link.textContent='Logg inn i Studio';progress.append(' ',link);retryLink();return;
   }
   let result;try{result=await response.json();}catch(e){throw new Error('Forbindelsen ble avbrutt. Kontroller status før du prøver igjen.');}
   if(!response.ok||!result.ok){message(result.error||'Handlingen kunne ikke bekreftes.',true);retryLink();return;}
   const target=safeUrl(result.url),doc=new DOMParser().parseFromString(result.html,'text/html'),updated=doc.querySelector('main#main.newsroom');
   if(!updated||updated.querySelector('script,iframe,object'))throw new Error('Svaret kunne ikke vises. Kontroller status.');
   const previous=main().dataset.selected;main().replaceWith(updated);dirty=false;uncertain=false;init();
   history.replaceState({},'',target.pathname+target.search);
   progress.hidden=true;document.body.classList.remove('nr-editing');
   if(mode==='settings')window.scrollTo({top:scrollY});
   else if(mode==='refresh'&&previous===updated.dataset.selected)window.scrollTo({top:scrollY});
   else {const start=document.querySelector('#nr-reader-start');start?.focus({preventScroll:true});start?.scrollIntoView({block:'start',behavior:'instant'});}
  }catch(e){uncertain=options.method==='POST';message(e.message||'Forbindelsen ble avbrutt. Kontroller status.',true);retryLink();}
  finally{busy=false;if(!uncertain)lock(false);}
 }
 document.addEventListener('input',event=>{if(event.target.closest('form[data-newsroom]')&&event.target.matches('textarea,input:not([type=hidden]):not([type=checkbox])'))dirty=true;});
 document.addEventListener('change',event=>{if(event.target.matches('.nr-approve [name=confirmed]')){const form=event.target.closest('form');form.querySelector('.nr-primary').disabled=!event.target.checked;}});
 document.addEventListener('focusin',event=>{if(event.target.closest('#main')&&event.target.matches('textarea,input[type=text],input:not([type])'))document.body.classList.add('nr-editing');});
 document.addEventListener('focusout',()=>{setTimeout(()=>{if(!document.activeElement?.matches('textarea,input[type=text],input:not([type])'))document.body.classList.remove('nr-editing');},0);});
 document.addEventListener('click',event=>{
  if(event.target.closest('[data-open-queue]')){const queue=document.querySelector('#nr-queue');queue.open=true;queue.scrollIntoView({block:'start'});queue.querySelector('summary')?.focus();return;}
  if(event.target.closest('[data-open-changes]')){const details=document.querySelector('#nr-changes');if(details){details.open=true;details.scrollIntoView({block:'center'});details.querySelector('textarea')?.focus({preventScroll:true});}return;}
  const reload=event.target.closest('[data-newsroom-reload]'),link=event.target.closest('a[data-newsroom-link]');
  if(!reload&&!link)return;if(event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;event.preventDefault();
  if(busy)return;if(dirty&&!window.confirm('Du har endringer som ikke er sendt inn. Vil du forlate dem?'))return;
  let url;try{url=safeUrl(reload?location.href:link.href);}catch(e){return;}url.searchParams.set('view','fragment');
  load(url.href,{},reload||link?.classList.contains('nr-refresh')?'refresh':'read');
 });
 document.addEventListener('submit',event=>{
  const form=event.target;if(!form.matches('form[data-newsroom]'))return;event.preventDefault();if(busy||uncertain||!form.reportValidity())return;
  const data=new FormData(form);data.set('response','json');load('/newsdesk.php',{method:'POST',body:data},data.get('action'));
 });
 window.addEventListener('beforeunload',event=>{if(busy||dirty){event.preventDefault();event.returnValue='';}});
 init();
})();
