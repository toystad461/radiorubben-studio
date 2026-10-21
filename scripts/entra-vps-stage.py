#!/usr/bin/env python3
"""Stage, but never activate, the inspected Radio Rubben test VPS configuration.

No secrets are read. Run as root on the test VPS. Files are staged under /root;
package installation, firewall enforcement tests and activation are separate.
"""
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile

APP = Path('/etc/nginx/rr-test-apps.conf')
POOL = Path('/etc/php/8.3/fpm/pool.d/rr-test.conf')
TLS = Path('/etc/nginx/conf.d/rr-test-tls.conf')


def candidates(app, pool, tls):
    """Fail closed on unexpected routing/pool structure; preserve WP verbatim."""
    login = '    location = /login.php { return 302 /local/login.php; }'
    deny = '    location ^~ /auth/ { return 403; }'
    if app.count(login) != 1 or app.count(deny) != 1:
        raise ValueError('Unexpected login/auth routing')
    boundary = '\nserver {\n    listen 127.0.0.1:8081;'
    if app.count(boundary) != 1:
        raise ValueError('Unexpected WordPress boundary')
    studio, wordpress = app.split(boundary)
    for marker in ('listen 127.0.0.1:8080;', 'fastcgi_param HTTPS on;',
                   'fastcgi_param SERVER_PORT 443;', 'unix:/run/php/rr-studio.sock;'):
        if marker not in studio:
            raise ValueError('Missing known Studio setting: ' + marker)
    if 'proxy_pass http://127.0.0.1:8080;' not in tls:
        raise ValueError('Unexpected TLS upstream')
    blocks = []
    for endpoint in ('start', 'callback'):
        blocks.append('''    location = /auth/%s.php {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
        fastcgi_param SERVER_PORT 443;
        fastcgi_pass unix:/run/rr-entra/entra.sock;
    }''' % endpoint)
    studio = studio.replace(login + '\n', '')
    studio = studio.replace(deny, '\n'.join(blocks) + '\n' + deny)
    studio = studio.replace('Eksterne tjenester er frakoblet.',
                            'Kun Microsoft-innlogging har ekstern tilgang.')
    new_app = studio + boundary + wordpress
    sections = re.split(r'(?m)^\[', pool)
    matches = [part for part in sections if part.startswith('rr-studio]\n')]
    if len(matches) != 1:
        raise ValueError('Expected one rr-studio pool')
    auth_pool = '[' + matches[0]
    if re.search(r'(?m)^\s*(env\[|include\s*=)', auth_pool):
        raise ValueError('Review pool environment/includes before staging')
    for key, expected in [('user', 'rr-studio'), ('group', 'rr-studio'),
                          ('listen', '/run/php/rr-studio.sock')]:
        if not re.search(r'(?m)^' + key + r'\s*=\s*' + re.escape(expected) + r'\s*$', auth_pool):
            raise ValueError('Unexpected pool identity/socket')
    if 'php_admin_value[session.save_path] = /srv/radiorubben-test/studio-sessions' not in auth_pool:
        raise ValueError('Unexpected session storage')
    auth_pool = auth_pool.replace('[rr-studio]', '[rr-entra]')
    auth_pool = auth_pool.replace('listen = /run/php/rr-studio.sock', 'listen = /run/rr-entra/entra.sock')
    auth_pool += '\nenv[https_proxy] = http://127.0.0.1:3139\nenv[HTTPS_PROXY] = http://127.0.0.1:3139\n'
    fpm = '[global]\npid = /run/rr-entra/php-fpm.pid\nerror_log = /proc/self/fd/2\ndaemonize = no\n\n' + auth_pool
    return new_app, fpm


SQUID = '''# Only TLS tunnels to the Microsoft identity host. No TLS interception.
http_port 127.0.0.1:3139
visible_hostname rr-entra-proxy
acl local_client src 127.0.0.1/32
acl CONNECT method CONNECT
acl tls_port port 443
acl identity dstdomain login.microsoftonline.com
acl exact_target url_regex -i ^login\\.microsoftonline\\.com:443$
http_access allow local_client CONNECT tls_port identity exact_target
http_access deny all
cache deny all
cache_mem 8 MB
access_log none
cache_log /dev/stderr
cache_store_log none
logfile_rotate 0
pid_filename /run/rr-entra-proxy/squid.pid
pinger_enable off
shutdown_lifetime 1 seconds
'''

