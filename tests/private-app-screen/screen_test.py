"""Isolated PHP HTTP + offline Chromium screen checks; NOT a Safari E2E test."""
from pathlib import Path
from urllib.parse import urlsplit, urlencode
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
import http.client, json, re, base64, mimetypes, time, hashlib, os, sys
R=Path(os.environ['RR_SCREEN_TEST_DIR']).resolve()
W=R/'runtime'; OUT=R/'screenshots'; OUT.mkdir(exist_ok=True)
sessions=json.loads((R/'session-fixture.json').read_text())
board_before=hashlib.sha256((W/'config/sending-board.json').read_bytes()).hexdigest()
report={'source_head':os.environ.get('RR_SCREEN_SOURCE_SHA','working-tree'),'mode':'Isolated PHP HTTP responses; Chromium offline rendering with embedded assets. Browser navigation to localhost is blocked by policy. No policy is changed.','checks':[],'screens':[],'limitations':['No physical iPhone or Safari/WebKit run.','No real Microsoft login. Synthetic sessions only.','CSP headers checked over HTTP; renderer uses inline assets and does not exercise CSP.','No browser end-to-end navigation, installed PWA, session persistence after device sleep or true iOS keyboard.']}
def check(name,ok,detail=None):
 report['checks'].append({'name':name,'passed':bool(ok),'detail':detail}); print(('PASS ' if ok else 'FAIL ')+name, str(detail or '')[:180])
def request(path,sid='owner',method='GET',data=None):
 c=http.client.HTTPConnection('127.0.0.1',int((R/'port.txt').read_text()),timeout=12)
 headers={}
 if sid:headers['Cookie']='rubben_studio='+sessions.get(sid,sid)
 if data is not None:
  data=urlencode(data);headers['Content-Type']='application/x-www-form-urlencoded'
 c.request(method,path,body=data,headers=headers)
 res=c.getresponse();body=res.read();status=res.status;h=dict(res.getheaders());c.close()
 return status,body,h
assets={}
def asset_bytes(path):
 path=urlsplit(path).path
 if not path.startswith('/assets/') or '..' in path:raise ValueError('Nonlocal asset')
 if path not in assets:assets[path]=(W/'public'/path.lstrip('/')).read_bytes()
 return assets[path]
def embedded_css(path,seen=None):
 seen=set() if seen is None else set(seen)
 if path in seen:return ''
 seen.add(path)
 css=asset_bytes(path).decode()
 css=re.sub(r'@import\s+url\([\'\"]?([^\)\'\"]+)[\'\"]?\)\s*;',lambda m:embedded_css(m[1],seen),css)
 return css

def screen_capture(page,path,full_page=True):
 # Capture only the viewport: this Chromium's full-document capture resets
 # touch emulation. Geometry checks still inspect the complete document.
 before=page.evaluate("matchMedia('(pointer:coarse)').matches")
 page.screenshot(path=str(path),full_page=False)
 assert before == page.evaluate("matchMedia('(pointer:coarse)').matches"), 'Screenshot changed input emulation'

def render(page,path,tag=None,viewport=None,text_scale=1.0):
 page.goto('about:blank')
 status,body,headers=request(path)
 if status!=200: raise RuntimeError(f'{path}: HTTP {status} {body[:200]!r}')
 soup=BeautifulSoup(body.decode(),'html.parser')
 scripts=[]
 for link in list(soup.find_all('link')):
  if 'stylesheet' in (link.get('rel') or []):
   style=soup.new_tag('style');style.string=embedded_css(link['href']);link.replace_with(style)
  else:link.decompose()
 for img in soup.select('img[src]'):
  src=img['src'];p=urlsplit(src).path
  if p.startswith('/assets/'):
   img['src']='data:'+ (mimetypes.guess_type(p)[0] or 'application/octet-stream')+';base64,'+base64.b64encode(asset_bytes(src)).decode()
 for script in soup.select('script[src]'):
  src=script['src']
  if src.startswith('/assets/') and src.endswith(('.js','.mjs')) or src.startswith('/assets/') and '.js?' in src:
   scripts.append((src,asset_bytes(src).decode()))
  del script['src'];script['type']='application/x-rr-test-placeholder'
 if viewport:page.set_viewport_size(viewport)
 page.set_content(str(soup),wait_until='load')
 for src,content in scripts:
  if src.endswith('.mjs'):continue
  page.add_script_tag(content=content)
 # Capture link activation without bypassing the browser's network policy.
 page.evaluate("""()=>{window.__testLinks=[];document.addEventListener('click',e=>{const a=e.target.closest('a');if(a){e.preventDefault();window.__testLinks.push(a.getAttribute('href'));}},true);}""")
 if text_scale!=1:
  page.evaluate("""scale=>{const rules=[...document.querySelectorAll('body *')].filter(e=>!['SCRIPT','STYLE','SVG','PATH'].includes(e.tagName)).map(e=>[e,parseFloat(getComputedStyle(e).fontSize)]);rules.forEach(([e,n])=>{e.style.fontSize=(n*scale)+'px';});}""",text_scale)
 page.wait_for_timeout(120)
 metric=page.evaluate("""()=>{const w=innerWidth;const visible=e=>{const r=e.getBoundingClientRect(),s=getComputedStyle(e);return r.width>0&&r.height>0&&s.visibility!=='hidden'&&s.display!=='none';};return {width:w,scrollWidth:document.documentElement.scrollWidth,navVisible:!!document.querySelector('.rr-app-nav')&&visible(document.querySelector('.rr-app-nav')),overflow:[...document.querySelectorAll('main *,header *,nav')].filter(visible).filter(e=>{const r=e.getBoundingClientRect();return r.right>w+1||r.left < -1}).map(e=>({tag:e.tagName,id:e.id,cls:e.className,text:e.innerText?.slice(0,65),right:Math.round(e.getBoundingClientRect().right)})).slice(0,16),smallTargets:[...document.querySelectorAll('.rr-app-nav a,.rr-primary-action,.rr-mobile-grid a,.rr-mobile-cases a,main button,main summary,main select')].filter(visible).filter(e=>e.getBoundingClientRect().height<44).map(e=>({tag:e.tagName,text:e.innerText?.slice(0,55),height:+e.getBoundingClientRect().height.toFixed(1)})).slice(0,14),inputFontSizes:[...document.querySelectorAll('main input:not([type=hidden]):not([type=checkbox]),main textarea,main select')].filter(visible).map(e=>parseFloat(getComputedStyle(e).fontSize))};}""")
 if tag:
  screen_capture(page,OUT/(tag+'.png'))
  report['screens'].append({'name':tag,'path':path,'viewport':page.viewport_size,'textScale':text_scale,**metric})
 return metric

