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
             'public/assets/music-studio.mjs', 'public/assets/music-studio.css', 'public/music-studio.php',
             'public/music-stream-auth.php')
WEB_FILES = tuple(dict.fromkeys((*WEB_FILES, 'app/music-play-queue.php', 'app/music-scheduling.php', 'app/jingles.php', 'app/views/navigation.php', 'public/music-library-audio.php', 'public/jingles.php', 'public/assets/jingles.css', 'public/assets/jingles.mjs')))
PLAYER_FILES = ('app/music-playout.php', 'app/music-liquidsoap.php',
                'scripts/music-liquidsoap.php', 'scripts/liquidsoap-child.py',
                'infra/studio-stream/radio.liq')
# Populated from reviewed Git objects, not from a running server.
REVIEWED_HASHES = {
    "verified_vps_pr102_library": {"app/music-library.php": "a98f2bd03c07ee9ad8df98cf9f0a64e2cccc8c71a4b9494200ae853b6d32d0a1"},
    "l01_before_queue_reliability": {"public/assets/music-studio.mjs": "7a7aa0391aac098a4abe977edfdb60dcf2933c8ea001eaf97bb60adb6e19703b"},
    "l01_before_player_message": {"public/assets/music-studio.mjs": "a013cecdb174065527ba5cccb01b253286018bdab1a4d1741d8ff92d6acd72b1"},
    "installed_l01_ui_20261101": {
        "public/music-studio.php": "3551b2ef117e80f62d1fbc7bb4bbb83b71d746a87375d7338053477ff447ea88",
        "public/assets/music-studio.mjs": "18e29e380783a104302d779789061ff0ae29ef3651ee55c956aeb309c8a42524",
        "public/assets/music-studio.css": "43bab865910c87d3e2362a3f96b881744f89aec0b9deb678b24ebf80e530738e"
    },
    "candidate_l02": {
        "app/jingles.php": "ca1d8dac595d29cd4911b2f0c44a1d268f98ffd44d475114f855f47eaa118df2",
        "app/music-autocue.php": "916ccc0e1164d972890deba19fa67b75395d69eb43d73e5c2fed7c121475652d",
        "app/music-library.php": "dc37fd5e471cada9f0eeb43e0370c55c3278b98b01104cb5c8d094af8ba0e689",
        "app/music-liquidsoap.php": "5941245fdf4e523b6e10ecca10c56f3d04b3f3c0034d23ab7627b2f8bbfd7111",
        "app/music-play-queue.php": "6a7a6fa9b211428a6590ff4b7a1242260e5de0f30d4493cc23cdcb09d3e3de23",
        "app/music-playout.php": "46913f5ecad2da6830136422d3e91ca31dda77864ac8b5f843c351c1065da5f8",
        "app/music-rotation.php": "8b2b6194939ee659b7d34524827ff1c6c8e6b86344cddd800a8278e78daef947",
        "app/music-scheduling.php": "9611eebfc254856790cf57400db7802c851dc775bad9c8f78d23ba77525cb162",
        "app/music-studio.php": "a597e9968b66121152d0364e97136cdfc2117e3b37be7d8cb52bd9203551449d",
        "app/views/navigation.php": "4c1308ba30dcc6bd334d2818f4c554c599ab8814d2a404e9814fd2044438d36d",
        "infra/studio-stream/radio.liq": "f3b9351bb270ed51bad0eb9fe807da1cb073f3fb816b45ae76f6bb9ff1ba2054",
        "infra/studio-stream/rr-studio-rotation.service": "aa5844e3ee3247a468ac7126afe8116fd89f0494856c06b0eb7aa3f46c49bff9",
        "infra/studio-stream/rr-studio-rotation.timer": "a8fe75229040f56d18b1d476c13a1d2bbf6447bacde362043bdf557bf5ded788",
        "public/assets/jingles.css": "301ee1ebf0883c833fe93afffde11d795ea6c3c5f6d1ba9789123053f06006ae",
        "public/assets/jingles.mjs": "cc1122212cc5a111011e1ad2afaa60210e218427305a80dfbfd512e170a54d92",
        "public/assets/music-studio.css": "a7cdd298d9c684ddf9800f53f993b23788ca3b34d71cdfdac1afc7ca71fcacaf",
        "public/assets/music-studio.mjs": "1604bc906fd792fe08db5bcefd41f084faf9d6e6ba4b1d0ac256944ce5a2be57",
        "public/jingles.php": "7588f7bf42eaf02169532d6e618a3d6d9083ca03b6ff0de9ed543e5e6df029f2",
        "public/music-library-audio.php": "b77828707570cfc9f31554a055413ea42b0312f9c909e5f64ff1e99fea4d3b67",
        "public/music-stream-auth.php": "ed255570d06abd656947170a2a5d2bc25b5eb3e87fd35ffa00a2fb0ccf4ade46",
        "public/music-studio.php": "cf38b03b98f6fb2441ad44599cea7bd95eeb3a66fd5824c94f4ebdd0eab084fc",
        "scripts/liquidsoap-child.py": "f24cc612dd30e76c9014b4b405371231ea2cdd21f2ee7edcf62d4a964fd5f6a8",
        "scripts/music-autocue.py": "0519faf71ea28fdeab9455dd4d0c831d3d4c0316ced13ded89fb997c1fcddd0a",
        "scripts/music-liquidsoap.php": "6cd2cf31c0bf2ffaa17a70b30c6eaed38c416d39ed5c77ce2c37877fc0c8ea23",
        "scripts/music-rotation.php": "4ad195ecf96d8f284ca4059ad848812588668ef5045c74e10a49902bdd7dd259"
    },
    "candidate_l01": {
        "public/assets/music-studio.css": "a7cdd298d9c684ddf9800f53f993b23788ca3b34d71cdfdac1afc7ca71fcacaf",
        "app/music-library.php": "124ff696e0eb1e596be383a00362711214c4f2bda9db062dd4df6adaa9f5ff79",
        "app/music-liquidsoap.php": "9cebcc0201049906e9c6d77df2f469a5f2fe684de10f8346fab0bac6e7f649c5",
        "app/music-playout.php": "b96df4046c4e5130277f8a9587518b9f8f7d8f0d5afa671f203d1a7c51006e3e",
        "app/music-studio.php": "7f6d9f8814c5c9c5cc950fa7cb0f9f79df668009c5e81c1a3cee32201203c732",
        "infra/studio-stream/radio.liq": "ad5fc0f904bac0980b29d4b8118d39b7def12c3d8e7878fec44f22ee17ecd5b7",
        "public/assets/music-studio.mjs": "80826c8d3bf536b339cff81b44f6ced758b7c6b9e8d751de56d905e17909e954",
        "public/music-stream-auth.php": "ed255570d06abd656947170a2a5d2bc25b5eb3e87fd35ffa00a2fb0ccf4ade46",
        "public/music-studio.php": "3657c81d1ac5c65a6c23c60616bbd3cf3e4596967e13a5b9263849692ca2e201",
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
        'candidate_ui_revision': '2026-11-01-L02-jingles',
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
