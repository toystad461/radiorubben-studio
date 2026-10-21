#!/usr/bin/env python3
"""Hidden, user-operated key entry for the existing isolated Radio Rubben VPS.

Run as rr-studio with a real terminal. No network, activation or overwrites.
Does not depend on Studio's scripts/ directory being included in its release.
"""
from __future__ import annotations

import getpass
import os
from pathlib import Path
import pwd
import stat
import sys
import warnings

DATA = Path('/srv/radiorubben-test/studio-data')
CONFIG = Path('/srv/radiorubben-test/studio/config')
KEY_NAME = 'elevenlabs.key'


def open_private_directory(data: Path, config: Path, uid: int) -> int:
    """Verify existing layout, then pin the private directory with a file handle."""
    if config.resolve(strict=True) != data:
        raise ValueError('unexpected_layout')
    fd = os.open(data, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    try:
        info = os.fstat(fd)
        if not stat.S_ISDIR(info.st_mode) or info.st_uid != uid or stat.S_IMODE(info.st_mode) != 0o700:
            raise PermissionError('private_directory_required')
    except BaseException:
        os.close(fd)
        raise
    return fd


def refuse_existing(directory_fd: int) -> None:
    try:
        os.stat(KEY_NAME, dir_fd=directory_fd, follow_symlinks=False)
    except FileNotFoundError:
        return
    raise FileExistsError('existing_key_not_changed')


def store_new_key(directory_fd: int, key: str) -> None:
    """Exclusive mode-0600 creation, matching Studio's existing key-file loader."""
    if not 20 <= len(key) <= 512 or any(ord(c) < 33 or ord(c) > 126 for c in key):
        raise ValueError('invalid_key_format')
    fd = os.open(KEY_NAME, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW,
                 0o600, dir_fd=directory_fd)
    created = os.fstat(fd)
    try:
        with os.fdopen(fd, 'wb') as stream:
            os.fchmod(stream.fileno(), 0o600)
            stream.write(key.encode('ascii'))
            stream.flush()
            os.fsync(stream.fileno())
            info = os.fstat(stream.fileno())
            if info.st_uid != os.geteuid() or stat.S_IMODE(info.st_mode) != 0o600:
                raise PermissionError('key_owner_or_mode_incorrect')
        os.fsync(directory_fd)
    except BaseException:
        # Only remove the incomplete file created by THIS invocation, never an older key.
        try:
            current = os.stat(KEY_NAME, dir_fd=directory_fd, follow_symlinks=False)
            if (current.st_dev, current.st_ino) == (created.st_dev, created.st_ino):
                os.unlink(KEY_NAME, dir_fd=directory_fd)
        except OSError:
            pass
        raise


def main() -> int:
    directory_fd = None
    key = None
    stage = 'bruker_og_terminal'
    try:
        if len(sys.argv) != 1 or not sys.stdin.isatty():
            print('STOPP: Kjor filen uten argumenter via SSH med terminal (-t).')
            return 2
        if os.geteuid() != pwd.getpwnam('rr-studio').pw_uid:
            print('STOPP: Denne filen skal kjores som rr-studio, ikke ubuntu eller root.')
            return 2
        stage = 'privat_datamappe'
        directory_fd = open_private_directory(DATA, CONFIG, os.geteuid())
        refuse_existing(directory_fd)
        stage = 'skjult_inntasting'
        print('Nokkelen lagres bare i test-VPS-ens eksisterende private datamappe.', flush=True)
        with warnings.catch_warnings():
            warnings.simplefilter('error', getpass.GetPassWarning)
            key = getpass.getpass('Lim inn ElevenLabs-testnokkel (skjult), trykk Enter: ')
        stage = 'nokkelformat_og_lagring'
        store_new_key(directory_fd, key)
        print('OK: Nokkelen er lagret privat med rettighet 600. Ingen lyd er generert.', flush=True)
        return 0
    except FileExistsError:
        print('STOPP: Nokkelfilen finnes allerede. Den er ikke lest eller overskrevet.')
        return 2
    except (Exception, KeyboardInterrupt) as error:
        print('STOPP:', stage, '| feiltype:', type(error).__name__,
              '| feilkode:', getattr(error, 'errno', None))
        print('Ikke del nokkelen. Ingen automatisk nytt forsok.')
        return 2
    finally:
        key = None
        if directory_fd is not None:
            os.close(directory_fd)


if __name__ == '__main__':
    raise SystemExit(main())
