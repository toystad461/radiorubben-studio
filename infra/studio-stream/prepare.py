#!/usr/bin/env python3
"""Prepare new PRIVATE loopback engine/Icecast configuration; start nothing.

Icecast settings reuse the reviewed pilot in PR #108 (f765697); the music
queue is supplied by Studio's existing manifest, never by playlist.list.
"""
import argparse
import json
from pathlib import Path
import re
import secrets
import xml.etree.ElementTree as ET


def prepare(destination, port=18980):
    if not isinstance(port, int) or not 1024 <= port <= 65535:
        raise ValueError('Use an unprivileged loopback port')
    root = Path(destination).absolute()
    if not re.fullmatch(r'/[A-Za-z0-9_./-]+', str(root.resolve())):
        raise ValueError('Use a simple absolute private runtime path')
    root.mkdir(mode=0o700, parents=False, exist_ok=False)
    root = root.resolve()
    for directory in ('logs', 'empty-web', 'engine'):
        (root / directory).mkdir(mode=0o700)
    password = secrets.token_hex(32)
    config = {'host': '127.0.0.1', 'port': port, 'mount': '/rubben-test.mp3', 'password': password}
    (root / 'engine.json').write_text(json.dumps(config, indent=2) + '\n')
    ice = ET.Element('icecast')
    def field(parent, key, value):
        ET.SubElement(parent, key).text = str(value)
    field(ice, 'hostname', 'localhost')
    field(ice, 'location', 'Private Radio Rubben test')
    field(ice, 'admin', 'local-test@example.invalid')
    limits = ET.SubElement(ice, 'limits')
    for key, value in {'clients': 5, 'sources': 1, 'burst-size': 0, 'queue-size': 524288,
                       'client-timeout': 15, 'header-timeout': 10, 'source-timeout': 10}.items():
        field(limits, key, value)
    auth = ET.SubElement(ice, 'authentication')
    for key, value in {'source-password': password, 'relay-password': secrets.token_hex(32),
                       'admin-user': 'admin', 'admin-password': secrets.token_hex(32)}.items():
        field(auth, key, value)
    listen = ET.SubElement(ice, 'listen-socket')
    field(listen, 'port', port)
    field(listen, 'bind-address', '127.0.0.1')
    paths = ET.SubElement(ice, 'paths')
    field(paths, 'logdir', root / 'logs')
    field(paths, 'webroot', root / 'empty-web')
    field(paths, 'adminroot', root / 'empty-web')
    logs = ET.SubElement(ice, 'logging')
    for key, value in {'accesslog': 'access.log', 'errorlog': 'error.log', 'loglevel': 2, 'logsize': 1024}.items():
        field(logs, key, value)
    security = ET.SubElement(ice, 'security')
    field(security, 'chroot', 0)
    # Run as a dedicated non-root service user. No global service or firewall
    # settings are installed/changed by this configuration generator.
    ET.ElementTree(ice).write(root / 'icecast.xml', encoding='unicode')
    for path in root.iterdir():
        if path.is_file():
            path.chmod(0o600)
    return root


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--destination', required=True)
    parser.add_argument('--port', type=int, default=18980)
    args = parser.parse_args()
    print('Prepared private configuration, no services started: ' + str(prepare(args.destination, args.port)))
