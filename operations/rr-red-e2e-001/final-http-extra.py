# Appended to run.py's login/action helpers for final installed-code checks.
action('Final kode: gjentatt publisering uten duplikat','wp_publish')
action('Final kode: WordPress-status','wp_status')
status,html,url=request('/e2e-source.php');record('Syntetisk kilde kan åpnes med innlogging',status==200 and 'ingen virkelig hendelse' in html)
anonymous=urllib.request.build_opener(urllib.request.ProxyHandler({}))
try:
    anonymous.open(base+'/wordpress/index.php?rest_route=/wp/v2/posts',timeout=10);anon_status=200
except urllib.error.HTTPError as e:anon_status=e.code
record('Anonym REST-tilgang er sperret',anon_status==401,anon_status)
# Normal WordPress password login for a frontend read; no application password leaves the private socket.
wpjar=http.cookiejar.CookieJar();wpopener=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(wpjar))
wpopener.open(base+'/wordpress/wp-login.php',timeout=15).read()
data=urllib.parse.urlencode({'log':'rr-red-e2e-001','pwd':credentials['wp_password'],'wp-submit':'Log In','redirect_to':base+'/wordpress/?p=4','testcookie':'1'}).encode()
with wpopener.open(urllib.request.Request(base+'/wordpress/wp-login.php',data=data),timeout=15) as r:html=r.read().decode();url=r.geturl()
record('Publisert testsak leses i WordPress-frontenden', 'TEST – syntetisk redaksjonell prøve' in html and 'KI-støtte' in html and 'e2e-source.php' in html,{'url':url})
print('FINAL_HTTP_COMPLETE')
