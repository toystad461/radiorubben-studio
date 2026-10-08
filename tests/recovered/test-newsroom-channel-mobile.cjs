const path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'../..');
(async()=>{
 const browser=await chromium.launch();
 try{for(const mode of ['radio','both'])for(const width of [375,390,1280]){
  const html=execFileSync(process.env.PHP_BIN||'php',[path.join(root,'tests/fixtures/mobile-newsroom.php'),mode],{encoding:'utf8'});
  const page=await browser.newPage({viewport:{width,height:844}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',route=>{
   const u=new URL(route.request().url());
   if(u.pathname.startsWith('/assets/'))return route.fulfill({path:path.join(root,'public',u.pathname)});
   return route.fulfill({contentType:'text/html',body:html});
  });
  await page.goto('http://fixture.test/newsdesk.php');
  assert.equal(await page.locator('.nr-channel-flow').count(),1);
  assert.match(await page.locator('.nr-channel-flow').innerText(),/Radio.*Manus trenger kontroll/s);
  assert.match(await page.locator('.nr-channel-flow .nr-primary').getAttribute('href'),/#radio-material$/);
  if(mode==='radio')assert.equal(await page.locator('[name=action][value=approve], [name=action][value=revise]').count(),0);
  await page.locator('.nr-inbox summary').click();
  await page.locator('select[name=channel]').selectOption('radio');
  assert.equal(await page.locator('select[name=channel]').evaluate(el=>new FormData(el.form).get('channel')),'radio');
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  assert.deepEqual(errors,[]);
  if(width===390)await page.screenshot({path:path.join(require('node:os').tmpdir(),'rr-desk-'+mode+'-390.png'),fullPage:true});
  await page.close();console.log('PASS channel desk '+mode+' '+width);
 }}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
