#!/usr/bin/python3
"""Root-owned, forced-command gateway. No shell or arbitrary remote commands."""
import base64
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile

APP = Path('/srv/radiorubben-test/studio')
RELEASES = Path('/srv/radiorubben-test/releases')
STATE = Path('/var/lib/rr-vps-deploy')
LIMIT = 12 * 1024 * 1024


# Fixed, read-only operational checks. Never accept unit names or shell from requests.
STATUS_UNITS = (
    'nginx.service', 'php8.3-fpm.service', 'rr-entra.service',
    'rr-music-playout.service', 'rr-playout.service', 'rr-stream.service',
)


def service_status(run=None):
    """Return bounded systemd state without logs, environment or credentials."""
    if run is None:
        run = subprocess.run
    result = {}
    for unit in STATUS_UNITS:
        proc = run(
            ['/usr/bin/systemctl', 'show', unit,
             '--property=LoadState,ActiveState,SubState', '--no-pager'],
            capture_output=True, text=True, timeout=5, check=False,
        )
        if proc.returncode != 0:
            result[unit] = {'load': 'unknown', 'active': 'unknown', 'sub': 'unknown'}
            continue
        props = dict(line.split('=', 1) for line in proc.stdout.splitlines()
                     if '=' in line)
        # Only bounded, known state strings are exposed in receipts.
        def state(key):
            value = props.get(key, 'unknown')
            return value if re.fullmatch(r'[a-z-]{1,32}', value) else 'unknown'
        result[unit] = {
            'load': state('LoadState'),
            'active': state('ActiveState'),
            'sub': state('SubState'),
        }
    return {'status': 'service-status', 'services': result}


def digest(data):
    return hashlib.sha256(data).hexdigest()


def allowed(name):
    return (isinstance(name, str) and
            re.fullmatch(r'(app|public)/[A-Za-z0-9_/-]+\.(php|mjs|js|css)', name) and
            all(p not in ('', '.', '..') for p in name.split('/')))


def rooted(app, name):
    if not allowed(name):
        raise ValueError('Path is outside the code allowlist')
    path = app / name
    for part in [path, *path.parents]:
        if part == app:
            break
        if part.is_symlink():
            raise ValueError('Symlink in code path')
    if path.exists() and (not path.is_file() or path.stat().st_nlink != 1):
        raise ValueError('Code target is not a regular single-link file')
    return path


def snapshot(app):
    result = {}
    for folder in ('app', 'public'):
        for path in sorted((app / folder).rglob('*')):
            name = path.relative_to(app).as_posix()
            if allowed(name):
                path = rooted(app, name)
                if path.is_file():
                    result[name] = digest(path.read_bytes())
    return result


def validate(app, request):
    if not re.fullmatch('[0-9a-f]{40}', request.get('commit', '')):
        raise ValueError('Exact source commit required')
    files = request.get('files')
    if not isinstance(files, list) or not 1 <= len(files) <= 64:
        raise ValueError('Expected 1 to 64 code files')
    changes = []
    seen = set()
    for entry in files:
        name = entry['path']
        path = rooted(app, name)
        if name in seen:
            raise ValueError('Duplicate path')
        seen.add(name)
        data = base64.b64decode(entry['content'], validate=True)
        if len(data) > 2 * 1024 * 1024 or digest(data) != entry['after']:
            raise ValueError('Candidate checksum or size mismatch')
        old = path.read_bytes() if path.exists() else None
        before = digest(old) if old is not None else None
        if before != entry['before']:
            raise ValueError('Active code differs from reviewed baseline: ' + name)
        if not path.parent.is_dir():
            raise ValueError('Parent directory must already exist')
        changes.append((path, data, old, path.stat() if old is not None else None))
    return changes


