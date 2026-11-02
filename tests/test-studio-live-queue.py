#!/usr/bin/env python3
"""A real output must honor an operator's next-item edit during a song."""
import importlib.util,json,subprocess,time
from pathlib import Path
spec=importlib.util.spec_from_file_location('stream',Path(__file__).with_name('test-studio-stream.py'))
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
t=m.Integration();t.tone_duration=30;ok=False
try:
 t.fixture()
 for tr in t.tracks: tr['technicalCheck']={'ready':True,'sourceSha256':tr['sha256']}
 t.library.write_text(json.dumps({'schemaVersion':1,'tracks':t.tracks}));t.library_bytes=t.library.read_bytes()
 ids=t.queue([0,2,3]);path=t.site/'config/music-play-queue.private.json';q=json.loads(path.read_text())
 q['studio']['rotation']={'enabled':True,'crossfade':3};q['studio']['editableNext']=True
 for i,e in enumerate(q['entries']):
  e['plannedAt']=f'2026-11-02T12:00:{i*15:02}+01:00';e['playback']={'cueIn':0,'cueOut':26 if i==0 else 8,'crossfadeOut':3 if i==0 else 0}
 path.write_text(json.dumps(q));t.change({'action':'sync'});t.start_icecast();t.start_worker();listener=t.listen();t.change({'action':'start'})
 t.until(lambda:(t.player().get('current')or{}).get('id')==ids[0],'first music starts')
 t.until(lambda:t.player()['position']>=3,'first music plays');assert not t.player()['reserved'],'next item was claimed too early'
 p=t.player();assert p['current']['id']==ids[0]
 body={'action':'playnext','trackId':t.tracks[1]['id'],'sha256':t.tracks[1]['sha256'],'revision':t.view()['revision']}
 assert t.api(body,role='observer')[0]==403
 assert t.api(body,csrf='invalid')[0]==403
 assert t.api(dict(body,revision=-1))[0]==409
 assert t.api(dict(body,sha256='0'*64))[0]==400
 t.change({'action':'remove','id':ids[1]})
 result=t.change({'action':'playnext','trackId':t.tracks[1]['id'],'sha256':t.tracks[1]['sha256']})
 inserted=result['entries'][1]['id'];assert inserted not in ids
 assert [e['id']for e in result['entries'][:3]]==[ids[0],inserted,ids[2]]
 assert t.player()['current']['id']==ids[0] and t.player()['streamConnected']
 # Same occurrence move must not duplicate it; active audio cannot be removed.
 t.change({'action':'playnext','id':inserted})
 assert t.api({'action':'remove','id':ids[0],'revision':t.view()['revision']})[0]==400
 t.until(lambda:bool(t.player()['reserved']),'late reservation',timeout=25)
 assert t.player()['position']>=13,t.player()['position']
 assert t.api({'action':'playnext','id':ids[2],'revision':t.view()['revision']})[0]==409
 t.until(lambda:all(t.player()['outcomes'].get(i,{}).get('status')=='completed' for i in [ids[0],inserted,ids[2]]),'edited queue finishes',timeout=85)
 listener.close();assert t.started_ids()[:3]==[ids[0],inserted,ids[2]],t.started_ids();assert ids[1] not in t.started_ids()
 outcomes=t.player()['outcomes'];a=outcomes[ids[0]];b=outcomes[inserted]
 assert abs(a['renderedSeconds']-26)<.2,a
 assert abs(a['renderClockEnd']-b['renderClockStart'])<.2,(a,b)
 assert not any(e['event']in['engine_fault','manifest_invalidated']for e in t.journal())
 out=t.root/'edited.mp3';out.write_bytes(listener.data);subprocess.run([t.ffmpeg,'-v','error','-i',str(out),'-f','null','-'],check=True)
 assert t.library.read_bytes()==t.library_bytes
 print(json.dumps({'passed':True,'started':t.started_ids(),'removedNeverPlayed':ids[1],'firstDuration':a['renderedSeconds'],'gapSeconds':b['renderClockStart']-a['renderClockEnd']}));ok=True
finally:t.close(ok)
