#!/usr/bin/env python3
"""Read-only L01 inventory for the existing isolated VPS; never installs or starts anything.

Run with the existing administrator account: sudo -n python3 -B preflight.py.
Output excludes credentials, raw configuration, track names and journal records.
Missing/unknown data requires review; this inventory cannot certify audible output.
"""
import datetime
import hashlib
import json
import math
import os
from pathlib import Path
import re
import socket
import stat
import subprocess
import sys
import time

ROOT = Path('/srv/radiorubben-test')
AUDIO_RUNTIME_SOURCE = 'a34d50bc1e38dee4567bcdfb5ebdd9abbdd59e0d'
SERVICES = ('nginx', 'php8.3-fpm', 'rr-entra', 'rr-music-playout',
            'rr-studio-stream', 'rr-studio-icecast')
PROPERTIES = ('LoadState', 'ActiveState', 'SubState', 'PrivateNetwork',
              'RestrictAddressFamilies', 'NoNewPrivileges', 'PrivateTmp')
PACKAGES = ('php8.3-cli', 'ffmpeg', 'liquidsoap', 'icecast2', 'nginx')
WEB_FILES = ('app/music-library.php', 'app/music-playout.php',
             'app/music-liquidsoap.php', 'app/music-studio.php',
             'public/assets/music-studio.mjs', 'public/music-studio.php',
             'public/music-stream-auth.php')
PLAYER_FILES = ('app/music-playout.php', 'app/music-liquidsoap.php',
                'scripts/music-liquidsoap.php', 'scripts/liquidsoap-child.py',
                'infra/studio-stream/radio.liq')
# Populated from reviewed Git objects, not from a running server.
REVIEWED_HASHES = {
    "l01_before_player_message": {"public/assets/music-studio.mjs": "a013cecdb174065527ba5cccb01b253286018bdab1a4d1741d8ff92d6acd72b1"},
    "candidate_l01": {
        "app/music-library.php": "124ff696e0eb1e596be383a00362711214c4f2bda9db062dd4df6adaa9f5ff79",
        "app/music-liquidsoap.php": "9cebcc0201049906e9c6d77df2f469a5f2fe684de10f8346fab0bac6e7f649c5",
        "app/music-playout.php": "b96df4046c4e5130277f8a9587518b9f8f7d8f0d5afa671f203d1a7c51006e3e",
        "app/music-studio.php": "7f6d9f8814c5c9c5cc950fa7cb0f9f79df668009c5e81c1a3cee32201203c732",
        "infra/studio-stream/radio.liq": "ad5fc0f904bac0980b29d4b8118d39b7def12c3d8e7878fec44f22ee17ecd5b7",
        "public/assets/music-studio.mjs": "7a7aa0391aac098a4abe977edfdb60dcf2933c8ea001eaf97bb60adb6e19703b",
        "public/music-stream-auth.php": "ed255570d06abd656947170a2a5d2bc25b5eb3e87fd35ffa00a2fb0ccf4ade46",
        "public/music-studio.php": "3551b2ef117e80f62d1fbc7bb4bbb83b71d746a87375d7338053477ff447ea88",
        "scripts/liquidsoap-child.py": "f24cc612dd30e76c9014b4b405371231ea2cdd21f2ee7edcf62d4a964fd5f6a8",
        "scripts/music-liquidsoap.php": "52a9fd6f2e46854f53e5447b2abaa6c7cec29e8893707f9800795494c0028ae1"
    },
    "last_reported_vps_release": {
        "app/music-library.php": "50cb886e43be0d745cd25e29587262e3f9fd0b3e467b7a4e9bdee6848c3aefe2",
        "app/music-playout.php": "b92a1dc3c7e393f12462cd0cbbcd7bd3341ee851cd475b27ffdd57c52c45552b",
        "app/music-studio.php": "50b2cf650bf6d12b03885d7964499cfe7e326feb34bb1adc599fdb089b4c399e",
        "public/assets/music-studio.mjs": "1b8dd0cb1a7983fff6fdeae2797d6a7fd4df5a138d513ac6ce66c6f17e867e14",
        "public/music-studio.php": "99e77bf3808f87df101f9d11d96f203a8e3286169f876c98df527a0b8241129d"
    },
    "pr99_base": {
        "app/music-library.php": "50cb886e43be0d745cd25e29587262e3f9fd0b3e467b7a4e9bdee6848c3aefe2",
        "app/music-playout.php": "b92a1dc3c7e393f12462cd0cbbcd7bd3341ee851cd475b27ffdd57c52c45552b",
        "app/music-studio.php": "50b2cf650bf6d12b03885d7964499cfe7e326feb34bb1adc599fdb089b4c399e",
        "public/assets/music-studio.mjs": "1b8dd0cb1a7983fff6fdeae2797d6a7fd4df5a138d513ac6ce66c6f17e867e14",
        "public/music-studio.php": "99e77bf3808f87df101f9d11d96f203a8e3286169f876c98df527a0b8241129d"
    }
}


