const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'../..');
const render=(view,role='admin',channel='radio',surface='')=>execFileSync(process.env.PHP_BIN||'php',[path.join(root,'tests/fixtures/learning-page.php'),view,role,channel,surface],{encoding:'utf8'});
(async()=>{
 const browser=await chromium.launch(process.env.LEARNING_BROWSER_CHANNEL?{channel:process.env.LEARNING_BROWSER_CHANNEL}:{});
 try{
  for(const width of [390,1280]){
   const page=await browser.newPage({viewport:{width,height:900}});
   await page.route('**/*',route=>{
    const url=new URL(route.request().url());
    if(['/learning.php','/learning-rules.php'].includes(url.pathname))return route.fulfill({contentType:'text/html',body:render(url.searchParams.get('view')||'pending',url.searchParams.get('role')||'admin',url.searchParams.get('mode')||url.searchParams.get('channel')||'radio',url.pathname==='/learning-rules.php'?'rules':'')});
    const local=path.join(root,'public',url.pathname);
    if(url.pathname.startsWith('/assets/')&&fs.existsSync(local))return route.fulfill({body:fs.readFileSync(local),contentType:url.pathname.endsWith('.css')?'text/css':'image/png'});
    return route.fulfill({status:404,body:''});
   });
   await page.goto('http://fixture.test/learning.php');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'No horizontal overflow');
   assert.equal(await page.locator('.learning-steps li').count(),0);
   assert.equal(await page.locator('#learning-item option').count(),2,'General rule plus corrected manuscript');
   assert.equal(await page.locator('#learning-rules article').count(),0,'Learning page has no rule list');
   await page.getByRole('link',{name:'Se og godkjenn forslag (1)'}).click();
   assert.equal(await page.locator('#learning-rules article').count(),1,'Pending list excludes other programs');
   assert.equal(await page.locator('#learning-rules button').filter({hasText:/^Godkjenn$/}).count(),1);
   assert.equal(await page.locator('script').count(),0,'Evidence is escaped');
   await page.locator('.learning-tabs a').filter({hasText:'Aktive'}).click();
   assert.equal(await page.locator('#learning-rules article').count(),1);
   assert.equal(await page.locator('#learning-rules button').filter({hasText:'Fjern fra bruk'}).count(),1);
   await page.locator('.learning-tabs a').filter({hasText:'Historikk'}).click();
   assert.equal(await page.locator('#learning-rules button').count(),0);
   await page.goto('http://fixture.test/learning.php?channel=web');
   assert.equal(await page.locator('#learning-channel').inputValue(),'web');
   assert.equal(await page.locator('#learning-item option').count(),2,'Web correction plus general rule');
   assert.match(await page.locator('.learning-comparison').first().innerText(),/Et tidligere nettutkast/);
   assert.equal(await page.locator('input[name=channel]').inputValue(),'web','Proposal binds web correction channel');
   await page.goto('http://fixture.test/learning.php?view=pending&role=observer');
   assert.equal(await page.locator('#rule-text').count(),0);
   assert.equal(await page.locator('#learning-rules button').count(),0,'Observer cannot decide');
   await page.goto('http://fixture.test/learning.php?mode=general');
   const instructions=await page.locator('#rule-text').inputValue();
   assert.equal(instructions,'','No already-active rules are prefilled');
   assert.equal(await page.getByRole('button',{name:'Lagre forslag og gå til godkjenning'}).isVisible(),true);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Direct instructions fit mobile');
   if(process.env.LEARNING_SCREENSHOT_DIR){fs.mkdirSync(process.env.LEARNING_SCREENSHOT_DIR,{recursive:true});await page.screenshot({path:path.join(process.env.LEARNING_SCREENSHOT_DIR,`learning-${width}.png`),fullPage:true});}
   await page.close();console.log(`PASS learning flow ${width}px: correction selection, approval lists and observer view`);
  }
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
