const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'crm-layout-'));
const render=mode=>{
 const file=path.join(temp,mode+'.html');
 execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'crm-page.php'),mode],{env:{...process.env,CRM_PREVIEW_FILE:file}});
 return fs.readFileSync(file,'utf8');
};
(async()=>{
 const pages=Object.fromEntries(['list','get','new'].map(mode=>[mode,render(mode)]));
 const browser=await chromium.launch(process.env.CHROMIUM_BIN?{executablePath:process.env.CHROMIUM_BIN}:{});
 try{
  for(const width of [320,390,800,1280]){
   const page=await browser.newPage({viewport:{width,height:844},hasTouch:width<=800}),errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   await page.route('**/*',r=>{
    const url=new URL(r.request().url());
    if(url.pathname.startsWith('/assets/'))return r.fulfill({path:path.join(root,'public',url.pathname)});
    return r.fulfill({contentType:'text/html',body:pages[url.searchParams.get('mode')]||pages.list});
   });
   for(const mode of ['list','get','new']){
    await page.goto('http://crm.test/crm.php?mode='+mode);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,mode+' overflow at '+width);
    if(mode==='get'&&width<=800){assert.equal(await page.locator('.crm-list').isVisible(),false);assert.equal(await page.locator('.crm-back').isVisible(),true);}
    if(mode==='list'){assert.equal(await page.locator('.crm-list').isVisible(),true);await page.locator('[name=q]').fill('Test');}
    if(mode==='new'){
     await page.locator('[name=company]').fill('Eksempelbedriften AS');
     await page.locator('[name=nextStep]').fill('Avtal et møte');
     assert.equal(await page.locator('[name=company]').inputValue(),'Eksempelbedriften AS');
     assert.equal(await page.locator('[name=oneDriveUrl]').count(),1);
    }
   }
   // Real page and JS, synthetic register responses; no company data is written.
   let searches=0, selected='';
   await page.route('**/crm-register.php',async route=>{
    searches++;const term=new URLSearchParams(route.request().postData()).get('query');
    if(term==='Slow')await new Promise(resolve=>setTimeout(resolve,900));
    if(term==='Error')return route.fulfill({status:503,json:{error:'Søket mislyktes. Prøv igjen.'}});
    const hits=term==='None'?[]:[{value:'card:aaaaaaaaaaaaaaaa',label:term+' AS · Allerede registrert · Aktiv partner · 999999999 · Bømlo'},{value:'org:974760673',label:term+' Ny AS · 974760673 · Stord · Ny potensiell kunde'}];
    return route.fulfill({json:{results:hits,warning:term==='Partial'?'Registeret er utilgjengelig. Viser CRM.':'',more:false}});
   });
   await page.route('**/crm.php',async route=>{
    if(route.request().method()==='POST'){
     const data=new URLSearchParams(route.request().postData());
     assert.equal(data.get('action'),'select_company');assert.equal(data.get('csrf'),'fixture-token');selected=data.get('choice');
     return route.fulfill({contentType:'text/html',body:'<p>Valgt</p>'});
    }
    return route.fulfill({contentType:'text/html',body:pages.list});
   });
   // Simulate a transport that ignores cancellation: sequence protection must still work.
   await page.addInitScript(()=>{const original=window.fetch;window.fetch=(url,options)=>original(url,String(url).includes('crm-register.php')?{...options,signal:undefined}:options);});
   await page.goto('http://crm.test/crm.php');
   const input=page.getByRole('combobox',{name:'Søk på bedriftsnavn'}),options=page.locator('#company-results [role=option]'),status=page.locator('#company-search-status');
   await input.fill('F');await page.waitForTimeout(350);assert.equal(searches,0);
   await input.fill('Fi');await page.waitForTimeout(100);await input.fill('Fiktiv');
   await options.first().waitFor();assert.equal(searches,1,'rapid typing is debounced');
   assert.match(await options.first().textContent(),/Aktiv partner.*999999999.*Bømlo/);
   if(width===390&&process.env.CRM_SEARCH_SCREENSHOT)await page.screenshot({path:process.env.CRM_SEARCH_SCREENSHOT,fullPage:true});
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'results fit mobile');
   await input.press('ArrowDown');assert.equal(await options.first().getAttribute('aria-selected'),'true');
   await input.press('ArrowDown');await input.press('ArrowUp');await input.press('Enter');
   await page.getByText('Valgt',{exact:true}).waitFor();assert.equal(selected,'card:aaaaaaaaaaaaaaaa','keyboard selects existing card');
   await page.goto('http://crm.test/crm.php');await input.fill('Ny');await options.last().waitFor();if(width<=800)await options.last().tap();else await options.last().click();
   await page.getByText('Valgt',{exact:true}).waitFor();assert.equal(selected,'org:974760673','click continues new registration');
   await page.goto('http://crm.test/crm.php');await input.fill('Slow');
   await status.filter({hasText:'Søker etter'}).waitFor();await input.fill('Fast');
   await options.first().filter({hasText:'Fast AS'}).waitFor();await page.waitForTimeout(1000);
   assert.match(await options.first().textContent(),/^Fast AS/,'older response cannot overwrite newer search');
   await input.press('Escape');assert.equal(await input.getAttribute('aria-expanded'),'false');assert.equal(await options.count(),0);
   await input.fill('Slow');await status.filter({hasText:'Søker etter'}).waitFor();await input.fill('');await page.waitForTimeout(1100);assert.equal(await options.count(),0,'clearing invalidates pending response');
   await input.fill('None');await status.filter({hasText:'Ingen treff'}).waitFor();
   await input.fill('Error');await status.filter({hasText:'Søket mislyktes'}).waitFor();
   await input.fill('Partial');await status.filter({hasText:'Registeret er utilgjengelig'}).waitFor();assert.equal(await options.count(),2,'warning retains available results');
   await input.press('ArrowUp');assert.equal(await options.last().getAttribute('aria-selected'),'true');
   await input.press('Tab');assert.equal(await input.getAttribute('aria-expanded'),'false','tab closes results');
   await page.goto('http://crm.test/crm.php?mode=get');
   await page.locator('[name=company]').fill('Ulagret bedriftsnavn');await input.fill('Fiktiv');await options.first().waitFor();
   page.once('dialog',dialog=>dialog.dismiss());await options.first().click();
   assert.equal(await page.locator('[name=company]').inputValue(),'Ulagret bedriftsnavn','cancelled selection preserves unsaved card');
   assert.deepEqual(errors,[]);await page.close();
   console.log('PASS CRM layout '+width+'px: autocomplete, keyboard/touch selection, out-of-order replies, empty/error states and unsaved edits');
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1}).finally(()=>fs.rmSync(temp,{recursive:true,force:true}));