PROXY_UNIT = '''[Unit]
Description=Radio Rubben Entra-only TLS proxy
After=network-online.target
Wants=network-online.target
[Service]
Type=simple
User=proxy
Group=proxy
RuntimeDirectory=rr-entra-proxy
ExecStart=/usr/sbin/squid -N -f /etc/rr-entra/squid.conf
Restart=on-failure
NoNewPrivileges=yes
PrivateTmp=yes
ProtectSystem=full
ProtectHome=yes
RestrictAddressFamilies=AF_UNIX AF_INET AF_INET6
[Install]
WantedBy=multi-user.target
'''

FPM_UNIT = '''[Unit]
Description=Radio Rubben isolated Entra PHP-FPM
After=rr-entra-proxy.service
Requires=rr-entra-proxy.service
[Service]
Type=simple
RuntimeDirectory=rr-entra
RuntimeDirectoryMode=0755
ExecStart=/usr/sbin/php-fpm8.3 --nodaemonize --fpm-config /etc/rr-entra/php-fpm.conf
Restart=on-failure
NoNewPrivileges=yes
PrivateTmp=yes
ProtectSystem=full
ProtectHome=yes
RestrictAddressFamilies=AF_UNIX AF_INET
IPAddressDeny=any
IPAddressAllow=127.0.0.1/32
[Install]
WantedBy=multi-user.target
'''


def main():
    if os.geteuid() != 0:
        raise SystemExit('Run with sudo on the test VPS.')
    if not Path('/srv/radiorubben-test/studio/public/auth/callback.php').is_file():
        raise SystemExit('Expected test VPS application not found.')
    for unit in ('rr-entra.service', 'rr-entra-proxy.service'):
        if Path('/etc/systemd/system', unit).exists():
            raise SystemExit('Existing Entra unit: review before proceeding.')
    for endpoint in ('start', 'callback'):
        if not Path('/srv/radiorubben-test/studio/public/auth', endpoint + '.php').is_file():
            raise SystemExit('Missing authentication endpoint.')
    originals = {str(p): p.read_text() for p in (APP, POOL, TLS)}
    nginx, fpm = candidates(originals[str(APP)], originals[str(POOL)], originals[str(TLS)])
    stage = Path(tempfile.mkdtemp(prefix='rr-entra-stage-', dir='/root'))
    files = {'rr-test-apps.conf': nginx, 'php-fpm.conf': fpm,
             'squid.conf': SQUID, 'rr-entra.service': FPM_UNIT,
             'rr-entra-proxy.service': PROXY_UNIT}
    for name, content in files.items():
        (stage / name).write_text(content)
    for path, content in originals.items():
        (stage / ('before-' + Path(path).name)).write_text(content)
    manifest = {
        'created_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
        'status': 'STAGED_ONLY_NOT_ACTIVATED',
        'source_sha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),
        'before_sha256': {p: hashlib.sha256(c.encode()).hexdigest() for p, c in originals.items()},
        'candidate_sha256': {p: hashlib.sha256(c.encode()).hexdigest() for p, c in files.items()},
    }
    (stage / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
    print('STAGED ONLY:', stage)
    print('No services, active configuration, credentials or packages changed.')
    print('WordPress block preserved; shared FPM pool unchanged.')
    print('Squid binary:', shutil.which('squid') or 'NOT INSTALLED')
    print('PHP-FPM binary:', shutil.which('php-fpm8.3') or 'NOT FOUND')
    print('Next: package readiness, syntax checks, actual egress-denial test, then activation.')


if __name__ == '__main__':
    try:
        main()
    except (OSError, ValueError) as error:
        raise SystemExit('STOP: ' + str(error))