class ProbeError(Exception):
    pass


def read_regular(path, limit):
    """No symlinks (including parent directories), devices, FIFO or unbounded reads."""
    path = Path(path)
    for parent in (path, *path.parents):
        if parent.is_symlink():
            raise ProbeError('symlink')
    fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK)
    try:
        before = os.fstat(fd)
        if not stat.S_ISREG(before.st_mode) or before.st_size > limit:
            raise ProbeError('not_regular_or_too_large')
        with os.fdopen(fd, 'rb', closefd=False) as handle:
            content = handle.read(limit + 1)
        after = os.fstat(fd)
        if len(content) > limit:
            raise ProbeError('too_large')
        if (before.st_size, before.st_mtime_ns) != (after.st_size, after.st_mtime_ns):
            raise ProbeError('changed_during_read')
        return content
    finally:
        os.close(fd)


def safe_probe(callback):
    try:
        return callback()
    except FileNotFoundError:
        return {'status': 'missing'}
    except PermissionError:
        return {'status': 'unreadable'}
    except (OSError, ProbeError, ValueError, TypeError, KeyError):
        return {'status': 'unverified'}


def command(args):
    # All callers use fixed, read-only commands. Never print raw stdout/stderr.
    result = subprocess.run(args, capture_output=True, text=True, timeout=8,
                            env={'PATH': '/usr/sbin:/usr/bin:/sbin:/bin', 'LC_ALL': 'C'})
    if len(result.stdout) > 65536:
        raise ProbeError('oversized_command_output')
    return result


def service_status(unit):
    try:
        result = command(['systemctl', 'show', unit + '.service',
                          '--property=' + ','.join(PROPERTIES)])
        values = dict(line.split('=', 1) for line in result.stdout.splitlines() if '=' in line)
        if values.get('LoadState') == 'not-found':
            return {'status': 'not-found'}
        if result.returncode or not all(key in values for key in PROPERTIES):
            return {'status': 'unverified'}
        filtered = {key: value for key, value in values.items()
                    if key in PROPERTIES and re.fullmatch(r'[A-Za-z0-9_ -]{0,100}', value)}
        if len(filtered) != len(PROPERTIES):
            return {'status': 'unverified'}
        return {'status': 'observed', **filtered}
    except (OSError, subprocess.TimeoutExpired, ProbeError):
        return {'status': 'unverified'}


def package_version(name):
    try:
        result = command(['dpkg-query', '-W', '-f=${db:Status-Status}\t${Version}', name])
        match = re.fullmatch(r'installed\t([A-Za-z0-9.+:~_-]{1,100})', result.stdout.strip())
        return {'status': 'installed', 'version': match[1]} if not result.returncode and match else {'status': 'not_confirmed'}
    except (OSError, subprocess.TimeoutExpired, ProbeError):
        return {'status': 'unverified'}


