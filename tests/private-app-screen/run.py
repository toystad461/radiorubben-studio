"""Run isolated screen tests; export results/screenshots, never sessions/config."""
from pathlib import Path
import os, sys, shutil, signal, subprocess, tempfile
HERE=Path(__file__).resolve().parent
output=Path(sys.argv[1]).resolve() if len(sys.argv)>1 else None
with tempfile.TemporaryDirectory(prefix='rr-private-screen-') as name:
 work=Path(name)
 env=dict(os.environ,RR_SCREEN_TEST_DIR=name)
 result=1
 try:
  subprocess.run([sys.executable,str(HERE/'prepare.py')],env=env,check=True)
  result=subprocess.run([sys.executable,str(HERE/'screen_test.py')],env=env).returncode
 finally:
  pid=work/'server.pid'
  if pid.is_file():
   try: os.kill(int(pid.read_text()),signal.SIGTERM)
   except ProcessLookupError: pass
  if output:
   output.mkdir(parents=True,exist_ok=True)
   if (work/'screen-results.json').is_file():shutil.copyfile(work/'screen-results.json',output/'screen-results.json')
   if (work/'screenshots').is_dir():shutil.copytree(work/'screenshots',output/'screenshots',dirs_exist_ok=True)
sys.exit(result)
