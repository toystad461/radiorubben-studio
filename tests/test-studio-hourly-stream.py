#!/usr/bin/env python3
"""Real isolated Liquidsoap/Icecast transport of the generated double beep."""
import importlib.util
import json
import subprocess
import hashlib
import math
from array import array
from pathlib import Path

spec = importlib.util.spec_from_file_location('stream', Path(__file__).with_name('test-studio-stream.py'))
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
t = m.Integration()
t.tone_duration = 14
ok = False
try:
    t.fixture()
    wav = t.root / 'hour.wav'
    subprocess.run(['python3', str(m.REPO/'scripts/make-hour-signal.py'), str(wav)], check=True)
    track = t.tracks[1]
    mp3 = t.site/'media/music/originals'/f"{track['id']}.mp3"
    subprocess.run([t.ffmpeg, '-y', '-v', 'error', '-i', str(wav), '-c:a', 'libmp3lame', '-b:a', '192k', str(mp3)], check=True)
    sha = hashlib.sha256(mp3.read_bytes()).hexdigest()
    track.update(sha256=sha, bytes=mp3.stat().st_size, jingle={'category':'hour','disabled':False}, technicalCheck={'ready':True,'sourceSha256':sha})
    track['metadata'].update(sourceSha256=sha, durationSeconds=1.254)
    t.library.write_text(json.dumps({'schemaVersion':1,'tracks':t.tracks}))
    t.library_bytes = t.library.read_bytes()
    ids = t.queue([0,1,3])
    path = t.site/'config/music-play-queue.private.json'
    q = json.loads(path.read_text())
    q['studio']['rotation'] = {'enabled':True,'crossfade':3}
    for i,e in enumerate(q['entries']):
        e['playback'] = {'cueIn':0,'cueOut':1.2 if i==1 else 12,'crossfadeOut':0}
        e['plannedAt'] = f'2026-11-02T12:00:{i*12:02}+01:00'
    q['entries'][1]['hourlyHour'] = 1793617200
    path.write_text(json.dumps(q))
    t.change({'action':'sync'})
    body={'action':'hourly','revision':t.view()['revision'],'hourly':{'trackId':track['id'],'sha256':sha,'notBefore':1793617200}}
    assert t.api(body,role='presenter')[0]==400
    assert t.api(body,csrf='invalid')[0]==403
    t.start_icecast()
    t.start_worker()
    listener = t.listen()
    t.change({'action':'start'})
    t.until(lambda:all(t.player()['outcomes'].get(i,{}).get('status')=='completed' for i in ids),'hour signal between intact music',timeout=50)
    listener.close()
    outcomes = [t.player()['outcomes'][i] for i in ids]
    assert t.started_ids()==ids
    assert outcomes[1]['hourlyHour']==1793617200
    assert abs(outcomes[1]['renderedSeconds']-1.2)<.15
    for i in (0,1):
        assert abs(outcomes[i]['renderClockEnd']-outcomes[i+1]['renderClockStart'])<.15
    output = t.root/'hour.mp3'
    output.write_bytes(listener.data)
    subprocess.run([t.ffmpeg,'-v','error','-i',str(output),'-f','null','-'],check=True)
    raw = subprocess.check_output([t.ffmpeg,'-v','error','-i',str(output),'-ac','1','-ar','8000','-f','f32le','-'])
    samples = array('f'); samples.frombytes(raw)
    pulses = []; active = False
    for start in range(0,len(samples)-160,160):
        power = abs(sum(v*complex(math.cos(2*math.pi*880*i/8000),math.sin(2*math.pi*880*i/8000)) for i,v in enumerate(samples[start:start+160])))/160
        on = power > .02
        if on and not active: pulses.append(0.)
        if on: pulses[-1] += .02
        active = on
    assert len(pulses)==2 and all(.1<p<.22 for p in pulses),pulses
    assert t.library.read_bytes()==t.library_bytes
    print(json.dumps({'passed':True,'started':ids,'duration':[e['renderedSeconds'] for e in outcomes],'hourIdentityPreserved':True,'musicOverlapIntoSignal':False}))
    ok = True
finally:
    t.close(ok)
