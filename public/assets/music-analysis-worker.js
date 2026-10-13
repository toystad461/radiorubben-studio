/* Radio Rubben isolated analysis adapter. AGPL-3.0-or-later; see music-analysis/LICENSE.js. */
var exports = {};
var ready = new Promise(resolve => { self.Module = {onRuntimeInitialized: () => resolve(self.Module)}; });
importScripts('music-analysis/essentia-wasm.umd.js','music-analysis/essentia.js-core.umd.js');
self.onmessage = async ({data}) => {
  let vector, rhythm;
  try {
    const engine = new Essentia(await ready);
    vector = engine.arrayToVector(data.samples);
    rhythm = engine.RhythmExtractor2013(vector,208,'multifeature',40);
    const key = engine.KeyExtractor(vector);
    self.postMessage({bpm:rhythm.bpm,key:key.key,scale:key.scale,tempoConfidence:rhythm.confidence,keyStrength:key.strength});
  } catch (_) { self.postMessage({error:'Lydanalysen kunne ikke fullføres.'}); }
  finally { if(rhythm)for(const v of Object.values(rhythm))if(v&&typeof v.delete==='function')v.delete();if(vector)vector.delete(); }
};