# Independent HTTP authorization/header checks against real PHP entry points.
for role in [None,'other','expired']:
 for path in ['/mobil.php','/control.php','/sending.php','/case.php?item=0000000000000001','/producer-api.php']:
  status,body,h=request(path,role)
  check(f'{role or "anonymous"}: {path} denies access',status in [303,401,403] and b'TESTUTKAST' not in body,{'http':status})
for path in ['/mobil.php','/control.php','/sending.php','/case.php?item=0000000000000001','/learning.php']:
 status,body,h=request(path)
 check(f'owner: {path} renders',status==200,{'http':status})
 check(f'no-store: {path}','no-store' in h.get('Cache-Control',''))
 check(f'CSP: {path}',"frame-ancestors 'none'" in h.get('Content-Security-Policy',''))
status,body,h=request('/app-manifest.php',None)
manifest=json.loads(body)
check('Manifest standalone and guarded start',status==200 and manifest.get('display')=='standalone' and manifest.get('start_url')=='/mobil.php')
check('Manifest has no synthetic private title',b'TESTUTKAST' not in body and b'Testredakt' not in body)
check('Landing rejects POST',request('/mobil.php',data={'action':'approve'},method='POST')[0]==405)

with sync_playwright() as p:
 browser=p.chromium.launch(**({'executable_path':os.environ['RR_SCREEN_BROWSER']} if os.environ.get('RR_SCREEN_BROWSER') else {}),headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
 report['browser']=browser.version
 report['screenshot_mode']='viewport; complete-document geometry measured separately'
 ctx=browser.new_context(viewport={'width':393,'height':852},device_scale_factor=1,is_mobile=True,has_touch=True,locale='nb-NO',color_scheme='dark',timezone_id='Europe/Oslo')
 ctx.route('**/*',lambda route:route.abort())
 page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 for label,size in [('iphone15pro',{'width':393,'height':852}),('smal',{'width':320,'height':568}),('landskap',{'width':852,'height':393}),('desktop',{'width':1280,'height':800})]:
  for name,path in [('hjem','/mobil.php'),('saker','/control.php'),('redigering','/case.php?item=0000000000000001'),('radio','/sending.php'),('laering','/learning.php')]:
   metric=render(page,path,f'{label}-{name}',size)
   check(f'{label} {name}: no horizontal overflow',metric['scrollWidth']<=size['width'],{'scrollWidth':metric['scrollWidth'],'viewport':size['width']})
   if label in ['iphone15pro','smal','landskap']:
    check(f'{label} {name}: mobile menu visible',metric['navVisible'])
    check(f'{label} {name}: controls at least 44px',not metric['smallTargets'],metric['smallTargets'])
   else:
    check(f'{label} {name}: desktop menu unchanged',not metric['navVisible'])
 # A short mouse-driven desktop window must not be classified as a phone.
 desktop_context=browser.new_context(viewport={'width':852,'height':393},is_mobile=False,has_touch=False)
 desktop_context.route('**/*',lambda route:route.abort())
 desktop_page=desktop_context.new_page()
 for name,path in [('hjem','/mobil.php'),('saker','/control.php'),('redigering','/case.php?item=0000000000000001'),('radio','/sending.php'),('laering','/learning.php')]:
  metric=render(desktop_page,path)
  check(f'Mouse desktop 852px {name}: no phone menu',not metric['navVisible'])
 desktop_context.close()
 # 200% font size is a stress simulation, not iOS Dynamic Type.
 for name,path in [('hjem','/mobil.php'),('redigering','/case.php?item=0000000000000001')]:
  metric=render(page,path,f'iphone15pro-stor-tekst-{name}',{'width':393,'height':852},2)
  check(f'200 percent font stress {name}: no horizontal overflow',metric['scrollWidth']<=393,{'scrollWidth':metric['scrollWidth']})
 # Capture actual button hit; independently request its rendered destination.
 for name,href in [('Saker','/control.php'),('Radio','/sending.php'),('Mer','/mobil.php#verktoy'),('Hjem','/mobil.php')]:
  render(page,'/mobil.php',viewport={'width':393,'height':852})
  page.get_by_role('navigation',name='Mobilmeny').get_by_role('link',name=name,exact=True).click()
  target=page.evaluate('window.__testLinks.at(-1)')
  check('Footer click '+name,target==href,{'href':target})
  route=urlsplit(target).path
  check('HTTP destination '+name,request(route)[0]==200)
 render(page,'/mobil.php',viewport={'width':393,'height':852})
 page.locator('.rr-mobile-cases a').first.click()
 href=page.evaluate('window.__testLinks.at(-1)')
 check('Case link selects original case',href=='/case.php?item=0000000000000001' and b'TESTUTKAST' in request(href)[1])
 render(page,'/mobil.php',viewport={'width':393,'height':852})
 page.locator('.rr-install-help summary').click()
 check('Install guidance opens',page.locator('.rr-install-help').get_attribute('open') is not None)
 page.evaluate("window.dispatchEvent(new Event('offline'))")
 check('Offline message visible',page.locator('#rr-network-message').is_visible() and 'frakoblet' in page.locator('#rr-network-message').inner_text())
 page.evaluate("window.dispatchEvent(new Event('online'))")
 check('Reconnect message does not claim server sync','Kontroller' in page.locator('#rr-network-message').inner_text())
 render(page,'/case.php?item=0000000000000001',viewport={'width':393,'height':852})
 field=page.locator('#radio-script');field.click();field.fill('TEST: Ulagret rettelse. Denne må bevares.\n\nNytt avsnitt med æ, ø og å.')
 check('Dirty editor requests leave warning',page.evaluate("()=>{const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented;}"))
 original_value=field.input_value()
 page.set_viewport_size({'width':393,'height':430})
 field.scroll_into_view_if_needed()
 page.screenshot(path=str(OUT/'iphone15pro-lav-visning-redigering.png'),full_page=False)
 check('Reduced viewport preserves textarea value',field.input_value()==original_value)
 # Simulate request failure without any HTTP write or generation.
 page.evaluate("()=>{window.fetch=async()=>{throw new TypeError('Syntetisk frakobling');};}")
 page.locator('[data-save=radio] button').click()
 page.wait_for_timeout(80)
 check('Failed save keeps typed text',field.input_value()==original_value)
 check('Failed save displays error','Syntetisk frakobling' in page.locator('#case-progress').inner_text())
 check('Failed save unlocks editor',not field.get_attribute('readonly'))
 page.screenshot(path=str(OUT/'iphone15pro-feilet-lagring.png'),full_page=False)
 check('No uncaught JavaScript errors',not errors,errors)
 # View login screen in offline renderer: no real Microsoft sign-in.
 status,body,h=request('/login.php',None)
 check('Anonymous login shows no private cases',status==200 and b'TESTUTKAST' not in body)
 browser.close()

check('Logout rejects incorrect CSRF',request('/logout.php',method='POST',data={'csrf':'wrong'})[0]==403)
check('Incorrect CSRF does not terminate session',request('/mobil.php')[0]==200)
check('Logout accepts correct CSRF',request('/logout.php',method='POST',data={'csrf':'c'*64})[0]==303)
check('Access denied after logout',request('/mobil.php')[0]==303)
check('Synthetic editorial worklist was not changed',hashlib.sha256((W/'config/sending-board.json').read_bytes()).hexdigest()==board_before)
before=json.loads((R/'original-runtime-hashes.json').read_text())
changed=[f for f,h in before.items() if hashlib.sha256((W/f).read_bytes()).hexdigest()!=h]
check('No runtime files modified during testing',not changed,changed)
report['summary']={'passed':sum(c['passed'] for c in report['checks']),'failed':sum(not c['passed'] for c in report['checks']),'screens':len(report['screens'])}
(R/'screen-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2))
print('SUMMARY',report['summary'])

if report['summary']['failed']:
 sys.exit(1)
