#!/usr/bin/env python3
import importlib.util
import io
from pathlib import Path
import subprocess
import tempfile
import unittest
import zipfile

spec = importlib.util.spec_from_file_location('release', Path(__file__).with_name('news-release.py'))
r = importlib.util.module_from_spec(spec)
spec.loader.exec_module(r)


class ReleaseTests(unittest.TestCase):
    def test_reproducible_and_minimal(self):
        self.assertEqual(r.archive_bytes(), r.archive_bytes())
        with zipfile.ZipFile(io.BytesIO(r.archive_bytes())) as z:
            self.assertEqual(set(z.namelist()), {'news-scripts.patch', 'manifest.json', 'README.txt'})
            targets = [line[6:] for line in z.read('news-scripts.patch').decode().splitlines() if line.startswith('+++ b/')]
            self.assertEqual(set(targets), set(r.CHANGES))

    def test_rejects_contamination(self):
        # PR15/16 modules, dependency creep, private files, path traversal, backup,
        # a secret in a permitted member, missing member, duplicate and ZIP trailer.
        extras = ['studio-public/broadcast.php', 'studio-public/weather.php',
                  'studio-private/app/templates/morning-v1.json',
                  'studio-private/app/programs.php', 'studio-private/app/editorial-memory.php',
                  'studio-private/app/integrations/WeatherOverview.php',
                  'studio-private/config/local.php', 'studio-private/config/sending-board.json',
                  '.env', '../secret', 'board.php.bak', 'studio-public/assets/weather-overview.js']
        with tempfile.TemporaryDirectory() as d:
            p = Path(d) / 'candidate.zip'
            p.write_bytes(r.archive_bytes())
            r.verify(p)
            for name in extras:
                with self.subTest(name=name):
                    p.write_bytes(r.archive_bytes())
                    with zipfile.ZipFile(p, 'a') as z:
                        z.writestr(name, 'PRIVATE_SENTINEL')
                    with self.assertRaises(ValueError):
                        r.verify(p)
            for variant in ['modified', 'missing', 'duplicate', 'trailer']:
                with self.subTest(variant=variant):
                    data = r.expected()
                    if variant == 'modified':
                        data['news-scripts.patch'] += b'PRIVATE_SENTINEL'
                    if variant == 'missing':
                        del data['manifest.json']
                    with zipfile.ZipFile(p, 'w') as z:
                        for name, body in data.items():
                            z.writestr(name, body)
                        if variant == 'duplicate':
                            z.writestr('README.txt', b'PRIVATE_SENTINEL')
                    if variant == 'trailer':
                        p.write_bytes(r.archive_bytes() + b'PRIVATE_SENTINEL')
                    with self.assertRaises(ValueError):
                        r.verify(p)

    def fixture(self, root):
        for path, (before, _) in r.CHANGES.items():
            if before:
                p = root / path
                p.parent.mkdir(parents=True, exist_ok=True)
                p.write_bytes(r.blob(before))
        for path, sha in r.DEPENDENCIES.items():
            p = root / path
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_bytes(r.blob(sha))

    def test_patch_roundtrip_and_readonly_preflight(self):
        with tempfile.TemporaryDirectory() as d:
            root = Path(d)
            self.fixture(root)
            private = root / 'studio-private/config/sending-board.json'
            private.parent.mkdir()
            private.write_text('PRIVATE_HISTORY_SENTINEL')
            r.preflight(root)
            for path, (before, _) in r.CHANGES.items():
                if before:
                    self.assertEqual((root / path).read_bytes(), r.blob(before))
            patch = root / 'delta.patch'
            patch.write_bytes(r.expected()['news-scripts.patch'])
            subprocess.run(['git', 'apply', str(patch)], cwd=root, check=True)
            for path, (_, after) in r.CHANGES.items():
                self.assertEqual((root / path).read_bytes(), r.blob(after))
            self.assertEqual(private.read_text(), 'PRIVATE_HISTORY_SENTINEL')
            with self.assertRaises(ValueError):
                r.preflight(root)  # repeated application must fail

    def test_mismatch_missing_dependency_and_symlink(self):
        for path in [*r.DEPENDENCIES, 'studio-private/app/board.php', 'studio-public/sending.php']:
            for mode in ['changed', 'missing', 'symlink']:
                with self.subTest(path=path, mode=mode), tempfile.TemporaryDirectory() as d:
                    root = Path(d)
                    self.fixture(root)
                    p = root / path
                    if mode == 'changed':
                        p.write_bytes(p.read_bytes() + b'\n')
                    else:
                        p.unlink()
                        if mode == 'symlink':
                            p.symlink_to(root / 'absent')
                    with self.assertRaises(ValueError):
                        r.preflight(root)


if __name__ == '__main__':
    unittest.main()
