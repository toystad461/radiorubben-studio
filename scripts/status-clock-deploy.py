#!/usr/bin/env python3
"""Selective status-clock repair. Default: read-only preflight. Never restarts services."""
import argparse
import datetime
import fcntl
import hashlib
import json
import os
import pathlib
import pwd
import shutil
import socket
import subprocess
import tempfile
import urllib.request

STUDIO_COMMIT = 'bb794f873035154aefbaa884dfc4d795a2f0b906'
WEB_COMMIT = '174c3fdcd4d541a8179cf5da75438074c0a685b8'
STUDIO_ROOT = pathlib.Path('/srv/radiorubben-test/releases/studio-music-fad12a2320de0ff0a036854c414accee794143bb')
WEB_ROOT = pathlib.Path('/run/webroots/r1417157')
# Ordered: server support before client; version change last. Never replace PR117's template.
MANIFEST = {
 'studio': [
  ('public/controlroom-api.php','39b86b537370ec69a538c7aa34993c1f2772fc9b7f6f797a91739cbd8dcffd05','2ca22028d3189536f6bafdacd103d3cafccfe1e5d9e4f467cde5c5124805a3db','fcccc661171ca36a007ffa45a0b8105a939f455f618683e003779c9d339530c3'),
  ('public/radio-status.php','46eb50c835b9264a56e97bd22dab21bc27febd9ad9661878e1285cc16ea3f825','668a0e5d82e858f392ad5710f31c93dedc79af5e3d17cdb8f689baca6aa60f16','3f61d2591ee696c3728a20ad154dcc88b3321ed8134e8be7aa04242cf7225f9d'),
  ('public/assets/ai-studio/controlroom.mjs','6bdd4b37b4a38eee5ee286cefa26b17f2e2dc52c521cef45c05faa7b42f93cdb','c3427b888de6f5c3b558fbcad446d850694b277e5c3f1f48a9b71b30e55008c1','e232bdf8c8c713a9a20b50dc17c366ce9e8eda2c6e895ccf17cd9258334ad027'),
 ],
 'wordpress': [
  ('wp-content/mu-plugins/rr-public-radio.js','dd703ec52ada6cba9493ade920833a59f678d61394ea28586d24bc73a5e7cddd','047fa9c34206be1b1da1c5146e04524b6f2bfde8c7ee4ea3706df092309ebb02','f1e336c975afad7ab79b7050b8e6f308bd8838ddadbc29f70dabe12fb8b3cd72'),
  ('wp-content/mu-plugins/rr-public-radio.php','91fc347819b8f1a27f6aba217fa2086ef08322476fab3d53fd1bc7e7b1bf4779','526f932ed762c254e22baa0870b5cd1e1db4db78a67ef2df66417fc61dc12d05','a8d19f009672c2ce91ffd663e666b0e532ae329091e9fc92d2444c0c39163508'),
 ]
}
TEMPLATE = 'public/ai-studio.php'
TEMPLATE_BEFORE = 'dc853d3248931c377e696b0678ec0556dc34b065b2470ca251231275837966c9'
OLD_TAG = b'/assets/ai-studio/controlroom.mjs?v=20261102'
NEW_TAG = b'/assets/ai-studio/controlroom.mjs?v=20261105-clock1'

def digest(data):
    return hashlib.sha256(data).hexdigest()

def checked_read(path):
    if path.is_symlink() or not path.is_file():
        raise RuntimeError('Missing/unsafe target: '+str(path))
    return path.read_bytes()

