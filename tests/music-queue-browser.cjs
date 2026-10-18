const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.RR_PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),php=process.env.PHP_BIN||'php';
const html=execFileSync(php,[path.join(root,'tests/fixtures/music-queue.php')],{encoding:'utf8'});
function wave(seconds=0.6){
 const samples=Math.round(seconds*16000),b=Buffer.alloc(44+samples*2);b.write('RIFF');b.writeUInt32LE(b.length-8,4);b.write('WAVEfmt ',8);b.writeUInt32LE(16,16);b.writeUInt16LE(1,20);b.writeUInt16LE(1,22);b.writeUInt32LE(16000,24);b.writeUInt32LE(32000,28);b.writeUInt16LE(2,32);b.writeUInt16LE(16,34);b.write('data',36);b.writeUInt32LE(samples*2,40);for(let i=0;i<samples;i++)b.writeInt16LE(Math.round(1000*Math.sin(i*2*Math.PI*440/16000)),44+i*2);return b;
}
(async()=>{
 const launch=process.env.RR_CHROMIUM_EXECUTABLE?{executablePath:process.env.RR_CHROMIUM_EXECUTABLE,args:['--no-sandbox']}:{};
 const browser=await chromium.launch(launch);
 try {
  for(const width of [390,1280]) {
   let revision=0,entries=[],streams=0,failMutation=false,sequence=0;
   const catalog=[1,2,3,4].map(i=>({trackId:String(i).padStart(32,'0'),sha256:String(i).repeat(64),artist:'Testartist',title:'Prøvelåt '+i,duration:180+i,available:true}));
   const snapshot=()=>({revision,entries,catalog});
   const initial=html.replace(/(<script type="application\/json" id="queue-initial">)[\s\S]*?(<\/script>)/,'$1'+JSON.stringify(snapshot())+'$2');
   const page=await browser.newPage({viewport:{width,height:1000}}),errors=[];page.on('pageerror',error=>errors.push(error.message));
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname.startsWith('/assets/'))return route.fulfill({path:path.join(root,'public',url.pathname)});
    if(url.pathname==='/music-library-audio.php'){streams++;return route.fulfill({contentType:'audio/wav',body:wave()});}
    if(url.pathname==='/music-queue-api.php'){
     if(request.method()==='POST'){
      if(failMutation)return route.fulfill({status:409,json:{error:'Køen er endret i en annen fane.'}});
      const input=request.postDataJSON();assert.equal(input.revision,revision);assert.equal(request.headers()['x-csrf-token'],'synthetic');
      if(input.action==='add'){const track=catalog.find(t=>t.trackId===input.trackId);entries.push({...track,id:'entry-'+(++sequence),audioUrl:'/music-library-audio.php?id='+track.trackId});}
      if(input.action==='remove')entries=entries.filter(e=>e.id!==input.id);
      if(input.action==='move'){const item=entries.find(e=>e.id===input.id);entries=entries.filter(e=>e.id!==input.id);const at=input.beforeId===null?entries.length:entries.findIndex(e=>e.id===input.beforeId);entries.splice(at,0,item);}
      if(input.action==='clear')entries=[];
      revision++;
     }
     return route.fulfill({json:snapshot()});
    }
    return route.fulfill({contentType:'text/html',body:initial});
   });
   await page.goto('https://fixture.test/music-queue.php');
   assert.equal(streams,0,'loading does not start audio');
   for(const i of [1,2,3]){await page.getByRole('button',{name:`Legg Testartist – Prøvelåt ${i} i kø`,exact:true}).click();await page.getByRole('button',{name:`Legg Testartist – Prøvelåt ${i} i kø`,exact:true}).waitFor();await page.waitForFunction(()=>!document.querySelector('#queue-refresh').disabled);}
   assert.equal(entries.length,3);assert.equal(streams,0,'editing queue never starts audio');
   await page.getByRole('button',{name:'Flytt Prøvelåt 3 opp',exact:true}).click();await page.waitForFunction(()=>!document.querySelector('#queue-refresh').disabled);assert.equal(entries[1].title,'Prøvelåt 3');
   await page.getByRole('button',{name:'Fjern Prøvelåt 2 fra kø',exact:true}).click();await page.waitForFunction(()=>!document.querySelector('#queue-refresh').disabled);assert.equal(entries.length,2);
   // Reload sees saved order and remains silent. History is deliberately per tab.
   await page.reload();await page.locator('#queue-refresh').click();await page.waitForFunction(()=>!document.querySelector('#queue-refresh').disabled);assert.equal(await page.locator('#queue-entries li').count(),2);assert.equal(streams,0);
   await page.locator('#queue-autoplay').check();assert.equal(streams,0);
   await page.locator('#queue-play').click();await page.waitForFunction(()=>document.querySelectorAll('#queue-history li').length===2);
   assert.equal(streams,2,'real audio ended events advance exactly once');
   assert.match(await page.locator('#queue-history').innerText(),/Ferdigspilt/);
   await page.locator('#queue-restart').click();assert.equal(streams,2,'reset never starts audio');assert.equal(await page.locator('#queue-history li').count(),0);
   failMutation=true;await page.getByRole('button',{name:'Flytt Prøvelåt 3 opp',exact:true}).click();await page.getByText(/Trykk Oppdater for å se lagret kø/).waitFor();assert(await page.locator('#queue-play').isDisabled());assert.equal(await page.locator('#queue-autoplay').isChecked(),false);
   failMutation=false;await page.locator('#queue-refresh').click();await page.waitForFunction(()=>!document.querySelector('#queue-refresh').disabled);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`overflow ${width}`);
   assert.deepEqual(errors,[]);
   if(process.env.RR_QUEUE_SCREENSHOTS){fs.mkdirSync(process.env.RR_QUEUE_SCREENSHOTS,{recursive:true});await page.screenshot({path:path.join(process.env.RR_QUEUE_SCREENSHOTS,`music-queue-${width}.png`),fullPage:true});}
   await page.close();
  }
  console.log('PASS music queue browser: saved order, reorder/remove, reload silence, real audio sequence, conflict, mobile layout');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
