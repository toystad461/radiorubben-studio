// Real Studio HTML/CSS and browser audio; only the API and audio endpoints are fixtures.
// The native integration test separately verifies Liquidsoap and Icecast themselves.
const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.RR_PLAYWRIGHT_MODULE||'playwright');
const repo=path.resolve(__dirname,'..'),php=process.env.PHP_BIN||'php';

function realView(){
  const tmp=fs.mkdtempSync(path.join(os.tmpdir(),'rr-liquidsoap-ui-'));
  try {
    for(const dir of ['app/views','public'])fs.mkdirSync(path.join(tmp,dir),{recursive:true});
    for(const name of ['helpers','users'])fs.copyFileSync(path.join(repo,'app',name+'.php'),path.join(tmp,'app',name+'.php'));
    for(const name of ['head','sidebar','navigation','account'])fs.copyFileSync(path.join(repo,'app/views',name+'.php'),path.join(tmp,'app/views',name+'.php'));
    fs.copyFileSync(path.join(repo,'public/music-studio.php'),path.join(tmp,'public/music-studio.php'));
    fs.writeFileSync(path.join(tmp,'app/bootstrap.php'),`<?php require __DIR__.'/helpers.php';require __DIR__.'/users.php';function current_user(){return ['id'=>'synthetic','name'=>'UI test','role'=>'producer'];} $_SESSION=['csrf'=>'synthetic'];$_SERVER['REQUEST_METHOD']='GET';$config=[];`);
    return execFileSync(php,[path.join(tmp,'public/music-studio.php')],{encoding:'utf8'});
  }finally{fs.rmSync(tmp,{recursive:true,force:true});}
}
function wave(seconds=20){
  const rate=16000,samples=Math.round(seconds*rate),b=Buffer.alloc(44+samples*2);
  b.write('RIFF');b.writeUInt32LE(b.length-8,4);b.write('WAVEfmt ',8);b.writeUInt32LE(16,16);b.writeUInt16LE(1,20);b.writeUInt16LE(1,22);b.writeUInt32LE(rate,24);b.writeUInt32LE(rate*2,28);b.writeUInt16LE(2,32);b.writeUInt16LE(16,34);b.write('data',36);b.writeUInt32LE(samples*2,40);
  for(let i=0;i<samples;i++)b.writeInt16LE(Math.round(2000*Math.sin(i*2*Math.PI*440/rate)),44+i*2);
  return b;
}
function fixture(engine='liquidsoap'){
  const program={programId:'god-morgen-bomlo',name:'God morgen Bømlo',description:'Syntetisk testprofil',proposal:true,hard:{artistMinutes:30,trackMinutes:60,weekdays:[1,2,3,4,5,6,7]},preferences:{energy:'moderat',maxEnergyStep:1,maxTempoStep:20,keyWeight:1,tags:[],genres:[]}};
  // Repeated media has distinct occurrence IDs; holding one must not hold them all.
  const entries=[1,2,3,4].map(i=>({id:'occurrence-'+i,trackId:'same-track',sha256:'a'.repeat(64),artist:'Syntetisk artist',title:'Testlåt '+i,duration:180,locked:false}));
  const track={...entries[0],available:true,energy:'moderat',bpm:100,sources:{energy:'manuell',bpm:'analyse'},tags:{Varm:'manuell'},policy:{approved:true,blocked:false,programs:[program.programId],weekdays:[1,2,3,4,5,6,7],periods:[],fields:{}}};
  return {revision:7,entries,catalog:[track],studio:{profiles:{[program.programId]:program},schedule:{programId:program.programId,date:'2026-10-26',time:'07:00'}},plan:{rows:entries,missing:[]},player:{engine,seq:1,online:true,streamConnected:false,capabilities:{pause:engine!=='liquidsoap'},current:null,reserved:{},active:{},outcomes:{},mode:'stopped',ready:true,pendingCommand:null,position:0,segments:['old-php-segment.wav']}};
}
async function instrument(page){
  await page.addInitScript(()=>{
    window.__monitorTest={created:[],plays:0,playing:0,paused:0,legacyStarts:0,legacyPeak:0};
    const NativeAudio=window.Audio;
    window.Audio=function(src){
      const audio=new NativeAudio(src),play=audio.play.bind(audio),pause=audio.pause.bind(audio);
      window.__monitorTest.created.push(src);window.__lastMonitorAudio=audio;
      audio.play=function(){window.__monitorTest.plays++;return play();};
      audio.pause=function(){window.__monitorTest.paused++;return pause();};
      audio.addEventListener('playing',()=>window.__monitorTest.playing++);
      return audio;
    };
    window.Audio.prototype=NativeAudio.prototype;
    const create=AudioContext.prototype.createBufferSource;
    AudioContext.prototype.createBufferSource=function(){
      const source=create.call(this),start=source.start;
      source.start=function(...args){
        window.__monitorTest.legacyStarts++;
        window.__monitorTest.legacyPeak=Math.max(...this.buffer.getChannelData(0).slice(0,1000).map(Math.abs));
        return start.apply(this,args);
      };
      return source;
    };
  });
}
async function setup(browser,html,width,engine){
  const page=await browser.newPage({viewport:{width,height:1000}}),state={snapshot:fixture(engine),posts:[],streamRequests:0,segmentRequests:0,failStream:false,failApi:false,errors:[]};
  page.on('pageerror',e=>state.errors.push(e.message));await instrument(page);
  await page.route('**/*',async route=>{
    const req=route.request(),url=new URL(req.url());
    if(url.pathname.startsWith('/assets/'))return route.fulfill({path:path.join(repo,'public',url.pathname)});
    if(url.pathname==='/music-studio-api.php'){
      if(state.failApi)return route.fulfill({status:503,json:{error:'Synthetic API unavailable'}});
      if(req.method()==='POST'){
        const body=req.postDataJSON();state.posts.push(body);
        assert.equal(body.revision,state.snapshot.revision);assert.equal(body.playerRevision,state.snapshot.player.seq);
        assert.equal(req.headers()['x-csrf-token'],'synthetic');
        const p=state.snapshot.player;p.seq++;
        if(body.action==='start')p.mode=p.engine==='liquidsoap'?'starting':'playing';
        if(body.action==='pause')p.mode='paused';
        if(body.action==='stop'){p.mode='stopped';p.current=null;p.active={};p.reserved={};p.streamConnected=false;}
      }
      return route.fulfill({json:state.snapshot});
    }
    if(url.pathname==='/isolated-test-stream.mp3'){
      state.streamRequests++;
      return state.failStream?route.fulfill({status:503,body:'Synthetic unavailable stream'}):route.fulfill({contentType:'audio/wav',body:wave()});
    }
    if(url.pathname==='/music-monitor-audio.php'){state.segmentRequests++;return route.fulfill({contentType:'audio/wav',body:wave(2)});}
    if(url.pathname==='/music-studio.php')return route.fulfill({contentType:'text/html',body:html});
    return route.fulfill({status:404,body:''});
  });
  await page.goto('https://fixture.test/music-studio.php');
  await page.waitForFunction(()=>document.querySelectorAll('#studio-entries [data-entry-id]').length===4);
  return {page,state};
}

