/** Small synchronous bus: one broken subscriber cannot stop the other modules. */
export class StudioEvents {
  #listeners = new Map();
  constructor(onError = () => {}) { this.onError = onError; }
  on(type, handler) {
    const group = this.#listeners.get(type) ?? new Set();
    group.add(handler); this.#listeners.set(type, group);
    return () => group.delete(handler);
  }
  emit(type, payload = {}) {
    const event = Object.freeze({ type, timestamp: new Date().toISOString(), payload });
    for (const handler of [...(this.#listeners.get(type) ?? []), ...(this.#listeners.get('*') ?? [])]) {
      try { handler(event); } catch (error) { try { this.onError(error); } catch { /* isolate reporter too */ } }
    }
    return event;
  }
}
export class StudioLog {
  constructor(bus, limit = 500) {
    this.rows = []; this.limit = limit;
    this.unsubscribe = bus.on('*', event => {
      if (['MIDI_MESSAGE','AI_STATUS'].includes(event.type)) return;
      // Only event labels and safe metadata; never manuscript, audio, credentials or raw device data.
      this.rows.unshift({ timestamp: event.timestamp, type: event.type, detail: event.payload.log ?? '' });
      this.rows.length = Math.min(this.rows.length, this.limit);
    });
  }
  clear() { this.rows = []; }
  export() { return this.rows.slice().reverse().map(r => `${r.timestamp}\t${r.type}\t${r.detail}`).join('\n'); }
}
