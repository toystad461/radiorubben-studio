#!/usr/bin/env python3
"""Private Unix relay: only bounded, non-stored text Responses; no arbitrary URLs."""
import http.server,socketserver,socket,struct,os,json,urllib.request,urllib.error
SOCKET='/srv/radiorubben-test/studio-data/producer-relay/api.sock'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
class Relay(http.server.BaseHTTPRequestHandler):
    def log_message(self,*args):pass
    def setup(self):
        self.request.settimeout(40);super().setup()
    def do_POST(self):
        try:
            pid,uid,gid=struct.unpack('3i',self.connection.getsockopt(socket.SOL_SOCKET,socket.SO_PEERCRED,12))
            if uid!=os.getuid() or self.path!='/v1/responses':raise ValueError()
            n=int(self.headers.get('Content-Length','0'))
            if not 0<n<=60000:raise ValueError()
            payload=json.loads(self.rfile.read(n));auth=self.headers.get('Authorization','')
            if not auth.startswith('Bearer ') or len(auth)>1024 or '\r' in auth or '\n' in auth:raise ValueError()
            if not isinstance(payload,dict) or set(payload)-{'model','store','max_output_tokens','instructions','input','text','reasoning'}:raise ValueError()
            if payload.get('store') is not False or not isinstance(payload.get('input'),str):raise ValueError()
            if not isinstance(payload.get('max_output_tokens'),int) or not 1<=payload['max_output_tokens']<=2000:raise ValueError()
            if payload.get('model')!='gpt-5.4-mini-2026-03-17':raise ValueError()
            body=json.dumps(payload).encode()
            req=urllib.request.Request('https://api.openai.com/v1/responses',data=body,headers={'Content-Type':'application/json','Authorization':auth},method='POST')
            try:
                with urllib.request.build_opener(NoRedirect).open(req,timeout=32)as response:status=response.status;result=response.read(262145)
            except urllib.error.HTTPError as error:status=error.code;result=b'{"error":{"message":"AI request rejected"}}'
            if len(result)>262144:raise ValueError()
        except ValueError:status=400;result=b'{"error":{"message":"Invalid relay request"}}'
        except Exception:status=502;result=b'{"error":{"message":"AI connection unavailable"}}'
        self.send_response(status);self.send_header('Content-Type','application/json');self.send_header('Content-Length',str(len(result)));self.end_headers();self.wfile.write(result)
class Server(socketserver.UnixStreamServer):pass
if __name__=='__main__':
    os.umask(0o077)
    if os.path.exists(SOCKET):os.unlink(SOCKET)
    with Server(SOCKET,Relay)as server:server.serve_forever()
