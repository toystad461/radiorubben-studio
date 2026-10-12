const path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.RR_PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
const html=execFileSync(process.env.PHP_BIN||'php',[path.join(root,'tests/recovered/test-case-page.php'),'render-wp-status'],{encoding:'utf8'});
(async()=>{const browser=await chromium.launch(process.env.RR_BROWSER_CHANNEL?{channel:process.env.RR_BROWSER_CHANNEL}:{});try{
 for(const width of [390,768,1280]){const page=await browser.newPage({viewport:{width,height:844}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',route=>{const u=new URL(route.request().url());if(u.pathname.startsWith('/assets/'))return route.fulfill({path:path.join(root,'public',u.pathname)});return route.fulfill({contentType:'text/html',body:html});});
 await page.goto('https://fixture.test/case.php');
 const check=page.getByRole('button',{name:'Kontroller WordPress-status',exact:true});assert.equal(await check.count(),1);assert(await check.isEnabled());
 assert(await page.locator('#prepare-all').isDisabled());assert(await page.locator('#web-title').isDisabled());
 assert.equal(await page.getByRole('button',{name:'Lag nettsak på nytt',exact:true}).count(),0);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`overflow at ${width}`);
 assert.deepEqual(errors,[]);await page.close();
 }console.log('PASS WordPress status action remains available and unsafe actions disabled at 390/768/1280px');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
