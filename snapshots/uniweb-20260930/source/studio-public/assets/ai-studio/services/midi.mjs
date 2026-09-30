export function parseMidi(bytes, timestamp = new Date().toISOString()) {
  const data = Array.from(bytes ?? []);
  const status = data[0];
  if (!Number.isInteger(status) || status < 128 || status > 255) return null;
  const family = status >> 4;
  const size = status < 240 ? ([12, 13].includes(family) ? 2 : 3) : 1;
  if (data.length < size || data.slice(1).some(n => !Number.isInteger(n) || n < 0 || n > 127)) return null;
  const types = {8:'noteoff',9:'noteon',10:'poly-pressure',11:'cc',12:'program',13:'pressure',14:'pitch'};
  let type = types[family] ?? 'system';
  if (type === 'noteon' && data[2] === 0) type = 'noteoff';
  return { timestamp, channel: status < 240 ? (status & 15) + 1 : null, type,
    number: ['cc','noteon','noteoff','poly-pressure'].includes(type) ? data[1] : null,
    value: type === 'pitch' ? data[1] + (data[2] << 7) : size === 2 ? data[1] : data[2] ?? null,
    raw: data };
}
export function likelyRode(port) {
  // Identification hint only: generic MIDI Function ports require manual selection.
  return /r[øo]decaster|\br[øo]de\b/i.test(`${port.manufacturer ?? ''} ${port.name ?? ''}`);
}
export class MidiService {
  constructor(bus, requestAccess = options => navigator.requestMIDIAccess(options)) {
    this.bus = bus; this.requestAccess = requestAccess; this.access = null;
    this.input = null; this.output = null; this.inputId = ''; this.outputId = '';
    this.manualInput = false; this.stopped = false; this.queue = Promise.resolve(); this.generation = 0;
  }
  ports(kind) { return Array.from(this.access?.[kind]?.values() ?? []).filter(p => p.state === 'connected'); }
  report(error = '') {
    this.bus.emit('MIDI_STATUS', { connected: !!this.input && this.input.state === 'connected' && this.input.connection === 'open',
      inputId: this.inputId, outputId: this.outputId, input: this.input?.name ?? '', output: this.output?.name ?? '',
      outputConnected: !!this.output && this.output.state === 'connected' && this.output.connection === 'open',
      inputs: this.ports('inputs').map(p => ({id:p.id,name:p.name ?? 'MIDI Input',manufacturer:p.manufacturer ?? ''})),
      outputs: this.ports('outputs').map(p => ({id:p.id,name:p.name ?? 'MIDI Output',manufacturer:p.manufacturer ?? ''})),
      error, log: error || (this.input ? 'MIDI-port åpen' : 'Ingen MIDI-inngang tilkoblet') });
  }
  async start() {
    const generation = ++this.generation;
    this.stopped = false;
    try {
      this.access ??= await this.requestAccess({sysex:false});
      if (this.stopped || generation !== this.generation) return;
      this.access.onstatechange = () => { if (!this.stopped) void this.refresh(); };
      await this.refresh();
    } catch { if (generation === this.generation) this.report('MIDI-tilgang ble avvist eller er utilgjengelig. Kontroller nettlesertillatelsen.'); }
  }
  refresh() {
    this.queue = this.queue.then(async () => {
      if (this.stopped) return;
      if (!this.inputId && !this.manualInput) {
        const candidates = this.ports('inputs').filter(likelyRode);
        if (candidates.length === 1) this.inputId = candidates[0].id;
      }
      try {
        await this.bind('input'); await this.bind('output'); this.report();
      } catch { this.report('MIDI-porten kunne ikke åpnes. Velg en port og prøv igjen.'); }
    });
    return this.queue;
  }
  async bind(kind) {
    const port = this.ports(`${kind}s`).find(p => p.id === this[`${kind}Id`]) ?? null;
    const previous = this[kind];
    if (previous === port && port?.connection === 'open') return;
    this[kind] = null;
    if (previous && previous !== port) {
      if (kind === 'input') previous.onmidimessage = null;
      try { await previous.close(); } catch { /* unplugged */ }
    }
    if (!port) return;
    await port.open();
    if (this.stopped || this[`${kind}Id`] !== port.id || port.state !== 'connected') { await port.close(); return; }
    this[kind] = port;
    if (kind === 'input') port.onmidimessage = event => {
      const message = parseMidi(event.data);
      if (message) this.bus.emit('MIDI_MESSAGE', { message, portId:port.id });
    };
  }
  select(kind, id) {
    if (!['input','output'].includes(kind)) return Promise.resolve();
    if (kind === 'input') this.manualInput = true;
    this[`${kind}Id`] = id; return this.refresh();
  }
  async stop() {
    this.stopped = true; ++this.generation;
    if (this.access) this.access.onstatechange = null;
    await this.queue;
    for (const kind of ['input','output']) {
      const port = this[kind]; this[kind] = null;
      if (port) { if (kind === 'input') port.onmidimessage = null; try { await port.close(); } catch { /* detached */ } }
    }
    this.report();
  }
}
