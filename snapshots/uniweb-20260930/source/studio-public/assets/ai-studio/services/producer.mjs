export const emptyContext = () => ({live:null, program:null,presenter:null,nowPlaying:null,next:null,remainingSeconds:null,source:'unavailable'});
export const demoContext = () => ({live:null,program:'God morgen Vestland',presenter:'Thomas',nowPlaying:'Queen – Radio Ga Ga',next:'Bryan Adams – Summer of ’69',remainingSeconds:null,source:'demo'});
export function publicStudioStatus(context) {
  // Deliberate whitelist. MIDI mute is never evidence of microphone on air.
  return {live:context.source === 'demo' ? null : context.live,program:context.program,presenter:context.presenter,nowPlaying:context.nowPlaying,micState:'unknown'};
}
export function estimateSeconds(text) { const words = text.trim().split(/\s+/).filter(Boolean).length; return Math.ceil(words / 2.3); }
export function prepareDraft(action, context, current = '') {
  if (action === 'shorter') return current.trim().split(/\s+/).slice(0, Math.max(1, Math.floor(current.trim().split(/\s+/).length * .7))).join(' ');
  if (action === 'local') return 'Lokalt stikk: [Sett inn en kontrollert lokal sak, kilde og dato.]\nHva betyr saken for folk her hos oss?';
  if (action === 'weather') return 'Værstikk: [Sett inn oppdatert varsel, sted, kilde og tidspunkt.]';
  if (action === 'sponsor') return 'Sponsorstikk: [Sett inn godkjent sponsortekst og nødvendig reklamemerking.]';
  if (action === 'next') return context.next ? `Her på Radio Rubben skal vi straks høre ${context.next}. Hyggelig at du er med oss!` : 'Neste låt: [Legg inn bekreftet artist og tittel.]';
  return `Du hører Radio Rubben${context.program ? ` og ${context.program}` : ''}. ${context.nowPlaying ? `Nettopp hørte vi ${context.nowPlaying}. ` : '[Legg inn forrige låt.] '}Hyggelig at du er med oss. ${context.next ? `Snart får du ${context.next}.` : '[Legg inn neste programpost.]'}`;
}
