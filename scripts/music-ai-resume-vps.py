#!/usr/bin/env python3
"""Selective test-VPS worker update and one explicit retry. Leaves AI timer paused."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import tempfile
import time

APP = Path('/srv/radiorubben-test/studio')
MEDIA = Path('/srv/radiorubben-test/studio-media')
DEST = Path('/etc/rr-music-ai')
WORKER = DEST / 'worker.php'
SERVICE = 'rr-music-ai.service'
TIMER = 'rr-music-ai.timer'
OLD = 'c400e0e4433b297f2cb0887a13f97a44ccb545ae62b556d6add4b0c4089b5881'
FILES = {
    'music-ai-worker.php': '75b8a9c6b9190609e0fcd5444c76f21c55941cf5cc7a56593bcb9cc17ba7a9de',
    'test-music-ai-worker.php': '51bd9f0be67a9d2107f86f931d033d4964a1fde0f245566229e6f32babdcd21d',
}

def digest(data):
    return hashlib.sha256(data).hexdigest()

def run(args, check=True, timeout=120):
    return subprocess.run(args, check=check, timeout=timeout, capture_output=True, text=True)

def value(unit, prop):
    return run(['systemctl', 'show', unit, '--property=' + prop, '--value']).stdout.strip()

def transient(command, writable=False, env=()):
    props = ['User=rr-studio', 'Group=rr-studio', 'NoNewPrivileges=yes',
             'PrivateTmp=yes', 'ProtectSystem=strict', 'ProtectHome=yes',
             'RestrictAddressFamilies=AF_UNIX AF_INET', 'IPAddressDeny=any',
             'IPAddressAllow=127.0.0.1/32', 'UMask=0077', 'RuntimeMaxSec=75']
    if writable:
        props.append('ReadWritePaths=' + str(MEDIA))
    environment = ['https_proxy=http://127.0.0.1:3140', 'HTTPS_PROXY=http://127.0.0.1:3140',
                   'NO_PROXY=', 'no_proxy=', 'RR_MUSIC_AI_MODEL=gpt-4.1-mini', *env]
    return run(['systemd-run', '--quiet', '--wait', '--pipe', '--collect'] +
               ['--property=' + p for p in props] + ['--setenv=' + e for e in environment] + command)

def replace_worker(data, mode):
    fd, name = tempfile.mkstemp(prefix='.worker-', dir=DEST)
    try:
        with os.fdopen(fd, 'wb') as handle:
            os.fchmod(handle.fileno(), mode)
            handle.write(data)
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(name, WORKER)
    finally:
        if os.path.exists(name):
            os.unlink(name)

def apply(source, track_id):
    if not re.fullmatch('[a-f0-9]{16,64}', track_id):
        raise RuntimeError('Invalid track ID')
    if not APP.is_dir() or not MEDIA.is_dir() or MEDIA.is_symlink() or WORKER.is_symlink():
        raise RuntimeError('Unexpected test environment')
    old = WORKER.read_bytes()
    if digest(old) not in (OLD, FILES['music-ai-worker.php']):
        raise RuntimeError('Installed worker differs from the observed version; no change made')
    mode = stat.S_IMODE(WORKER.stat().st_mode)
    if WORKER.stat().st_uid != os.geteuid() or mode & 0o022:
        raise RuntimeError('Unexpected worker ownership or permissions')
    sources = {name: (source / name).read_bytes() for name in FILES}
    if any(digest(sources[name]) != expected for name, expected in FILES.items()):
        raise RuntimeError('Source hash mismatch')
    command = value(SERVICE, 'ExecStart')
    if str(WORKER) + ' --once --app-root=' + str(APP) not in command or value(SERVICE, 'DropInPaths'):
        raise RuntimeError('Worker service changed; inspect before updating')
    if value('rr-music-ai-proxy.service', 'ActiveState') != 'active':
        raise RuntimeError('Existing music proxy is not active')
    enabled, active = value(TIMER, 'UnitFileState'), value(TIMER, 'ActiveState')
    if enabled not in ('enabled', 'disabled') or active not in ('active', 'inactive'):
        raise RuntimeError('Timer state needs inspection')
    with tempfile.TemporaryDirectory(prefix='rr-ai-recovery-', dir='/run') as temp:
        stage = Path(temp)
        stage.chmod(0o755)
        for name, data in sources.items():
            (stage / name).write_bytes(data)
            (stage / name).chmod(0o644)
        test_env = ['RR_MUSIC_AI_TEST_APP=' + str(APP), 'RR_MUSIC_AI_TEST_WORKER=' + str(stage / 'music-ai-worker.php')]
        transient(['/usr/bin/php8.3', '-l', str(stage / 'music-ai-worker.php')])
        transient(['/usr/bin/php8.3', str(stage / 'test-music-ai-worker.php')], env=test_env)
        transient(['/usr/bin/php8.3', str(stage / 'music-ai-worker.php'), '--check', '--app-root=' + str(APP)])
        backup = Path(tempfile.mkdtemp(prefix='recovery-', dir=DEST))
        backup.chmod(0o700)
        (backup / 'worker.php').write_bytes(old)
        (backup / 'worker.php').chmod(0o600)
        manifest = {'workerBefore': digest(old), 'workerAfter': FILES['music-ai-worker.php'],
                    'timerEnabledBefore': enabled, 'timerActiveBefore': active, 'appRoot': str(APP.resolve())}
        (backup / 'manifest.json').write_text(json.dumps(manifest, indent=2))
        (backup / 'manifest.json').chmod(0o600)
        changed = False
        try:
            run(['systemctl', 'disable', '--now', TIMER])
            deadline = time.monotonic() + 95
            while value(SERVICE, 'ActiveState') not in ('inactive', 'failed'):
                if time.monotonic() >= deadline:
                    raise RuntimeError('Existing assessment still running; no worker update')
                time.sleep(1)
            if value(TIMER, 'ActiveState') != 'inactive' or value(TIMER, 'UnitFileState') != 'disabled':
                raise RuntimeError('AI timer did not pause')
            with (MEDIA / 'music/library.lock').open('rb') as lock:
                fcntl.flock(lock, fcntl.LOCK_SH)
                snapshot = (MEDIA / 'music/library.json').read_bytes()
                (backup / 'library-before.json').write_bytes(snapshot)
                (backup / 'library-before.json').chmod(0o600)
            replace_worker(sources['music-ai-worker.php'], mode)
            changed = True
            if digest(WORKER.read_bytes()) != FILES['music-ai-worker.php']:
                raise RuntimeError('Installed hash mismatch')
            completed = transient(['/usr/bin/php8.3', str(WORKER), '--retry', '--app-root=' + str(APP), '--track-id=' + track_id], writable=True)
            outcome = json.loads(completed.stdout)
            if outcome.get('status') not in ('prepared', 'already_prepared') or outcome.get('trackId') != track_id:
                raise RuntimeError('Selected track was not prepared; saved attempt history is retained')
            return {'status': 'worker_updated_selected_track_prepared_timer_paused',
                    'workerSha256': digest(WORKER.read_bytes()), 'backup': str(backup), 'result': outcome}
        except BaseException:
            if changed:
                replace_worker(old, mode)
            # Restore code/timer only. Never overwrite concurrent library changes.
            run(['systemctl', 'enable' if enabled == 'enabled' else 'disable', TIMER])
            run(['systemctl', 'start' if active == 'active' else 'stop', TIMER])
            print('Worker/timer restored; library attempt history retained. Private backup: ' + str(backup), file=sys.stderr)
            raise

if __name__ == '__main__':
    try:
        if os.geteuid() != 0 or len(sys.argv) != 3 or sys.argv[1] != '--apply' or not sys.argv[2].startswith('--track-id='):
            raise RuntimeError('Use sudo: music-ai-resume-vps.py --apply --track-id=...')
        os.umask(0o077)
        print(json.dumps(apply(Path(__file__).resolve().parent, sys.argv[2][11:]), ensure_ascii=False, indent=2))
    except Exception as error:
        # Subprocess errors may hold config-related output: never print them.
        print('STOPP: ' + (str(error) if type(error) is RuntimeError else 'Recovery did not complete; no credentials printed.'), file=sys.stderr)
        sys.exit(1)
