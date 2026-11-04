#!/usr/bin/env python3
"""PR115 menu only, preserving the verified PR117 console on rubben-test.

Default: inspect/prepare only. --apply installs six files; --rollback RECEIPT
restores only this transaction's bytes, refusing any subsequent code changes.
No service restart, queue operation, content write or whole-release deployment.
"""
import argparse
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import socket
import stat
import subprocess
import tempfile
import time
import urllib.request

ROOT = Path('/srv/radiorubben-test/releases/studio-music-fad12a2320de0ff0a036854c414accee794143bb')
BACKUPS = Path('/var/lib/rr-vps-deploy')
MENU_COMMIT = '9bbfae2c7b97414c2b3b96b18aeafa731cb6c5d8'
STIKK_COMMIT = '290f80c4492122d3cb62060cdfab68872e758ff6'
BEFORE = {
    'app/views/navigation.php': 'b88904b9d42a6243c11de8d6c22af513c17957787cc261c132cb72b2f27cb92d',
    'app/views/sidebar.php': 'e459b4277a7124cc896eaf73ff283327f25a659b1b3c8003a87b01621abe813a',
    'app/views/head.php': '0d4e5414c4410365d06b257e487e34b5348a08def262dff53f73375d04ea0866',
    'public/assets/studio.css': '405e2b52f4c836fc19aaf4cbc9566873d57e8e0421238632392e1b2283223ae5',
    'public/assets/ai-studio/app.mjs': 'c70656d0ba51850ebca827f30dc7263229bd9cee0706729e067c8c6407ac6ae1',
    'public/ai-studio.php': 'fa7fc0c94d258e3e6e01b2b1e73d087114b70fbef5db8dead87cc6ce8a5b9247',
}
AFTER = {
    'app/views/navigation.php': '3f6da7444e8d69992edf26f26fe953d45f5033570c5ae796ecc150193f5982cd',
    'app/views/sidebar.php': 'aebb929943b86d89cb32d54e27a3501c2b47438f4893cd1b8346f3b5589f3980',
    'app/views/head.php': '61901ae3982df5c6fc90d602583eb07b8a16da23cce791fcbf6d6b08afc17b6d',
    'public/assets/studio.css': '97c3ab5f6e5f2874426ab18488faee9135ac865b447dd5dd614f2221ba4e1ba9',
    'public/assets/ai-studio/app.mjs': '6b0122f8933dc7c1b9241bcb83f83cc37b855c3aaaba4a98dc438ac30a5b857d',
    'public/ai-studio.php': 'dc853d3248931c377e696b0678ec0556dc34b065b2470ca251231275837966c9',
}
# Assets first, then the PHP entry points/cache versions. Every file is atomic.
ORDER = ['public/assets/studio.css', 'public/assets/ai-studio/app.mjs',
         'app/views/navigation.php', 'app/views/sidebar.php',
         'app/views/head.php', 'public/ai-studio.php']
SERVICES = ('nginx', 'php8.3-fpm', 'rr-studio-stream', 'rr-studio-icecast')


def digest(data):
    return hashlib.sha256(data).hexdigest()


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def checked_file(root, name):
    require(name in BEFORE, 'File is outside the six-file manifest')
    p = root / name
    require(p.resolve() == p, 'Symlink in target path: ' + name)
    s = p.lstat()
    require(stat.S_ISREG(s.st_mode) and s.st_nlink == 1, 'Not a single regular file: ' + name)
    return p


def read_current(root):
    return {name: checked_file(root, name).read_bytes() for name in BEFORE}


def fetch_source(name):
    url = f'https://raw.githubusercontent.com/toystad461/radiorubben-studio/{MENU_COMMIT}/{name}'
    with urllib.request.urlopen(url, timeout=25) as response:
        data = response.read(200000)
    require(digest(data) == AFTER[name], 'Source hash mismatch: ' + name)
    return data


def build_payload(current, fetch=fetch_source):
    require({p: digest(b) for p, b in current.items()} == BEFORE,
            'Installed files have changed. No installation; reconcile fresh hashes.')
    result = {p: fetch(p) for p in list(BEFORE)[:4]}
    name = 'public/assets/ai-studio/app.mjs'
    old = b"    if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');\n"
    new = old + b"    if (active) {\n      const group = link.closest('details.nav-group');\n      if (group) group.open = true;\n    }\n"
    require(current[name].count(old) == 1, 'Menu anchor is not unique')
    result[name] = current[name].replace(old, new)
    name = 'public/ai-studio.php'
    old = b'/assets/ai-studio/app.mjs?v=stikk-20261102'
    require(current[name].count(old) == 1, 'Script version anchor is not unique')
    result[name] = current[name].replace(old, b'/assets/ai-studio/app.mjs?v=menu-stikk-20261104')
    require({p: digest(b) for p, b in result.items()} == AFTER, 'Candidate hash mismatch')
    return result


