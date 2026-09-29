#!/usr/bin/env python3
"""Build/verify a pinned, code-only PR18 delta. Never deploy or read private data."""
import argparse
import difflib
import hashlib
import io
import json
from pathlib import Path
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
BASE = 'd753535963d9d1dbf20d104a8b23478547f75f36'
HEAD = '4f498188e443bcc6065261dd768be9adbf607a61'
# Reviewed Git blob identities, not mutable worktree files or an editable manifest.
CHANGES = {
    'studio-private/app/board.php': ('8e15d48efbeeff4c28cd5ec3cd81e196f0dd5545', '3547ad88def53a7cb68e5c955ace53d8bc1a9546'),
    'studio-private/app/news-script.php': (None, '6813f64458bd0334d0aad66b38348b1796417a55'),
    'studio-public/sending.php': ('5252a786fec359091cc7111c1099c972c962da3c', '99b5b1076b907baa641d7e25d76bf0b2c107efa3'),
}
DEPENDENCIES = {
    'studio-private/app/programs.php': '2276aca37906c5ce5fab424826671d42669f24aa',
    'studio-private/app/editorial-memory.php': '4abb4fe533c37368e77e625b98eb9e19e5afd008',
    'studio-private/app/story-script.php': 'b9c88fb44c80748426d2d1a37715c8edd057c6c2',
}
# These dependencies are inspected but NEVER included in the patch.
NOTICE = b'''PR18 news scripts: REVIEW PATCH ONLY - NOT AN UPLOAD ZIP
Apply only to an offline copy of reconciled Studio code. Paths are relative to
Uniweb's sibling studio-private/ and studio-public/ directories.
The base is a GitHub development reference, NOT a verified Uniweb snapshot.
Run scripts/news-release.py preflight --target /offline/code-copy from the repo.
A match does not authorize deployment or prove runtime/configuration readiness.
Read overlays/uniweb/NEWS-RELEASE.md in the repository for outstanding gates.
Never upload this ZIP, patch or manifest into the public document root.
No private config, data, history, backups, PR15/16 modules or dependencies included.
'''


def blob(sha):
    if sha is None:
        return b''
    data = subprocess.check_output(['git', '-C', str(ROOT), 'cat-file', 'blob', sha])
    actual = hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()
    if actual != sha:
        raise ValueError('Git blob identity mismatch')
    return data


def digest(data):
    return hashlib.sha256(data).hexdigest()


def expected():
    patch = ''
    files = {}
    for path, (before, after) in sorted(CHANGES.items()):
        old, new = blob(before), blob(after)
        patch += ''.join(difflib.unified_diff(
            old.decode().splitlines(keepends=True), new.decode().splitlines(keepends=True),
            fromfile='a/' + path if before else '/dev/null', tofile='b/' + path))
        files[path] = {'beforeSha256': digest(old) if before else None, 'afterSha256': digest(new)}
    manifest = {'format': 1, 'base': BASE, 'head': HEAD, 'productionVerified': False,
                'files': files, 'requiredExistingCode': {p: digest(blob(s)) for p, s in sorted(DEPENDENCIES.items())},
                'unverifiedRuntime': ['producer_request/configuration', 'bootstrap/auth/views', 'PHP curl/DOM', 'live source and AI calls']}
    return {'news-scripts.patch': patch.encode(),
            'manifest.json': (json.dumps(manifest, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode(),
            'README.txt': NOTICE}


def archive_bytes():
    out = io.BytesIO()
    with zipfile.ZipFile(out, 'w', compression=zipfile.ZIP_STORED) as z:
        for name, data in sorted(expected().items()):
            entry = zipfile.ZipInfo(name, (1980, 1, 1, 0, 0, 0))
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            z.writestr(entry, data)
    return out.getvalue()


def verify(path):
    # Exact byte comparison also rejects duplicates, traversal, symlinks, ZIP comments,
    # appended data and secrets inserted into an allowed file. No heuristic secret scan.
    if Path(path).read_bytes() != archive_bytes():
        raise ValueError('Package differs from pinned code-only artifact (extra, missing or modified bytes)')


def regular(root, relative):
    path = root
    for part in Path(relative).parts:
        path = path / part
        if path.is_symlink():
            raise ValueError('Symlink is not allowed: ' + relative)
    if not path.is_file():
        raise ValueError('Required existing code missing: ' + relative)
    return path


def preflight(target):
    root = Path(target).absolute()
    # Read explicit code paths only; never scan config, user data, caches or backups.
    for path, (before, _) in CHANGES.items():
        candidate = root / path
        if before is None:
            if candidate.exists() or candidate.is_symlink():
                raise ValueError('New module already exists: ' + path)
        elif regular(root, path).read_bytes() != blob(before):
            raise ValueError('STOP: reconcile active code before patching: ' + path)
    for path, sha in DEPENDENCIES.items():
        if regular(root, path).read_bytes() != blob(sha):
            raise ValueError('STOP: unresolved program/source dependency: ' + path)
    # This is a textual compatibility check, not a claim about the live server.
    with __import__('tempfile').TemporaryDirectory() as tmp:
        patch = Path(tmp) / 'news.patch'
        patch.write_bytes(expected()['news-scripts.patch'])
        subprocess.run(['git', 'apply', '--check', str(patch)], cwd=root, check=True)
    print('Reference code matches; runtime/auth/producer and fresh Uniweb provenance still require review. No files changed.')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=['build', 'verify', 'preflight'])
    parser.add_argument('--archive', type=Path, default=ROOT / 'dist/news-scripts-pr18.zip')
    parser.add_argument('--target', type=Path)
    args = parser.parse_args()
    if args.command == 'build':
        data = archive_bytes()
        args.archive.parent.mkdir(parents=True, exist_ok=True)
        args.archive.write_bytes(data)
        verify(args.archive)
        print('Built verified review-only patch: ' + str(args.archive))
    elif args.command == 'verify':
        verify(args.archive)
        print('Pinned code-only package verified')
    else:
        if args.target is None:
            parser.error('--target is required')
        preflight(args.target)


if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        raise SystemExit(str(error))
