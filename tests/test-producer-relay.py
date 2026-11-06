import importlib.util,pathlib,unittest,json,io,os,socket,threading,tempfile,urllib.request
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('relay',pathlib.Path(__file__).resolve().parents[1]/'infra/studio-stream/producer-relay.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class Tests(unittest.TestCase):
 def test_boundary(self):
  with tempfile.TemporaryDirectory()as d:
   path=d+'/api.sock';server=m.Server(path,m.Relay);thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
   def call(payload,path='/v1/responses',auth='Bearer synthetic'):
    body=json.dumps(payload).encode();s=socket.socket(socket.AF_UNIX);s.connect(d+'/api.sock');s.sendall(('POST '+path+' HTTP/1.0\r\nContent-Length: '+str(len(body))+'\r\nAuthorization: '+auth+'\r\n\r\n').encode()+body);chunks=[]
    while True:
     b=s.recv(65536)
     if not b:break
     chunks.append(b)
    s.close();return b''.join(chunks)
   payload={'model':'gpt-5.4-mini-2026-03-17','store':False,'max_output_tokens':700,'input':'synthetic'}
   class Response(io.BytesIO):status=200
   class Opener:
    def open(self,req,timeout):
     assert req.full_url=='https://api.openai.com/v1/responses';assert timeout==32;return Response(b'{"status":"completed"}')
   try:
    with patch.object(m.urllib.request,'build_opener',return_value=Opener()):
     self.assertIn(b'200 OK',call(payload))
     for bad in [dict(payload,store=True),dict(payload,tools=[]),dict(payload,model='other'),dict(payload,max_output_tokens=99999)]:self.assertIn(b'400 Bad Request',call(bad))
     self.assertIn(b'400 Bad Request',call(payload,'https://evil.invalid/'))
     self.assertIn(b'400 Bad Request',call(payload,auth='bad'))
   finally:server.shutdown();server.server_close();thread.join()
if __name__=='__main__':unittest.main()