def run(args):
    return subprocess.run(args, check=True, capture_output=True, text=True, timeout=30).stdout


def service_state():
    result = {}
    for service in SERVICES:
        values = dict(line.split('=', 1) for line in run([
            'systemctl', 'show', service, '-p', 'ActiveState', '-p', 'SubState', '-p', 'MainPID'
        ]).splitlines() if '=' in line)
        require(values.get('ActiveState') == 'active' and values.get('SubState') == 'running'
                and int(values.get('MainPID', '0')) > 0, 'Service is not running: ' + service)
        result[service] = values
    return result


def validate_environment():
    require(os.geteuid() == 0, 'Run with sudo -n python3')
    require(socket.gethostname() == 'vps-5363103', 'Wrong server')
    require(ROOT.resolve() == ROOT and ROOT.is_dir(), 'Unexpected release path')
    nginx = run(['nginx', '-T'])
    roots = {Path(p.strip()).resolve() for p in re.findall(r'^\s*root\s+([^;]+);', nginx, re.M)}
    require(ROOT / 'public' in roots, 'Nginx does not serve the verified release')
    for command in ('php', 'node', 'php-fpm8.3'):
        require(shutil.which(command), 'Missing validation command: ' + command)
    # Read FPM configuration, never print phpinfo or secret/config values.
    info = run(['php-fpm8.3', '-i'])
    settings = {}
    for key in ('opcache.enable', 'opcache.validate_timestamps', 'opcache.revalidate_freq', 'opcache.file_update_protection'):
        match = re.search(r'^' + re.escape(key) + r'\s*=>\s*(\S+)', info, re.M)
        require(match, 'Cannot verify FPM cache setting: ' + key)
        settings[key] = match.group(1)
    require(settings['opcache.enable'].lower() in ('off', '0') or
            settings['opcache.validate_timestamps'].lower() in ('on', '1'),
            'FPM does not revalidate changed PHP files; cache handling needs review')
    for key in ('opcache.revalidate_freq', 'opcache.file_update_protection'):
        require(settings[key].isdigit() and int(settings[key]) <= 60, 'Unexpected FPM cache delay')
    for config in Path('/etc/php/8.3/fpm/pool.d').glob('*.conf'):
        for line in config.read_text().splitlines():
            if line.lstrip().startswith(';'):
                continue
            require(not re.search(r'php_(?:admin_)?(?:value|flag)\[opcache\.', line),
                    'Pool-specific OPcache override needs review')
    return settings