def code_hash(path, relative):
    digest = hashlib.sha256(read_regular(path, 4 * 1024 * 1024)).hexdigest()
    matches = [label for label, files in REVIEWED_HASHES.items() if files.get(relative) == digest]
    return {'sha256': digest, 'matches': matches or ['unreconciled']}


def json_summary(path, kind):
    data = json.loads(read_regular(path, 16 * 1024 * 1024))
    if not isinstance(data, dict):
        raise ProbeError('not_object')
    if kind == 'heartbeat':
        stamp = data.get('timestamp')
        if type(stamp) not in (int, float) or not math.isfinite(stamp):
            raise ProbeError('invalid_timestamp')
        engine = data.get('engine')
        return {'status': 'observed', 'age_seconds': round(time.time() - stamp, 1),
                'engine': engine if engine in ('liquidsoap', 'ffmpeg') else 'not_identified',
                'connected_reported': data.get('connected') is True}
    key = 'tracks' if kind == 'library' else 'entries'
    entries = data.get(key)
    if not isinstance(entries, list):
        raise ProbeError('invalid_entries')
    report = {'status': 'observed', 'entries': len(entries)}
    if type(data.get('revision')) is int:
        report['revision'] = data['revision']
    if kind == 'manifest':
        report['ready_reported'] = data.get('ready') is True
    return report


def validate_environment(root=ROOT):
    if socket.gethostname() != 'vps-5363103':
        raise ProbeError('wrong_host')
    if os.geteuid() != 0:
        raise ProbeError('existing_administrator_required')
    release = root / 'studio'
    if not release.is_symlink():
        raise ProbeError('missing_release_link')
    resolved = release.resolve(strict=True)
    releases = root / 'releases'
    if root.is_symlink() or releases.is_symlink() or resolved.parent != releases:
        raise ProbeError('unexpected_release_location')
    if not resolved.is_dir() or not re.fullmatch(r'[A-Za-z0-9._-]{1,160}', resolved.name):
        raise ProbeError('invalid_release')
    return resolved


def collect(root=ROOT):
    release = validate_environment(root)
    report = {
        'status': 'inventory_only_not_installation_or_audio_pass',
        'environment': 'Studio VPS - test',
        'observed_at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
        'reviewed_audio_runtime_source': AUDIO_RUNTIME_SOURCE,
        'candidate_ui_revision': '2026-10-25.1',
        'snapshot_atomic': False,
        'active_release': release.name,
        'services': {name: service_status(name) for name in SERVICES},
        'packages': {name: package_version(name) for name in PACKAGES},
        'web_code': {p: safe_probe(lambda p=p: code_hash(release / p, p)) for p in WEB_FILES},
        'player_code': {p: safe_probe(lambda p=p: code_hash(root / 'player-code' / p, p)) for p in PLAYER_FILES},
    }
    report['data'] = {
        'queue': safe_probe(lambda: json_summary(root / 'studio-data/music-play-queue.private.json', 'queue')),
        'manifest': safe_probe(lambda: json_summary(root / 'playout/control.json', 'manifest')),
        'heartbeat': safe_probe(lambda: json_summary(root / 'playout/heartbeat.json', 'heartbeat')),
    }
    # Do not read local.php, music-playout.local.php, engine.json, icecast.xml,
    # sessions, SSH files, Nginx dumps, raw journal or original music files.
    report['requires_followup'] = ['reconcile_exact_release_and_configuration',
                                   'verify_authenticated_monitor_and_tls',
                                   'verify_three_tracks_audio_restart_and_rollback']
    return report


def main():
    try:
        report = collect()
    except ProbeError as error:
        print(json.dumps({'status': 'refused', 'reason': str(error)}))
        return 1
    except (OSError, ValueError):
        print(json.dumps({'status': 'refused', 'reason': 'environment_unverified'}))
        return 1
    print(json.dumps(report, indent=2, ensure_ascii=True))
    return 0


if __name__ == '__main__':
    sys.exit(main())
