"""Exercise the actual workflow unlock block with disposable synthetic keys."""
import os
from pathlib import Path
import subprocess
import tempfile
import textwrap

workflow = (Path(__file__).resolve().parents[1] / '.github/workflows/vps-test-deploy.yml').read_text()
block = workflow.split('      - name: Send to fixed VPS gateway', 1)[1].split('        run: |\n', 1)[1]
script = textwrap.dedent(block.split('          ssh-keygen -F 5.249.252.78', 1)[0].rstrip()) + '\n'
subprocess.run(['bash', '-n'], input=script, text=True, check=True)
with tempfile.TemporaryDirectory() as folder:
    for phrase, supplied, expected in [
        ('', '', 0), ('synthetic-only', 'synthetic-only', 0),
        ('synthetic-only', 'wrong', 1), ('synthetic-only', '', 1)
    ]:
        key = Path(folder) / 'fixture'
        key.unlink(missing_ok=True)
        key.with_suffix('.pub').unlink(missing_ok=True)
        subprocess.run(['ssh-keygen', '-q', '-t', 'ed25519', '-N', phrase, '-f', str(key)], check=True)
        env = {**os.environ, 'DEPLOY_KEY': key.read_text(), 'KNOWN_HOSTS': 'synthetic',
               'DEPLOY_PASSPHRASE': supplied}
        print('Checking synthetic key case; expected exit:', expected, flush=True)
        result = subprocess.run(['bash', '-c', script], env=env, text=True, capture_output=True, timeout=30)
        if result.returncode != expected:
            raise RuntimeError('Unexpected unlock result for synthetic fixture')
print('PASS: plain/encrypted keys accepted; incorrect/missing passphrases rejected')