def atomic_write(path, data, metadata=None):
    fd, temp = tempfile.mkstemp(prefix='.rr-menu-', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(data)
            if metadata:
                os.fchown(stream.fileno(), metadata['uid'], metadata['gid'])
                os.fchmod(stream.fileno(), metadata['mode'])
            stream.flush()
            os.fsync(stream.fileno())
        # Fresh mtime is intentional: FPM must notice the new PHP content.
        os.replace(temp, path)
        directory = os.open(path.parent, os.O_DIRECTORY)
        try:
            os.fsync(directory)
        finally:
            os.close(directory)
    finally:
        if os.path.exists(temp):
            os.unlink(temp)


def save_receipt(folder, receipt):
    atomic_write(folder / 'receipt.json', (json.dumps(receipt, indent=2) + '\n').encode())


def restore(root, folder, receipt):
    require(receipt['root'] == str(root) and receipt['before'] == BEFORE and receipt['after'] == AFTER,
            'Receipt is not this menu transaction')
    current = read_current(root)
    originals = {p: (folder / 'originals' / p).read_bytes() for p in BEFORE}
    require({p: digest(b) for p, b in originals.items()} == BEFORE, 'Backup hash mismatch')
    for p in BEFORE:
        require(digest(current[p]) in (BEFORE[p], AFTER[p]), 'Later edit blocks rollback: ' + p)
    for p in reversed(ORDER):
        path = checked_file(root, p)
        require(digest(path.read_bytes()) == digest(current[p]), 'Concurrent change blocks rollback: ' + p)
        if digest(current[p]) == AFTER[p]:
            atomic_write(path, originals[p], receipt['metadata'][p])
    require({p: digest(b) for p, b in read_current(root).items()} == BEFORE, 'Rollback verification failed')
    receipt['status'] = 'rolled_back'
    save_receipt(folder, receipt)


def install(root, parent, current, payload, before_services, after_check, lint=True):
    # Public caller supplies the fixed root; explicit paths make isolated tests possible.
    require({p: digest(b) for p, b in current.items()} == BEFORE, 'Wrong installation baseline')
    require({p: digest(b) for p, b in payload.items()} == AFTER, 'Wrong installation candidate')
    folder = Path(tempfile.mkdtemp(prefix='menu-20261104-', dir=parent))
    receipt = {'root': str(root), 'menuCommit': MENU_COMMIT, 'preservedConsoleCommit': STIKK_COMMIT,
               'before': BEFORE, 'after': AFTER, 'metadata': {}, 'servicesBefore': before_services,
               'status': 'prepared', 'startedAtUTC': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
               'browserAndAudioAcceptance': 'pending'}
    for p in BEFORE:
        source = checked_file(root, p)
        s = source.stat()
        receipt['metadata'][p] = {'uid': s.st_uid, 'gid': s.st_gid, 'mode': stat.S_IMODE(s.st_mode)}
        for sub, data in [('originals', current[p]), ('candidate', payload[p])]:
            target = folder / sub / p
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(data)
        if lint and p.endswith(('.php', '.mjs')):
            command = ['php', '-l'] if p.endswith('.php') else ['node', '--check']
            run(command + [str(folder / 'candidate' / p)])
    save_receipt(folder, receipt)
    print('BACKUP:', folder, flush=True)
    require(read_current(root) == current, 'Files changed during preparation; no runtime writes')
    try:
        receipt['status'] = 'installing'
        save_receipt(folder, receipt)
        for p in ORDER:
            target = checked_file(root, p)
            require(target.read_bytes() == current[p], 'Concurrent edit: ' + p)
            atomic_write(target, payload[p], receipt['metadata'][p])
        require(read_current(root) == payload, 'Post-install hash mismatch')
        receipt['servicesAfter'] = after_check()
        require(receipt['servicesAfter'] == before_services, 'Service state/PID changed during installation')
        receipt['status'] = 'installed_files_verified'
        receipt['finishedAtUTC'] = time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())
        save_receipt(folder, receipt)
    except BaseException:
        restore(root, folder, receipt)
        raise
    return folder, receipt


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--apply', action='store_true')
    group.add_argument('--rollback', type=Path)
    args = parser.parse_args()
    os.umask(0o077)
    cache = validate_environment()
    require(BACKUPS.is_dir() and BACKUPS.resolve() == BACKUPS, 'Missing verified private backup directory')
    s = BACKUPS.stat()
    require(s.st_uid == 0 and not s.st_mode & 0o022, 'Backup directory is not protected')
    fd = os.open(BACKUPS / 'menu-20261104.lock', os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        services = service_state()
        if args.rollback:
            folder = args.rollback.parent.resolve()
            require(folder.parent == BACKUPS and folder.name.startswith('menu-20261104-')
                    and args.rollback.name == 'receipt.json', 'Invalid rollback receipt')
            receipt = json.loads((folder / 'receipt.json').read_text())
            restore(ROOT, folder, receipt)
            require(service_state() == services, 'Service changed during rollback')
            print('ROLLED BACK:', folder)
            return
        current = read_current(ROOT)
        if {p: digest(b) for p, b in current.items()} == AFTER:
            print('ALREADY INSTALLED: six hashes match; browser/audio acceptance still required')
            return
        payload = build_payload(current)
        print('PREFLIGHT OK: six before/after hashes; PR117 retained; FPM cache:', cache)
        if not args.apply:
            print('READ ONLY: rerun with --apply for this six-file installation')
            return
        folder, receipt = install(ROOT, BACKUPS, current, payload, services, service_state)
        # A private copy keeps rollback available without another network download.
        shutil.copyfile(__file__, folder / 'installer.py')
        print(json.dumps(receipt, indent=2))
        print('ROLLBACK: sudo -n python3 ' + str(folder / 'installer.py') + ' --rollback ' + str(folder / 'receipt.json'))


if __name__ == '__main__':
    main()
