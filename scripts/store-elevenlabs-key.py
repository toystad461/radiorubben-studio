#!/usr/bin/env python3
"""User-operated hidden entry. No network, activation, overwrite or secret output."""
import getpass
import os
from pathlib import Path
import sys
import warnings


def store_key(directory: Path, key: str) -> None:
    if directory.is_symlink() or not directory.is_dir():
        raise ValueError('Privat konfigurasjonsmappe mangler.')
    if not 20 <= len(key) <= 512 or any(ord(c) < 33 or ord(c) > 126 for c in key):
        raise ValueError('Ugyldig nøkkelformat. Ingenting er lagret.')
    target = directory / 'elevenlabs.key'
    # Exclusive creation refuses existing secrets and symlinks, without reading them.
    fd = os.open(target, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    try:
        with os.fdopen(fd, 'w', encoding='ascii') as stream:
            stream.write(key)
            stream.flush()
            os.fsync(stream.fileno())
    except BaseException:
        target.unlink(missing_ok=True)
        raise


def main() -> int:
    if len(sys.argv) != 1 or not sys.stdin.isatty():
        print('Kjør skriptet selv i Terminal. Nøkkelen skal ikke være et argument eller pipes inn.', file=sys.stderr)
        return 1
    directory = Path(__file__).resolve().parent.parent / 'config'
    try:
        with warnings.catch_warnings():
            warnings.simplefilter('error', getpass.GetPassWarning)
            key = getpass.getpass('Lim inn ElevenLabs-nøkkelen (skjult), og trykk Enter: ')
        store_key(directory, key)
    except FileExistsError:
        print('En nøkkelfil finnes allerede. Den er ikke lest eller overskrevet.', file=sys.stderr)
        return 1
    except (ValueError, OSError, getpass.GetPassWarning, EOFError, KeyboardInterrupt):
        print('Nøkkelen ble ikke lagret. Kontroller terminal og filrettigheter.', file=sys.stderr)
        return 1
    finally:
        key = None
    print('Nøkkelen er lagret privat på denne maskinen. TTS er fortsatt avslått; ingenting er sendt til serveren.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
