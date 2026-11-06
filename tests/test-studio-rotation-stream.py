#!/usr/bin/env python3
import importlib.util,json,time,subprocess,math,os
from pathlib import Path
from array import array
spec=importlib.util.spec_from_file_location('stream',Path(__file__).with_name('test-studio-stream.py'))
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
xf=float(os.environ.get('RR_TEST_CROSSFADE','3'));edge=xf!=3;duration=30 if xf==10 else 14
t=m.Integration();t.tone_duration=duration+2;ok=False
try:
 t.fixture();ids=t.queue([0,2] if edge else [0,2,3,1]);qpath=t.site/'config/music-play-queue.private.json'
 q=json.loads(qpath.read_text());q['studio']['rotation']={'enabled':True,'crossfade':xf}
 for i,e in enumerate(q['entries']):
  e['playback']={'cueIn':1,'cueOut':duration+1,'crossfadeOut':xf if i<(1 if edge else 2) else 0}
  e['plannedAt']=f'2026-11-01T07:{i:02}:00+01:00'
 qpath.write_text(json.dumps(q));t.change({'action':'sync'})
 t.start_icecast();t.start_worker();listener=t.listen();t.change({'action':'start'})
 t.until(lambda:all(t.player()['outcomes'].get(x,{}).get('status')=='completed' for x in ids),'four cue-cut PCM completions',timeout=65)
 t.until(lambda:t.player()['mode']=='stopped','end of queue');listener.close()
 assert t.started_ids()==ids,t.started_ids()
 outcomes=[t.player()['outcomes'][x] for x in ids]
 for o in outcomes:assert abs(o['renderedSeconds']-duration)<.2,o
 overlaps=[outcomes[i]['renderClockEnd']-outcomes[i+1]['renderClockStart'] for i in range(len(ids)-1)]
 assert all(abs(v-xf)<.2 for v in overlaps[:(1 if edge else 2)]),overlaps
 if not edge:assert abs(overlaps[2])<.15,overlaps
 p=t.root/'crossfade.mp3';p.write_bytes(listener.data)
 decoded=subprocess.check_output([t.ffmpeg,'-v','error','-i',str(p),'-ac','1','-ar','8000','-f','f32le','-'])
 samples=array('f');samples.frombytes(decoded);mixed=[0,0,0];windows=[]
 for start in range(0,len(samples)-800,800):
  a=samples[start:start+800];powers=[]
  for f in [440,880,990,660]:
   powers.append(abs(sum(v*complex(math.cos(2*math.pi*f*i/8000),math.sin(2*math.pi*f*i/8000)) for i,v in enumerate(a)))/800)
  for i,pair in enumerate([(0,1),(1,2),(2,3)]):
   if min(powers[pair[0]],powers[pair[1]])>.003:mixed[i]+=.1
 if edge:
  assert mixed[0]>xf*.8 if xf else mixed[0]<.2,mixed
 else:
  assert min(mixed[:2])>2,mixed
  assert mixed[2]<.2,mixed
 assert t.library.read_bytes()==t.library_bytes
 print(json.dumps({'passed':True,'overlapSeconds':overlaps,'audibleMixedSeconds':mixed,'durations':[x['renderedSeconds'] for x in outcomes]}));ok=True
finally:t.close(ok)