def atomic(path, data, metadata=None):
    fd, tmp = tempfile.mkstemp(prefix='.rr-deploy-', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(data)
            stream.flush()
            os.fsync(stream.fileno())
            os.fchmod(stream.fileno(), (metadata.st_mode & 0o777) if metadata else 0o644)
            if metadata:
                os.fchown(stream.fileno(), metadata.st_uid, metadata.st_gid)
        os.replace(tmp, path)
        directory = os.open(path.parent, os.O_DIRECTORY)
        try:
            os.fsync(directory)
        finally:
            os.close(directory)
    finally:
        if os.path.exists(tmp):
            os.unlink(tmp)


def health():
    for service in ('php8.3-fpm', 'rr-entra'):
        subprocess.run(['/usr/bin/systemctl', 'is-active', '--quiet', service], check=True)
    subprocess.run(['/usr/bin/curl', '--fail', '--silent', '--show-error',
                    '--max-time', '20', '--output', '/dev/null',
                    'https://5.249.252.78/login.php'], check=True)


def reload_php():
    subprocess.run(['/usr/bin/systemctl', 'reload', 'php8.3-fpm'], check=True)
    # Separate Entra FPM has no ExecReload; restarting clears its code cache.
    subprocess.run(['/usr/bin/systemctl', 'restart', 'rr-entra'], check=True)


def deploy(app, state, request, reload_hook=reload_php, health_hook=health):
    changes = validate(app, request)
    if (state / 'pending.json').exists():
        raise ValueError('Previous deployment needs recovery; no new writes')
    health_hook()
    backup = Path(tempfile.mkdtemp(prefix='backup-', dir=state))
    receipt = {'commit': request['commit'], 'files': request['files'], 'backup': str(backup)}
    # Receipts contain checksums only, not source code or private app data.
    receipt['files'] = [{k: v for k, v in f.items() if k != 'content'} for f in request['files']]
    for path, data, old, meta in changes:
        if old is not None:
            dest = backup / path.relative_to(app)
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(path, dest)
            os.chown(dest, meta.st_uid, meta.st_gid)
    atomic(backup / 'manifest.json', json.dumps(receipt, indent=2).encode())
    atomic(state / 'pending.json', json.dumps(receipt).encode())
    written = []
    try:
        for path, data, old, meta in changes:
            current = path.read_bytes() if path.exists() else None
            if current != old:
                raise ValueError('Concurrent code change detected')
            atomic(path, data, meta)
            written.append((path, data, old, meta))
        reload_hook()
        health_hook()
        for path, data, old, meta in written:
            if path.read_bytes() != data:
                raise ValueError('Post-deploy checksum mismatch')
        receipt['status'] = 'deployed'
        atomic(state / 'last-success.json', json.dumps(receipt, indent=2).encode())
        (state / 'pending.json').unlink()
        return receipt
    except BaseException:
        for path, data, old, meta in reversed(written):
            if not path.is_file() or path.is_symlink() or path.read_bytes() != data:
                raise RuntimeError('Rollback blocked by concurrent change; pending journal retained')
            if old is None:
                path.unlink()
            else:
                atomic(path, old, meta)
        reload_hook()
        health_hook()
        (state / 'pending.json').unlink()
        raise


def main():
    os.umask(0o077)
    if os.geteuid() != 0:
        raise ValueError('Gateway must run through the fixed sudo rule')
    app = APP.resolve(strict=True)
    if app.parent != RELEASES or not app.name.startswith('studio-'):
        raise ValueError('Unexpected active Studio release')
    # No writable parent may redirect root writes through a substituted path.
    for folder in [app, *app.parents, APP.parent]:
        st = folder.stat()
        if st.st_uid != 0 or st.st_mode & 0o022:
            raise ValueError('Release ancestry must be root-owned and non-writable')
    STATE.mkdir(mode=0o700, exist_ok=True)
    if STATE.is_symlink() or STATE.stat().st_uid != 0 or STATE.stat().st_mode & 0o077:
        raise ValueError('Unsafe gateway state directory')
    with (STATE / 'lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        raw = sys.stdin.buffer.read(LIMIT + 1)
        if len(raw) > LIMIT:
            raise ValueError('Request too large')
        request = json.loads(raw)
        mode = request.get('mode')
        if mode == 'status':
            result = service_status()
        elif mode == 'snapshot':
            result = {'status': 'snapshot', 'app': str(app), 'files': snapshot(app),
                      'recoveryRequired': (STATE / 'pending.json').exists()}
        elif mode in ('check', 'apply'):
            # Code subdirectories must also be immutable to runtime users.
            for item in request.get('files', []):
                path = rooted(app, item['path'])
                if path.exists():
                    st = path.stat()
                    if st.st_uid != 0 or st.st_mode & 0o022:
                        raise ValueError('Code file must be root-owned and non-writable')
                for folder in path.parents:
                    st = folder.stat()
                    if st.st_uid != 0 or st.st_mode & 0o022:
                        raise ValueError('Code parent must be root-owned and non-writable')
                    if folder == app:
                        break
            if mode == 'check':
                validate(app, request)
                result = {'status': 'checked', 'commit': request['commit'], 'count': len(request['files'])}
            else:
                result = deploy(app, STATE, request)
        else:
            raise ValueError('Unsupported mode')
        print(json.dumps(result))


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('STOPP: ' + str(error), file=sys.stderr)
        sys.exit(1)
