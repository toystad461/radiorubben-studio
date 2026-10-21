#!/usr/bin/env python3
"""One-time, fail-closed installer for the Radio Rubben test VPS only.

Runs synthetic tests against active app code, verifies enforced IP filtering,
then API smoke and at most one library assessment before enabling the timer.
Never changes web-FPM, Entra, WordPress, keys or global Studio configuration.
"""
import hashlib
import json
import os
from pathlib import Path
import pwd
import shutil
import socket
import subprocess
import sys
import tempfile

APP = Path('/srv/radiorubben-test/studio')
MEDIA = Path('/srv/radiorubben-test/studio-media')
DEST = Path('/etc/rr-music-ai')
UNITS = Path('/etc/systemd/system')
NAMES = ('rr-music-ai-proxy.service', 'rr-music-ai.service', 'rr-music-ai.timer')
SOURCE = {
    'scripts/music-ai-worker.php': 'a2b2d314b5efce409f023b8973b42b9f130bbccf',
    'tests/recovered/test-music-ai-worker.php': '37fad97ab690c90633683219aa7912dff133cc9a',
}
PROPERTIES = [
    'User=rr-studio', 'Group=rr-studio', 'NoNewPrivileges=yes',
    'PrivateTmp=yes', 'ProtectSystem=strict', 'ProtectHome=yes',
    'ReadWritePaths=' + str(MEDIA), 'RestrictAddressFamilies=AF_UNIX AF_INET',
    'IPAddressDeny=any', 'IPAddressAllow=127.0.0.1/32', 'UMask=0077',
]
ENVIRONMENT = [
    'https_proxy=http://127.0.0.1:3140', 'HTTPS_PROXY=http://127.0.0.1:3140',
    'NO_PROXY=', 'no_proxy=', 'RR_MUSIC_AI_MODEL=gpt-4.1-mini',
]
SQUID = r'''http_port 127.0.0.1:3140
visible_hostname rr-music-ai-proxy
acl local_client src 127.0.0.1/32
acl CONNECT method CONNECT
acl tls_port port 443
acl openai dstdomain api.openai.com
acl exact_target url_regex -i ^api\.openai\.com:443$
http_access allow local_client CONNECT tls_port openai exact_target
http_access deny all
cache deny all
cache_mem 8 MB
access_log none
cache_log /dev/stderr
cache_store_log none
logfile_rotate 0
pid_filename /run/rr-music-ai-proxy/squid.pid
pinger_enable off
shutdown_lifetime 1 seconds
'''
PROXY = '''[Unit]
Description=Radio Rubben music AI OpenAI-only TLS proxy
After=network-online.target
Wants=network-online.target
[Service]
Type=simple
User=proxy
Group=proxy
RuntimeDirectory=rr-music-ai-proxy
ExecStart=/usr/sbin/squid -N -f /etc/rr-music-ai/squid.conf
Restart=on-failure
NoNewPrivileges=yes
PrivateTmp=yes
ProtectSystem=full
ProtectHome=yes
RestrictAddressFamilies=AF_UNIX AF_INET AF_INET6
[Install]
WantedBy=multi-user.target
'''
WORKER = '''[Unit]
Description=Radio Rubben one-track music AI assessment
After=rr-music-ai-proxy.service
Requires=rr-music-ai-proxy.service
[Service]
Type=oneshot
TimeoutStartSec=90
''' + '\n'.join(PROPERTIES) + '\n' + '\n'.join('Environment="' + e + '"' for e in ENVIRONMENT) + '''
ExecStart=/usr/bin/php8.3 /etc/rr-music-ai/worker.php --once --app-root=/srv/radiorubben-test/studio
'''
TIMER = '''[Unit]
Description=Radio Rubben periodic music AI queue
[Timer]
OnActiveSec=60s
OnUnitInactiveSec=60s
AccuracySec=5s
Unit=rr-music-ai.service
[Install]
WantedBy=timers.target
'''

def run(args, **kwargs):
    return subprocess.run(args, check=True, timeout=kwargs.pop('timeout', 120), **kwargs)

def transient(command, capture=False):
    args = ['systemd-run', '--quiet', '--wait', '--pipe', '--collect']
    args += ['--property=' + p for p in PROPERTIES]
    args += ['--setenv=' + e for e in ENVIRONMENT]
    return run(args + command, capture_output=capture, text=True)

def network_check():
    # Prove denial using a known-live second listener, not a remote timeout.
    listeners = []
    try:
        for host in ('127.0.0.1', '127.0.0.2'):
            s = socket.socket(); listeners.append(s)
            s.bind((host, 0)); s.listen(4)
            with socket.create_connection(s.getsockname(), timeout=2):
                pass
        allowed, blocked = [s.getsockname()[1] for s in listeners]
        probe = f'''import socket, errno
with socket.create_connection(('127.0.0.1', {allowed}), timeout=3): pass
try:
    s = socket.create_connection(('127.0.0.2', {blocked}), timeout=3)
except OSError as e:
    if not isinstance(e, TimeoutError) and e.errno not in (errno.EACCES, errno.EPERM): raise
else:
    s.close()
    raise RuntimeError('IP filtering is not enforced')
print('PASS: IP filtering enforced')
'''
        transient(['/usr/bin/python3', '-c', probe])
    finally:
        for s in listeners: s.close()