(async()=>{
  const html=realView();
  const launch={args:['--no-sandbox'],...(process.env.RR_CHROMIUM_EXECUTABLE?{executablePath:process.env.RR_CHROMIUM_EXECUTABLE}:{})};
  const browser=await chromium.launch(launch);
  try {
    for(const width of [390,1280]){
      const {page,state}=await setup(browser,html,width,'liquidsoap');
      const p=state.snapshot.player;
      assert(await page.locator('#player-pause').isDisabled(),'unsupported pause stays disabled');
      assert(await page.locator('#monitor').isDisabled());
      assert.equal(await page.locator('#monitor-status').innerText(),'Testlytting er ikke konfigurert i dette miljøet.');
      assert.match(await page.locator('#player-connection').innerText(),/Motor: svarer · Icecast: ikke bekreftet tilkoblet/);
      assert.equal(state.streamRequests+state.segmentRequests,0,'opening Studio never starts audio');
      await page.locator('#player-start').click();
      await page.waitForFunction(()=>document.querySelector('#player-mode').textContent==='Klargjør teststrøm');
      assert.deepEqual(state.posts.map(x=>x.action),['start']);
      assert.equal(state.streamRequests+state.segmentRequests,0,'starting the server never autoplays local audio');
      p.streamUrl='/isolated-test-stream.mp3';p.seq++;
      await page.waitForFunction(()=>!document.querySelector('#monitor-status').textContent.includes('ikke konfigurert'));
      assert(await page.locator('#monitor').isDisabled(),'online daemon alone does not enable Icecast monitor');

      // A seq-only update must lock held occurrences without replacing rows or draft editors.
      await page.locator('details.ms-panel').evaluate(el=>el.open=true);
      await page.locator('#profile-description').fill('Ulagret programendring');
      await page.getByRole('button',{name:'Godkjenning / rettelser',exact:true}).click();
      await page.locator('#policy-days').fill('1, 3, 5');
      await page.evaluate(()=>window.__savedRow=document.querySelector('[data-entry-id="occurrence-2"]'));
      p.message='Køen er ferdig.';p.mode='playing';p.streamConnected=true;p.current=state.snapshot.entries[0];p.active={'occurrence-1':state.snapshot.entries[0],'occurrence-3':state.snapshot.entries[2]};p.reserved={'occurrence-2':state.snapshot.entries[1]};p.seq++;
      await page.waitForFunction(()=>document.querySelector('[data-entry-id="occurrence-2"]').dataset.playbackState==='reserved');
      assert.equal(state.snapshot.revision,7,'only player sequence changed');
      assert.equal(await page.locator('#player-detail').innerText(),'Avspilling pågår på testserveren.');
      assert.equal(p.message,'Køen er ferdig.','rendering leaves stored state intact');
      assert(await page.evaluate(()=>window.__savedRow===document.querySelector('[data-entry-id="occurrence-2"]')),'row DOM identity survives player status');
      assert.equal(await page.locator('#profile-description').inputValue(),'Ulagret programendring');
      assert.equal(await page.locator('#policy-days').inputValue(),'1, 3, 5');
      assert(await page.locator('#policy-dialog').evaluate(el=>el.open));
      assert(await page.locator('#policy-days').evaluate(el=>document.activeElement===el),'focused editor survives heartbeat');
      for(const id of [1,2,3])assert(await page.locator(`[data-entry-id="occurrence-${id}"] button`).evaluateAll(buttons=>buttons.every(b=>b.disabled)),'all held-row actions are disabled');
      assert.equal(await page.locator('[data-entry-id="occurrence-4"] [data-queue-action="remove"]').isDisabled(),false,'another occurrence of the same track stays editable');
      assert(await page.locator('#reset-queue').isDisabled());
      await page.locator('#policy-cancel').click();
      assert.equal(state.streamRequests+state.segmentRequests,0,'connected status does not autoplay');
      assert.match(await page.locator('#player-connection').innerText(),/Icecast: tilkoblet/);

      await page.locator('#monitor').click();
      await page.waitForFunction(()=>window.__monitorTest.playing>0);
      assert.match(await page.locator('#monitor-status').innerText(),/faktiske Icecast-teststrømmen/);
      assert(state.streamRequests>0);assert.equal(state.segmentRequests,0,'Liquidsoap never fetches legacy WAV segments');
      assert.deepEqual(await page.evaluate(()=>window.__monitorTest.created),['/isolated-test-stream.mp3']);
      await page.locator('#monitor').click();
      assert(await page.evaluate(()=>window.__lastMonitorAudio.paused&&!window.__lastMonitorAudio.getAttribute('src')));
      assert.deepEqual(state.posts.map(x=>x.action),['start'],'local monitor stop never stops server');

      state.failStream=true;await page.locator('#monitor').click();
      await page.waitForFunction(()=>document.querySelector('#monitor-status').textContent.includes('Testlytting feilet'));
      assert.deepEqual(state.posts.map(x=>x.action),['start'],'monitor network/decode errors never stop server');
      assert.equal(state.segmentRequests,0);
      state.failStream=false;

      p.online=false;p.streamConnected=false;p.seq++;
      await page.waitForFunction(()=>document.querySelector('#player-connection').textContent.includes('Motor: svarer ikke'));
      assert(await page.locator('#monitor').isDisabled());assert(await page.locator('#player-start').isDisabled());
      assert.match(await page.locator('#player-detail').innerText(),/^Avspillingen kan ikke bekreftes/);
      const playCount=await page.evaluate(()=>window.__monitorTest.plays);
      p.online=true;p.streamConnected=true;p.seq++;
      await page.waitForFunction(()=>!document.querySelector('#monitor').disabled);
      assert.equal(await page.evaluate(()=>window.__monitorTest.plays),playCount,'reconnected engine never restarts local monitor');
      state.failApi=true;
      await page.waitForFunction(()=>document.querySelector('#player-mode').textContent==='Spillerstatus må oppdateres');
      assert.match(await page.locator('#player-detail').innerText(),/^Avspillingen kan ikke bekreftes/);
      state.failApi=false;
      await page.waitForFunction(()=>document.querySelector('#player-mode').textContent==='Spiller på testserveren');
      assert.equal(await page.locator('#player-detail').innerText(),'Avspilling pågår på testserveren.');
      await page.locator('#player-stop').click();
      await page.waitForFunction(()=>document.querySelector('#player-mode').textContent==='Stoppet');
      assert.deepEqual(state.posts.map(x=>x.action),['start','stop'],'server stop is an explicit operator action');
      assert(await page.locator('#monitor').isDisabled());

      p.mode='playing';p.current=state.snapshot.entries[0];p.active={'occurrence-1':state.snapshot.entries[0]};p.streamConnected=true;p.seq++;
      await page.waitForFunction(()=>!document.querySelector('#monitor').disabled);
      await page.locator('#monitor').click();
      await page.waitForFunction(()=>window.__lastMonitorAudio&&!window.__lastMonitorAudio.paused);
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'no horizontal overflow '+width);
      if(process.env.RR_STUDIO_SCREENSHOTS){fs.mkdirSync(process.env.RR_STUDIO_SCREENSHOTS,{recursive:true});await page.screenshot({path:path.join(process.env.RR_STUDIO_SCREENSHOTS,`music-liquidsoap-${width}.png`),fullPage:true});}
      await page.evaluate(()=>window.dispatchEvent(new Event('pagehide')));
      assert(await page.evaluate(()=>window.__lastMonitorAudio.paused&&!window.__lastMonitorAudio.getAttribute('src')));
      assert.deepEqual(state.posts.map(x=>x.action),['start','stop'],'pagehide only stops local audio');
      assert.deepEqual(state.errors,[]);await page.close();
      assert.deepEqual(state.posts.map(x=>x.action),['start','stop'],'closing page sends no server stop');

      const legacy=await setup(browser,html,width,'php');
      legacy.state.snapshot.player.message='Fullført på isolert testutgang.';legacy.state.snapshot.player.current=legacy.state.snapshot.entries[0];legacy.state.snapshot.player.mode='playing';legacy.state.snapshot.player.seq++;
      await legacy.page.waitForFunction(()=>document.querySelector('#player-mode').textContent==='Spiller på testserveren');
      assert.equal(await legacy.page.locator('#player-detail').innerText(),'Avspilling pågår på testserveren.');
      assert.equal(await legacy.page.locator('#player-pause').isDisabled(),false,'PHP pause remains supported');
      assert.equal(legacy.state.segmentRequests,0);
      await legacy.page.locator('#monitor').click();
      await legacy.page.waitForFunction(()=>window.__monitorTest.legacyStarts>0&&window.__monitorTest.legacyPeak>0.001);
      assert.equal(legacy.state.streamRequests,0);assert.equal(legacy.state.segmentRequests,1,'legacy PHP monitor still decodes WAV');
      assert.deepEqual(await legacy.page.evaluate(()=>window.__monitorTest.created),[]);
      await legacy.page.locator('#player-pause').click();
      await legacy.page.waitForFunction(()=>document.querySelector('#monitor').textContent==='Lytt til testutgangen');
      assert.deepEqual(legacy.state.posts.map(x=>x.action),['pause']);
      assert.deepEqual(legacy.state.errors,[]);await legacy.page.close();
    }
    console.log('PASS Liquidsoap UI: distinct daemon/Icecast states, starting, capability pause, held occurrence controls, seq-only draft preservation, user-triggered real browser audio, no legacy segments, local stop/error/pagehide, PHP monitor compatibility, layout 390/1280');
  }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
