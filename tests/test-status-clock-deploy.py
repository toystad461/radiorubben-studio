import importlib.util
import json
import os
import pathlib
import tempfile
import unittest
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('deploy',pathlib.Path(__file__).parents[1]/'scripts/status-clock-deploy.py')
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

class DeployTest(unittest.TestCase):
 def setUp(self):
  self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup)
  self.root=pathlib.Path(self.tmp.name)/'site';self.root.mkdir()
  self.backup=pathlib.Path(self.tmp.name)/'backup';self.backup.mkdir()
  self.rows=[];self.old={};self.new={}
  for n in ['wp-content/mu-plugins/rr-public-radio.js','wp-content/mu-plugins/rr-public-radio.php']:
   old=('old '+n).encode();new=('new '+n).encode();p=self.root/n;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(old);p.chmod(0o640)
   self.old[n]=old;self.new[n]=new;self.rows.append((n,m.digest(old),m.digest(old),m.digest(new)))
  self.addCleanup(patch.stopall)
  patch.dict(m.MANIFEST,{'wordpress':self.rows}).start()
  patch.object(m,'fetch',side_effect=lambda target,name:self.new[name]).start()
 def prepare(self):
  plan=m.plan('wordpress',self.root);receipt={'target':'wordpress','root':str(self.root),'files':{}}
  for i,(name,item) in enumerate(plan.items()):
   saved=str(i)+'.before';(self.backup/saved).write_bytes(item['before'])
   receipt['files'][name]={'before':m.digest(item['before']),'after':m.digest(item['data']),'meta':item['meta'],'backup':saved}
  return plan,receipt
 def test_preflight_writes_nothing(self):
  m.plan('wordpress',self.root)
  for n,data in self.old.items():self.assertEqual((self.root/n).read_bytes(),data)
 def test_unknown_file_aborts(self):
  (self.root/self.rows[-1][0]).write_bytes(b'newer editor work')
  with self.assertRaisesRegex(RuntimeError,'Refusing changed file'):m.plan('wordpress',self.root)
  self.assertEqual((self.root/self.rows[0][0]).read_bytes(),self.old[self.rows[0][0]])
 def test_source_corruption_aborts(self):
  patch.object(m,'fetch',return_value=b'bad').start()
  with self.assertRaisesRegex(RuntimeError,'Source hash mismatch'):m.plan('wordpress',self.root)
 def test_install_rollback_and_metadata(self):
  plan,receipt=self.prepare();m.install(self.root,plan,self.backup,receipt)
  for n,data in self.new.items():self.assertEqual((self.root/n).read_bytes(),data);self.assertEqual((self.root/n).stat().st_mode&0o777,0o640)
  m.rollback(self.root,self.backup/'receipt.json','wordpress')
  for n,data in self.old.items():self.assertEqual((self.root/n).read_bytes(),data)
 def test_partial_failure_restores_only_our_changes(self):
  plan,receipt=self.prepare();real=m.atomic;calls=0
  def fail(path,data,meta):
   nonlocal calls
   calls+=1
   if calls==2:raise OSError('injected write failure')
   real(path,data,meta)
  patch.object(m,'atomic',side_effect=fail).start()
  with self.assertRaises(OSError):m.install(self.root,plan,self.backup,receipt)
  for n,data in self.old.items():self.assertEqual((self.root/n).read_bytes(),data)
 def test_concurrent_edit_is_preserved(self):
  plan,receipt=self.prepare();last=list(plan)[-1];(self.root/last).write_bytes(b'newer work')
  with self.assertRaisesRegex(RuntimeError,'Concurrent edit'):m.install(self.root,plan,self.backup,receipt)
  self.assertEqual((self.root/last).read_bytes(),b'newer work');first=list(plan)[0];self.assertEqual((self.root/first).read_bytes(),self.old[first])
 def test_rollback_refuses_newer_work(self):
  plan,receipt=self.prepare();m.install(self.root,plan,self.backup,receipt);last=list(plan)[-1];(self.root/last).write_bytes(b'newer work')
  with self.assertRaisesRegex(RuntimeError,'Rollback refused'):m.rollback(self.root,self.backup/'receipt.json','wordpress')
  self.assertEqual((self.root/list(plan)[0]).read_bytes(),self.new[list(plan)[0]])
 def test_symlink_is_refused(self):
  path=self.root/self.rows[0][0];path.unlink();path.symlink_to(self.root/self.rows[1][0])
  with self.assertRaisesRegex(RuntimeError,'unsafe target'):m.plan('wordpress',self.root)
 def test_preserves_pr117_template_except_script_version(self):
  before=b'Weather Traffic unsaved-script-layout '+m.OLD_TAG+b' existing menu'
  path=self.root/m.TEMPLATE;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(before)
  patch.dict(m.MANIFEST,{'studio':[]}).start();patch.object(m,'TEMPLATE_BEFORE',m.digest(before)).start()
  plan=m.plan('studio',self.root)
  self.assertEqual(plan[m.TEMPLATE]['data'],before.replace(m.OLD_TAG,m.NEW_TAG))
  self.assertEqual(path.read_bytes(),before)

if __name__=='__main__':unittest.main()
