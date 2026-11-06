#!/usr/bin/env python3
"""Verify actual rendered duration, order and rejection of changed source audio."""
import importlib.util,pathlib,tempfile,subprocess,json,hashlib,array,math
spec=importlib.util.spec_from_file_location('bridge',pathlib.Path(__file__).resolve().parents[1]/'scripts/music-hour-bridge.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
with tempfile.TemporaryDirectory(prefix='rr-hour-render-')as tmp:
 root=pathlib.Path(tmp);(root/'originals').mkdir();songs=[]
 for i,hz in enumerate([440,880,1320]):
  identity=hashlib.md5(str(i).encode()).hexdigest();p=root/'originals'/(identity+'.mp3')
  subprocess.run(['ffmpeg','-v','error','-f','lavfi','-i',f'sine=frequency={hz}:duration=18','-c:a','libmp3lame',str(p)],check=True)
  songs.append({'trackId':identity,'sha256':hashlib.sha256(p.read_bytes()).hexdigest(),'cueIn':2,'duration':7})
 r={'songs':songs,'duration':21};out=root/'montage.mp3';result=m.render(root,r,out)
 assert abs(result['duration']-21)<.03,result
 pcm=subprocess.check_output(['ffmpeg','-v','error','-i',str(out),'-f','f32le','-ac','1','-ar','8000','-']);samples=array.array('f',pcm)
 for i,hz in enumerate([440,880,1320]):
  segment=samples[(i*7+2)*8000:(i*7+3)*8000]
  crossings=sum(1 for a,b in zip(segment,segment[1:])if a<=0<b)
  assert abs(crossings-hz)<3,(i,crossings)
 r['songs'][0]['sha256']='0'*64
 try:m.render(root,r,root/'invalid.mp3')
 except AssertionError:pass
 else:raise AssertionError('Changed source accepted')
 assert not(root/'invalid.mp3').exists()
 print(json.dumps({'durationSeconds':result['duration'],'threeExcerptsInOrder':True,'changedSourceRejected':True}))
