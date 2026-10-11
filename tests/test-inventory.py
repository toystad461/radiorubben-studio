import copy, hashlib, importlib.util, pathlib, unittest

ROOT = pathlib.Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('inventory', ROOT / 'scripts/inventory/validate-report.py')
inventory = importlib.util.module_from_spec(spec)
spec.loader.exec_module(inventory)

class InventoryTest(unittest.TestCase):
    def setUp(self):
        self.collector = (ROOT / 'scripts/inventory/read-production.php').read_bytes()
        # Use a real previously observed file list, but synthetic values only.
        import ast
        tree = ast.parse((ROOT / 'scripts/inventory/validate-report.py').read_text())
        paths = next(ast.literal_eval(n.value) for n in ast.walk(tree)
                     if isinstance(n, ast.Assign) and any(isinstance(t, ast.Name) and t.id == 'allowed' for t in n.targets))
        self.report = dict(productionWritten=False, existingSharedLock=True,
                           collectorSha256=hashlib.sha256(self.collector).hexdigest(),
                           deployCommit='a'*40, files={p: dict(status='present', stableDuringRead=True,
                           sha256='b'*64, recordedLive='b'*64) for p in paths})

    def test_valid(self):
        inventory.validate(self.report, self.collector)

    def test_reject_invariants(self):
        for field, value in [('productionWritten', True), ('existingSharedLock', False),
                             ('collectorSha256', 'c'*64), ('deployCommit', 'unknown')]:
            with self.subTest(field=field), self.assertRaises(ValueError):
                report = copy.deepcopy(self.report); report[field] = value
                inventory.validate(report, self.collector)

    def test_reject_file_changes(self):
        for field, value in [('stableDuringRead', False), ('sha256', 'invalid'), ('recordedLive', 'c'*64)]:
            with self.subTest(field=field), self.assertRaises(ValueError):
                report = copy.deepcopy(self.report); next(iter(report['files'].values()))[field] = value
                inventory.validate(report, self.collector)

    def test_absence_requires_proof(self):
        report = copy.deepcopy(self.report); path = next(iter(report['files']))
        report['files'][path] = dict(status='absent')
        with self.assertRaises(ValueError): inventory.validate(report, self.collector)
        report['files'][path]['absenceVerified'] = True
        inventory.validate(report, self.collector)

    def test_reject_extra_path(self):
        self.report['files']['studio-private/config/local.php'] = dict(status='absent', absenceVerified=True)
        with self.assertRaises(ValueError): inventory.validate(self.report, self.collector)

if __name__ == '__main__': unittest.main()
