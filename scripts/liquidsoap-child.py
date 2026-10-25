#!/usr/bin/env python3
"""Linux child guard: kill the audio engine if its PHP supervisor disappears.

The inherited daemon.lock descriptor also remains held until engine exit.
No shell, service management, privilege change or network operation here.
"""
import ctypes
import os
import signal
import sys

if len(sys.argv) != 4 or not sys.argv[1].isdigit():
    raise SystemExit('Usage: liquidsoap-child.py <parent-pid> <binary> <script>')
parent = int(sys.argv[1])
libc = ctypes.CDLL(None, use_errno=True)
if libc.prctl(1, signal.SIGKILL, 0, 0, 0) != 0:  # PR_SET_PDEATHSIG
    raise OSError(ctypes.get_errno(), 'Cannot set parent-death signal')
if os.getppid() != parent:
    raise SystemExit('Supervisor already exited')
os.set_inheritable(3, True)
os.execv(sys.argv[2], [sys.argv[2], sys.argv[3]])
