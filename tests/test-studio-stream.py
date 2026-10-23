#!/usr/bin/env python3
"""Real Studio HTTP queue -> PHP supervisor -> Liquidsoap -> loopback Icecast.

Only synthetic MP3s and a temporary copy of app/public are used. No VPS,
existing media, service manager, firewall or public listener is touched.
Requires native PHP with pcntl, Liquidsoap 2.2.4, Icecast and FFmpeg on Linux.
"""
from array import array
from concurrent.futures import ThreadPoolExecutor
import fcntl
import hashlib
import importlib.util
import json
import math
import os
from pathlib import Path
import shutil
import signal
import socket
import statistics
import subprocess
import sys
import tempfile
import threading
import time
import urllib.error
import urllib.request
import xml.etree.ElementTree as ET


REPO = Path(__file__).resolve().parents[1]
OPENER = urllib.request.build_opener(urllib.request.ProxyHandler({}))


def executable(variable, fallback):
    candidate = os.environ.get(variable) or shutil.which(fallback)
    if not candidate or not os.path.isfile(candidate) or not os.access(candidate, os.X_OK):
        raise RuntimeError(f"Missing native executable: {variable} / {fallback}")
    return str(Path(candidate).absolute())


def free_port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


def sha256(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def process_running(pid):
    try:
        # A zombie cannot retain an audio socket or daemon.lock descriptor.
        return Path(f'/proc/{pid}/stat').read_text().split(') ', 1)[1][0] != 'Z'
    except FileNotFoundError:
        return False


class Listener:
    """Capture actual HTTP MP3; closing this object disconnects its only client."""
    def __init__(self, url):
        self.url = url
        self.data = bytearray()
        self.ready = threading.Event()
        self.done = threading.Event()
        self.cancel = threading.Event()
        self.error = None
        self.thread = threading.Thread(target=self._read, daemon=True)
        self.thread.start()

    def _read(self):
        deadline = time.monotonic() + 30
        try:
            while not self.cancel.is_set() and time.monotonic() < deadline:
                try:
                    response = OPENER.open(self.url, timeout=1)
                    break
                except (urllib.error.URLError, TimeoutError, OSError):
                    self.cancel.wait(.05)
            else:
                if not self.cancel.is_set():
                    self.error = 'No HTTP audio connection before deadline'
                return
            with response:
                if 'audio/mpeg' not in response.headers.get('Content-Type', ''):
                    raise AssertionError('Icecast did not return MP3 audio')
                self.ready.set()
                while not self.cancel.is_set():
                    chunk = response.read(2048)
                    if not chunk:
                        break
                    self.data.extend(chunk)
        except (OSError, urllib.error.URLError, AssertionError) as error:
            if not self.cancel.is_set():
                self.error = str(error)
        finally:
            self.done.set()

    def close(self):
        self.cancel.set()
        self.thread.join(timeout=3)
        assert not self.thread.is_alive(), 'Listener did not disconnect'


class Integration:
    def __init__(self):
        self.begin = time.monotonic()
        self.php = executable('PHP_BIN', 'php')
        self.liquidsoap = executable('LIQUIDSOAP_BIN', 'liquidsoap')
        self.icecast_bin = executable('ICECAST_BIN', 'icecast2')
        self.ffmpeg = executable('FFMPEG_BIN', 'ffmpeg')
        self.ffprobe = executable('FFPROBE_BIN', 'ffprobe')
        self.root = Path(tempfile.mkdtemp(prefix='rr-studio-stream-')).resolve()
        self.site = self.root / 'site'
        self.bus = self.root / 'playout'
        self.runtime = self.root / 'private-engine'
        self.base = f'http://127.0.0.1:{free_port()}'
        self.ice_port = free_port()
        self.stream = f'http://127.0.0.1:{self.ice_port}/rubben-test.mp3'
        self.env = dict(os.environ, PHP_BIN=self.php, LIQUIDSOAP_BIN=self.liquidsoap)
        if os.geteuid() == 0:
            self.env['RR_STREAM_TEST_ALLOW_ROOT'] = '1'
        self.processes = []
        self.handles = []
        self.listeners = []
        self.worker = None
        self.icecast = None
        self.tracks = []
        self.observed_children = set()
        self.checks = []

    def phase(self, label):
        print(f'[{time.monotonic() - self.begin:5.1f}s] {label}', flush=True)

    def until(self, predicate, label, timeout=20):
        deadline = min(time.monotonic() + timeout, self.begin + 175)
        while time.monotonic() < deadline:
            value = predicate()
            if value:
                return value
            if self.worker is not None and self.worker.poll() is not None:
                raise AssertionError(f'Supervisor exited unexpectedly ({self.worker.returncode}): {label}')
            time.sleep(.06)
        raise AssertionError('Timeout: ' + label)

    def launch(self, name, command):
        handle = (self.root / f'{name}.log').open('ab')
        self.handles.append(handle)
        process = subprocess.Popen(command, stdout=handle, stderr=subprocess.STDOUT,
                                   env=self.env, cwd=REPO, start_new_session=True)
        self.processes.append(process)
        return process

    @staticmethod
    def stop(process, sig=signal.SIGTERM):
        if process is None:
            return
        if process.poll() is None:
            process.send_signal(sig)
            try:
                process.wait(timeout=4)
            except subprocess.TimeoutExpired:
                os.killpg(process.pid, signal.SIGKILL)
                process.wait(timeout=3)

    def request(self, path, body=None, role='producer', csrf='synthetic', method=None):
        request = urllib.request.Request(
            self.base + path,
            data=None if body is None else json.dumps(body).encode(),
            method=method or ('POST' if body is not None else 'GET'),
            headers={'Content-Type': 'application/json', 'X-Test-Role': role, 'X-CSRF-Token': csrf})
        try:
            response = OPENER.open(request, timeout=3)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            raw = response.read()
            return response.status, json.loads(raw) if raw else None

    def api(self, body=None, **kwargs):
        return self.request('/music-studio-api.php', body, **kwargs)

    def view(self):
        status, data = self.api()
        assert status == 200, (status, data)
        return data

    def change(self, body):
        # Worker journal callbacks may legitimately advance playerRevision
        # between GET and POST. Retry only the API's explicit conflict result.
        for _ in range(40):
            current = self.view()
            status, data = self.api(dict(body, revision=current['revision'],
                                         playerRevision=current['player']['seq']))
            if status == 409:
                time.sleep(.04)
                continue
            assert status == 200, (body, status, data)
            return data
        raise AssertionError('Persistent API revision conflict: ' + str(body))

    def player(self):
        player = self.view()['player']
        # PHP serializes an empty associative array as []; once populated these
        # three occurrence-indexed collections are JSON objects.
        for key in ['outcomes', 'reserved', 'active']:
            if player.get(key) == []:
                player[key] = {}
        return player

    def journal(self):
        path = self.bus / 'events.jsonl'
        if not path.exists():
            return []
        with (self.bus / 'control.lock').open('a+b') as lock:
            fcntl.flock(lock, fcntl.LOCK_SH)
            records = [json.loads(line) for line in path.read_text().splitlines()]
            for record in records:
                if record.get('detail') == []:
                    record['detail'] = {}
            return records

    def started_ids(self):
        return [record['detail']['entryId'] for record in self.journal() if record['event'] == 'started']

    def child_pids(self):
        if self.worker is None or self.worker.poll() is not None:
            return []
        # Some native container runtimes omit /proc/<pid>/task/<pid>/children.
        # Read only PPIDs, selecting this test's own supervisor children.
        children = []
        for path in Path('/proc').iterdir():
            if not path.name.isdigit():
                continue
            try:
                fields = (path / 'stat').read_text().split(') ', 1)[1].split()
                if int(fields[1]) == self.worker.pid and fields[0] != 'Z':
                    children.append(int(path.name))
            except (FileNotFoundError, ProcessLookupError, PermissionError):
                continue
        children.sort()
        self.observed_children.update(children)
        return children

    def start_worker(self):
        self.worker = self.launch('supervisor', [self.php, str(REPO / 'scripts/music-liquidsoap.php'),
                                                  'serve', str(self.bus), str(self.runtime / 'engine.json')])
        self.until(lambda: self.player().get('online'), 'supervisor heartbeat')

    def start_icecast(self):
        self.icecast = self.launch('icecast', [self.icecast_bin, '-c', str(self.runtime / 'icecast.xml')])
        def ready():
            if self.icecast.poll() is not None:
                raise AssertionError('Icecast failed to start')
            try:
                with socket.create_connection(('127.0.0.1', self.ice_port), timeout=.2):
                    return True
            except OSError:
                return False
        self.until(ready, 'loopback Icecast listening', timeout=5)

    def fixture(self):
        self.phase('Create synthetic library and isolated PHP HTTP fixture')
        for directory in ['config', 'media/music/originals']:
            (self.site / directory).mkdir(parents=True, exist_ok=True)
        shutil.copytree(REPO / 'app', self.site / 'app')
        shutil.copytree(REPO / 'public', self.site / 'public')
        for directory in ['audio', 'monitor']:
            (self.bus / directory).mkdir(parents=True, exist_ok=True)
        (self.site / 'app/bootstrap.php').write_text("""<?php
require __DIR__.'/helpers.php'; require_once __DIR__.'/users.php';
function current_user() {
    $role = $_SERVER['HTTP_X_TEST_ROLE'] ?? 'producer';
    return $role === 'guest' ? null : ['id'=>'synthetic', 'name'=>'Synthetic test', 'role'=>$role];
}
$_SESSION = ['csrf'=>'synthetic']; $config = [];
""")
        (self.site / 'config/music-playout.local.php').write_text(
            "<?php return ['enabled'=>true,'root'=>" + json.dumps(str(self.bus)) +
            ",'stream_path'=>'/music-test-stream/'];")
        for index, frequency in enumerate([440, 660, 880, 990, 1100, 1210, 1320, 1430, 1540]):
            identity = hashlib.md5(f'studio-stream-synthetic-{index}'.encode()).hexdigest()
            path = self.site / 'media/music/originals' / f'{identity}.mp3'
            subprocess.run([self.ffmpeg, '-nostdin', '-v', 'error', '-f', 'lavfi', '-i',
                            f'sine=frequency={frequency}:duration=6', '-c:a', 'libmp3lame',
                            '-b:a', '128k', str(path)], check=True, timeout=10)
            duration = float(subprocess.check_output([self.ffprobe, '-v', 'error', '-show_entries',
                                                     'format=duration', '-of', 'default=nw=1:nk=1', str(path)], timeout=5))
            digest = sha256(path)
            self.tracks.append({'id': identity, 'sha256': digest, 'bytes': path.stat().st_size,
                                'originalName': f'Synthetic {index}.mp3', 'tags': ['Varm'],
                                'metadata': {'source': 'file', 'sourceSha256': digest,
                                             'durationSeconds': duration,
                                             'fields': {'artist': f'Synthetic artist {index}',
                                                        'title': f'Synthetic tone {frequency}'}}})
        self.library = self.site / 'media/music/library.json'
        self.library.write_text(json.dumps({'schemaVersion': 1, 'tracks': self.tracks}))
        (self.site / 'media/music/library.lock').touch()
        self.library_bytes = self.library.read_bytes()

        spec = importlib.util.spec_from_file_location('studio_stream_prepare', REPO / 'infra/studio-stream/prepare.py')
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        module.prepare(self.runtime, self.ice_port)
        if os.geteuid() == 0:
            # Test-only permission setup for Icecast's documented privilege drop.
            # Keep engine.json and XML at 0600; only temporary directory traversal
            # and Icecast-owned empty/log directories are granted to nobody.
            import grp
            import pwd
            nobody = pwd.getpwnam('nobody')
            self.root.chmod(0o711)
            self.runtime.chmod(0o711)
            for name in ['logs', 'empty-web']:
                os.chown(self.runtime / name, nobody.pw_uid, nobody.pw_gid)
            tree = ET.parse(self.runtime / 'icecast.xml')
            change = ET.SubElement(tree.getroot().find('security'), 'changeowner')
            ET.SubElement(change, 'user').text = nobody.pw_name
            ET.SubElement(change, 'group').text = grp.getgrgid(nobody.pw_gid).gr_name
            tree.write(self.runtime / 'icecast.xml', encoding='unicode')
        self.server = self.launch('php-http', [self.php, '-S', self.base.removeprefix('http://'),
                                                '-t', str(self.site / 'public')])
        def http_ready():
            try:
                return self.api()[0] == 200
            except (OSError, urllib.error.URLError):
                return False
        self.until(http_ready, 'PHP HTTP ready')

        assert self.api(role='guest')[0] == 401
        assert self.api({'action': 'reset', 'revision': 0}, role='observer')[0] == 403
        assert self.api({'action': 'reset', 'revision': 0}, csrf='wrong')[0] == 403
        assert self.request('/music-stream-auth.php', role='guest')[0] == 401
        assert self.request('/music-stream-auth.php', role='observer')[0] == 403
        assert self.request('/music-stream-auth.php')[0] == 204
        assert self.request('/music-stream-auth.php', method='HEAD')[0] == 204
        assert self.request('/music-stream-auth.php', method='POST')[0] == 405
        self.change({'action': 'schedule', 'schedule': {'programId': 'god-morgen-bomlo',
                                                      'date': '2026-10-19', 'time': '07:00'}})
        for track in self.tracks:
            self.change({'action': 'policy', 'trackId': track['id'], 'sha256': track['sha256'],
                         'policy': {'approved': True, 'blocked': False, 'programs': ['god-morgen-bomlo'],
                                    'weekdays': [1, 2, 3, 4, 5, 6, 7], 'periods': [],
                                    'fields': {'energy': 'moderat'}}})
        current = self.view()
        body = {'action': 'add', 'revision': current['revision'], 'trackId': self.tracks[0]['id'],
                'sha256': self.tracks[0]['sha256']}
        with ThreadPoolExecutor(max_workers=2) as pool:
            statuses = list(pool.map(lambda _: self.api(body)[0], range(2)))
        assert sorted(statuses) == [200, 409], statuses
        self.checks.append('HTTP roles, CSRF, revision conflict and private stream authorization')

    def queue(self, indexes):
        self.change({'action': 'reset'})
        for index in indexes:
            track = self.tracks[index]
            self.change({'action': 'add', 'trackId': track['id'], 'sha256': track['sha256']})
        result = self.view()
        assert all(not row['blocked'] for row in result['plan']['rows']), result['plan']['rows']
        return [entry['id'] for entry in result['entries']]

    def listen(self):
        listener = Listener(self.stream)
        self.listeners.append(listener)
        return listener

    def main_audio(self):
        self.phase('Three existing occurrences: actual MP3, listener close/reopen, fade and journal')
        ids = self.queue([0, 1, 2])
        self.start_icecast()
        self.start_worker()
        time.sleep(.5)
        assert self.player()['mode'] == 'stopped'
        assert not self.child_pids() and not self.started_ids()
        assert not (self.runtime / 'engine/current.liq').exists(), 'Opening a queue must never launch the engine'
        duplicate = subprocess.run([self.php, str(REPO / 'scripts/music-liquidsoap.php'), 'serve',
                                    str(self.bus), str(self.runtime / 'engine.json')], env=self.env,
                                   capture_output=True, timeout=8)
        assert duplicate.returncode == 3, duplicate.stderr.decode()
        first = self.listen()
        self.change({'action': 'start'})
        self.until(lambda: self.player().get('current', {}) and self.player()['current']['id'] == ids[0],
                   'first actual started occurrence')
        assert first.ready.wait(3), first.error
        first_child = self.child_pids()
        assert len(first_child) == 1, first_child
        self.until(lambda: self.player()['streamConnected'] is True, 'fresh PCM heartbeat and stream connection')
        assert self.player()['capabilities']['pause'] is False
        current = self.view()
        assert self.api({'action': 'pause', 'revision': current['revision'],
                         'playerRevision': current['player']['seq']})[0] == 400
        self.change({'action': 'start'})
        self.until(lambda: not self.player()['pendingCommand'], 'duplicate Start acknowledged')
        assert self.child_pids() == first_child, 'Duplicate Start replaced the live engine'
        self.until(lambda: self.player()['position'] > 1.5, 'audible first-track capture')
        first.close()
        before = self.player()['position']
        # Position follows the observed PCM heartbeat, not a wall-time guess.
        # There are no listeners while waiting for its next real advance.
        self.until(lambda: self.player()['position'] > before, 'PCM advances without listeners', timeout=2)
        assert self.player()['mode'] == 'playing'
        second = self.listen()
        assert second.ready.wait(3), second.error
        reopened = self.view()  # Fresh HTTP request: independent of any previous page/client.
        assert reopened['player']['current']['id'] == ids[0]
        assert reopened['player']['streamUrl'] == '/music-test-stream/'
        self.until(lambda: all(self.player()['outcomes'].get(identity, {}).get('status') == 'completed'
                               for identity in ids), 'all three actual PCM completions', timeout=25)
        self.until(lambda: self.player()['mode'] == 'stopped' and not self.player()['streamConnected']
                   and not self.child_pids(), 'finished queue stops without looping')
        second.done.wait(3)
        second.close()
        assert not first.error and not second.error, (first.error, second.error)
        assert self.started_ids() == ids, 'Journal must contain each real occurrence exactly once and in order'
        time.sleep(.4)
        assert self.started_ids() == ids and not self.child_pids()
        records = self.journal()
        for identity in ids:
            selected = [(position, row['event']) for position, row in enumerate(records)
                        if row['detail'].get('entryId') == identity or row['detail'].get('entry', {}).get('id') == identity]
            names = [name for _, name in selected]
            assert names.count('started') == names.count('completed') == 1, names
            assert names.index('reserved') < names.index('started') < names.index('completed')
            outcome = self.player()['outcomes'][identity]
            assert outcome['evidence'] == 'liquidsoap-pcm'
            assert abs(outcome['renderedSeconds'] - 6) < .15, outcome
            assert outcome['startedAt'] and outcome['endedAt']
        self.audio_receipt = self.analyze_audio([first.data, second.data])
        self.checks.append('three ordered HTTP MP3 tones, sequential fades, occurrence receipts and unchanged listener-independent play')
        self.checks.append('no autoplay, one worker/child, duplicate Start does not replay, finished queue does not loop')

    def analyze_audio(self, captures):
        all_labels = []
        complete_middle = []
        total_seconds = 0
        for number, data in enumerate(captures):
            mp3 = self.root / f'captured-{number}.mp3'
            mp3.write_bytes(data)
            decoded = subprocess.run([self.ffmpeg, '-v', 'error', '-i', str(mp3), '-ac', '1', '-ar', '8000',
                                      '-f', 's16le', '-'], capture_output=True, check=True, timeout=10).stdout
            samples = array('h')
            samples.frombytes(decoded)
            if sys.byteorder != 'little':
                samples.byteswap()
            total_seconds += len(samples) / 8000
            groups = []
            for start in range(0, len(samples) - 800, 800):
                powers = []
                for frequency in [440, 660, 880]:
                    coeff = 2 * math.cos(2 * math.pi * frequency / 8000)
                    previous = earlier = 0.0
                    for sample in samples[start:start + 800]:
                        value = sample + coeff * previous - earlier
                        earlier, previous = previous, value
                    power = max(0., previous * previous + earlier * earlier - coeff * previous * earlier)
                    powers.append(math.sqrt(power) / 800)
                peak = max(powers)
                label = [440, 660, 880][powers.index(peak)] if peak > 100 else None
                if label is not None:
                    if not all_labels or all_labels[-1] != label:
                        all_labels.append(label)
                    if not groups or groups[-1][0] != label:
                        groups.append((label, []))
                    groups[-1][1].append(peak)
            complete_middle.extend(levels for label, levels in groups if label == 660 and len(levels) > 40)
        assert all_labels == [440, 660, 880], ('Actual audio order', all_labels)
        assert total_seconds > 15, ('Captured too little real audio', total_seconds)
        assert complete_middle, 'Missing complete middle track for fade measurement'
        levels = max(complete_middle, key=len)
        center = statistics.median(levels[15:-15])
        fade_in = statistics.mean(levels[:2]) / center
        fade_out = statistics.mean(levels[-2:]) / center
        assert fade_in < .65 and fade_out < .65, ('No measured sequential fade envelope', fade_in, fade_out)
        return {'tone_order_hz': all_labels, 'decoded_seconds': round(total_seconds, 3),
                'middle_fade_in_ratio': round(fade_in, 3), 'middle_fade_out_ratio': round(fade_out, 3),
                'http_mp3_bytes': sum(len(data) for data in captures)}

    def stop_and_crash(self):
        self.phase('STOP interrupts current, releases prefetch; SIGKILL kills guarded child and requires fresh Start')
        ids = self.queue([3, 4])
        self.change({'action': 'start'})
        self.until(lambda: self.player().get('current', {}) and self.player()['current']['id'] == ids[0]
                   and ids[1] in self.player().get('reserved', {}), 'current plus reserved next occurrence')
        current = self.view()
        assert self.api({'action': 'remove', 'id': ids[1], 'revision': current['revision']})[0] == 400
        self.library.write_text('broken test fixture')
        assert self.view()['libraryError']
        self.change({'action': 'stop'})
        self.until(lambda: self.player()['mode'] == 'stopped' and not self.player().get('current')
                   and not self.player().get('reserved') and not self.child_pids(), 'operator Stop confirmed')
        self.library.write_bytes(self.library_bytes)
        stopped = self.player()
        assert stopped['outcomes'][ids[0]]['status'] == 'interrupted'
        assert ids[1] not in stopped['outcomes'] and ids[1] not in self.started_ids()
        assert not stopped['streamConnected']
        self.change({'action': 'start'})
        self.until(lambda: self.player().get('current', {}) and self.player()['current']['id'] == ids[1],
                   'explicit restart plays the released, never-started reservation')
        children = self.child_pids()
        assert len(children) == 1, children
        self.stop(self.worker, signal.SIGKILL)
        self.worker = None
        self.until(lambda: all(not process_running(pid) for pid in children), 'parent-death guard closes child', timeout=5)
        self.start_worker()
        self.until(lambda: self.player()['outcomes'].get(ids[1], {}).get('status') == 'interrupted',
                   'restart reconciles actual interrupted occurrence')
        assert self.player()['mode'] == 'stopped' and not self.child_pids()
        assert self.started_ids().count(ids[1]) == 1
        self.checks.append('Stop with unreadable library, protected prefetch, child parent-death guard and no automatic restart/replay')

    def corrupt_spool(self):
        self.phase('Post-publication missing file and changed checksum fail before actual start')
        for index, failure in [(5, 'missing'), (6, 'changed')]:
            identity = self.queue([index])[0]
            self.change({'action': 'start'})
            # Emulate a file disappearing/changing after API publication. Hold
            # the normal lock so the fault cannot race a half-written test file.
            with (self.bus / 'control.lock').open('a+b') as lock:
                fcntl.flock(lock, fcntl.LOCK_EX)
                spool = self.bus / 'audio' / (self.tracks[index]['sha256'] + '.mp3')
                if failure == 'missing':
                    spool.unlink()
                else:
                    with spool.open('ab') as audio:
                        audio.write(b'synthetic-test-checksum-change')
            self.until(lambda: self.player()['outcomes'].get(identity, {}).get('status') == 'failed',
                       failure + ' spool rejected')
            self.until(lambda: not self.child_pids(), failure + ' engine stopped')
            failed = self.player()['outcomes'][identity]
            assert not failed.get('startedAt') and identity not in self.started_ids()
            assert self.player()['mode'] == 'blocked'
        self.checks.append('missing and altered spool copies never become started/completed occurrences')

    def connection_failures(self):
        self.phase('No claims before Icecast connection; connection loss interrupts and blocks')
        ids = self.queue([7, 8])
        self.stop(self.icecast)
        self.icecast = None
        journal_before = len(self.journal())
        self.change({'action': 'start'})
        self.until(lambda: self.player()['mode'] == 'blocked' and not self.child_pids(),
                   'missing Icecast blocks output')
        records = self.journal()[journal_before:]
        assert not any(row['event'] in ['reserved', 'started', 'completed'] for row in records), records
        assert all(identity not in self.player()['outcomes'] for identity in ids)
        self.start_icecast()
        time.sleep(.3)
        assert self.player()['mode'] == 'blocked' and not self.child_pids(), 'Reconnect must not restart automatically'
        self.change({'action': 'start'})
        self.until(lambda: self.player().get('current', {}) and self.player()['current']['id'] == ids[0],
                   'explicit retry starts after confirmed connection')
        child = self.child_pids()
        self.stop(self.icecast)
        self.icecast = None
        self.until(lambda: self.player()['mode'] == 'blocked'
                   and self.player()['outcomes'].get(ids[0], {}).get('status') == 'interrupted'
                   and not self.child_pids(), 'connection loss stops child and records interruption', timeout=10)
        assert all(not process_running(pid) for pid in child)
        assert ids[1] not in self.started_ids()
        assert not self.player()['streamConnected']
        self.checks.append('no decode/claim before connection, loss stops active audio, no automatic reconnection playback')

        self.phase('SIGSTOP freezes PCM: watchdog blocks and terminates the still-live child')
        self.start_icecast()
        self.change({'action': 'start'})
        self.until(lambda: self.player().get('current', {}) and self.player()['current']['id'] == ids[1],
                   'released next occurrence starts before freeze')
        children = self.child_pids()
        assert len(children) == 1, children
        os.kill(children[0], signal.SIGSTOP)
        freeze_time = time.monotonic()
        self.until(lambda: self.player()['mode'] == 'blocked' and not self.player()['streamConnected'],
                   'stale PCM heartbeat invalidates connected status', timeout=9)
        self.until(lambda: not process_running(children[0])
                   and self.player()['outcomes'].get(ids[1], {}).get('status') == 'interrupted',
                   'frozen child killed and occurrence interrupted', timeout=5)
        assert time.monotonic() - freeze_time < 11
        assert self.player()['outcomes'][ids[1]]['status'] != 'completed'
        self.checks.append('SIGSTOP cannot leave stale connected/playing status; PCM watchdog kills the child')

    def finish(self):
        assert all(sha256(self.site / 'media/music/originals' / (track['id'] + '.mp3')) == track['sha256']
                   for track in self.tracks), 'A library original changed'
        records = self.journal()
        assert [row['seq'] for row in records] == list(range(1, len(records) + 1))
        assert all(row['output'] == 'isolated-test' for row in records)
        self.checks.append('all original hashes preserved; journal sequence is contiguous')
        receipt = {'result': 'PASS', 'scope': 'synthetic local HTTP/loopback only; no VPS or public stream',
                   'seconds': round(time.monotonic() - self.begin, 2),
                   'checks': self.checks, 'actual_audio': self.audio_receipt,
                   'started_occurrence_ids': self.started_ids(), 'journal_records': len(records)}
        (self.root / 'receipt.json').write_text(json.dumps(receipt, indent=2) + '\n')
        destination = os.environ.get('RR_STREAM_TEST_ARTIFACTS')
        if destination:
            output = Path(destination)
            output.mkdir(parents=True, exist_ok=True)
            for name in ['receipt.json', 'captured-0.mp3', 'captured-1.mp3']:
                shutil.copyfile(self.root / name, output / name)
        print(json.dumps(receipt, indent=2), flush=True)

    def close(self, success):
        for listener in self.listeners:
            listener.close()
        for process in reversed(self.processes):
            self.stop(process)
        # Guard against an implementation regression leaving our own child alive.
        for pid in self.observed_children:
            if process_running(pid):
                try:
                    command = Path(f'/proc/{pid}/cmdline').read_bytes()
                    if str(self.runtime / 'engine/current.liq').encode() in command:
                        os.kill(pid, signal.SIGKILL)
                except (FileNotFoundError, ProcessLookupError):
                    pass
        for handle in self.handles:
            handle.close()
        if success and not os.environ.get('RR_STREAM_TEST_KEEP'):
            shutil.rmtree(self.root)
        else:
            print('Isolated test files: ' + str(self.root), flush=True)


if __name__ == '__main__':
    test = Integration()
    passed = False
    try:
        test.fixture()
        test.main_audio()
        test.stop_and_crash()
        test.corrupt_spool()
        test.connection_failures()
        test.finish()
        passed = True
    except Exception:
        for path in [test.root / 'supervisor.log', test.runtime / 'engine/engine.log']:
            if path.exists():
                print('\n' + path.name + '\n' + path.read_text(errors='replace')[-6000:], file=sys.stderr)
        raise
    finally:
        test.close(passed)
