import importlib.util
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('inventory',Path(__file__).parents[1]/'infra/studio-stream/install-inventory.py')
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

class InventoryTests(unittest.TestCase):
    def test_nginx_metadata_is_scoped_and_redacted(self):
        raw='''# configuration file /etc/nginx/sites-enabled/rr-test:
server {
 root /srv/radiorubben-test/studio/public;
 fastcgi_pass unix:/run/php/rr-studio.sock;
 set $private DO_NOT_PRINT;
}
# configuration file /etc/nginx/sites-enabled/production:
server { root /var/www/production; set $private DO_NOT_PRINT; }
'''
        result=m.nginx_metadata(raw)
        self.assertEqual(len(result),1)
        self.assertEqual(result[0]['fastcgi_sockets'],['/run/php/rr-studio.sock'])
        self.assertNotIn('DO_NOT_PRINT',str(result))
        self.assertNotIn('production',str(result))
    def test_wrong_host_stops_before_commands(self):
        with patch.object(m.os,'geteuid',return_value=0),patch.object(m.socket,'gethostname',return_value='other'),patch.object(m,'run') as run:
            with self.assertRaises(RuntimeError):m.collect()
            run.assert_not_called()
    def test_file_metadata_does_not_read_or_write_contents(self):
        with tempfile.TemporaryDirectory() as folder:
            p=Path(folder)/'file';p.write_text('DO_NOT_PRINT');before=p.stat()
            result=m.file_metadata(p)
            self.assertEqual(result['type'],'file');self.assertNotIn('DO_NOT_PRINT',str(result))
            self.assertEqual(p.read_text(),'DO_NOT_PRINT');self.assertEqual(p.stat().st_mtime_ns,before.st_mtime_ns)

if __name__=='__main__':unittest.main()
