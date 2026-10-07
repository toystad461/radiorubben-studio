from pathlib import Path
import shutil, subprocess, json, secrets, time, os, hashlib, socket
R=Path(os.environ['RR_SCREEN_TEST_DIR']).resolve()
SOURCE=Path(__file__).resolve().parents[2]
with socket.socket() as sock:
 sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
(R/'port.txt').write_text(str(port))
run=R/'runtime'
run.mkdir(mode=0o700)
for name in ['app','public']:
 shutil.copytree(SOURCE/name,run/name,symlinks=False)
(run/'config').mkdir(mode=0o700)
shutil.copyfile(SOURCE/'config/example.php',run/'config/example.php')
(run/'sessions').mkdir(mode=0o700)
config=run/'config/local.php'
config.write_text(f"""<?php return [
'site_mode'=>'app', 'auth_mode'=>'entra', 'base_url'=>'http://127.0.0.1:{port}',
'tenant_id'=>'00000000-0000-4000-8000-000000000001',
'client_id'=>'00000000-0000-4000-8000-000000000002',
'client_secret'=>'synthetic-not-a-real-secret', 'private_app_enabled'=>true,
'openai_api_key'=>'', 'openai_model'=>''
];
""")
os.chmod(config,0o600)
now=time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime())
items=[]
for i,title in enumerate(['TESTUTKAST: Velkommen til Radio Rubben','TESTUTKAST: Slik virker arbeidslisten på mobilen','TESTUTKAST: Lang tittel for å kontrollere linjebryting og lesbarhet på en smal skjerm','TESTUTKAST: Musikktips og lokale ideer']):
 items.append(dict(id=f'{i+1:016x}',originId=None,title=title,sourceName='Syntetisk test – ikke en nyhet',sourceUrl='',sourceAt=None,capturedAt=None,summary='Dette er et tydelig merket testutkast, ikke en faktisk nyhet.',program='god-morgen-vestland',channel='both',script='Dette er et testmanus for skjermkontroll. Det skal ikke brukes i sending.\n\nHer kontrollerer vi lesbarhet, tekstfelt og knapper. Ingen faktiske nyheter blir behandlet.',notes='Kun lokal test',status='draft',verified=False,createdAt=now,updatedAt=now,createdBy='Testredaktør',approvedBy=None,revision=1,web={'title':title,'intro':'Dette er en syntetisk ingress for skjermtesten.','body':'Dette er et testutkast, ikke en faktisk nyhet.\n\nTesten kontrollerer hvordan Radio Rubben Studio virker på mobil.','approvedHash':None}))
board=run/'config/sending-board.json'
board.write_text(json.dumps({'items':items,'updatedAt':now},ensure_ascii=False))
php='require $argv[1]; session_name("rubben_studio"); session_id($argv[2]); session_start(); $_SESSION=["user"=>["id"=>"synthetic-test-sub","provider"=>"entra","oid"=>($argv[3]==="owner"?STUDIO_OWNER_OID:"00000000-0000-4000-8000-000000000003"),"name"=>"Testredaktør","role"=>"admin"],"expires"=>time()+(int)$argv[4],"csrf"=>str_repeat("c",64)]; session_write_close();'
sids={}
for name,kind,expiry in [('owner','owner',3600),('other','other',3600),('expired','owner',-1)]:
 sid=secrets.token_hex(16);sids[name]=sid
 subprocess.run(['php','-d',f'session.save_path={run}/sessions','-r',php,str(run/'app/auth/StudioLocalUsers.php'),sid,kind,str(expiry)],check=True)
(R/'session-fixture.json').write_text(json.dumps(sids))
os.chmod(R/'session-fixture.json',0o600)
env=os.environ.copy()
for key in list(env):
 if key.startswith(('ENTRA_','STUDIO_','VIPPS_','OPENAI_')):del env[key]
log=open(R/'php-server.log','w')
p=subprocess.Popen(['php','-d',f'session.save_path={run}/sessions','-d','allow_url_fopen=0','-d','opcache.enable=0','-d','disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client','-S',f'127.0.0.1:{port}','-t',str(run/'public')],env=env,stdout=log,stderr=log)
(R/'server.pid').write_text(str(p.pid))
# Record original runtime hashes, independent of synthetic config and sessions.
hashes={str(p.relative_to(run)):hashlib.sha256(p.read_bytes()).hexdigest() for sub in ['app','public'] for p in (run/sub).rglob('*') if p.is_file()}
(R/'original-runtime-hashes.json').write_text(json.dumps(hashes,indent=2))
print('Isolated fixture ready; public root and sessions are separate; no production credentials.')

# Wait for the isolated HTTP listener, not for a real identity provider.
for attempt in range(100):
 try:
  with socket.create_connection(('127.0.0.1',port),timeout=.2): break
 except OSError: time.sleep(.02)
else: raise RuntimeError('PHP fixture did not start')
