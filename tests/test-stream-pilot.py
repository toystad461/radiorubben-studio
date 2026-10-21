#!/usr/bin/env python3
"""Exercise real Liquidsoap -> Icecast -> MP3 -> PCM; synthetic audio only."""
import array
import hashlib
import importlib.util
import math
import os
from pathlib import Path
import socket
import subprocess
import tempfile
import time
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('pilot', ROOT / 'infra/stream-pilot/prepare.py')
pilot = importlib.util.module_from_spec(spec)
spec.loader.exec_module(pilot)
LIQ = os.environ.get('LIQUIDSOAP_BIN', 'liquidsoap')
ICE = os.environ.get('ICECAST_BIN', 'icecast2')


def run(*args):
    return subprocess.run(args, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=45)


with tempfile.TemporaryDirectory(prefix='rubben-stream-') as temp:
    root = Path(temp)
    tracks = []
    for i, freq in enumerate((440, 880)):
        path = root / f'tone{i}.wav'
        run('ffmpeg', '-v', 'error', '-f', 'lavfi', '-i', f'sine=frequency={freq}:duration=9', '-ac', '2', str(path))
        tracks.append(path)
    original = [hashlib.sha256(p.read_bytes()).hexdigest() for p in tracks]
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    prepared = pilot.prepare(root / 'pilot', tracks, port)
    try:
        pilot.prepare(prepared, tracks, port)
        raise AssertionError('Existing destination overwritten')
    except FileExistsError:
        pass
    assert prepared.stat().st_mode & 0o777 == 0o700
    assert (prepared / 'radio.liq').stat().st_mode & 0o777 == 0o600
    run(LIQ, '--check', str(prepared / 'radio.liq'))
    if os.environ.get('RR_STREAM_CHECK_ONLY') == '1':
        print('PASS: configuration, permissions, overwrite protection and Liquidsoap syntax. Audio test not run.')
        raise SystemExit(0)
    processes = []
    handles = []
    try:
        for command, name in (([ICE, '-c', str(prepared / 'icecast.xml')], 'icecast'),
                              ([LIQ, str(prepared / 'radio.liq')], 'liquidsoap')):
            log = open(root / (name + '.log'), 'wb')
            handles.append(log)
            processes.append(subprocess.Popen(command, stdout=log, stderr=log))
            if name == 'icecast':
                for _ in range(100):
                    if processes[0].poll() is not None:
                        raise AssertionError('Icecast exited before readiness')
                    try:
                        with socket.create_connection(('127.0.0.1', port), timeout=.2):
                            break
                    except OSError:
                        time.sleep(.1)
                else:
                    raise AssertionError('Icecast did not listen')
        url = f'http://127.0.0.1:{port}/rubben-test.mp3'
        for _ in range(100):
            assert all(p.poll() is None for p in processes), 'A streaming process exited'
            try:
                response = urllib.request.urlopen(url, timeout=3)
                break
            except OSError:
                time.sleep(.2)
        else:
            raise AssertionError('No stream mount')
        with response:
            assert 'audio/mpeg' in response.headers['Content-Type']
            data = response.read(192000 // 8 * 22)
        (root / 'capture.mp3').write_bytes(data)
        pcm = run('ffmpeg', '-v', 'error', '-i', str(root / 'capture.mp3'), '-f', 's16le', '-ac', '1', '-ar', '8000', '-').stdout
        samples = array.array('h', pcm)
        assert len(samples) > 8000 * 18, 'Too little decoded audio'
        def strength(chunk, freq):
            n = len(chunk)
            real = sum(v * math.cos(2 * math.pi * freq * i / 8000) for i, v in enumerate(chunk))
            imag = sum(v * math.sin(2 * math.pi * freq * i / 8000) for i, v in enumerate(chunk))
            return 2 * math.hypot(real, imag) / n
        frames = [samples[i:i+2000] for i in range(0, len(samples)-2000, 2000)]
        bands = [(strength(f, 440), strength(f, 880)) for f in frames]
        assert any(a > 1000 and b < 100 for a, b in bands), 'First track missing'
        assert any(b > 1000 and a < 100 for a, b in bands), 'Second track missing'
        assert any(a > 300 and b > 300 for a, b in bands), 'No audible crossfade overlap'
        assert max(abs(v) for v in samples) < 32760, 'Clipped samples'
        # A disconnected listener must not stop the motor; another can reconnect.
        with urllib.request.urlopen(url, timeout=5) as again:
            assert len(again.read(4096)) == 4096
        assert all(p.poll() is None for p in processes)
        assert original == [hashlib.sha256(p.read_bytes()).hexdigest() for p in tracks]
        print('PASS: real MP3 stream, both tracks, crossfade, no clipping, reconnect and unchanged originals')
    except Exception:
        for log in root.glob('*.log'):
            print(log.name, log.read_text(errors='replace')[-4000:])
        if (prepared / 'error.log').exists():
            print((prepared / 'error.log').read_text(errors='replace')[-4000:])
        raise
    finally:
        for p in reversed(processes):
            if p.poll() is None:
                p.terminate()
                try:
                    p.wait(timeout=8)
                except subprocess.TimeoutExpired:
                    p.kill()
                    p.wait()
        for h in handles:
            h.close()
