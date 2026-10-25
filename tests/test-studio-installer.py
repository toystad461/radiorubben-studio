import base64
import contextlib
import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('installer',Path(__file__).parents[1]/'infra/studio-stream/install-test.py')
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

class InstallerTests(unittest.TestCase):
    def test_nginx_patch_only_test_studio_server(self):
        conf='server {\n listen 127.0.0.1:8080;\n root /srv/radiorubben-test/studio/public;\n}\nserver {\n root /srv/radiorubben-test;\n}'
        result=m.patch_nginx(conf,'# private test route\n',True)
        self.assertEqual(result.split('server {')[2],conf.split('server {')[2])
        self.assertIn('location = /music-studio-api.php { return 503; }',result)
        self.assertLess(result.index('# private test route'),result.index('root /srv/radiorubben-test;'))
    def test_ambiguous_or_existing_route_stops(self):
        conf='server { listen 127.0.0.1:8080;\n root /srv/radiorubben-test/studio/public;\n}'
        for bad in (conf+'\n'+conf,conf+'\n# /music-test-stream/',conf.replace('8080','8082')):
            with self.assertRaises(RuntimeError):m.patch_nginx(bad,'route')
    def test_bundle_rejects_hash_drift_and_traversal(self):
        for name,digest in [('web/../../etc/passwd',m.sha(b'a')),('web/app/a.php','bad')]:
            b={'files':{name:{'data':base64.b64encode(b'a').decode(),'after':digest}}}
            with patch.object(m,'PAYLOAD_B64',base64.b64encode(json.dumps(b).encode()).decode()):
                with self.assertRaises(RuntimeError):m.bundle()
    def test_atomic_and_private_backup(self):
        with tempfile.TemporaryDirectory() as t:
            root=Path(t);p=root/'target';p.write_bytes(b'old')
            manifest={'files':{}};backup=root/'backup';backup.mkdir(mode=0o700)
            m.record(backup,manifest,p,b'new');m.atomic(p,b'new',0o640)
            self.assertEqual(p.read_bytes(),b'new');self.assertEqual(p.stat().st_mode&0o777,0o640)
            self.assertEqual((backup/'file-0').read_bytes(),b'old')
            self.assertEqual((backup/'file-0').stat().st_mode&0o777,0o600)
    def test_new_code_directories_are_traversable_with_private_umask(self):
        import os
        with tempfile.TemporaryDirectory() as t:
            p=Path(t)/'infra/studio-stream'
            mask=os.umask(0o077)
            try:
                with patch.object(m,'ancestors'):m.code_parent(p)
            finally:os.umask(mask)
            self.assertEqual(p.stat().st_mode&0o777,0o755)
            self.assertEqual(p.parent.stat().st_mode&0o777,0o755)
    def rollback_fixture(self,t,changed=False):
        root=Path(t)/'root';app=root/'releases/studio-test';app.mkdir(parents=True);(root/'studio').symlink_to(app)
        state=Path(t)/'state';backup=state/'l01-installs/test';backup.mkdir(parents=True)
        target=app/'code';target.write_bytes(b'newer' if changed else b'new')
        (backup/'old').write_bytes(b'old');(root/'stream-runtime').mkdir();(root/'stream-runtime/private').write_text('secret')
        queue=root/'queue';queue.write_text('original queue and history')
        manifest={'app':str(app),'legacy_enabled':True,'legacy_active':True,'files':{str(target):{
            'backup':'old','before':m.sha(b'old'),'after':m.sha(b'new'),'mode':0o644,'uid':0,'gid':0}}}
        (backup/'manifest.json').write_text(json.dumps(manifest))
        return root,state,backup,target,queue
    def test_rollback_restores_code_and_preserves_runtime_data(self):
        with tempfile.TemporaryDirectory() as t:
            root,state,backup,target,queue=self.rollback_fixture(t)
            with patch.object(m,'ROOT',root),patch.object(m,'STATE',state),patch.object(m,'ancestors'),patch.object(m,'command'),contextlib.redirect_stdout(io.StringIO()):m.rollback(backup)
            self.assertEqual(target.read_bytes(),b'old')
            self.assertEqual(queue.read_text(),'original queue and history')
            self.assertEqual((backup/'stopped-runtime-private/private').read_text(),'secret')
    def test_rollback_refuses_newer_code(self):
        with tempfile.TemporaryDirectory() as t:
            root,state,backup,target,queue=self.rollback_fixture(t,True)
            with patch.object(m,'ROOT',root),patch.object(m,'STATE',state),patch.object(m,'ancestors'),patch.object(m,'command'):
                with self.assertRaisesRegex(RuntimeError,'concurrent_change'):m.rollback(backup)
            self.assertEqual(target.read_bytes(),b'newer')
            self.assertEqual(queue.read_text(),'original queue and history')
    def test_command_error_never_prints_private_output(self):
        import subprocess
        with patch.object(m.subprocess,'run',return_value=subprocess.CompletedProcess([],1,b'secret',b'password')):
            with self.assertRaisesRegex(RuntimeError,'command_failed:php'):m.command(['/usr/bin/php'])

if __name__=='__main__':unittest.main()
