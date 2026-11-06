import pathlib, shutil, tempfile, subprocess, hashlib, json, xml.etree.ElementTree as ET
p=pathlib.Path('/etc/nginx/conf.d/rr-test-tls.conf')
i=pathlib.Path('/srv/radiorubben-test/stream-runtime/icecast.xml')
old=p.read_bytes(); ice=i.read_bytes()
assert b'location = /radio.mp3' not in old
assert old.count(b'    location = /wordpress ')==1
root=ET.fromstring(ice); assert root.findtext('limits/clients')=='5'
b=pathlib.Path(tempfile.mkdtemp(prefix='public-listener-',dir='/var/lib/rr-vps-deploy'))
shutil.copy2(p,b/'nginx.conf'); shutil.copy2(i,b/'icecast.xml')
route=b'''    # Public read-only audio; Studio authentication remains on its own routes.
    location = /radio.mp3 {
        limit_except GET { deny all; }
        proxy_pass http://127.0.0.1:18980/rubben-test.mp3;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Cookie "";
        proxy_set_header Authorization "";
        proxy_buffering off;
        proxy_request_buffering off;
        proxy_read_timeout 30s;
        proxy_hide_header Server;
        add_header Cache-Control "no-store" always;
        add_header X-Accel-Buffering "no" always;
        add_header X-Robots-Tag "noindex, nofollow, noarchive" always;
        add_header Access-Control-Allow-Origin "*" always;
    }
'''
new=old.replace(b'    location = /wordpress ',route+b'    location = /wordpress ')
newice=ice.replace(b'<clients>5</clients>',b'<clients>100</clients>')
assert ET.fromstring(newice).findtext('limits/clients')=='100'
try:
    p.write_bytes(new); i.write_bytes(newice)
    subprocess.run(['nginx','-t'],check=True,capture_output=True)
    subprocess.run(['systemctl','kill','--kill-who=main','--signal=HUP','rr-studio-icecast'],check=True)
    subprocess.run(['systemctl','reload','nginx'],check=True)
except Exception:
    p.write_bytes(old); i.write_bytes(ice)
    subprocess.run(['systemctl','kill','--kill-who=main','--signal=HUP','rr-studio-icecast'])
    subprocess.run(['systemctl','reload','nginx'])
    raise
receipt={'backup':str(b),'url':'https://5.249.252.78/radio.mp3','clientLimit':100,'files':[{'path':str(f),'before':hashlib.sha256(before).hexdigest(),'after':hashlib.sha256(f.read_bytes()).hexdigest()} for f,before in [(p,old),(i,ice)]]}
(b/'receipt.json').write_text(json.dumps(receipt,indent=2))
print(json.dumps(receipt))
