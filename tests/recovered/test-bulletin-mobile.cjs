const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'../..');
(async()=>{const browser=await chromium.launch();try{
 for(const mode of ['render-choices','render-bulletin']){
  const html=execFileSync('php',[path.join(__dirname,'test-news-page.php'),mode],{encoding:'utf8'});
  for(const width of [375,390,1280]){
   const page=await browser.newPage({viewport:{width,height:900}});
   await page.route('**/*',route=>{const u=new URL(route.request().url());if(u.pathname==='/sending.php')return route.fulfill({contentType:'text/html',body:html});const f=path.join(root,'public',u.pathname);if(u.pathname.startsWith('/assets/')&&fs.existsSync(f))return route.fulfill({body:fs.readFileSync(f),contentType:u.pathname.endsWith('.css')?'text/css':'image/png'});return route.fulfill({status:404,body:''});});
   await page.goto('http://fixture.test/sending.php');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'no horizontal overflow');
   assert.equal(await page.getByRole('button',{name:'Lag nyhetssending',exact:true}).count(),1);
   const chosen=page.locator('input[name="selected[]"]');await chosen.first().check();
   assert.equal(await page.locator('input[name="selected[]"]:checked').count(),1);
   const action=await page.evaluate(()=>{const f=document.querySelector('#remove-selected');const b=f.querySelector('button[value="bulletin_create"]');return Object.fromEntries(new FormData(f,b));});
   assert.equal(action.action,'bulletin_create');assert.equal(action['selected[]'],'1234567890abcdef');assert.equal(action.weather_area,'bomlo');
   if(mode==='render-bulletin'){
    assert.equal(await page.getByRole('button',{name:'Godkjenn samlet manus',exact:true}).count(),1);
    assert.equal(await page.getByRole('button',{name:'Generer samlet lydprøve',exact:true}).count(),0,'no TTS before approval');
    assert.equal(await page.getByText(/Stemmen i denne sendingen er KI-generert/).count(),1);
   }
   if(process.env.BULLETIN_SCREENSHOT_DIR){fs.mkdirSync(process.env.BULLETIN_SCREENSHOT_DIR,{recursive:true});await page.screenshot({path:path.join(process.env.BULLETIN_SCREENSHOT_DIR,`${mode}-${width}.png`),fullPage:true});}
   await page.close();console.log(`PASS Bulletin ${mode} ${width}px`);
  }
 }
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
