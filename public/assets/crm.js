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

// Native select keeps keyboard and touch selection familiar. Old responses never replace newer results.
const companySearch=document.querySelector('[data-company-search]');
if(companySearch) {
  const query=companySearch.querySelector('[data-company-query]');
  const choices=companySearch.querySelector('[data-company-choices]');
  const results=companySearch.querySelector('[data-company-results]');
  const status=companySearch.querySelector('[role=status]');
  const open=companySearch.querySelector('[data-company-open]');
  let timer, sequence=0, controller;
  function clearResults() {
    choices.replaceChildren(new Option('Velg et treff',''));
    results.hidden=true; open.disabled=true;
  }
  async function search() {
    clearTimeout(timer);
    const current=++sequence; controller?.abort(); clearResults();
    const value=query.value.trim();
    if(value.length<2) {status.textContent='Skriv minst to tegn.';return;}
    controller=new AbortController(); status.textContent='Søker etter bedrifter …';
    try {
      const response=await fetch('/crm-register.php',{method:'POST',signal:controller.signal,body:new URLSearchParams({csrf:companySearch.elements.csrf.value,action:'search',query:value})});
      const data=await response.json();
      if(current!==sequence)return;
      if(!response.ok)throw new Error(data.error||'Søket mislyktes.');
      for(const hit of data.results) choices.add(new Option(hit.label,hit.value));
      results.hidden=!data.results.length;
      status.textContent=(data.warning?data.warning+' ':'')+(data.results.length?'Velg en bedrift. Registrerte kort åpnes for oppdatering.':'Ingen treff. Prøv et annet navn eller opprett kort manuelt.')+(data.more?' Viser de første registertreffene. Skriv et mer presist navn for å avgrense.':'');
    } catch(error) {
      if(current===sequence && error.name!=='AbortError')status.textContent=error.message||'Søket mislyktes. Prøv igjen.';
    }
  }
  query.addEventListener('input',()=>{
    clearTimeout(timer); ++sequence; controller?.abort(); clearResults();
    status.textContent=query.value.trim().length<2?'Skriv minst to tegn.':'Venter på søket …';
    if(query.value.trim().length>=2)timer=setTimeout(search,650);
  });
  query.addEventListener('keydown',event=>{
    if(event.key==='Enter'){event.preventDefault();search();}
    if(event.key==='Escape'){clearTimeout(timer);++sequence;controller?.abort();clearResults();status.textContent='Søket er lukket.';}
  });
  companySearch.querySelector('[data-company-search-button]').addEventListener('click',search);
  choices.addEventListener('change',()=>{open.disabled=!choices.value;});
  companySearch.addEventListener('submit',event=>{
    if(!choices.value){event.preventDefault();return;}
    const edited=document.querySelector('.crm-edit');
    if(edited?.dataset.dirty==='true' && !confirm('Du har ulagrede endringer i bedriftskortet. Vil du forlate dem og åpne valgt bedrift?'))event.preventDefault();
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
