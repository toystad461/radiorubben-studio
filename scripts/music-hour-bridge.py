#!/usr/bin/env python3
"""Render three source-verified music excerpts locally; no external uploads."""
import argparse, pathlib, json, hashlib, re, subprocess, math

def render(root, request, output):
    root = pathlib.Path(root).resolve()
    songs, duration = request['songs'], request['duration']
    assert len(songs) == 3 and math.isfinite(duration) and 18 <= duration <= 45
    args = ['ffmpeg', '-nostdin', '-v', 'error', '-y']
    filters = []
    for i, song in enumerate(songs):
        assert re.fullmatch('[a-f0-9]{32}', song['trackId'])
        assert re.fullmatch('[a-f0-9]{64}', song['sha256'])
        path = root / 'originals' / (song['trackId'] + '.mp3')
        assert not path.is_symlink() and path.is_file() and path.stat().st_size <= 20*1024*1024
        assert hashlib.sha256(path.read_bytes()).hexdigest() == song['sha256']
        start, length = song['cueIn'], song['duration']
        assert math.isfinite(start) and start >= 0 and abs(length-duration/3) < .001
        args += ['-ss', str(start), '-t', str(length), '-i', str(path)]
        filters.append(f'[{i}:a]aresample=44100,aformat=channel_layouts=stereo,atrim=duration={length},asetpts=PTS-STARTPTS,afade=t=in:d=0.12,afade=t=out:st={length-.2}:d=0.2[a{i}]')
    filters.append(f'[a0][a1][a2]concat=n=3:v=0:a=1,loudnorm=I=-18:TP=-2:LRA=11,aresample=44100,atrim=duration={duration}[out]')
    args += ['-filter_complex', ';'.join(filters), '-map', '[out]', '-c:a', 'libmp3lame', '-b:a', '192k', '-map_metadata', '-1', str(output)]
    subprocess.run(args, check=True, capture_output=True, timeout=60)
    decoded = subprocess.run(['ffmpeg', '-v', 'error', '-i', str(output), '-f', 's16le', '-ac', '2', '-ar', '44100', '-'], check=True, capture_output=True, timeout=15)
    measured = len(decoded.stdout)/(44100*4)
    assert abs(measured-duration) < .03, (duration, measured)
    assert any(decoded.stdout), 'No decoded audio'
    return {'duration': measured, 'sha256': hashlib.sha256(pathlib.Path(output).read_bytes()).hexdigest()}

if __name__ == '__main__':
    p = argparse.ArgumentParser()
    p.add_argument('--library', required=True)
    p.add_argument('--request', required=True)
    p.add_argument('--output', required=True)
    a = p.parse_args()
    print(json.dumps(render(a.library, json.loads(pathlib.Path(a.request).read_text()), a.output)))
