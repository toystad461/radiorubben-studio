#!/usr/bin/env python3
import hashlib
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

BASE = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('resume', BASE / 'scripts/music-ai-resume-vps.py')
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

class RecoveryTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.app, self.media, self.dest = [self.root / n for n in ('app', 'media', 'dest')]
        for p in (self.app, self.media / 'music', self.dest):
            p.mkdir(parents=True)
        self.worker = self.dest / 'worker.php'
        self.old = b'old worker\n'
        self.worker.write_bytes(self.old)
        self.worker.chmod(0o644)
        (self.media / 'music/library.json').write_bytes(b'{"fixture":true}')
        (self.media / 'music/library.lock').touch()
        self.source = self.root / 'source'
        self.source.mkdir()
        for name, source in [('music-ai-worker.php', BASE / 'scripts/music-ai-worker.php'),
                             ('test-music-ai-worker.php', BASE / 'tests/recovered/test-music-ai-worker.php')]:
            (self.source / name).write_bytes(source.read_bytes())
        for name, v in {'APP': self.app, 'MEDIA': self.media, 'DEST': self.dest, 'WORKER': self.worker,
                        'OLD': hashlib.sha256(self.old).hexdigest()}.items():
            p = patch.object(m, name, v)
            p.start()
            self.addCleanup(p.stop)
        real_temp = tempfile.TemporaryDirectory
        p = patch.object(m.tempfile, 'TemporaryDirectory', side_effect=lambda **kw: real_temp(prefix=kw.get('prefix', 'test-')))
        p.start()
        self.addCleanup(p.stop)
        self.state = {'UnitFileState': 'enabled', 'ActiveState': 'active'}
        self.commands = []
        self.probes = []

    def value(self, unit, prop):
        if unit == m.TIMER:
            return self.state[prop]
        if prop == 'ExecStart':
            return str(self.worker) + ' --once --app-root=' + str(self.app)
        if prop == 'DropInPaths':
            return ''
        return 'active' if unit == 'rr-music-ai-proxy.service' else 'inactive'

    def run_command(self, args, **kwargs):
        self.commands.append(args)
        if args[1] == 'disable':
            self.state['UnitFileState'] = 'disabled'
            if '--now' in args:
                self.state['ActiveState'] = 'inactive'
        elif args[1] == 'enable':
            self.state['UnitFileState'] = 'enabled'
        elif args[1] in ('start', 'stop'):
            self.state['ActiveState'] = 'active' if args[1] == 'start' else 'inactive'
        return subprocess.CompletedProcess(args, 0, '', '')

    def transient(self, args, **kwargs):
        self.probes.append((args, kwargs))
        if '--retry' in args:
            self.assertEqual(self.state, {'UnitFileState': 'disabled', 'ActiveState': 'inactive'})
            self.assertEqual(m.digest(self.worker.read_bytes()), m.FILES['music-ai-worker.php'])
            self.assertTrue(kwargs['writable'])
            return subprocess.CompletedProcess(args, 0, json.dumps({'status': 'prepared', 'trackId': 'a' * 29}), '')
        return subprocess.CompletedProcess(args, 0, '', '')

    def perform(self, probe=None):
        with patch.object(m, 'value', side_effect=self.value), patch.object(m, 'run', side_effect=self.run_command), \
             patch.object(m, 'transient', side_effect=probe or self.transient):
            return m.apply(self.source, 'a' * 29)

    def test_success_pauses_only_music_ai_and_keeps_private_backup(self):
        result = self.perform()
        self.assertEqual(result['result']['status'], 'prepared')
        self.assertEqual(self.state, {'UnitFileState': 'disabled', 'ActiveState': 'inactive'})
        backup = Path(result['backup'])
        self.assertEqual(backup.stat().st_mode & 0o777, 0o700)
        self.assertEqual((backup / 'worker.php').read_bytes(), self.old)
        self.assertEqual((backup / 'library-before.json').stat().st_mode & 0o777, 0o600)
        self.assertEqual(sum('--retry' in args for args, _ in self.probes), 1)
        self.assertTrue(all(cmd[-1] == m.TIMER for cmd in self.commands))

    def test_failure_rolls_back_code_timer_but_preserves_library_changes(self):
        def fail(args, **kwargs):
            if '--retry' in args:
                (self.media / 'music/library.json').write_bytes(b'new attempt history')
                raise RuntimeError('synthetic failure')
            return self.transient(args, **kwargs)
        with self.assertRaises(RuntimeError):
            self.perform(fail)
        self.assertEqual(self.worker.read_bytes(), self.old)
        self.assertEqual(self.state, {'UnitFileState': 'enabled', 'ActiveState': 'active'})
        self.assertEqual((self.media / 'music/library.json').read_bytes(), b'new attempt history')

    def test_unknown_installed_worker_stops_before_actions(self):
        self.worker.write_bytes(b'other deployment')
        with self.assertRaises(RuntimeError):
            self.perform()
        self.assertEqual(self.commands, [])
        self.assertEqual(self.probes, [])
        self.assertEqual(self.worker.read_bytes(), b'other deployment')

    def test_corrupt_source_stops_before_actions(self):
        (self.source / 'music-ai-worker.php').write_bytes(b'wrong')
        with self.assertRaises(RuntimeError):
            self.perform()
        self.assertEqual(self.commands, [])
        self.assertEqual(self.probes, [])

    def test_transient_retains_network_isolation_and_scoped_write_access(self):
        with patch.object(m, 'run') as run:
            m.transient(['php', 'probe'], writable=True)
        args = run.call_args.args[0]
        for expected in ['--property=IPAddressDeny=any', '--property=IPAddressAllow=127.0.0.1/32',
                         '--property=ProtectSystem=strict', '--property=ReadWritePaths=' + str(self.media),
                         '--setenv=https_proxy=http://127.0.0.1:3140']:
            self.assertIn(expected, args)

if __name__ == '__main__':
    unittest.main()
