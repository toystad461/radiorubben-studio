#!/usr/bin/env python3
"""Pinned L01 installer. Default is read-only; --apply needs explicit approval.

The release builder embeds only the reviewed payload in the downloadable copy.
No SSH, sudo, PHP network isolation, production or original-media changes.
"""
import argparse
import base64
import datetime
import fcntl
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import pwd
import re
import shutil
import socket
import stat
import subprocess
import tempfile
import time

ROOT=Path('/srv/radiorubben-test')
STATE=Path('/var/lib/rr-vps-deploy')
CONF=Path('/etc/nginx/rr-test-apps.conf')
UNITS=('rr-studio-icecast','rr-studio-stream')
PAYLOAD_B64=''  # populated in the checksum-pinned downloadable copy only
NGINX_SECTION='295a1fbd410bd38b33025a2908d6a2aa6e6fbdc909d01778ee583472bbed2e4f'

def need(ok,reason):
    if not ok:raise RuntimeError(reason)

def sha(data):return hashlib.sha256(data).hexdigest()

def command(args,check=True,input=None):
    p=subprocess.run(args,input=input,capture_output=True,timeout=40,
        env={'PATH':'/usr/sbin:/usr/bin:/sbin:/bin','LC_ALL':'C'})
    if check:need(p.returncode==0,'command_failed:'+Path(args[0]).name)
    return p

def ancestors(path):
    for p in [path,*path.parents]:
        s=p.lstat();need(not stat.S_ISLNK(s.st_mode) and s.st_uid==0 and not s.st_mode&0o022,'unsafe_path:'+str(p))

def read(path,limit=16*1024*1024):
    need(not path.is_symlink() and path.is_file(),'not_regular:'+str(path))
    s=path.stat();need(s.st_nlink==1 and s.st_size<=limit,'unsafe_file:'+str(path))
    return path.read_bytes()

