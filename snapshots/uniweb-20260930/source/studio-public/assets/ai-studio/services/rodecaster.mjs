/** Only interpret documented send messages after operator confirms a RØDECaster input. */
export function interpretRode(m) {
  if (m.type !== 'cc') return null;
  if (m.number === 27 && m.channel <= 6 && [0,1].includes(m.value)) return {type:m.value ? 'RODE_MUTE':'RODE_UNMUTE',label:`Kanal ${m.channel}: mute ${m.value ? 'på':'av'} (MIDI)`};
  if (m.number === 24 && m.channel <= 6 && [0,1].includes(m.value)) return {type:'RODE_LISTEN',label:`Kanal ${m.channel}: listen ${m.value ? 'på':'av'} (MIDI)`};
  if (m.number === 20 && m.channel <= 6 && m.value === 1) return {type:'RODE_CHANNEL_BUTTON',label:`Kanalknapp ${m.channel}`};
  if (m.number === 17 && m.channel === 1 && m.value === 1) return {type:'RODE_RECORD_BUTTON',label:'Opptaksknapp trykket · opptaksstatus ukjent'};
  return null;
}
