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
WEB_FILES = tuple(dict.fromkeys((*WEB_FILES, 'app/music-live-queue.php', 'app/music-play-queue.php', 'app/music-scheduling.php', 'app/jingles.php', 'app/views/navigation.php', 'public/music-library-audio.php', 'public/jingles.php', 'public/assets/jingles.css', 'public/assets/jingles.mjs')))
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
        "app/jingles.php": "0ad87e1365b6e5a632f57e50c0d2bbdcd8aec96b154e11b7fe083e211d83fb84",
        "app/music-autocue.php": "916ccc0e1164d972890deba19fa67b75395d69eb43d73e5c2fed7c121475652d",
        "app/music-library.php": "dc37fd5e471cada9f0eeb43e0370c55c3278b98b01104cb5c8d094af8ba0e689",
        "app/music-liquidsoap.php": "a073dc651035ffa3410ba28c2a18cfb9653548c5eda46864a8bd964b274c81c5",
        "app/music-play-queue.php": "6a7a6fa9b211428a6590ff4b7a1242260e5de0f30d4493cc23cdcb09d3e3de23",
        "app/music-playout.php": "63450e1fa9491b71c8f8535ce9dff757b86b638d3e65a499dd900d1134553817",
        "app/music-rotation.php": "3f9736dd50bcdc42e746f9e21715a9f04566eee3b98e4781d750b77cf007a135",
        "app/music-scheduling.php": "9611eebfc254856790cf57400db7802c851dc775bad9c8f78d23ba77525cb162",
        "app/music-studio.php": "b1f3949a07a10f4aac104b57c1f9abdb180b1e1f98c270579c988986152e5511",
        "app/views/navigation.php": "3f6da7444e8d69992edf26f26fe953d45f5033570c5ae796ecc150193f5982cd",
        "infra/studio-stream/radio.liq": "11097a564990acc6d7c9092f0c390475743d1b278c0d151c9766a1e99e2d43be",
        "infra/studio-stream/rr-studio-rotation.service": "aa5844e3ee3247a468ac7126afe8116fd89f0494856c06b0eb7aa3f46c49bff9",
        "infra/studio-stream/rr-studio-rotation.timer": "a8fe75229040f56d18b1d476c13a1d2bbf6447bacde362043bdf557bf5ded788",
        "public/assets/jingles.css": "9270651a664f085b02d61991870832af84268d2f2ffe1e6413d122fa68b8dbea",
        "public/assets/jingles.mjs": "cc1122212cc5a111011e1ad2afaa60210e218427305a80dfbfd512e170a54d92",
        "public/assets/music-studio.css": "a66d05df66bfcfa8771ef7b3d0496d0f405c494bcfff6ae7c3b7379e34e2d303",
        "public/assets/music-studio.mjs": "2398d046126b859cd9505104f89298007f6c94cd1677aa773cfbf55efc582cc9",
        "public/jingles.php": "308883106c69ef14d5561d6c341b6d72217cccb88b670ff4cdc7b343e158e6fb",
        "public/music-library-audio.php": "b77828707570cfc9f31554a055413ea42b0312f9c909e5f64ff1e99fea4d3b67",
        "public/music-stream-auth.php": "ed255570d06abd656947170a2a5d2bc25b5eb3e87fd35ffa00a2fb0ccf4ade46",
        "public/music-studio.php": "5adef6ff61eccbd451fd296e35d77e8da89e20c023729a35f302db751875e4cc",
        "scripts/liquidsoap-child.py": "f24cc612dd30e76c9014b4b405371231ea2cdd21f2ee7edcf62d4a964fd5f6a8",
        "scripts/music-autocue.py": "0519faf71ea28fdeab9455dd4d0c831d3d4c0316ced13ded89fb997c1fcddd0a",
        "scripts/music-liquidsoap.php": "1be1a4bd5a9c582a8a08c0c3ad5744fa54e055d289e7575d9a00adc8a11e8782",
        "scripts/music-rotation.php": "c5d9eaf546bb812cc824bf0c155ca65448905db208eca395ed482d34ac33b66f"
        ,"app/music-hourly.php": "f311e08d294350c98f8c9c828de374f967b9b39ebda25177cd96a9dd7246584b"
        ,"app/music-live-queue.php": "db98f5e1ee1ef65a158907d080d112425e2919a23c6b40e93401a17e32f76b4f"
        ,"app/music-hour-bridge.php": "176154e4ff38149219a5e35d52e44bf5692d98b14bcb283b9b8f5835cfc0738e"
        ,"app/music-hour-bridge-worker.php": "d0ca38d6feb6f502ca55ec3abecfddab0d517527974402140cb08ad799592f98"
        ,"scripts/music-hour-bridge.py": "3764ff2adf9b9ed1c8d833bee496e353d124328fa8ca38460f295c1d4e41ed47"
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
