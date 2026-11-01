#!/usr/bin/env python3
"""The partial L01 installer must never package L02 dependencies as an L01 update."""
import importlib.util
from pathlib import Path
import tempfile
import unittest

class LegacyBuilderTests(unittest.TestCase):
    def test_l02_tree_is_refused_by_pinned_l01_builder(self):
        repo = Path(__file__).resolve().parents[1]
        spec = importlib.util.spec_from_file_location('legacy_builder', repo/'infra/studio-stream/build-test-installer.py')
        builder = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(builder)
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory)/'L01-install-test.py'
            with self.assertRaisesRegex(ValueError, 'Source not in candidate manifest'):
                builder.build('a'*40, target)
            self.assertFalse(target.exists())

if __name__ == '__main__':
    unittest.main()
