#!/usr/bin/env python3
"""Synthetic restart/Stop test on disposable HTTP, Liquidsoap and Icecast instances."""
import importlib.util,json,subprocess,shutil,time
from pathlib import Path
spec=importlib.util.spec_from_file_location('stream',Path(__file__).with_name('test-studio-stream.py'))
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
t=m.Integration();t.tone_duration=30;ok=False
try:
 t.fixture();ids=t.queue([0,2,3,1]);qpath=t.site/'config/music-play-queue.private.json'
 q=json.loads(qpath.read_text());q['studio']['rotation']={'enabled':True,'crossfade':0}
 for i,e in enumerate(q['entries']):
  e['playback']={'cueIn':0,'cueOut':20,'crossfadeOut':0}
  e['plannedAt']=f'2026-11-01T07:{i:02}:00+01:00'
 qpath.write_text(json.dumps(q));t.change({'action':'sync'})
 (t.site/'scripts').mkdir();shutil.copyfile(m.REPO/'scripts/music-boot.php',t.site/'scripts/music-boot.php')
 def boot():return subprocess.run([t.php,str(t.site/'scripts/music-boot.php')],check=True,capture_output=True,text=True,timeout=50).stdout
 t.start_icecast();t.start_worker();t.change({'action':'start'})
 t.until(lambda:t.player()['mode']=='playing','first actual audio')
 first=t.player()['current']['id'];t.stop(t.worker);t.worker=None
 t.start_worker();assert t.player()['mode']=='stopped'
 assert 'resume requested' in boot()
 t.until(lambda:t.player()['mode']=='playing','automatic actual audio after restart')
 assert t.player()['current']['id']!=first,'interrupted occurrence must not replay'
 status,public=t.request('/radio-status.php',role='anonymous');assert status==200 and public['playing']
 assert set(public['now'])=={'artist','title','kind'}
 t.change({'action':'stop'});t.until(lambda:t.player()['mode']=='stopped' and not t.child_pids(),'operator stop')
 t.stop(t.worker);t.worker=None;t.start_worker();assert 'not required' in boot()
 assert t.player()['mode']=='stopped' and not t.child_pids(),'operator Stop must survive restart'
 status,public=t.request('/radio-status.php',role='anonymous');assert status==200 and public['now'] is None
 print(json.dumps({'passed':True,'checks':['actual PCM after daemon restart','interrupted occurrence not replayed','operator stop survives restart','anonymous metadata allowlist and stopped state']}));ok=True
finally:t.close(ok)