def main():
    if os.geteuid() != 0 or sys.argv[1:] != ['--install']:
        raise RuntimeError('Run with sudo: music-ai-vps-install.py --install')
    for p in [DEST] + [UNITS / n for n in NAMES]:
        if os.path.lexists(p):
            raise RuntimeError('Existing setup; inspect before retry: ' + str(p))
    for binary in ('/usr/bin/php8.3', '/usr/sbin/squid'):
        if not Path(binary).is_file(): raise RuntimeError('Required binary missing: ' + binary)
    for user in ('rr-studio', 'proxy'): pwd.getpwnam(user)
    if not APP.is_dir() or MEDIA.is_symlink() or not MEDIA.is_dir():
        raise RuntimeError('Expected test Studio/media unavailable')
    # Fail if an unrelated service already owns the selected listener.
    with socket.socket() as s: s.bind(('127.0.0.1', 3140))
    source = Path(__file__).resolve().parents[1]
    for name, expected in SOURCE.items():
        data = (source / name).read_bytes()
        actual = hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()
        if actual != expected: raise RuntimeError('Unexpected source: ' + name)
    with tempfile.TemporaryDirectory(prefix='rr-ai-check-') as tmp:
        root = Path(tmp); root.chmod(0o755)
        shutil.copytree(APP / 'app', root / 'app')
        (root / 'vendor').symlink_to(APP / 'vendor')
        for name in SOURCE:
            target = root / name; target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(source / name, target); target.chmod(0o644)
        run(['/usr/bin/php8.3', '-l', str(root / 'scripts/music-ai-worker.php')])
        run(['sudo', '-u', 'rr-studio', '/usr/bin/php8.3', str(root / 'tests/recovered/test-music-ai-worker.php')])
        (root / 'squid.conf').write_text(SQUID)
        run(['/usr/sbin/squid', '-k', 'parse', '-f', str(root / 'squid.conf')])
        for name, data in zip(NAMES, (PROXY, WORKER, TIMER)):
            (root / name).write_text(data)
        run(['systemd-analyze', 'verify'] + [str(root / n) for n in NAMES])
        network_check()
        installed = []; created_dest = False
        try:
            DEST.mkdir(mode=0o755); created_dest = True
            shutil.copyfile(source / 'scripts/music-ai-worker.php', DEST / 'worker.php')
            (DEST / 'squid.conf').write_text(SQUID)
            for p in DEST.iterdir(): p.chmod(0o644)
            for name in NAMES:
                # Exclusive creation; never overwrite another installation.
                target = UNITS / name
                with target.open('x') as f: f.write((root / name).read_text())
                installed.append(target); target.chmod(0o644)
            run(['systemctl', 'daemon-reload'])
            run(['systemctl', 'start', NAMES[0]])
            # Wait for proxy readiness with bounded connection retries, no key.
            run(['/usr/bin/curl', '--silent', '--show-error', '--retry', '5',
                 '--retry-connrefused', '--retry-delay', '1', '--max-time', '10',
                 '--proxy', 'http://127.0.0.1:3140', '--noproxy', '',
                 '--output', '/dev/null', 'https://api.openai.com/v1/models'])
            denial = subprocess.run(
                ['/usr/bin/curl', '--silent', '--max-time', '10', '--proxy',
                 'http://127.0.0.1:3140', '--noproxy', '', '--output', '/dev/null',
                 '--write-out', '%{http_connect}', 'https://example.com/'],
                capture_output=True, text=True, timeout=15)
            if denial.stdout != '403': raise RuntimeError('Proxy denial test failed')
            print('PASS: proxy blocks other hosts', flush=True)
            command = ['/usr/bin/php8.3', str(DEST / 'worker.php')]
            for mode in ('--check', '--smoke'):
                transient(command + [mode, '--app-root=' + str(APP)])
            result = transient(command + ['--once', '--app-root=' + str(APP)], capture=True)
            outcome = json.loads(result.stdout)
            print('First assessment:', outcome['status'], flush=True)
            if outcome['status'] not in ('prepared', 'idle', 'empty'):
                raise RuntimeError('First assessment not ready; timer stays disabled')
            run(['systemctl', 'enable', '--now', NAMES[0], NAMES[2]])
            run(['systemctl', 'is-active', NAMES[0], NAMES[2]])
            print('OK: AI timer active, at most one track per minute. Manual fields preserved.')
            print('Stop processing: sudo systemctl disable --now rr-music-ai.timer; sudo systemctl stop rr-music-ai.service')
        except BaseException:
            # Only newly installed services/files; never restore library data.
            for name in NAMES:
                subprocess.run(['systemctl', 'disable', '--now', name], capture_output=True)
            for p in installed: p.unlink(missing_ok=True)
            if created_dest: shutil.rmtree(DEST)
            subprocess.run(['systemctl', 'daemon-reload'])
            print('STOP: new services removed. Existing Studio and library data preserved.', file=sys.stderr)
            raise

if __name__ == '__main__':
    try: main()
    except Exception as error:
        print('STOP:', str(error), file=sys.stderr)
        sys.exit(1)
