#!/usr/bin/env python3
"""Prepare a private, loopback-only music pilot; does not start any service."""
import argparse
import hashlib
import json
import re
from pathlib import Path
import secrets
import shutil
import xml.etree.ElementTree as ET


def prepare(destination, tracks, port=18980):
    if not 1024 <= port <= 65535:
        raise ValueError('Use an unprivileged port between 1024 and 65535')
    sources = [Path(p).resolve(strict=True) for p in tracks]
    if len(sources) < 2:
        raise ValueError('Select at least two music files')
    for p in sources:
        if not p.is_file() or p.suffix.lower() not in {'.mp3', '.wav', '.flac', '.ogg', '.m4a'}:
            raise ValueError('Expected local audio files, not URLs or directories')
    root = Path(destination).absolute()
    if not re.fullmatch(r'/[A-Za-z0-9_./-]+', str(root.resolve())):
        raise ValueError('Use a destination path containing only letters, digits, /, _, . and -')
    # The destination must be new: never overwrite an active pilot or credentials.
    root.mkdir(mode=0o700, parents=False, exist_ok=False)
    root = root.resolve()
    (root / 'audio').mkdir(mode=0o700)
    entries = []
    for i, source in enumerate(sources):
        target = root / 'audio' / (str(i) + source.suffix.lower())
        shutil.copyfile(source, target)
        target.chmod(0o600)
        digest = hashlib.sha256(target.read_bytes()).hexdigest()
        entries.append({'file': str(target), 'sha256': digest})
    # No untrusted metadata, annotate protocols, or user-supplied Liquidsoap code.
    # All copied paths are JSON-quoted below, not interpolated as code.
    password = secrets.token_hex(32)
    script = '\n'.join([
        'settings.log.file.set(false)',
        'settings.log.stdout.set(true)',
        'settings.server.telnet.set(false)',
        'settings.server.socket.set(false)',
        'music = playlist.list(mode="normal", ' + json.dumps([x['file'] for x in entries]) + ')',
        'music = crossfade(duration=3., fade_in=3., fade_out=3., music)',
        'output.icecast(%mp3(bitrate=192, samplerate=44100, stereo=true),',
        '  host="127.0.0.1", port=' + str(port) + ', password=' + json.dumps(password) + ',',
        '  mount="/rubben-test.mp3", name="Radio Rubben PRIVATE TEST",',
        '  public=false, fallible=true, music)',
        '',
    ])
    (root / 'radio.liq').write_text(script)
    ice = ET.Element('icecast')
    def field(parent, name, value):
        ET.SubElement(parent, name).text = str(value)
    field(ice, 'hostname', 'localhost')
    field(ice, 'location', 'Private test')
    field(ice, 'admin', 'localhost')
    limits = ET.SubElement(ice, 'limits')
    field(limits, 'clients', 5)
    field(limits, 'sources', 1)
    field(limits, 'burst-size', 0)
    auth = ET.SubElement(ice, 'authentication')
    field(auth, 'source-password', password)
    field(auth, 'relay-password', secrets.token_hex(32))
    field(auth, 'admin-user', 'admin')
    field(auth, 'admin-password', secrets.token_hex(32))
    listen = ET.SubElement(ice, 'listen-socket')
    field(listen, 'port', port)
    field(listen, 'bind-address', '127.0.0.1')
    paths = ET.SubElement(ice, 'paths')
    field(paths, 'logdir', root)
    field(paths, 'webroot', root / 'empty-web')
    field(paths, 'adminroot', root / 'empty-web')
    (root / 'empty-web').mkdir(mode=0o700)
    logs = ET.SubElement(ice, 'logging')
    field(logs, 'accesslog', 'access.log')
    field(logs, 'errorlog', 'error.log')
    field(logs, 'loglevel', 2)
    field(logs, 'logsize', 1024)
    security = ET.SubElement(ice, 'security')
    field(security, 'chroot', 0)
    ET.ElementTree(ice).write(root / 'icecast.xml', encoding='unicode')
    (root / 'manifest.json').write_text(json.dumps({'purpose': 'private-music-pilot', 'tracks': entries}, indent=2))
    for p in root.iterdir():
        if p.is_file():
            p.chmod(0o600)
    return root


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--destination', required=True)
    parser.add_argument('--port', type=int, default=18980)
    parser.add_argument('tracks', nargs='+')
    args = parser.parse_args()
    result = prepare(args.destination, args.tracks, args.port)
    print('Prepared private pilot in ' + str(result))
    print('Not started. Loopback address: http://127.0.0.1:' + str(args.port) + '/rubben-test.mp3')
