#!/usr/bin/env python3
import importlib.util,pathlib,json,subprocess,time,hashlib,shutil
spec=importlib.util.spec_from_file_location('stream',pathlib.Path(__file__).with_name('test-studio-stream.py'));m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
t=m.Integration();t.tone_duration=18;ok=False
try:
 t.fixture();(t.site/'scripts').symlink_to(m.REPO/'scripts',target_is_directory=True);(t.site/'vendor').symlink_to(m.REPO/'vendor',target_is_directory=True)
 songs=[{'trackId':t.tracks[i]['id'],'sha256':t.tracks[i]['sha256'],'artist':'Synthetic','title':str(i),'cueIn':2,'duration':7}for i in [0,2,3]]
 request={'hourUtc':int(time.time())+180,'duration':21,'songs':songs,'key':hashlib.sha256(b'worker-test').hexdigest()}
 queue={'studio':{'rotation':{'bridgeEnabled':True,'bridgeRequest':request},'policies':{}}};qpath=t.root/'bridge-q.json';qpath.write_text(json.dumps(queue))
 runner=t.root/'bridge.php';runner.write_text('<?php require '+json.dumps(str(t.site/'app/music-studio.php'))+';require '+json.dumps(str(t.site/'app/music-hour-bridge-worker.php'))+';echo music_hour_bridge_prepare(json_decode(file_get_contents($argv[1]),true),$argv[2],time());')
 args=[t.php,str(runner),str(qpath),str(t.library.parent)]
 assert subprocess.check_output(args,text=True).strip()=='prepared'
 index=json.loads(t.library.read_text());created=[x for x in index['tracks']if x.get('hourBridge')]
 assert len(created)==1 and created[0]['technicalCheck']['ready'] and created[0]['cuePoints']['points']['end']==21
 assert subprocess.check_output(args,text=True).strip()=='ready'
 assert len(json.loads(t.library.read_text())['tracks'])==len(t.tracks)+1
 t.tracks.append(created[0]);ids=t.queue([4,len(t.tracks)-1,5]);path=t.site/'config/music-play-queue.private.json';q=json.loads(path.read_text());q['studio']['rotation']={'enabled':True,'crossfade':0};q['studio']['editableNext']=True
 for i,e in enumerate(q['entries']):
  e['plannedAt']='2026-11-02T14:00:00+01:00';e['playback']={'cueIn':0,'cueOut':21 if i==1 else 18,'crossfadeOut':0}
 q['entries'][1]['hourBridgeHour']=request['hourUtc'];path.write_text(json.dumps(q));t.change({'action':'sync'});t.start_icecast();t.start_worker();listener=t.listen();t.change({'action':'start'})
 t.until(lambda:all(t.player()['outcomes'].get(i,{}).get('status')=='completed'for i in ids),'three-source montage finishes between songs',timeout=110)
 listener.close();outcomes=t.player()['outcomes']
 assert t.started_ids()[:3]==ids
 assert abs(outcomes[ids[0]]['renderedSeconds']-18)<.2
 assert abs(outcomes[ids[1]]['renderedSeconds']-21)<.2
 assert abs(outcomes[ids[2]]['renderedSeconds']-18)<.2
 gaps=[outcomes[b]['renderClockStart']-outcomes[a]['renderClockEnd']for a,b in zip(ids,ids[1:])];assert all(abs(x)<.2 for x in gaps),gaps
 recording=t.root/'bridge-stream.mp3';recording.write_bytes(listener.data);subprocess.run([t.ffmpeg,'-v','error','-i',str(recording),'-f','null','-'],check=True)
 print(json.dumps({'prepared':True,'idempotent':True,'originalTracksPreserved':len(t.tracks)-1,'montageDuration':outcomes[ids[1]]['renderedSeconds'],'gaps':gaps}));ok=True
finally:t.close(ok)
