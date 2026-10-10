'use strict';
const lookup = document.querySelector('[data-crm-lookup]');
if (lookup) lookup.addEventListener('click', async () => {
  const form = lookup.closest('form');
  const status = form.querySelector('[data-lookup-status]');
  lookup.disabled = true; status.textContent = 'Henter opplysninger …';
  try {
    const response = await fetch('/crm-register.php', {method:'POST', body:new URLSearchParams({csrf:form.elements.csrf.value,orgNumber:form.elements.orgNumber.value})});
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Oppslaget mislyktes.');
    const preserved = [];
    for (const [key,value] of Object.entries(result.fields)) {
      const field = form.elements.namedItem(key);
      if (!field) continue;
      if (!field.value.trim() || key === 'orgNumber') field.value = value;
      else if (field.value !== value && value) preserved.push(key);
    }
    status.textContent = `Hentet ${result.fields.company} fra Brønnøysundregistrene. Kontroller feltene før lagring.${preserved.length ? ' Eksisterende felt er beholdt.' : ''}${!result.fields.website ? ' Hjemmeside er ikke registrert; legg inn en bekreftet adresse selv.' : ''}`;
  } catch (error) { status.textContent = error.message || 'Oppslaget mislyktes. Prøv senere.'; }
  finally { lookup.disabled = false; }
});
const job = document.querySelector('[data-crm-demo-job]');
if (job) job.addEventListener('submit', async event => {
  event.preventDefault();
  const button = job.querySelector('button'); button.disabled = true;
  const status = document.querySelector('[data-demo-status]'); status.textContent='Lager lydprøven. Ikke send på nytt mens den arbeider …';
  try {
    const response=await fetch(job.action,{method:'POST',body:new FormData(job)});
    const result=await response.json();
    if (!response.ok) throw new Error(result.error || 'Lydjobben må kontrolleres.');
    location.reload();
  } catch(error) { status.textContent=(error.message || 'Forbindelsen ble brutt.')+' Last siden på nytt for å se lagret status. Ingen automatisk gjentakelse.'; }
});

// Approval always concerns persisted text. Do not let visible unsaved edits appear approved.
const proposalEditor=document.querySelector('[data-proposal-edit]');
if(proposalEditor) proposalEditor.addEventListener('input',()=>{
  document.querySelector('[data-proposal-edit-status]').textContent='Du har ulagrede endringer. Lagre tekstene før godkjenning, lydprøve eller eksport.';
  for(const form of document.forms){
    if(form!==proposalEditor&&form.elements.namedItem('action'))for(const button of form.querySelectorAll('button'))button.disabled=true;
  }
});

