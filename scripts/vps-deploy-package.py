#!/usr/bin/env python3
"""Build an explicit, reviewed file set; never package an entire branch."""
import base64
import importlib.util
import json
from pathlib import Path
import re
import subprocess
import sys

spec = importlib.util.spec_from_file_location('gateway', Path(__file__).with_name('vps-deploy-gateway.py'))
g = importlib.util.module_from_spec(spec)
spec.loader.exec_module(g)
request = json.loads(Path('deploy/vps-test.json').read_text())
mode = request.get('mode')
if mode == 'snapshot':
    result = {'mode': 'snapshot'}
else:
    if mode not in ('check', 'apply'):
        raise SystemExit('Invalid deployment mode')
    commit = request.get('commit', '')
    if not re.fullmatch('[0-9a-f]{40}', commit):
        raise SystemExit('Exact source SHA required')
    actual = subprocess.check_output(['git', 'rev-parse', commit + '^{commit}'], text=True).strip()
    if actual != commit:
        raise SystemExit('Source commit mismatch')
    result = {'mode': mode, 'commit': commit, 'files': []}
    for item in request['files']:
        name = item['path']
        if not g.allowed(name):
            raise SystemExit('Forbidden path')
        data = subprocess.check_output(['git', 'show', commit + ':' + name])
        if g.digest(data) != item['after']:
            raise SystemExit('Source hash mismatch: ' + name)
        result['files'].append({**item, 'content': base64.b64encode(data).decode()})
    if not 1 <= len(result['files']) <= 64:
        raise SystemExit('Expected 1 to 64 files')
encoded = json.dumps(result)
if len(encoded.encode()) > g.LIMIT:
    raise SystemExit('Package too large')
Path(sys.argv[1]).write_text(encoded)
