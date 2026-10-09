const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'../..');
(async()=>{
 const html=execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'test-case-page.php'),'render'],{encoding:'utf8'});
 const browser=await chromium.launch();
 try{
  for(const width of [375,390,1280]){
   const page=await browser.newPage({viewport:{width,height:900}});
   await page.route('**/*',route=>{
    const u=new URL(route.request().url());
    if(u.pathname==='/case.php')return route.fulfill({contentType:'text/html',body:html});
    const f=path.join(root,'public',u.pathname);
    if(u.pathname.startsWith('/assets/')&&fs.existsSync(f))return route.fulfill({body:fs.readFileSync(f),contentType:u.pathname.endsWith('.css')?'text/css':u.pathname.endsWith('.js')?'text/javascript':'image/png'});
    return route.fulfill({status:404,body:''});
   });
   await page.goto('http://fixture.test/case.php');
   const audio=page.locator('#audio-material');await audio.scrollIntoViewIfNeeded();
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'no horizontal overflow');
   assert.match(await audio.innerText(),/Denne stemmen er KI-generert/,'audible disclosure visible before manuscript approval');
   assert.equal(await audio.locator('select[name=profile] option').count(),8);
   assert.equal(await audio.locator('textarea[name=script]').count(),1);
   assert.equal(await audio.locator('button[value=audio_tts]').count(),0,'disabled TTS has no paid action');
   assert.equal(await audio.locator('button[value=audio_enqueue]').count(),0,'unapproved audio has no enqueue action');
   await audio.locator('textarea[name=script]').fill('Et endret manus som ennå ikke er lagret.');
   await audio.locator('button[value=audio_check]').click();
   assert.match(await page.locator('#case-progress').innerText(),/Lagre manusendringene/,'unsaved audio cannot be reviewed as if it were stored');
   await audio.locator('summary').filter({hasText:'Uttaleordbok'}).click();
   await audio.locator('input[name=term]').fill('Rubbestadneset');
   assert.equal(await audio.locator('input[name=term]').inputValue(),'Rubbestadneset');
   if(process.env.AUDIO_SCREENSHOT_DIR){fs.mkdirSync(process.env.AUDIO_SCREENSHOT_DIR,{recursive:true});await page.screenshot({path:path.join(process.env.AUDIO_SCREENSHOT_DIR,`audio-${width}.png`),fullPage:true});}
   await page.close();console.log(`PASS RR Audio ${width}px: profiles, manuscript, dictionary and disabled TTS`);
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
