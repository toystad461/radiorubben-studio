#!/usr/bin/env python3
"""Saved source-bound cues -> catalog -> actual PCM, both sides of a jingle.

220/1760 Hz markers outside the cues must not reach the captured stream.
Uses only the integration fixture's synthetic media and loopback services.
"""
import importlib.util
import json
import math
import subprocess
from array import array
from pathlib import Path

spec = importlib.util.spec_from_file_location('stream', Path(__file__).with_name('test-studio-stream.py'))
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
t = m.Integration()
t.tone_duration = 12
t.cue_markers = True
ok = False
try:
    t.fixture()
    ids = t.queue([0, 2, 3, 1, 4])
    helper = t.root / 'save-cues.php'
    helper.write_text('''<?php
require $argv[1].'/app/music-studio.php';
require_once $argv[1].'/app/music-library-cues.php';
$root=$argv[1].'/media/music';$qpath=$argv[1].'/config/music-play-queue.private.json';
foreach(music_library_read($root) as $track) {
 music_library_cues_save($track['id'],$track['sha256'],$track['revision']??0,
 ['start'=>1.,'end'=>11.,'vocalStart'=>null,'transition'=>null],
 ['id'=>'test','role'=>'producer'],'synthetic','synthetic',$root);
}
foreach(music_library_read($root,'jingle') as $track) {
 music_worker_commit($root,$track,['cuePoints'=>['schemaVersion'=>1,
 'sourceSha256'=>$track['sha256'],'source'=>'automatic-silence',
 'points'=>['start'=>1.,'end'=>11.,'vocalStart'=>null,'transition'=>null]]]);
}
$q=json_decode(file_get_contents($qpath),true,512,JSON_THROW_ON_ERROR);
$q['studio']['rotation']=music_rotation_settings(['enabled'=>true,'crossfade'=>2]);
$catalog=music_schedule_catalog(music_library_read($root,'all'),$q['studio']['policies']);
foreach($q['entries'] as $i=>&$entry) {
 $entry['playback']=music_rotation_audio($catalog[$entry['trackId']]);
 $entry['playback']['crossfadeOut']=$i<2?2.:0.;
 if($i===3)$entry['eventType']='reklame';
}
unset($entry);$q=music_live_retime($q,music_playout_empty());
file_put_contents($qpath,json_encode($q,JSON_THROW_ON_ERROR));
''')
    subprocess.run([t.php, str(helper), str(t.site)], check=True)
    t.library_bytes = t.library.read_bytes()
    t.change({'action':'sync'})
    manifest = json.loads((t.bus/'control.json').read_text())
    assert all(e['playback']['cueIn']==1 and e['playback']['cueOut']==11 for e in manifest['entries'])
    t.start_icecast()
    t.start_worker()
    listener = t.listen()
    t.change({'action':'start'})
    t.until(lambda: all(t.player()['outcomes'].get(i,{}).get('status')=='completed' for i in ids),
            'five saved-cue completions', timeout=65)
    t.until(lambda: t.player()['mode']=='stopped', 'queue ended')
    listener.close()
    recording = t.root/'saved-cues.mp3'
    recording.write_bytes(listener.data)
    assert t.started_ids()==ids
    outcomes = [t.player()['outcomes'][i] for i in ids]
    assert outcomes[3]['eventType']=='reklame', 'audio type lost between queue and journal'
    durations = [x['renderedSeconds'] for x in outcomes]
    assert all(abs(d-10)<.15 for d in durations), durations
    overlaps = [outcomes[i]['renderClockEnd']-outcomes[i+1]['renderClockStart'] for i in range(4)]
    assert all(abs(v-2)<.15 for v in overlaps[:2]), overlaps
    assert all(abs(v)<.05 for v in overlaps[2:]), overlaps
    pcm = array('f')
    pcm.frombytes(subprocess.check_output([t.ffmpeg,'-v','error','-i',str(recording),'-ac','1','-ar','8000','-f','f32le','-']))
    frequencies = [220,1760,440,880,990,660,1100]
    kernels = [[complex(math.cos(2*math.pi*f*i/8000),math.sin(2*math.pi*f*i/8000)) for i in range(400)] for f in frequencies]
    marker_windows = 0
    jingle_mix_windows = []
    for start in range(0,len(pcm)-400,400):
        window = pcm[start:start+400]
        powers = [abs(sum(v*k for v,k in zip(window,kernel)))/400 for kernel in kernels]
        marker_windows += max(powers[:2])>.005
        if powers[5]>.005 and max(powers[4],powers[6])>.005:
            jingle_mix_windows.append(start/8000)
    assert marker_windows==0, marker_windows
    # One analysis window may straddle each hard boundary. Two adjacent mixed
    # windows, or sustained mixing within the jingle, must still fail.
    assert len(jingle_mix_windows)<=2, jingle_mix_windows
    if len(jingle_mix_windows)==2:
        assert abs(jingle_mix_windows[1]-jingle_mix_windows[0]-10)<.15, jingle_mix_windows
    assert t.library.read_bytes()==t.library_bytes
    for track in t.tracks:
        assert m.sha256(t.site/'media/music/originals'/(track['id']+'.mp3'))==track['sha256']
    receipt = {'passed':True,'durations':durations,'overlapSeconds':overlaps,
               'outsideCueMarkerSeconds':marker_windows*.05,'jingleMixedWindowsAt':jingle_mix_windows,
               'measurementWindowSeconds':.05,'originalsUnchanged':True}
    (t.root/'cue-receipt.json').write_text(json.dumps(receipt,indent=2))
    print(json.dumps(receipt), flush=True)
    ok = True
finally:
    t.close(ok)
