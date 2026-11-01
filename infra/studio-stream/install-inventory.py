#!/usr/bin/env python3
"""Read only: bounded configuration metadata needed for the L01 test install.

Never prints raw Nginx configuration, environment, credentials or journal rows.
Does not install, restart, reload or edit anything.
"""
import datetime
import grp
import hashlib
import json
import os
from pathlib import Path
import pwd
import re
import socket
import stat
import subprocess

ROOT = Path('/srv/radiorubben-test')

def run(args):
    return subprocess.run(args, text=True, capture_output=True, timeout=10,
        env={'PATH':'/usr/sbin:/usr/bin:/sbin:/bin','LC_ALL':'C'})

def nginx_metadata(text):
    result=[]
    for part in re.split(r'^# configuration file ',text,flags=re.M)[1:]:
        name,sep,body=part.partition(':\n')
        if not sep or not re.fullmatch(r'/etc/nginx/[A-Za-z0-9_./-]+',name):
            continue
        roots=re.findall(r'^\s*root\s+(/srv/radiorubben-test/[A-Za-z0-9_./-]+)\s*;',body,re.M)
        if not any('/studio/' in p or '/releases/studio-' in p for p in roots):
            continue
        result.append({'file':name,'dump_section_sha256':hashlib.sha256(body.encode()).hexdigest(),
            'roots':roots,'fastcgi_sockets':sorted(set(re.findall(r'fastcgi_pass\s+unix:(/[A-Za-z0-9_./-]+)\s*;',body))),
            'already_has_stream_route':'/music-test-stream/' in body})
    return result

def file_metadata(p):
    if not p.exists() and not p.is_symlink():return {'status':'missing'}
    s=p.lstat()
    return {'status':'observed','uid':s.st_uid,'gid':s.st_gid,
        'mode':oct(stat.S_IMODE(s.st_mode)),'symlink':stat.S_ISLNK(s.st_mode),
        'type':'directory' if stat.S_ISDIR(s.st_mode) else 'file' if stat.S_ISREG(s.st_mode) else 'socket' if stat.S_ISSOCK(s.st_mode) else 'other'}

def collect():
    if os.geteuid()!=0 or socket.gethostname()!='vps-5363103':
        raise RuntimeError('existing_test_administrator_required')
    test=run(['/usr/sbin/nginx','-t'])
    dump=run(['/usr/sbin/nginx','-T'])
    if len(dump.stdout)>2*1024*1024:raise RuntimeError('nginx_output_limit')
    version=run(['/usr/sbin/nginx','-V'])
    accounts={}
    for name in ('rr-playout','rr-stream','rr-studio'):
        try:
            p=pwd.getpwnam(name)
            accounts[name]={'uid':p.pw_uid,'gid':p.pw_gid,'group':grp.getgrgid(p.pw_gid).gr_name}
        except KeyError:accounts[name]={'status':'missing'}
    listening=run(['/usr/bin/ss','-H','-ltn'])
    port_free=listening.returncode==0 and not any(
        len(fields:=line.split())>=4 and fields[3].endswith(':18980')
        for line in listening.stdout.splitlines())
    journal=ROOT/'playout/events.jsonl';last={}
    if journal.exists():
        if journal.is_symlink() or not journal.is_file() or journal.stat().st_size>16*1024*1024:
            raise RuntimeError('journal_requires_review')
        with journal.open() as f:
            for line in f:
                if line.strip():last=json.loads(line)
    state=last.get('state',{})
    mode=state.get('mode')
    report={'status':'installation_inventory_only','observed_at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),
        'nginx_test_ok':test.returncode==0,'nginx_dump_ok':dump.returncode==0,
        'nginx_auth_request_built_in':'--with-http_auth_request_module' in version.stderr,
        'test_studio_configs':nginx_metadata(dump.stdout),'service_accounts':accounts,
        'loopback_port_18980_free':port_free,
        'player_mode':mode if mode in ('playing','paused','stopped','blocked','starting') else 'unverified',
        'has_current_entry':bool(state.get('current')),
        'paths':{str(p):file_metadata(p) for p in [ROOT,ROOT/'player-code',ROOT/'playout',
            ROOT/'playout/audio',ROOT/'stream-runtime',ROOT/'studio-data',ROOT/'studio-sessions']}}
    for item in report['test_studio_configs']:
        for sock in item['fastcgi_sockets']:report['paths'][sock]=file_metadata(Path(sock))
    return report

if __name__=='__main__':
    try:print(json.dumps(collect(),indent=2))
    except Exception:
        print('{"status":"unverified","reason":"inventory_failed_no_raw_output"}')
        raise SystemExit(1)
