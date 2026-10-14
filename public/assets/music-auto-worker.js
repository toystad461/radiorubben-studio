/* Radio Rubben adapter, AGPL-3.0-or-later. YAMNet/TensorFlow.js: Apache-2.0; see yamnet/LICENSE.js. */
var exports={};var ready=new Promise(resolve=>{self.Module={onRuntimeInitialized:()=>resolve(self.Module)};});
importScripts('music-analysis/essentia-wasm.umd.js','music-analysis/essentia.js-core.umd.js','music-analysis/yamnet/tf.min.js');
self.onmessage=async({data})=>{
 let vector,rhythm,model;
 try{
  self.postMessage({progress:'Tempo og toneart'});const engine=new Essentia(await ready);vector=engine.arrayToVector(data.tonal);rhythm=engine.RhythmExtractor2013(vector,208,'multifeature',40);const key=engine.KeyExtractor(vector);
  const measurements={engine:'essentia.js-0.1.3',bpm:rhythm.bpm,key:key.key,scale:key.scale,tempoConfidence:rhythm.confidence,keyStrength:key.strength,startSeconds:data.startSeconds,durationSeconds:data.tonalDuration};
  const samples=data.whole,sr=16000,duration=samples.length/sr,hop=400;let sum=0,max=0,clipped=0;const powers=[];
  for(let begin=0;begin<samples.length;begin+=hop){let power=0;const end=Math.min(samples.length,begin+hop);for(let i=begin;i<end;i++){const s=samples[i];power+=s*s;sum+=s*s;max=Math.max(max,Math.abs(s));if(Math.abs(s)>=.999)clipped++;}powers.push(Math.sqrt(power/(end-begin)));}
  let first=-1,last=-1;for(let i=0;i<powers.length-2;i++)if(powers[i]>.001&&powers[i+1]>.001&&powers[i+2]>.001){if(first<0)first=i;last=i+2;}if(first<0)throw Error('Ingen tydelig lyd funnet.');
  const profile={engine:'rr-audio-queue-1',durationSeconds:duration,rmsDbfs:Math.max(-160,20*Math.log10(Math.sqrt(sum/samples.length))),peakDbfs:Math.max(-160,20*Math.log10(max)),clippedFraction:clipped/samples.length,leadingSilenceSeconds:first*hop/sr,trailingSilenceSeconds:Math.max(0,duration-(last+1)*hop/sr),vocalStartSeconds:null,lastVocalSeconds:null,vocalScoreMax:0,voiceModel:'yamnet-tfjs-1',voiceCoverageSeconds:duration,waveformPeaks:Array.from({length:256},(_,bin)=>{const a=Math.floor(bin*samples.length/256),b=Math.floor((bin+1)*samples.length/256);let peak=0;for(let i=a;i<b;i++)peak=Math.max(peak,Math.abs(samples[i]));return Math.round(Math.min(2,peak)*1000)/1000;})};
  self.postMessage({progress:'Laster vokalmotor'});await tf.setBackend('cpu');await tf.ready();model=await tf.loadGraphModel('music-analysis/yamnet/model.json');
  // AudioSet classes: speech0–5, singing24–32. Require three adjacent positive frames; never infer absence of vocals from no detection.
  const classes=[0,1,2,3,4,5,24,25,26,27,28,29,30,31,32],chunkSeconds=9.6,chunkSamples=153600;let run=0,runStart=0,lastPositive=null;
  for(let offset=0;offset<samples.length;offset+=chunkSamples){let input,output;try{const slice=samples.slice(offset,Math.min(samples.length,offset+chunkSamples));input=tf.tensor1d(slice);output=await model.executeAsync(input,'Identity');if(output.shape[1]!==521)throw Error('Ukjent vokalmodellformat.');const scores=await output.data();
    for(let f=0;f<output.shape[0];f++){const time=offset/sr+f*.48;if(time>=Math.min(duration,offset/sr+slice.length/sr)-.48)continue;let score=0;for(const c of classes)score=Math.max(score,scores[f*521+c]);profile.vocalScoreMax=Math.max(profile.vocalScoreMax,score);if(score>=.55){if(lastPositive===null||time-lastPositive>.75){run=0;}if(run===0)runStart=time;run++;lastPositive=time;if(run>=3){if(profile.vocalStartSeconds===null)profile.vocalStartSeconds=runStart;profile.lastVocalSeconds=Math.min(duration,time+.96);}}else{run=0;lastPositive=null;}}
   }finally{tf.dispose([input,output].filter(Boolean));}self.postMessage({progress:'Vokal og avslutning · '+Math.min(100,Math.round((offset+chunkSamples)/samples.length*100))+' %'});await new Promise(r=>setTimeout(r,0));
  }
  self.postMessage({measurements,profile});
 }catch(error){self.postMessage({error:error.message||'Musikkanalysen kunne ikke fullføres.'});}
 finally{model?.dispose();if(rhythm)for(const v of Object.values(rhythm))if(v&&typeof v.delete==='function')v.delete();vector?.delete();}
};
