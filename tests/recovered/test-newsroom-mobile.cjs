const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'../..');
const render=mode=>execFileSync(process.env.PHP_BIN||'php',[path.join(root,'tests/fixtures/mobile-newsroom.php'),mode||''],{encoding:'utf8'});
const initial=render(),next=render('next');
(async()=>{
 const browser=await chromium.launch(process.env.CHROMIUM_BIN?{executablePath:process.env.CHROMIUM_BIN,args:['--no-sandbox','--disable-dev-shm-usage','--use-gl=angle','--use-angle=swiftshader','--enable-unsafe-swiftshader']}:{});
 try{
  for(const width of [375,390,800,1280]){
   const page=await browser.newPage({viewport:{width,height:844},deviceScaleFactor:1});let posts=0,documents=0,fail=false,networkFail=false,active=initial;
   const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname.startsWith('/assets/'))return route.fulfill({path:path.join(root,'public',url.pathname),contentType:url.pathname.endsWith('.css')?'text/css':'text/javascript'});
    if(url.pathname==='/fixture-image.svg')return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="960" height="540"><rect width="960" height="540" fill="#182235"/><text x="480" y="275" text-anchor="middle" fill="white" font-size="60" font-family="sans-serif">RADIO RUBBEN</text></svg>'});
    if(url.pathname==='/newsdesk.php'){
     if(request.method()==='POST'){
      posts++;assert.match(request.postData(),/name="confirmed"\r\n\r\n1/);assert.match(request.postData(),/fixture-token/);assert.match(request.postData(),/fixture-csrf/);
      if(networkFail)return route.abort();
      if(fail)return route.fulfill({status:409,json:{ok:false,error:'Saken er endret. Kontroller status.'}});
      active=next;
     }else if(!url.searchParams.has('view')){documents++;return route.fulfill({contentType:'text/html',body:initial});}
     return route.fulfill({json:{ok:true,html:active,url:'/newsdesk.php?filter=ready&item=wp%3A2',selected:'wp:2',advance:true}});
    }
    return route.fulfill({status:404,body:''});
   });
   await page.goto('http://fixture.test/newsdesk.php');
   const geometry=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,queue:document.querySelector('#nr-queue').open,sidebar:getComputedStyle(document.querySelector('.sidebar')).display,title:document.querySelector('.nr-title').getBoundingClientRect().top,dock:document.querySelector('.nr-dock').getBoundingClientRect().bottom}));
   assert.equal(geometry.overflow,false,'No horizontal scrolling at '+width);
   assert.equal(geometry.queue,width>800,'Queue responds to mobile width');
   if(width<=800){assert.equal(geometry.sidebar,'none');assert.ok(geometry.title<400,'Article starts above the fold');assert.ok(geometry.dock<=844&&geometry.dock>800,'Approval dock stays in viewport');}
   const primary=page.locator('.nr-approve .nr-primary');assert.equal(await primary.isDisabled(),true,'Explicit read confirmation required');
   await page.locator('[data-open-changes]').click();assert.equal(await page.locator('#nr-changes').getAttribute('open'),'');
   await page.locator('[name=comment]').fill('Gjør ingressen kortere');
   if(width<=800)assert.equal(await page.locator('.nr-dock').isVisible(),false,'Keyboard editing hides action dock');
   page.once('dialog',dialog=>dialog.dismiss());await page.locator('.nr-refresh').click();assert.equal(await page.locator('[name=comment]').inputValue(),'Gjør ingressen kortere','Cancelled navigation preserves text');
   await page.locator('[name=confirmed]').check();
   fail=true;await primary.click();await page.locator('#newsroom-progress').filter({hasText:'Saken er endret'}).waitFor();assert.equal(await page.locator('[name=comment]').inputValue(),'Gjør ingressen kortere','Conflict keeps entered text');
   fail=false;networkFail=true;await primary.click();await page.locator('[data-newsroom-reload]').waitFor();assert.equal(await primary.isDisabled(),true,'Unknown result prevents duplicate post');
   networkFail=false;page.once('dialog',dialog=>dialog.accept());await page.locator('[data-newsroom-reload]').click();await page.waitForFunction(()=>document.querySelector('#newsroom-progress').hidden);
   await page.locator('[name=confirmed]').check();await primary.click();await page.locator('.nr-title').filter({hasText:'Neste eksempelsak'}).waitFor();
   assert.equal(documents,1,'Actions replace fragment without a page reload');assert.equal(posts,3);assert.equal(await page.locator('[name=confirmed]').isChecked(),false,'Next story requires fresh approval');assert.equal(await primary.isDisabled(),true);
   assert.deepEqual(errors,[]);
   if(width===390){await page.goto('http://fixture.test/newsdesk.php');await page.screenshot({path:process.env.MOBILE_SCREENSHOT||'/tmp/newsroom-mobile.png',fullPage:false});}
   await page.close();console.log('PASS mobile desk '+width+'px: one-page approval, rewrite, conflict and network recovery');
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
