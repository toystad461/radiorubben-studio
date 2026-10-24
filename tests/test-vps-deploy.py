import base64
import importlib.util
import json
from types import SimpleNamespace
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('gateway', Path(__file__).resolve().parents[1] / 'scripts/vps-deploy-gateway.py')
g = importlib.util.module_from_spec(spec)
spec.loader.exec_module(g)

class DeployTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.base = Path(self.tmp.name)
        self.app = self.base / 'app-root'
        (self.app / 'app').mkdir(parents=True)
        self.path = self.app / 'app/example.php'
        self.path.write_bytes(b'old code')
        self.state = self.base / 'state'
        self.state.mkdir()
        self.request = {'commit': 'a' * 40, 'files': [{'path': 'app/example.php',
            'before': g.digest(b'old code'), 'after': g.digest(b'new code'),
            'content': base64.b64encode(b'new code').decode()}]}
    def tearDown(self):
        self.tmp.cleanup()
    def test_success_backup_receipt(self):
        result = g.deploy(self.app, self.state, self.request, lambda: None, lambda: None)
        self.assertEqual(self.path.read_bytes(), b'new code')
        self.assertEqual((Path(result['backup']) / 'app/example.php').read_bytes(), b'old code')
        self.assertEqual(result['status'], 'deployed')
        self.assertNotIn('content', result['files'][0])
        self.assertFalse((self.state / 'pending.json').exists())
    def test_drift_stops_before_backup(self):
        self.path.write_bytes(b'operator change')
        with self.assertRaises(ValueError):
            g.deploy(self.app, self.state, self.request, lambda: None, lambda: None)
        self.assertEqual(self.path.read_bytes(), b'operator change')
        self.assertEqual(list(self.state.iterdir()), [])
    def test_health_failure_rolls_back(self):
        calls = []
        def health():
            calls.append(1)
            if len(calls) == 2:
                raise RuntimeError('synthetic health failure')
        with self.assertRaises(RuntimeError):
            g.deploy(self.app, self.state, self.request, lambda: None, health)
        self.assertEqual(self.path.read_bytes(), b'old code')
        self.assertEqual(len(calls), 3)
        self.assertFalse((self.state / 'pending.json').exists())
    def test_concurrent_change_is_preserved(self):
        def reload():
            self.path.write_bytes(b'operator change')
            raise RuntimeError('concurrent')
        with self.assertRaisesRegex(RuntimeError, 'Rollback blocked'):
            g.deploy(self.app, self.state, self.request, reload, lambda: None)
        self.assertEqual(self.path.read_bytes(), b'operator change')
        self.assertTrue((self.state / 'pending.json').exists())
    def test_unfinished_deploy_blocks(self):
        (self.state / 'pending.json').write_text('{}')
        with self.assertRaisesRegex(ValueError, 'recovery'):
            g.deploy(self.app, self.state, self.request, lambda: None, lambda: None)
        self.assertEqual(self.path.read_bytes(), b'old code')
    def test_reject_paths_symlink_hash_duplicates(self):
        for name in ['config/local.php', 'app/../config/local.php', '/etc/passwd', 'public/.htaccess', 'app/x/../../a.php']:
            with self.subTest(name=name), self.assertRaises(ValueError):
                g.rooted(self.app, name)
        self.path.unlink()
        self.path.symlink_to(self.base / 'private.php')
        with self.assertRaises(ValueError):
            g.validate(self.app, self.request)
        self.path.unlink()
        self.path.write_bytes(b'old code')
        self.request['files'][0]['after'] = '0' * 64
        with self.assertRaises(ValueError):
            g.validate(self.app, self.request)
        self.request['files'][0]['after'] = g.digest(b'new code')
        self.request['files'] *= 2
        with self.assertRaises(ValueError):
            g.validate(self.app, self.request)
    def test_status_uses_only_fixed_units_and_no_request_commands(self):
        calls = []
        def fake_run(args, **kwargs):
            calls.append((args, kwargs))
            return SimpleNamespace(returncode=0,
                stdout='LoadState=loaded\nActiveState=active\nSubState=running\n')
        result = g.service_status(fake_run)
        self.assertEqual(result['status'], 'service-status')
        self.assertEqual(set(result['services']), set(g.STATUS_UNITS))
        self.assertTrue(all(x['active'] == 'active' for x in result['services'].values()))
        self.assertEqual(len(calls), len(g.STATUS_UNITS))
        self.assertTrue(all(args[:2] == ['/usr/bin/systemctl', 'show']
                            and kwargs['timeout'] == 5 for args, kwargs in calls))

    def test_status_handles_missing_service_and_untrusted_output(self):
        def fake_run(args, **kwargs):
            if args[2] == 'rr-stream.service':
                return SimpleNamespace(returncode=1, stdout='')
            return SimpleNamespace(returncode=0,
                stdout='LoadState=loaded\nActiveState=active\nSubState=unexpected secret')
        result = g.service_status(fake_run)
        self.assertEqual(result['services']['rr-stream.service']['active'], 'unknown')
        self.assertEqual(result['services']['nginx.service']['sub'], 'unknown')

    def test_snapshot_excludes_private_data(self):
        (self.app / 'config').mkdir()
        (self.app / 'config/local.php').write_text('private')
        self.assertEqual(g.snapshot(self.app), {'app/example.php': g.digest(b'old code')})

if __name__ == '__main__':
    unittest.main()
