"""Selective v0.2.0 VPS promotion; preserves audio, data and access controls."""
import hashlib,json,os,pathlib,shutil,subprocess,sys,tempfile,time,fcntl
ROOT=pathlib.Path('/srv/radiorubben-test/studio').resolve()
PACKAGE=pathlib.Path('/var/tmp/l02-official-package')
ALLOWED={'app/views/sidebar.php','public/ai-studio.php','public/assets/ai-studio/controlroom.mjs','public/music-studio.php','public/assets/music-studio.mjs','public/music-library.php','nginx.conf','public/release.json'}
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest() if p.exists() else None
def target(n):
    assert n in ALLOWED
    return pathlib.Path('/etc/nginx/rr-test-apps.conf') if n=='nginx.conf' else ROOT/n
def atomic(p,data,meta):
    fd,n=tempfile.mkstemp(prefix='.rr-release-',dir=p.parent)
    try:
        with os.fdopen(fd,'wb') as f:
            f.write(data);f.flush();os.fsync(f.fileno());os.fchmod(f.fileno(),meta['mode']);os.fchown(f.fileno(),meta['uid'],meta['gid'])
        os.replace(n,p)
    finally:
        if os.path.exists(n):os.unlink(n)
def services():return subprocess.check_output(['systemctl','show','rr-studio-stream','rr-studio-icecast','-p','MainPID','-p','NRestarts','-p','ActiveState'],text=True)
def restore(backup,rows):
    for e in rows:
        assert sha(target(e['path']))==e['after'], 'Concurrent change'
        if e['before'] is not None:assert sha(backup/'before'/e['path'])==e['before']
    for e in reversed(rows):
        p=target(e['path'])
        if e['before'] is None:p.unlink()
        else:atomic(p,(backup/'before'/e['path']).read_bytes(),e['meta'])
    subprocess.run(['nginx','-t'],check=True);subprocess.run(['systemctl','reload','nginx'],check=True)
if __name__=='__main__':
    assert os.geteuid()==0
    assert str(ROOT)=='/srv/radiorubben-test/releases/studio-music-fad12a2320de0ff0a036854c414accee794143bb'
    with open('/var/lib/rr-vps-deploy/l02-official.lock','a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if sys.argv[1]=='rollback':
            b=pathlib.Path(sys.argv[2]).resolve();assert b.parent==pathlib.Path('/var/lib/rr-vps-deploy')
            restore(b,json.loads((b/'receipt.json').read_text())['files']);print('Rollback verified');sys.exit()
        rows=json.loads((PACKAGE/'manifest.json').read_text());assert {e['path'] for e in rows}==ALLOWED and len(rows)==len(ALLOWED)
        for e in rows:
            p=target(e['path']);assert not p.is_symlink();assert sha(p)==e['before'],e['path'];assert sha(PACKAGE/'files'/e['path'])==e['after']
            st=p.stat() if p.exists() else p.parent.stat();e['meta']={'mode':st.st_mode&0o777 if p.exists() else 0o644,'uid':st.st_uid,'gid':st.st_gid}
            if p.suffix in ('.php','.mjs'):
                subprocess.run(['php','-l',str(PACKAGE/'files'/e['path'])] if p.suffix=='.php' else ['node','--check',str(PACKAGE/'files'/e['path'])],check=True)
        if sys.argv[1]=='preflight':print('Preflight passed: 8 files');sys.exit()
        assert sys.argv[1]=='apply'
        previous=services();assert previous.count('ActiveState=active')==2
        b=pathlib.Path(tempfile.mkdtemp(prefix='l02-official-020-',dir='/var/lib/rr-vps-deploy'));b.chmod(0o700)
        for e in rows:
            if e['before'] is not None:
                saved=b/'before'/e['path'];saved.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(target(e['path']),saved);assert sha(saved)==e['before']
        shutil.copy2(__file__,b/'installer.py')
        receipt={'version':'0.2.0','utc':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'backup':str(b),'files':rows,'servicesBefore':previous,'status':'prepared'}
        (b/'receipt.json').write_text(json.dumps(receipt,indent=2));changed=[]
        try:
            for e in rows:
                assert sha(target(e['path']))==e['before'];atomic(target(e['path']),(PACKAGE/'files'/e['path']).read_bytes(),e['meta']);changed.append(e)
            subprocess.run(['nginx','-t'],check=True);subprocess.run(['systemctl','reload','nginx'],check=True)
            assert all(sha(target(e['path']))==e['after'] for e in rows)
        except BaseException:
            restore(b,changed);raise
        receipt.update(status='installed',servicesAfter=services());assert receipt['servicesAfter']==previous
        (b/'receipt.json').write_text(json.dumps(receipt,indent=2));print(json.dumps(receipt))