def atomic(path,data,mode=0o644,uid=0,gid=0):
    fd,tmp=tempfile.mkstemp(prefix='.rr-l01-',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:
            f.write(data);f.flush();os.fchmod(f.fileno(),mode);os.fchown(f.fileno(),uid,gid);os.fsync(f.fileno())
        os.replace(tmp,path)
        d=os.open(path.parent,os.O_DIRECTORY)
        try:os.fsync(d)
        finally:os.close(d)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

def patch_nginx(text,route,maintenance=False):
    marker=re.compile(r'(?m)^[ \t]*root /srv/radiorubben-test/studio/public;[ \t]*$')
    matches=list(marker.finditer(text));need(len(matches)==1,'ambiguous_studio_server')
    need('/music-test-stream/' not in text and '/_music_stream_auth' not in text,'stream_route_already_exists')
    at=matches[0].end()
    prefix=text[:at];start=prefix.rfind('server {')
    need(start>=0 and re.search(r'listen\s+127\.0\.0\.1:8080\s*;',prefix[start:]),'unexpected_studio_listener')
    addition='\n'+route
    if maintenance:
        need('location = /music-studio-api.php' not in text,'existing_api_override')
        addition+='\nlocation = /music-studio-api.php { return 503; }\n'
    return text[:at]+addition+text[at:]

def bundle():
    need(bool(PAYLOAD_B64),'use_built_checksum_pinned_installer')
    b=json.loads(base64.b64decode(PAYLOAD_B64,validate=True))
    for relative,item in b['files'].items():
        need(re.fullmatch(r'(web|player)/[A-Za-z0-9_./-]+',relative) and '..' not in Path(relative).parts,'bad_bundle_path')
        need(sha(base64.b64decode(item['data'],validate=True))==item['after'],'bad_payload_hash')
    return b

def journal_state():
    data=read(ROOT/'playout/events.jsonl');state={}
    for line in data.splitlines():
        if line.strip():state=json.loads(line).get('state',{})
    return state

def data_fingerprint(app):
    media=(app/'media').resolve(strict=True)
    need(media==ROOT/'studio-media','unexpected_media_root')
    index=media/'music/library.json';catalog=json.loads(read(index));files={str(index):sha(read(index))}
    for track in catalog['tracks']:
        need(re.fullmatch(r'[a-f0-9]{32}',track['id']) is not None,'bad_track_id')
        p=media/'music/originals'/(track['id']+'.mp3');digest=sha(read(p,20*1024*1024))
        need(digest==track['sha256'],'original_hash_mismatch');files[str(p)]=digest
    for p in (ROOT/'studio-data/music-play-queue.private.json',ROOT/'playout/control.json'):
        files[str(p)]=sha(read(p))
    return files

def probe(b):
    need(os.geteuid()==0 and socket.gethostname()=='vps-5363103','wrong_environment')
    app=(ROOT/'studio').resolve(strict=True)
    need(app==ROOT/'releases/studio-music-fad12a2320de0ff0a036854c414accee794143bb','release_changed')
    ancestors(app);ancestors(ROOT/'player-code');ancestors(STATE)
    need(stat.S_IMODE(STATE.stat().st_mode)==0o700,'unsafe_gateway_state')
    need(not (STATE/'pending.json').exists(),'pending_deploy')
    need(not (ROOT/'stream-runtime').exists() and not (ROOT/'stream-runtime').is_symlink(),'runtime_already_exists')
    state=journal_state();need(state.get('mode')=='stopped' and not state.get('current'),'player_not_stopped')
    for unit in UNITS:
        need(not Path('/etc/systemd/system/'+unit+'.service').exists() and not Path('/etc/systemd/system/'+unit+'.service').is_symlink(),'unit_already_exists')
        p=command(['systemctl','show',unit+'.service','--property=LoadState'],False)
        need(b'LoadState=not-found' in p.stdout,'unexpected_loaded_unit')
    for name,uid,gid in [('rr-playout',993,984),('rr-stream',992,983),('rr-studio',999,988)]:
        account=pwd.getpwnam(name);need((account.pw_uid,account.pw_gid)==(uid,gid),'service_account_changed')
    for name,version in [('liquidsoap','2.2.4-1'),('icecast2','2.4.4-4build4')]:
        need(command(['dpkg-query','-W','-f=${Version}',name]).stdout.decode().strip()==version,'audio_package_changed')
    command(['/usr/bin/php','-r',"exit(function_exists('pcntl_async_signals') && function_exists('proc_open') ? 0 : 1);"])
    need(stat.S_ISSOCK(Path('/run/php/rr-studio.sock').stat().st_mode),'missing_studio_socket')
    need(sha(read(app/'app/music-library.php'))=='a98f2bd03c07ee9ad8df98cf9f0a64e2cccc8c71a4b9494200ae853b6d32d0a1','library_changed')
    command(['/usr/sbin/nginx','-t']);dump=command(['/usr/sbin/nginx','-T']).stdout.decode()
    parts=re.split(r'^# configuration file ',dump,flags=re.M)
    sections=[x.partition(':\n')[2] for x in parts if x.startswith(str(CONF)+':\n')]
    need(len(sections)==1 and sha(sections[0].encode())==NGINX_SECTION,'nginx_changed')
    conf=read(CONF);ancestors(CONF)
    need(stat.S_IMODE(CONF.stat().st_mode)==0o644,'unexpected_nginx_permissions')
    patch_nginx(conf.decode(),b['route'])
    listeners=command(['/usr/bin/ss','-H','-ltn']).stdout.decode()
    need(not any(len(f:=line.split())>=4 and f[3].endswith(':18980') for line in listeners.splitlines()),'stream_port_in_use')
    for rel,item in b['files'].items():
        p=(app if rel.startswith('web/') else ROOT/'player-code')/rel.split('/',1)[1]
        parent=p.parent
        while not parent.exists():parent=parent.parent
        ancestors(parent)
        need(not p.is_symlink(),'symlink_target:'+rel)
        before=sha(read(p)) if p.exists() else None
        need(before==item['before'],'code_drift:'+rel)
        if p.exists():ancestors(p)
        if p.suffix=='.php':command(['/usr/bin/php','-l'],input=base64.b64decode(item['data']))
    queue=json.loads(read(ROOT/'studio-data/music-play-queue.private.json'))
    need(queue['revision']==24 and len(queue['entries'])==3,'queue_changed')
    cfg=ROOT/'studio-data/music-playout.local.php'
    need(cfg.is_file() and not cfg.is_symlink(),'player_config_missing')
    check=command(['/usr/sbin/runuser','-u','rr-studio','--','/usr/bin/php','-r',"$c=require $argv[1]; if(($c['enabled']??false)!==true || ($c['root']??null)!=='/srv/radiorubben-test/playout')exit(1);",str(cfg)])
    return app,conf,data_fingerprint(app)

def code_parent(path):
    missing=[];p=path
    while not p.exists():missing.append(p);p=p.parent
    ancestors(p)
    for p in reversed(missing):
        p.mkdir(mode=0o755);p.chmod(0o755)
    ancestors(path)

def record(backup,manifest,path,after):
    key=str(path);before=read(path) if path.exists() else None
    s=path.stat() if before is not None else None
    name='file-'+str(len(manifest['files']))
    if before is not None:atomic(backup/name,before,0o600)
    manifest['files'][key]={'backup':name if before is not None else None,'before':sha(before) if before is not None else None,
        'after':sha(after),'accepted':[sha(after)],'uid':s.st_uid if s else 0,'gid':s.st_gid if s else 0,'mode':stat.S_IMODE(s.st_mode) if s else 0o644}
    atomic(backup/'manifest.json',json.dumps(manifest,indent=2).encode(),0o600)

def rollback(backup):
    ancestors(backup)
    need(backup.parent==STATE/'l01-installs','wrong_backup_location')
    m=json.loads(read(backup/'manifest.json'));app=(ROOT/'studio').resolve(strict=True)
    need(str(app)==m['app'],'active_release_changed')
    for unit in reversed(UNITS):command(['systemctl','stop',unit],False);command(['systemctl','disable',unit],False)
    # Verify all targets before restoring any. Never restore journal/media data.
    for key,v in m['files'].items():
        p=Path(key);digest=sha(read(p)) if p.exists() else None
        need(digest in [v['before'],v['after'],*v.get('accepted',[])], 'rollback_concurrent_change:'+key)
        if v['backup']:need(sha(read(backup/v['backup']))==v['before'],'backup_corrupt')
    for key,v in reversed(list(m['files'].items())):
        p=Path(key)
        if v['backup']:atomic(p,read(backup/v['backup']),v['mode'],v['uid'],v['gid'])
        elif p.exists():p.unlink()
    runtime=ROOT/'stream-runtime'
    if runtime.exists():os.replace(runtime,backup/'stopped-runtime-private')
    command(['systemctl','daemon-reload'])
    command(['/usr/sbin/nginx','-t']);command(['systemctl','reload','nginx'])
    command(['systemctl','reload','php8.3-fpm']);command(['systemctl','restart','rr-entra'])
    if m['legacy_enabled']:command(['systemctl','enable','rr-music-playout'])
    if m['legacy_active']:command(['systemctl','start','rr-music-playout'])
    print(json.dumps({'status':'rolled_back','backup':str(backup),'runtime_data_preserved':True}))

def apply(b,app,conf,data_before):
    parent=STATE/'l01-installs';parent.mkdir(mode=0o700,exist_ok=True);ancestors(parent)
    need(stat.S_IMODE(parent.stat().st_mode)==0o700,'unsafe_backup_parent')
    backup=Path(tempfile.mkdtemp(prefix='l01-',dir=parent))
    legacy_enabled=command(['systemctl','is-enabled','rr-music-playout'],False).returncode==0
    legacy_active=command(['systemctl','is-active','rr-music-playout'],False).returncode==0
    m={'source':b['source'],'app':str(app),'legacy_enabled':legacy_enabled,'legacy_active':legacy_active,
        'files':{},'timeUTC':datetime.datetime.now(datetime.timezone.utc).isoformat()}
    journal_before=read(ROOT/'playout/events.jsonl')
    atomic(backup/'journal.before.jsonl',journal_before,0o600)
    m['journal_prefix_sha256']=sha(journal_before)
    final_conf=patch_nginx(conf.decode(),b['route']).encode()
    maintenance=patch_nginx(conf.decode(),b['route'],True).encode()
    # Stage all secrets/root backups privately. No original audio is copied.
    record(backup,m,CONF,maintenance)
    m['files'][str(CONF)]['accepted'].append(sha(final_conf))
    cfg=ROOT/'studio-data/music-playout.local.php'
    config=command(['/usr/sbin/runuser','-u','rr-studio','--','/usr/bin/php','-r',"$c=require $argv[1];$c['stream_path']='/music-test-stream/';echo '<?php return '.var_export($c,true).';'.PHP_EOL;",str(cfg)]).stdout
    record(backup,m,cfg,config)
    for rel,item in b['files'].items():
        p=(app if rel.startswith('web/') else ROOT/'player-code')/rel.split('/',1)[1]
        record(backup,m,p,base64.b64decode(item['data']))
    for unit,content in b['units'].items():record(backup,m,Path('/etc/systemd/system/'+unit+'.service'),content.encode())
    try:
        atomic(CONF,maintenance);command(['/usr/sbin/nginx','-t']);command(['systemctl','reload','nginx'])
        need(journal_state().get('mode')=='stopped' and not journal_state().get('current'),'player_started_during_preparation')
        command(['systemctl','stop','rr-music-playout']);command(['systemctl','disable','rr-music-playout'])
        need(data_fingerprint(app)==data_before,'data_changed_before_install')
        for rel,item in b['files'].items():
            p=(app if rel.startswith('web/') else ROOT/'player-code')/rel.split('/',1)[1]
            need((sha(read(p)) if p.exists() else None)==item['before'],'concurrent_code_change')
            code_parent(p.parent)
            meta=m['files'][str(p)]
            atomic(p,base64.b64decode(item['data']),meta['mode'],meta['uid'],meta['gid'])
        prepare=backup/'prepare.py';atomic(prepare,b['prepare'].encode(),0o600)
        spec=importlib.util.spec_from_file_location('reviewed_prepare',prepare);mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)
        runtime=mod.prepare(ROOT/'stream-runtime')
        play=pwd.getpwnam('rr-playout');stream=pwd.getpwnam('rr-stream')
        runtime.chmod(0o711)
        for name in ('engine.json','engine'):os.chown(runtime/name,play.pw_uid,play.pw_gid)
        for name in ('icecast.xml','logs','empty-web'):os.chown(runtime/name,stream.pw_uid,stream.pw_gid)
        for unit,content in b['units'].items():atomic(Path('/etc/systemd/system/'+unit+'.service'),content.encode())
        command(['systemd-analyze','verify',*[str(Path('/etc/systemd/system/'+u+'.service')) for u in UNITS]])
        atomic(cfg,config,0o600,pwd.getpwnam('rr-studio').pw_uid,pwd.getpwnam('rr-studio').pw_gid)
        command(['systemctl','daemon-reload'])
        command(['systemctl','reload','php8.3-fpm']);command(['systemctl','restart','rr-entra'])
        command(['systemctl','enable',*UNITS]);command(['systemctl','start',*UNITS])
        main_pid=int(command(['systemctl','show','rr-studio-stream','--property=MainPID','--value']).stdout.strip())
        need(main_pid>0,'missing_supervisor_pid')
        for _ in range(30):
            beat=json.loads(read(ROOT/'playout/heartbeat.json'));state=journal_state()
            if state.get('engine')=='liquidsoap' and state.get('mode')=='stopped' and beat.get('pid')==main_pid and time.time()-beat.get('timestamp',0)<5:break
            time.sleep(0.3)
        else:raise RuntimeError('stopped_supervisor_not_confirmed')
        for u in UNITS:command(['systemctl','is-active','--quiet',u])
        need(data_fingerprint(app)==data_before,'data_changed_during_install')
        need(read(ROOT/'playout/events.jsonl').startswith(journal_before),'journal_prefix_changed')
        for rel,item in b['files'].items():
            p=(app if rel.startswith('web/') else ROOT/'player-code')/rel.split('/',1)[1]
            need(sha(read(p))==item['after'],'installed_hash_mismatch')
        # Switch only from the installer-owned maintenance file.
        need(sha(read(CONF))==sha(maintenance),'concurrent_nginx_change')
        atomic(CONF,final_conf)
        m['files'][str(CONF)]['after']=sha(final_conf)
        atomic(backup/'manifest.json',json.dumps(m,indent=2).encode(),0o600)
        command(['/usr/sbin/nginx','-t']);command(['systemctl','reload','nginx'])
        status=command(['/usr/bin/curl','--silent','--max-time','8','--output','/dev/null','--write-out','%{http_code}',
            '-H','Host: 5.249.252.78','http://127.0.0.1:8080/music-test-stream/']).stdout.decode()
        need(status in ('401','403'),'anonymous_stream_not_denied')
        receipt={'status':'installed_stopped_not_audio_pass','source':b['source'],'backup':str(backup),
            'app':str(app),'queue_revision':24,'queue_entries':3,'data_hashes_unchanged':True,
            'anonymous_stream_http':status,'private_route':'/music-test-stream/',
            'authenticated_listening_verified':False,'external_tls_stream_verified':False,'timeUTC':datetime.datetime.now(datetime.timezone.utc).isoformat()}
        atomic(backup/'receipt.json',json.dumps(receipt,indent=2).encode(),0o600)
        print(json.dumps(receipt,indent=2))
    except BaseException:
        try:rollback(backup)
        except Exception:print(json.dumps({'status':'recovery_required','backup':str(backup)}))
        raise

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--apply',action='store_true');parser.add_argument('--rollback')
    args=parser.parse_args();os.umask(0o077)
    need(os.geteuid()==0 and socket.gethostname()=='vps-5363103','wrong_environment')
    if args.rollback:
        with (STATE/'lock').open('a') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB);rollback(Path(args.rollback))
        return
    b=bundle();app,conf,data=probe(b)
    if not args.apply:
        print(json.dumps({'status':'ready_for_explicit_install_approval','source':b['source'],
            'target_files':list(b['files']),'units':list(b['units']),'nginx_file':str(CONF),
            'library_preserved':True,'queue_preserved':True,'automatic_music_start':False},indent=2));return
    with (STATE/'lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        app,conf,data=probe(b);apply(b,app,conf,data)

if __name__=='__main__':
    try:main()
    except Exception as error:
        # Never print subprocess stdout/stderr or private configuration.
        print(json.dumps({'status':'stopped','reason':str(error) if isinstance(error,RuntimeError) else type(error).__name__}))
        raise SystemExit(1)
