const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),tmp=fs.mkdtempSync(path.join(os.tmpdir(),'crm-demo-mobile-'));
(async()=>{let browser;try{
 const file=path.join(tmp,'outreach.html');execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'crm-outreach-page.php'),'view'],{env:{...process.env,CRM_OUTREACH_PREVIEW:file}});
 const newFile=path.join(tmp,'new.html');execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'crm-page.php'),'new'],{env:{...process.env,CRM_PREVIEW_FILE:newFile}});
 browser=await chromium.launch();
 for(const width of [320,390,800,1280]){
  const page=await browser.newPage({viewport:{width,height:900}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',r=>{const u=new URL(r.request().url());
   if(u.pathname==='/crm-register.php')return r.fulfill({contentType:'application/json',body:JSON.stringify({fields:{company:'Fiktiv AS',orgNumber:'999999999',website:'https://example.test',industry:'Test',businessAddress:'Testvegen 1',organizationForm:'AS'}})});
   if(u.pathname.startsWith('/assets/'))return r.fulfill({path:path.join(root,'public',u.pathname)});
   return r.fulfill({contentType:'text/html',body:fs.readFileSync(u.pathname==='/crm.php'?newFile:file,'utf8')});
  });
  await page.goto('https://crm.test/crm.php');await page.locator('[name=company]').fill('Eget navn');await page.locator('[name=orgNumber]').fill('999999999');await page.getByRole('button',{name:'Hent fra Brønnøysundregistrene'}).click();
  await page.getByText(/Hentet Fiktiv AS/).waitFor({state:'visible'});
  assert.equal(await page.locator('[name=company]').inputValue(),'Eget navn');assert.equal(await page.locator('[name=website]').inputValue(),'https://example.test');
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Lookup fits '+width);
  await page.goto('https://crm.test/crm-outreach.php');
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Proposal fits '+width);
  assert.equal(await page.getByRole('textbox',{name:'Introduksjonsmail',exact:true}).isVisible(),true);
  assert.equal(await page.getByRole('button',{name:'Last ned Outlook-utkast med lydvedlegg'}).count(),0,'Unapproved export hidden');
  assert.deepEqual(errors,[]);await page.close();console.log('PASS CRM lookup and proposal '+width+'px');
 }
}finally{if(browser)await browser.close();fs.rmSync(tmp,{recursive:true,force:true});}})().catch(e=>{console.error(e);process.exitCode=1;});
