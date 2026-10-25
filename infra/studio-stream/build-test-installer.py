#!/usr/bin/env python3
"""Build one self-contained checksum-pinned file; never contacts a server."""
import argparse
import base64
import hashlib
import importlib.util
import json
from pathlib import Path
import re

REPO=Path(__file__).resolve().parents[2]
WEB={
    'app/music-playout.php':'b92a1dc3c7e393f12462cd0cbbcd7bd3341ee851cd475b27ffdd57c52c45552b',
    'app/music-liquidsoap.php':None,
    'app/music-studio.php':'50b2cf650bf6d12b03885d7964499cfe7e326feb34bb1adc599fdb089b4c399e',
    'public/assets/music-studio.mjs':'1b8dd0cb1a7983fff6fdeae2797d6a7fd4df5a138d513ac6ce66c6f17e867e14',
    'public/music-studio.php':'99e77bf3808f87df101f9d11d96f203a8e3286169f876c98df527a0b8241129d',
    'public/music-stream-auth.php':None,
}
PLAYER={
    'app/music-playout.php':WEB['app/music-playout.php'],
    'app/music-liquidsoap.php':None,'scripts/music-liquidsoap.php':None,
    'scripts/liquidsoap-child.py':None,'infra/studio-stream/radio.liq':None,
}

def build(source,output):
    if not re.fullmatch('[a-f0-9]{40}',source):raise ValueError('Exact source commit required')
    pre=REPO/'infra/studio-stream/preflight.py'
    spec=importlib.util.spec_from_file_location('reviewed',pre);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
    files={}
    for scope,entries in [('web',WEB),('player',PLAYER)]:
        for relative,before in entries.items():
            data=(REPO/relative).read_bytes();digest=hashlib.sha256(data).hexdigest()
            if m.REVIEWED_HASHES['candidate_l01'][relative]!=digest:raise ValueError('Source not in candidate manifest: '+relative)
            files[scope+'/'+relative]={'before':before,'after':digest,'data':base64.b64encode(data).decode()}
    route=(REPO/'infra/studio-stream/nginx-stream.conf.example').read_text()
    route=route.replace('unix:REVIEW_EXISTING_STUDIO_FPM_SOCKET','unix:/run/php/rr-studio.sock')
    if 'unix:REVIEW_EXISTING_STUDIO_FPM_SOCKET' in route:raise ValueError('Unreviewed socket')
    units={name:(REPO/'infra/studio-stream'/(name+'.service')).read_text()
           for name in ('rr-studio-icecast','rr-studio-stream')}
    payload={'source':source,'files':files,'route':route,'units':units,
             'prepare':(REPO/'infra/studio-stream/prepare.py').read_text()}
    script=(REPO/'infra/studio-stream/install-test.py').read_text()
    marker="PAYLOAD_B64=''"
    if script.count(marker)!=1:raise ValueError('Unexpected installer template')
    encoded=base64.b64encode(json.dumps(payload,sort_keys=True).encode()).decode()
    script=script.replace(marker,"PAYLOAD_B64='"+encoded+"'")
    compile(script,'L01-install-test.py','exec')
    output=Path(output);output.write_text(script)
    return hashlib.sha256(output.read_bytes()).hexdigest()

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--source-commit',required=True);p.add_argument('--output',required=True)
    a=p.parse_args();print(build(a.source_commit,a.output))
