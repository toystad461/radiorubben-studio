export function serverProducerContext(v, now=Date.now()) {
  const age=now-Date.parse(v?.generatedAt);
  if(v?.schemaVersion!==1||!Number.isFinite(age)||age< -5000||age>5000||v.status!=='playing'||!v.queueKnown)throw Error('Fersk serverkø mangler. Oppdater før du lager musikkstikk.');
  const label=t=>t.kind==='jingle'?`Jingle: ${t.title}`:`${t.artist} – ${t.title}`;
  return {source:'vps',program:null,nowPlaying:v.now?label(v.now):null,next:v.next?.[0]?label(v.next[0]):null,nextTracks:(v.next||[]).map(label),remainingSeconds:null,live:true};
}
