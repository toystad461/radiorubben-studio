#!/usr/bin/env python3
"""Safety and interpretation checks for the read-only L01 VPS inventory."""
import contextlib
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('preflight', Path(__file__).resolve().parents[1] / 'infra/studio-stream/preflight.py')
probe = importlib.util.module_from_spec(spec)
spec.loader.exec_module(probe)


class InventoryTests(unittest.TestCase):
    def test_wrong_host_refuses_before_reading_files_or_running_commands(self):
        with patch.object(probe.socket, 'gethostname', return_value='production'), \
             patch.object(probe, 'command') as command, \
             patch.object(probe, 'read_regular') as read, \
             contextlib.redirect_stdout(io.StringIO()) as output:
            self.assertEqual(probe.main(), 1)
            self.assertEqual(json.loads(output.getvalue())['reason'], 'wrong_host')
            command.assert_not_called()
            read.assert_not_called()

    def test_no_automatic_privilege_escalation(self):
        with patch.object(probe.socket, 'gethostname', return_value='vps-5363103'), \
             patch.object(probe.os, 'geteuid', return_value=1000), \
             patch.object(probe, 'command') as command:
            with self.assertRaisesRegex(probe.ProbeError, 'administrator'):
                probe.validate_environment()
            command.assert_not_called()

    def test_read_refuses_symlinks_parent_symlinks_large_files_and_fifo(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            plain = root / 'plain'
            plain.write_bytes(b'1234')
            (root / 'link').symlink_to(plain)
            (root / 'dirlink').symlink_to(root, target_is_directory=True)
            os.mkfifo(root / 'pipe')
            for path, limit in [(root / 'link', 10), (root / 'dirlink/plain', 10), (plain, 3), (root / 'pipe', 10)]:
                with self.subTest(path=path):
                    with self.assertRaises(probe.ProbeError):
                        probe.read_regular(path, limit)
            self.assertEqual(probe.read_regular(plain, 4), b'1234')

    def test_summaries_never_emit_credentials_titles_or_raw_records(self):
        data = {'entries': [{'title': 'DO_NOT_PRINT', 'password': 'SECRET'}],
                'revision': 7, 'ready': True, 'api_key': 'SECRET'}
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / 'data.json'
            path.write_text(json.dumps(data))
            result = probe.json_summary(path, 'manifest')
            self.assertEqual(result, {'status': 'observed', 'entries': 1, 'revision': 7, 'ready_reported': True})
            path.write_text('{broken SECRET')
            self.assertEqual(probe.safe_probe(lambda: probe.json_summary(path, 'queue')), {'status': 'unverified'})

    def test_missing_input_is_not_reported_as_empty_queue(self):
        with tempfile.TemporaryDirectory() as tmp:
            result = probe.safe_probe(lambda: probe.json_summary(Path(tmp) / 'missing', 'queue'))
            self.assertEqual(result, {'status': 'missing'})
            self.assertNotIn('entries', result)

    def test_real_service_names_and_failed_commands_are_not_health_checks(self):
        self.assertIn('rr-studio-stream', probe.SERVICES)
        self.assertIn('rr-studio-icecast', probe.SERVICES)
        self.assertNotIn('rr-stream', probe.SERVICES)
        with patch.object(probe, 'command', return_value=subprocess.CompletedProcess([], 1, 'SECRET', 'SECRET')):
            self.assertEqual(probe.service_status('rr-studio-stream'), {'status': 'unverified'})
        with patch.object(probe, 'command', return_value=subprocess.CompletedProcess([], 1, 'LoadState=not-found\n', '')):
            self.assertEqual(probe.service_status('rr-studio-stream'), {'status': 'not-found'})
        with patch.object(probe, 'command', side_effect=subprocess.TimeoutExpired('systemctl', 8)):
            self.assertEqual(probe.service_status('rr-studio-stream'), {'status': 'unverified'})

    def test_package_presence_requires_success_and_installed_status(self):
        with patch.object(probe, 'command', return_value=subprocess.CompletedProcess([], 0, 'installed\t2.2.4-1', '')):
            self.assertEqual(probe.package_version('liquidsoap')['version'], '2.2.4-1')
        with patch.object(probe, 'command', return_value=subprocess.CompletedProcess([], 1, 'installed\t2.2.4-1', '')):
            self.assertEqual(probe.package_version('liquidsoap')['status'], 'not_confirmed')

    def test_inventory_does_not_change_fixture_and_retains_unknown_hashes(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            release = root / 'releases/test-release'
            release.mkdir(parents=True)
            (root / 'studio').symlink_to(release)
            (root / 'studio-data').mkdir()
            (root / 'studio-data/music-play-queue.private.json').write_text('{"entries":[],"revision":1}')
            (release / 'app').mkdir()
            (release / 'app/music-studio.php').write_text('selective local change')
            files = [p for p in root.rglob('*') if p.is_file()]
            def snapshot():
                return {str(p): (hashlib.sha256(p.read_bytes()).hexdigest(), p.stat().st_mtime_ns, p.stat().st_mode) for p in files}
            before = snapshot()
            with patch.object(probe.socket, 'gethostname', return_value='vps-5363103'), \
                 patch.object(probe.os, 'geteuid', return_value=0), \
                 patch.object(probe, 'service_status', return_value={'status': 'unverified'}), \
                 patch.object(probe, 'package_version', return_value={'status': 'not_confirmed'}):
                result = probe.collect(root)
            self.assertEqual(snapshot(), before)
            self.assertEqual(result['web_code']['app/music-studio.php']['matches'], ['unreconciled'])
            self.assertEqual(result['data']['queue']['entries'], 0)
            self.assertEqual(result['data']['manifest'], {'status': 'missing'})
            self.assertEqual(result['status'], 'inventory_only_not_installation_or_audio_pass')

    def test_candidate_manifest_matches_runtime_files_in_this_tree(self):
        repo = Path(__file__).resolve().parents[1]
        for relative, expected in probe.REVIEWED_HASHES['candidate_l01'].items():
            content = (repo / relative).read_bytes()
            self.assertEqual(hashlib.sha256(content).hexdigest(), expected, relative)


if __name__ == '__main__':
    unittest.main()