// One combobox, with cancellation and a generation guard even during the debounce.
const companySearch=document.querySelector('[data-company-search]');
if(companySearch) {
  const query=companySearch.querySelector('[data-company-query]');
  const results=companySearch.querySelector('[data-company-results]');
  const status=companySearch.querySelector('[role=status]');
  const choice=companySearch.elements.choice;
  let timer, sequence=0, controller, hits=[], active=-1, composing=false;
  function close() {
    clearTimeout(timer); ++sequence; controller?.abort();
    hits=[]; active=-1; choice.value=''; results.replaceChildren(); results.hidden=true;
    query.setAttribute('aria-expanded','false'); query.removeAttribute('aria-activedescendant');
    query.setAttribute('aria-busy','false');
  }
  function activate(index) {
    active=index;
    [...results.children].forEach((option,i)=>option.setAttribute('aria-selected',String(i===active)));
    query.setAttribute('aria-activedescendant',results.children[active].id);
    results.children[active].scrollIntoView({block:'nearest'});
  }
  function choose(index) {
    if(!hits[index])return;
    choice.value=hits[index].value;
    companySearch.requestSubmit();
  }
  async function search() {
    clearTimeout(timer);
    close(); const current=sequence, value=query.value.trim();
    if(value.length<2) {status.textContent='Skriv minst to tegn.';return;}
    controller=new AbortController(); query.setAttribute('aria-busy','true');
    status.textContent='Søker etter bedrifter …';
    try {
      const response=await fetch('/crm-register.php',{method:'POST',signal:controller.signal,body:new URLSearchParams({csrf:companySearch.elements.csrf.value,action:'search',query:value})});
      const data=await response.json();
      if(current!==sequence)return;
      if(!response.ok)throw new Error(data.error||'Søket mislyktes.');
      if(!Array.isArray(data.results))throw new Error('Søket ga et ugyldig svar. Prøv igjen.');
      hits=data.results.filter(hit=>typeof hit.label==='string'&&/^(card:[a-f0-9]{16}|org:[0-9]{9})$/.test(hit.value));
      hits.forEach((hit,index)=>{
        const option=document.createElement('li');
        option.id='company-option-'+current+'-'+index; option.setAttribute('role','option');
        option.setAttribute('aria-selected','false'); option.textContent=hit.label;
        option.addEventListener('mousedown',event=>event.preventDefault());
        option.addEventListener('click',()=>choose(index)); results.append(option);
      });
      results.hidden=!hits.length; query.setAttribute('aria-expanded',String(!!hits.length));
      status.textContent=(data.warning?data.warning+' ':'')+(hits.length?hits.length+' treff. Velg en bedrift med klikk eller piltaster og Enter.':'Ingen treff. Prøv et annet navn eller opprett kort manuelt.')+(data.more?' Skriv et mer presist navn for flere relevante treff.':'');
    } catch(error) {
      if(current===sequence&&error.name!=='AbortError')status.textContent=error.message||'Søket mislyktes. Prøv igjen.';
    } finally {if(current===sequence)query.setAttribute('aria-busy','false');}
  }
  function schedule() {
    close(); const valid=query.value.trim().length>=2;
    status.textContent=valid?'Venter på søket …':'Skriv minst to tegn.';
    if(valid&&!composing)timer=setTimeout(search,300);
  }
  query.addEventListener('input',schedule);
  query.addEventListener('compositionstart',()=>{composing=true;close();});
  query.addEventListener('compositionend',()=>{composing=false;schedule();});
  query.addEventListener('keydown',event=>{
    if(event.isComposing||composing)return;
    if(event.key==='ArrowDown'||event.key==='ArrowUp'){
      event.preventDefault();
      if(hits.length)activate(event.key==='ArrowDown'?(active+1)%hits.length:(active<=0?hits.length-1:active-1));
    }
    if(event.key==='Enter'){event.preventDefault();if(active>=0)choose(active);else search();}
    if(event.key==='Escape'){event.preventDefault();close();status.textContent='Søket er lukket.';}
  });
  companySearch.addEventListener('focusout',event=>{if(!companySearch.contains(event.relatedTarget))close();});
  document.addEventListener('click',event=>{if(!companySearch.contains(event.target))close();});
  companySearch.addEventListener('submit',event=>{
    if(!hits.some(hit=>hit.value===choice.value)){event.preventDefault();return;}
    const edited=document.querySelector('.crm-edit');
    if(edited?.dataset.dirty==='true'&&!confirm('Du har ulagrede endringer i bedriftskortet. Vil du forlate dem og åpne valgt bedrift?'))event.preventDefault();
  });
  document.querySelector('.crm-edit')?.addEventListener('input',event=>{event.currentTarget.dataset.dirty='true';});
}

const websiteDraft=document.querySelector('[data-website-draft]');
if(websiteDraft)websiteDraft.addEventListener('submit',()=>{
  websiteDraft.querySelector('button').disabled=true;
  websiteDraft.querySelector('[role=status]').textContent='Leser hjemmesiden og lager reklameutkast. Vent på resultatet før du prøver igjen …';
});
const messagePreview=document.querySelector('[data-message-preview]');
if(proposalEditor&&messagePreview)proposalEditor.addEventListener('input',()=>{
  const f=proposalEditor.elements;
  // The source disclosure remains visible; there is never a second editable copy of the script.
  const disclosure=messagePreview.textContent.includes('Reklameutkastet er laget med KI-støtte')?'\n\nReklameutkastet er laget med KI-støtte og er et uforpliktende forslag.':'';
  messagePreview.textContent=f.intro.value+'\n\nFORSLAG TIL REKLAMEMANUS\n'+f.script.value+'\n\n'+f.sponsor.value+disclosure;
});
