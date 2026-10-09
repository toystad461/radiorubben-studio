import importlib.util
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location('audio_key', ROOT / 'scripts/store-elevenlabs-key.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

class PrivateKeyTests(unittest.TestCase):
    def test_private_exclusive_write(self):
        with tempfile.TemporaryDirectory() as d:
            folder = Path(d)
            module.store_key(folder, 'fixture-not-a-real-key-1234')
            target = folder / 'elevenlabs.key'
            self.assertEqual(target.stat().st_mode & 0o777, 0o600)
            with self.assertRaises(FileExistsError):
                module.store_key(folder, 'different-fixture-key-1234')
            self.assertEqual(target.read_text(), 'fixture-not-a-real-key-1234')

    def test_symlink_and_invalid_input_rejected(self):
        with tempfile.TemporaryDirectory() as d:
            folder = Path(d)
            for value in ('short', 'fixture-key-with-newline\n', 'x' * 513):
                with self.assertRaises(ValueError):
                    module.store_key(folder, value)
            external = folder / 'existing'
            external.write_text('preserve')
            (folder / 'elevenlabs.key').symlink_to(external)
            with self.assertRaises(FileExistsError):
                module.store_key(folder, 'fixture-not-a-real-key-1234')
            self.assertEqual(external.read_text(), 'preserve')

    def test_no_terminal_fails_without_secret_output(self):
        result = subprocess.run([sys.executable, str(ROOT / 'scripts/store-elevenlabs-key.py')],
                                input='fixture-not-a-real-key-1234', text=True, capture_output=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('fixture-not-a-real-key', result.stdout + result.stderr)

if __name__ == '__main__':
    unittest.main()
