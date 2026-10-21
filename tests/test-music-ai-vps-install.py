import hashlib
import importlib.util
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch, MagicMock

spec = importlib.util.spec_from_file_location('installer', Path(__file__).resolve().parents[1] / 'scripts/music-ai-vps-install.py')
m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)

class InstallerTests(unittest.TestCase):
    def exercise(self, fail=None):
        with tempfile.TemporaryDirectory() as d:
            base = Path(d); app = base/'studio'; (app/'app').mkdir(parents=True)
            (app/'app/example.php').write_text('<?php // fixture')
            (app/'vendor').mkdir(); media = base/'media'; media.mkdir()
            source = base/'source'; (source/'scripts').mkdir(parents=True)
            (source/'tests/recovered').mkdir(parents=True)
            hashes = {}
            for name in m.SOURCE:
                data=b'<?php // synthetic'; (source/name).write_bytes(data)
                hashes[name]=hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()
            dest=base/'etc'; units=base/'units';units.mkdir()
            existing=units/'unrelated.service';existing.write_text('preserve')
            calls=[]
            def runner(args, **kw):
                calls.append(args)
                return subprocess.CompletedProcess(args,0,stdout='',stderr='')
            def transient(args, capture=False):
                if '--smoke' in args and fail=='smoke': raise RuntimeError('synthetic smoke failure')
                return subprocess.CompletedProcess(args,0,stdout='{"status":"prepared"}',stderr='')
            def network():
                if fail=='network': raise RuntimeError('synthetic filter failure')
            actual_is_file=Path.is_file
            with patch.multiple(m,APP=app,MEDIA=media,DEST=dest,UNITS=units,SOURCE=hashes,__file__=str(source/'scripts/installer.py')), \
                 patch.object(m.sys,'argv',['installer','--install']), \
                 patch.object(m.os,'geteuid',return_value=0), \
                 patch.object(m.pwd,'getpwnam'), patch.object(m.socket,'socket',return_value=MagicMock()), \
                 patch.object(Path,'is_file',lambda p: True if str(p) in ('/usr/bin/php8.3','/usr/sbin/squid') else actual_is_file(p)), \
                 patch.object(m,'run',side_effect=runner), patch.object(m,'transient',side_effect=transient), \
                 patch.object(m,'network_check',side_effect=network), \
                 patch.object(m.subprocess,'run',return_value=subprocess.CompletedProcess([],0,stdout='403',stderr='')):
                if fail:
                    with self.assertRaises(RuntimeError): m.main()
                    self.assertFalse(dest.exists())
                    self.assertFalse(any((units/n).exists() for n in m.NAMES))
                    self.assertFalse(any(c[:2]==['systemctl','enable'] for c in calls))
                else:
                    m.main()
                    self.assertTrue((dest/'worker.php').exists())
                    self.assertIn(['systemctl','enable','--now',m.NAMES[0],m.NAMES[2]],calls)
                    # Repeat installation must stop before overwriting.
                    with self.assertRaises(RuntimeError): m.main()
                self.assertEqual(existing.read_text(),'preserve')
                self.assertEqual((app/'app/example.php').read_text(),'<?php // fixture')
    def test_success_and_repeat_rejection(self): self.exercise()
    def test_filter_failure_installs_nothing(self): self.exercise('network')
    def test_smoke_failure_removes_only_new_setup(self): self.exercise('smoke')
    def test_isolation_and_allowlist(self):
        self.assertIn('IPAddressDeny=any',m.WORKER)
        self.assertIn('ReadWritePaths=/srv/radiorubben-test/studio-media',m.WORKER)
        self.assertIn('http_access deny all',m.SQUID)
        self.assertIn('^api\\.openai\\.com:443$',m.SQUID)
        self.assertNotIn('client_secret',m.WORKER)
        self.assertIn('OnUnitInactiveSec=60s',m.TIMER)

unittest.main()
