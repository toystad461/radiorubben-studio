const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.RR_PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
(async()=>{
 const browser=await chromium.launch(process.env.RR_BROWSER_CHANNEL?{channel:process.env.RR_BROWSER_CHANNEL}:{});
 try{
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const editor=execFileSync(process.env.PHP_BIN||'php',[path.join(__dirname,'fixtures/cue-display.php')],{encoding:'utf8'});
  const control=fs.readFileSync(path.join(root,'public/ai-studio.php'),'utf8').match(/<section class="track-grid" aria-label="Serveravspilling">[\s\S]*?<\/section>/)[0];
  const css=fs.readFileSync(path.join(root,'public/assets/music-cues.css'),'utf8')+fs.readFileSync(path.join(root,'public/assets/ai-studio/controlroom.css'),'utf8');
  const points={start:1,vocalStart:15,transition:110,end:119};
  const view={schemaVersion:1,status:'playing',now:{title:'Syntetisk prøvelåt',artist:'Testartist',duration:118,cues:{points,source:'proposal',duration:120,playbackIn:1,playbackOut:119,waveformPeaks:Array.from({length:256},(_,i)=>.25+.3*Math.abs(Math.sin(i*.21)))}},position:9,next:[],queueKnown:true,engineOnline:true,streamConnected:true};
  await page.route('https://cue.test/**',route=>{const url=new URL(route.request().url());if(url.pathname==='/controlroom-api.php')return route.fulfill({json:{...view,generatedAt:new Date().toISOString(),requestId:url.searchParams.get('requestId')}});return route.fulfill({contentType:'text/html',body:`<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><style>body{background:#071522;color:#e4eff6;font:15px system-ui;margin:16px}main{max-width:850px;margin:auto}button,input{background:#16384c;color:#e4eff6;padding:10px;border:1px solid #537389;border-radius:6px}button{cursor:pointer}canvas{width:100%}a{color:#70cdff}.track-grid{display:block}.small{color:#bed0dc}h2{font-size:23px}${css}</style><main><h1>Utviklingsprøve · syntetiske data</h1><span id="server-air-status"></span><p id="server-connection"></p>${control}<hr>${editor}<audio id="library-preview-audio"></audio></main>`});});
  await page.goto('https://cue.test/');
  await page.addScriptTag({content:fs.readFileSync(path.join(root,'public/assets/ai-studio/controlroom.mjs'),'utf8')});
  await page.addScriptTag({content:fs.readFileSync(path.join(root,'public/assets/music-cues.mjs'),'utf8')});
  await page.waitForFunction(()=>document.querySelector('#server-cue-summary').textContent.includes('Vokalstart: 0:15'));
  assert.equal(await page.locator('[data-cue-select="vocalStart"]').textContent(),'Stemmen inn · 0:15.0');
  assert.equal(await page.locator('[name=vocalStart]').inputValue(),'15');
  for(const width of [390,1280]){
   await page.setViewportSize({width,height:1100});await page.waitForTimeout(2200);
   assert(await page.locator('#server-cues').isVisible());
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no horizontal overflow');
   // White cursor must use source time: cueIn 1 + measured 9 = 10 seconds.
   assert(await page.evaluate(()=>{const c=document.querySelector('#server-cues');const pixel=c.getContext('2d').getImageData(Math.round(c.width*10/120),120,1,1).data;return pixel[0]>230&&pixel[1]>230&&pixel[2]>230;}),'cursor includes trimmed cue-in offset');
   if(process.env.RR_CUE_EVIDENCE){fs.mkdirSync(process.env.RR_CUE_EVIDENCE,{recursive:true});await page.screenshot({path:path.join(process.env.RR_CUE_EVIDENCE,`cue-display-${width}.png`),fullPage:true});}
  }
  await page.locator('.cue-adjust summary').click();await page.locator('[name=vocalStart]').fill('12');
  await page.evaluate(()=>window.dispatchEvent(new CustomEvent('rr-audio-analysis-complete',{detail:{id:'b'.repeat(32)}})));
  assert.equal(await page.locator('[name=vocalStart]').inputValue(),'12','unsaved manual edit survives completed analysis');
  assert.match(await page.locator('#cue-status').textContent(),/Ny analyse er lagret/);
  view.now.cues.points.vocalStart=null;await page.waitForTimeout(2200);assert.match(await page.locator('#server-cue-summary').textContent(),/Vokalstart: ukjent/);
  view.status='stopped';await page.waitForTimeout(2200);assert.equal(await page.locator('#server-cues').isVisible(),false,'no stale markers after stop');
  view.status='playing';view.now.cues=null;view.now.title='Neste fil';await page.waitForTimeout(2200);assert.equal(await page.locator('#server-cues').isVisible(),false,'next file does not retain previous cues');
  assert.deepEqual(errors,[]);console.log('PASS actual browser: editor proposal, 390/1280 graphics, PCM offset, unknown vocal, stop/track change, unsaved edits');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
