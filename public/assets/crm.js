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
