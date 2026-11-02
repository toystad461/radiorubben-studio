import urllib.request,urllib.parse,urllib.error,http.cookiejar,re,json,pathlib,subprocess,ssl
root=pathlib.Path('/srv/radiorubben-test');private=root/'rr-red-e2e-001'
credentials=json.loads((private/'credentials.json').read_text());case=credentials['case_id'];base='https://5.249.252.78'
jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(jar))
results=[]
def request(path,data=None):
    assert path.startswith('/') and not path.startswith('//')
    req=urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None)
    try:
        with opener.open(req,timeout=35) as r:return r.status,r.read().decode(),r.geturl()
    except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.url
def hidden(html,name):
    return re.search(r'name="'+name+r'"[^>]*value="([^"]*)"',html).group(1)
def record(label,ok,detail=None):
    results.append({'test':label,'passed':bool(ok),'detail':detail});print(json.dumps(results[-1],ensure_ascii=True),flush=True)
    (private/'http-results.json').write_text(json.dumps(results,indent=2))
    assert ok,label
status,html,url=request('/local/login.php');csrf=hidden(html,'csrf')
status,html,url=request('/local/login.php',{'csrf':csrf,'email':credentials['email'],'password':credentials['password']})
record('Normal Studio-login',status==200 and '/login' not in url,{'status':status})
def state():
    status,html,url=request('/case.php?item='+case)
    assert status==200 and 'name="revision"' in html,(status,url,html[:200])
    return hidden(html,'csrf'),int(hidden(html,'revision')),html
def action(label,name,expected=200,extra=None,stale=False,badcsrf=False):
    csrf,rev,html=state();data={'csrf':'invalid' if badcsrf else csrf,'id':case,'revision':rev-1 if stale else rev,'action':name,'response':'json'};data.update(extra or {})
    status,body,_=request('/case.php',data)
    try:result=json.loads(body)
    except ValueError:result={'body':body[:100]}
    record(label,status==expected,{'status':status,'response':result});return result
lines=['TEST – syntetisk redaksjonell prøve','Dette er oppdiktede testdata for Radio Rubbens isolerte testmiljø.','Testprotokollen inneholder tre kontrollpunkter og beskriver ingen virkelig hendelse.']
payload=dict(zip(['title','intro','body'],lines))
action('CSRF avvises','save_web',403,payload,badcsrf=True)
action('Lagre syntetisk utkast','save_web',extra=payload)
action('Foreldet revisjon avvises','save_web',409,payload,stale=True)
action('Publisering uten kontroll/godkjenning avvises','wp_publish',409)
action('Ekte WordPress-kladd opprettes','wp_draft')
action('Feil faktapåstand lagres for negativ kontroll','save_web',extra=dict(payload,body='Testprotokollen inneholder ni kontrollpunkter.'))
result=action('Syntetisk kildekontroll oppdager avvik','check_web');record('Avvik gir ikke kontrollert-status',result['webChecked'] is False)
action('Avvik kan ikke godkjennes','approve_publish',409,{'confirmed':'1'})
action('Rettelse lagres','save_web',extra=payload)
result=action('Kildekontroll med ordrette belegg','check_web');record('Korrekt testgrunnlag godtas',result['webChecked'] is True)
action('Kontroll alene gir ikke publisering','wp_publish',409)
action('Bekreftelsesfelt kreves','approve_publish',409)
action('Godkjenning registreres i test','approve_web',extra={'confirmed':'1'})
result=action('Endring etter godkjenning opphever kontroll','save_web',extra=dict(payload,body=lines[2]+' Endret tekst.'));record('Endret innhold er ukontrollert',result['webChecked'] is False)
action('Tidligere godkjenning kan ikke gjenbrukes','wp_publish',409)
action('Gjenopprett korrekt testtekst','save_web',extra=payload)
action('Ny kildekontroll','check_web')
action('Sluttgodkjenn og publiser syntetisk test','approve_publish',extra={'confirmed':'1'})
action('Gjentatt publisering uten duplikat','wp_publish')
action('Faktisk WordPress-status leses tilbake','wp_status')
print('HTTP_FLOW_COMPLETE')