def atomic(path, data, meta):
    fd, name = tempfile.mkstemp(prefix='.rr-status-', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as out:
            out.write(data); out.flush(); os.fsync(out.fileno())
            os.fchmod(out.fileno(), meta['mode'])
            if os.geteuid() == 0:
                os.fchown(out.fileno(), meta['uid'], meta['gid'])
            elif (os.fstat(out.fileno()).st_uid, os.fstat(out.fileno()).st_gid) != (meta['uid'], meta['gid']):
                raise RuntimeError('Cannot preserve ownership')
        os.replace(name, path)
    finally:
        if os.path.exists(name): os.unlink(name)

def fetch(target, name):
    repo, commit, source = ('radiorubben-studio', STUDIO_COMMIT, name) if target == 'studio' else ('radiorubben-web', WEB_COMMIT, 'wordpress/'+name)
    url = 'https://raw.githubusercontent.com/toystad461/'+repo+'/'+commit+'/'+source
    with urllib.request.urlopen(url, timeout=25) as response:
        if not response.url.startswith('https://raw.githubusercontent.com/'):
            raise RuntimeError('Unexpected download destination')
        data = response.read(200001)
        if len(data) > 200000: raise RuntimeError('Unexpected file size')
        return data

def plan(target, root):
    result = {}
    for name, lf, crlf, after in MANIFEST[target]:
        path=root/name; before=checked_read(path); observed=digest(before)
        if observed not in (lf,crlf):
            raise RuntimeError('Refusing changed file '+name+' SHA256='+observed)
        data=fetch(target,name)
        if digest(data)!=after: raise RuntimeError('Source hash mismatch: '+name)
        st=path.stat()
        result[name]={'before':before,'data':data,'meta':{'uid':st.st_uid,'gid':st.st_gid,'mode':st.st_mode & 0o777}}
    if target=='studio':
        path=root/TEMPLATE; before=checked_read(path)
        if digest(before)!=TEMPLATE_BEFORE or before.count(OLD_TAG)!=1:
            raise RuntimeError('PR117 template changed; refusing overwrite. SHA256='+digest(before))
        st=path.stat()
        result[TEMPLATE]={'before':before,'data':before.replace(OLD_TAG,NEW_TAG),'meta':{'uid':st.st_uid,'gid':st.st_gid,'mode':st.st_mode & 0o777}}
    return result

def validate(planned):
    with tempfile.TemporaryDirectory(prefix='rr-status-check-') as work:
        for index,(name,item) in enumerate(planned.items()):
            suffix=pathlib.Path(name).suffix; path=pathlib.Path(work)/(str(index)+suffix);path.write_bytes(item['data'])
            if suffix!='.php' and shutil.which('node') is None:
                continue  # Pinned JS was checked in CI; web hosting need not install Node.
            command=['php','-l',str(path)] if suffix=='.php' else ['node','--check',str(path)]
            subprocess.run(command,check=True,stdout=subprocess.DEVNULL,timeout=20)

def services():
    return {name:subprocess.check_output(['systemctl','show',name,'-p','ActiveState','-p','MainPID'],text=True).strip() for name in ('rr-studio-stream','rr-studio-icecast')}

def environment(target):
    if target=='studio':
        if socket.gethostname()!='vps-5363103' or os.geteuid()!=0: raise RuntimeError('Run with sudo on the existing test-VPS')
        config=subprocess.run(['nginx','-T'],capture_output=True,text=True,check=True).stdout
        import re
        roots=re.findall(r'^\s*root\s+([^;]+);',config,re.M)
        if not any(pathlib.Path(p.strip()).resolve()==STUDIO_ROOT/'public' for p in roots): raise RuntimeError('Active Nginx release must be checked')
        # PHP must notice the changed routes without an FPM restart.
        info=subprocess.run(['php-fpm8.3','-i'],capture_output=True,text=True,check=True).stdout
        def setting(key):
            match=re.search(r'^'+re.escape(key)+r'\s*=>\s*([^\s]+)',info,re.M)
            return match.group(1) if match else None
        if setting('opcache.enable')=='On' and (setting('opcache.validate_timestamps')!='On' or int(setting('opcache.revalidate_freq') or '999')>2):
            raise RuntimeError('FPM cache requires separate inspection')
        return STUDIO_ROOT,pathlib.Path('/var/lib/rr-vps-deploy')
    if pwd.getpwuid(os.geteuid()).pw_name!='cptk37ymg_w1417156': raise RuntimeError('Run as the existing Uniweb WordPress account')
    if not (WEB_ROOT/'wp-load.php').is_file(): raise RuntimeError('WordPress target missing')
    return WEB_ROOT,pathlib.Path.home()/'.radiorubben-deploy/backups'

def install(root, planned, backup, receipt):
    changed=[]
    try:
        for name,item in planned.items():
            if checked_read(root/name)!=item['before']: raise RuntimeError('Concurrent edit: '+name)
            atomic(root/name,item['data'],item['meta']);changed.append(name)
            if checked_read(root/name)!=item['data']: raise RuntimeError('Verification failed: '+name)
    except BaseException:
        for name in reversed(changed):
            item=planned[name]
            if checked_read(root/name)==item['data']: atomic(root/name,item['before'],item['meta'])
        raise
    receipt['status']='installed_files_verified'
    (backup/'receipt.json').write_text(json.dumps(receipt,indent=2)+'\n')

def rollback(root, receipt_path, target):
    receipt_path=receipt_path.resolve(); receipt=json.loads(receipt_path.read_text())
    names=[row[0] for row in MANIFEST[target]]+([TEMPLATE] if target=='studio' else [])
    if receipt['target']!=target or receipt['root']!=str(root) or set(receipt['files'])!=set(names): raise RuntimeError('Receipt target mismatch')
    restored={}
    for name,item in receipt['files'].items():
        if not isinstance(item['backup'],str) or pathlib.Path(item['backup']).name!=item['backup']: raise RuntimeError('Invalid backup path')
        current=checked_read(root/name);old=checked_read(receipt_path.parent/item['backup'])
        if digest(current)!=item['after'] or digest(old)!=item['before']: raise RuntimeError('Rollback refused: file changed '+name)
        restored[name]=(old,item['meta'])
    for name in reversed(names):
        data,meta=restored[name];atomic(root/name,data,meta)
        if checked_read(root/name)!=data: raise RuntimeError('Rollback verification failed: '+name)
    print('ROLLBACK VERIFIED: selected code files only')

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--target',choices=['studio','wordpress'],required=True)
    group=parser.add_mutually_exclusive_group();group.add_argument('--apply',action='store_true');group.add_argument('--rollback',type=pathlib.Path)
    args=parser.parse_args();root,parent=environment(args.target)
    if args.rollback:
        if args.rollback.resolve().parent.parent!=parent.resolve(): raise RuntimeError('Receipt outside private backup directory')
        with (parent/'status-clock.lock').open('a') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
            rollback(root,args.rollback,args.target)
        return
    planned=plan(args.target,root);validate(planned)
    before_services=services() if args.target=='studio' else None
    if before_services and any('ActiveState=active' not in value for value in before_services.values()): raise RuntimeError('Inspect inactive stream service first')
    report={name:{'before':digest(v['before']),'after':digest(v['data'])} for name,v in planned.items()}
    print(json.dumps({'target':args.target,'files':report,'mode':'apply' if args.apply else 'preflight'},indent=2))
    if not args.apply: return
    parent.mkdir(mode=0o700,parents=True,exist_ok=True)
    with (parent/'status-clock.lock').open('a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        backup=pathlib.Path(tempfile.mkdtemp(prefix='status-clock-20261105-',dir=parent));os.chmod(backup,0o700)
        receipt={'target':args.target,'root':str(root),'studioCommit':STUDIO_COMMIT,'webCommit':WEB_COMMIT,'startedAtUTC':datetime.datetime.now(datetime.timezone.utc).isoformat(),'status':'prepared','files':{},'servicesBefore':before_services,'browserAcceptance':'pending'}
        for index,(name,item) in enumerate(planned.items()):
            saved=str(index)+'.before';(backup/saved).write_bytes(item['before'])
            if (backup/saved).read_bytes()!=item['before']: raise RuntimeError('Backup mismatch')
            receipt['files'][name]={**report[name],'backup':saved,'meta':item['meta']}
        shutil.copyfile(__file__,backup/'installer.py')
        (backup/'receipt.json').write_text(json.dumps(receipt,indent=2)+'\n')
        print('BACKUP:',backup,flush=True)
        install(root,planned,backup,receipt)
        receipt['servicesAfter']=services() if args.target=='studio' else None
        receipt['servicesUnchanged']=receipt['servicesAfter']==before_services
        (backup/'receipt.json').write_text(json.dumps(receipt,indent=2)+'\n')
        print(json.dumps(receipt,indent=2))
        prefix='sudo -n ' if args.target=='studio' else ''
        print('ROLLBACK:',prefix+'python3 '+str(backup/'installer.py')+' --target '+args.target+' --rollback '+str(backup/'receipt.json'))
        if not receipt['servicesUnchanged']: raise RuntimeError('Service state changed concurrently; inspect before accepting')
        if args.target=='wordpress': print('Next: purge the existing WordPress page/minify cache, then check both players.')

if __name__=='__main__':
    main()
