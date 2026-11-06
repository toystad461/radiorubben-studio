#!/usr/bin/env python3
"""Exercise the real six-file manifest on disposable directories only."""
import importlib.util
import json
import os
from pathlib import Path
import shutil
import stat
import sys
import tempfile
import unittest
from unittest.mock import patch

REPO = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('menu', REPO / 'scripts/studio-menu-vps.py')
menu = importlib.util.module_from_spec(spec)
spec.loader.exec_module(menu)


def prepare_overlay(root):
    """Only for an isolated CI checkout; never call the live installer."""
    if os.environ.get('CI') != 'true':
        raise RuntimeError('Overlay preparation is restricted to CI')
    root = root.resolve()
    if root == REPO or not (root / '.git').exists() or root == menu.ROOT:
        raise RuntimeError('Expected a separate disposable checkout')
    current = menu.read_current(root)
    payload = menu.build_payload(current, lambda p: (REPO / p).read_bytes())
    for p, content in payload.items():
        (root / p).write_bytes(content)
    for p in ['tests/fixtures/navigation.php', 'tests/navigation-mobile.cjs',
              'tests/controlroom.php', 'tests/studio-board-mobile.cjs']:
        shutil.copyfile(REPO / p, root / p)
    print('Overlay prepared: PR117 baseline plus exactly six PR115 menu changes')


class SelectiveMenu(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.fixture = Path(os.environ['RR_MENU_FIXTURES']).resolve()
        cls.current = menu.read_current(cls.fixture)
        cls.payload = menu.build_payload(cls.current, lambda p: (REPO / p).read_bytes())

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name) / 'release'
        self.backups = Path(self.temp.name) / 'backups'
        self.backups.mkdir()
        for p, data in self.current.items():
            target = self.root / p
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(data)
            target.chmod(0o640)
        self.protected = self.root / 'data' / 'journal.jsonl'
        self.protected.parent.mkdir()
        self.protected.write_bytes(b'preserve active data\n')

    def install(self, check=lambda: {}):
        return menu.install(self.root, self.backups, self.current, self.payload, {}, check, lint=False)

    def test_install_and_real_rollback_preserve_data_and_modes(self):
        folder, receipt = self.install()
        self.assertEqual(menu.read_current(self.root), self.payload)
        self.assertEqual(receipt['status'], 'installed_files_verified')
        for p in menu.BEFORE:
            self.assertEqual(stat.S_IMODE((self.root / p).stat().st_mode), 0o640)
        self.assertEqual(self.protected.read_bytes(), b'preserve active data\n')
        menu.restore(self.root, folder, receipt)
        self.assertEqual(menu.read_current(self.root), self.current)
        self.assertEqual(self.protected.read_bytes(), b'preserve active data\n')

    def test_preserves_complete_pr117_console_except_menu_patch(self):
        p = 'public/assets/ai-studio/app.mjs'
        addition = b"    if (active) {\n      const group = link.closest('details.nav-group');\n      if (group) group.open = true;\n    }\n"
        self.assertEqual(self.payload[p].replace(addition, b''), self.current[p])
        for value in (b'weatherSource', b'stikk-20261102', b'action(lastAction, true)'):
            self.assertIn(value, self.payload[p])
        p = 'public/ai-studio.php'
        self.assertEqual(self.payload[p].replace(b'menu-stikk-20261104', b'stikk-20261102'), self.current[p])
        self.assertIn(b'data-action="traffic"', self.payload[p])

    def test_newer_baseline_is_rejected_before_download(self):
        changed = dict(self.current)
        changed['public/ai-studio.php'] += b'newer work'
        with patch.object(menu, 'fetch_source') as fetch:
            with self.assertRaisesRegex(RuntimeError, 'Installed files have changed'):
                menu.build_payload(changed, fetch)
            fetch.assert_not_called()
        self.assertEqual(menu.read_current(self.root), self.current)

    def test_corrupt_source_is_rejected(self):
        with self.assertRaisesRegex(RuntimeError, 'Candidate hash mismatch'):
            menu.build_payload(self.current, lambda p: b'bad download')
        self.assertEqual(menu.read_current(self.root), self.current)

    def test_failure_mid_install_restores_originals(self):
        write = menu.atomic_write
        failed = False
        def fault(path, data, metadata=None):
            nonlocal failed
            if path == self.root / 'app/views/head.php' and not failed:
                failed = True
                raise OSError('injected write failure')
            write(path, data, metadata)
        with patch.object(menu, 'atomic_write', fault):
            with self.assertRaisesRegex(OSError, 'injected'):
                self.install()
        self.assertEqual(menu.read_current(self.root), self.current)
        receipt = json.loads(next(self.backups.glob('*/receipt.json')).read_text())
        self.assertEqual(receipt['status'], 'rolled_back')

    def test_service_change_rolls_back_without_service_commands(self):
        with self.assertRaisesRegex(RuntimeError, 'Service state/PID changed'):
            self.install(lambda: {'pid': 'different'})
        self.assertEqual(menu.read_current(self.root), self.current)

    def test_later_edit_blocks_rollback_without_overwrite(self):
        folder, receipt = self.install()
        target = self.root / 'public/ai-studio.php'
        target.write_bytes(b'newer operator work')
        with self.assertRaisesRegex(RuntimeError, 'Later edit blocks rollback'):
            menu.restore(self.root, folder, receipt)
        self.assertEqual(target.read_bytes(), b'newer operator work')
        self.assertEqual((self.root / 'app/views/navigation.php').read_bytes(), self.payload['app/views/navigation.php'])

    def test_symlink_and_hardlink_are_rejected(self):
        target = self.root / 'app/views/head.php'
        target.unlink()
        target.symlink_to(self.protected)
        with self.assertRaisesRegex(RuntimeError, 'Symlink'):
            menu.read_current(self.root)
        target.unlink()
        os.link(self.protected, target)
        with self.assertRaisesRegex(RuntimeError, 'single regular file'):
            menu.read_current(self.root)

    def test_concurrent_change_before_write_is_rejected(self):
        target = self.root / 'app/views/head.php'
        target.write_bytes(b'newer work')
        with self.assertRaisesRegex(RuntimeError, 'Files changed during preparation'):
            self.install()
        self.assertEqual(target.read_bytes(), b'newer work')
        self.assertEqual((self.root / 'public/assets/studio.css').read_bytes(), self.current['public/assets/studio.css'])


if __name__ == '__main__':
    if len(sys.argv) == 3 and sys.argv[1] == '--prepare-overlay':
        prepare_overlay(Path(sys.argv[2]))
    else:
        unittest.main()
