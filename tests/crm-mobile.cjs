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
 const browser=await chromium.launch();
 try{
  for(const width of [320,390,800,1280]){
   const page=await browser.newPage({viewport:{width,height:844}}),errors=[];
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
   assert.deepEqual(errors,[]);await page.close();
   console.log('PASS CRM layout '+width+'px: list, detail, create and mobile navigation');
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1}).finally(()=>fs.rmSync(temp,{recursive:true,force:true}));
