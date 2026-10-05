export const ACTIONS = { new:'Lag neste stikk', shorter:'Gjør stikk kortere', local:'Lokal sak', weather:'Vær', next:'Presenter neste låt', sponsor:'Sponsorstikk', refresh:'Oppdater Producer', open:'Åpne AI Studio', lightsOff:'Slå av alle Hue-lys' };
export const defaults = () => Object.keys(ACTIONS).slice(0,8).map((action, i) => ({pad:i+1, action, enabled:false, channel:1, type:'noteon', number:60+i, value:127}));
export function validateMappings(rows) {
  if (!Array.isArray(rows) || rows.length !== 8) throw new Error('Mapping må inneholde åtte pads.');
  const matches = new Set();
  return rows.map((r, i) => {
    if (!r || r.pad !== i+1 || typeof r.enabled !== 'boolean' || !Object.hasOwn(ACTIONS,r.action)
      || !['cc','noteon'].includes(r.type) || !Number.isInteger(r.channel) || r.channel < 1 || r.channel > 16
      || !Number.isInteger(r.number) || r.number < 0 || r.number > 127 || !Number.isInteger(r.value) || r.value < 0 || r.value > 127
      || (r.type === 'noteon' && r.value === 0)) throw new Error(`Ugyldige verdier for PAD ${i+1}.`);
    const key = `${r.channel}:${r.type}:${r.number}:${r.value}`;
    if (r.enabled && matches.has(key)) throw new Error('To aktive pads kan ikke bruke samme MIDI-melding.');
    if (r.enabled) matches.add(key);
    return {pad:r.pad,action:r.action,enabled:r.enabled,channel:r.channel,type:r.type,number:r.number,value:r.value};
  });
}
export class PadMapping {
  constructor(bus, rows = defaults()) { this.bus = bus; this.set(rows); this.recent = new Map(); }
  set(rows) { this.rows = validateMappings(rows); this.recent?.clear(); }
  match(message) { return this.rows.find(r => r.enabled && ['channel','type','number','value'].every(k => r[k] === message[k])); }
  handle(message, now = performance.now()) {
    const row = this.match(message); if (!row) return;
    // Suppress short duplicate bursts, while allowing pads configured to send On-only.
    if (now - (this.recent.get(row.pad) ?? -Infinity) < 180) return;
    this.recent.set(row.pad, now);
    this.bus.emit('RODE_PAD_PRESSED', {pad:row.pad,action:row.action,log:`PAD ${row.pad} · ${ACTIONS[row.action]}`});
    this.bus.emit('PRODUCER_ACTION', {action:row.action,log:ACTIONS[row.action]});
  }
}
