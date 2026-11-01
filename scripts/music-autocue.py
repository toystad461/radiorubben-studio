import pathlib,json,subprocess,re,hashlib,time,concurrent.futures,argparse
parser=argparse.ArgumentParser(description='Read-only full-file cue and decode check; no original changes.')
parser.add_argument('--library',required=True);parser.add_argument('--report',required=True);parser.add_argument('--missing-only',action='store_true');parser.add_argument('--limit',type=int,default=200);args=parser.parse_args()
root=pathlib.Path(args.library).resolve();tracks=json.loads((root/'library.json').read_text())['tracks']
if args.missing_only:tracks=[t for t in tracks if t.get('technicalCheck',{}).get('sourceSha256')!=t['sha256']]
tracks=tracks[:max(0,args.limit)]
def scan(t):
 assert re.fullmatch('[a-f0-9]{32}',t['id'])
 p=root/'originals'/(t['id']+'.mp3');assert not p.is_symlink();assert hashlib.sha256(p.read_bytes()).hexdigest()==t['sha256']
 r=subprocess.run(['ffmpeg','-nostdin','-hide_banner','-i',str(p),'-af','silencedetect=noise=-50dB:d=0.1,volumedetect','-f','null','-'],capture_output=True,text=True,timeout=90)
 duration=t['metadata']['durationSeconds'];leading=0.;trailing=0.;spans=[];start=None
 for line in r.stderr.splitlines():
  m=re.search(r'silence_start: ([\d.]+)',line)
  if m:start=float(m.group(1))
  m=re.search(r'silence_end: ([\d.]+)',line)
  if m and start is not None:spans.append((start,float(m.group(1))));start=None
 for a,b in spans:
  if a<.1:leading=b
  if b>=duration-.15:trailing=max(0,duration-a)
 peak=re.search(r'max_volume: (-?[\d.]+) dB',r.stderr);mean=re.search(r'mean_volume: (-?[\d.]+) dB',r.stderr)
 cueIn=round(max(0,leading-.05),3);cueOut=round(min(duration,duration-trailing+.05),3);warnings=[]
 if peak and float(peak.group(1))>=-.1:warnings.append('Peak near full scale; clipping cannot be ruled out')
 if any(b-a>2 and a>.1 and b<duration-.15 for a,b in spans):warnings.append('Internal silence over 2 seconds')
 okay=r.returncode==0 and cueOut-cueIn>=1 and cueOut-cueIn>=duration*.75 and not re.search(r'Error while decoding|Invalid data found',r.stderr)
 return {'id':t['id'],'sha256':t['sha256'],'kind':t.get('kind','music'),'name':t['originalName'],'revision':t.get('revision',0),'duration':duration,'cueIn':cueIn,'cueOut':cueOut,'leadingSilence':leading,'trailingSilence':trailing,'peakDbfs':float(peak.group(1)) if peak else None,'meanDbfs':float(mean.group(1)) if mean else None,'ready':okay,'warnings':warnings,'engine':'ffmpeg-silencedetect--50dB-100ms-v1','checkedAtUtc':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime())}
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:results=list(pool.map(scan,tracks))
p=pathlib.Path(args.report);p.write_text(json.dumps(results,ensure_ascii=False,indent=2));p.chmod(0o600)
print(json.dumps({'music':sum(t['kind']=='music' for t in results),'jingles':sum(t['kind']=='jingle' for t in results),'ready':sum(t['ready'] for t in results),'needsReview':[t['name'] for t in results if not t['ready']],'totalMusicSeconds':sum(t['duration'] for t in results if t['kind']=='music')},ensure_ascii=False))
