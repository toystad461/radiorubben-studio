#!/usr/bin/env python3
"""A six-second competing queue lock must not kill active PCM output."""
import importlib.util, fcntl, json, time
from pathlib import Path
spec=importlib.util.spec_from_file_location('stream',Path(__file__).with_name('test-studio-stream.py'))
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
t=m.Integration();t.tone_duration=20;ok=False
try:
 t.fixture();ids=t.queue([0,2]);t.start_icecast();t.start_worker();listener=t.listen();t.change({'action':'start'})
 t.until(lambda:len(t.started_ids())==1,'first PCM start',timeout=100)
 with (t.bus/'control.lock').open('r+b') as lock:
  fcntl.flock(lock,fcntl.LOCK_EX);time.sleep(6);fcntl.flock(lock,fcntl.LOCK_UN)
 t.until(lambda:all(t.player()['outcomes'].get(x,{}).get('status')=='completed' for x in ids),'both complete after lock contention',timeout=65)
 t.until(lambda:t.player()['mode']=='stopped','end of queue');listener.close()
 assert t.started_ids()==ids,t.started_ids()
 assert len(listener.data)>100000
 print(json.dumps({'passed':True,'contendedSeconds':6,'completed':ids,'receivedBytes':len(listener.data)}));ok=True
finally:t.close(ok)
