// Real PHP HTTP API + persistent PHP/FFmpeg worker + synthetic MP3 + Chromium.
const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,execFileSync}=require('node:child_process');const {once}=require('node:events');
const {chromium}=require(process.env.RR_PLAYWRIGHT_MODULE||'playwright');
const repo=path.resolve(__dirname,'..'),php=process.env.PHP_BIN||'php';
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'rr-studio-integration-')),app=path.join(temp,'site'),bus=path.join(temp,'playout');
let server,worker,browser;const failures=[];
const wait=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,label,timeout=15000){const deadline=Date.now()+timeout;while(Date.now()<deadline){const v=await fn();if(v)return v;await wait(80);}throw Error('Timeout: '+label);}
async function stop(proc,signal='SIGTERM'){if(proc&&proc.exitCode===null){proc.kill(signal);await Promise.race([once(proc,'exit'),wait(2000)]);if(proc.exitCode===null)proc.kill('SIGKILL');}}
(async()=>{
 for(const dir of ['config','media/music/originals','public'])fs.mkdirSync(path.join(app,dir),{recursive:true});
 fs.cpSync(path.join(repo,'app'),path.join(app,'app'),{recursive:true});fs.cpSync(path.join(repo,'public'),path.join(app,'public'),{recursive:true});
 for(const dir of ['audio','monitor'])fs.mkdirSync(path.join(bus,dir),{recursive:true});
 fs.writeFileSync(path.join(app,'app/bootstrap.php'),`<?php require __DIR__.'/helpers.php';require_once __DIR__.'/users.php';function current_user(){ $r=$_SERVER['HTTP_X_TEST_ROLE']??'producer';return $r==='guest'?null:['id'=>'synthetic','name'=>'Synthetic test','role'=>$r];} $_SESSION=['csrf'=>'synthetic'];$config=[];`);
 fs.writeFileSync(path.join(app,'config/music-playout.local.php'),`<?php return ['enabled'=>true,'root'=>${JSON.stringify(bus)}];`);
 const crypto=require('node:crypto');const tracks=[];
 for(let i=1;i<=5;i++){
  const id=crypto.createHash('md5').update('synthetic-studio-'+i).digest('hex'),file=path.join(app,'media/music/originals',id+'.mp3');
  execFileSync('ffmpeg',['-nostdin','-v','error','-f','lavfi','-i',`sine=frequency=${330+i*110}:duration=6`,'-c:a','libmp3lame','-b:a','128k',file]);
  const bytes=fs.readFileSync(file),sha=crypto.createHash('sha256').update(bytes).digest('hex');
  tracks.push({id,sha256:sha,bytes:bytes.length,originalName:'Synthetic '+i+'.mp3',metadata:{source:'file',sourceSha256:sha,fields:{artist:'Synthetic artist '+i,title:'Synthetic tone '+i},durationSeconds:6.04},tags:['Varm']});
 }
 const originalHashes=tracks.map(t=>t.sha256);fs.writeFileSync(path.join(app,'media/music/library.json'),JSON.stringify({schemaVersion:1,tracks}));fs.writeFileSync(path.join(app,'media/music/library.lock'),'');
 const socket=require('node:net').createServer();socket.listen(0,'127.0.0.1');await once(socket,'listening');const port=socket.address().port;await new Promise(r=>socket.close(r));const base='http://127.0.0.1:'+port;
 server=spawn(php,['-S','127.0.0.1:'+port,'-t',path.join(app,'public')]);server.stderr.on('data',()=>{});
 async function api(body,role='producer',csrf='synthetic'){const response=await fetch(base+'/music-studio-api.php',{method:body?'POST':'GET',headers:{'Content-Type':'application/json','X-Test-Role':role,'X-CSRF-Token':csrf},...(body?{body:JSON.stringify(body)}:{})});const text=await response.text();let data;try{data=JSON.parse(text);}catch{throw Error(response.status+' '+text);}return {status:response.status,data};}
 await until(async()=>{try{return (await api()).status===200;}catch{return false;}},'HTTP ready');
 const get=async()=>(await api()).data;
 async function change(body){const v=await get();const r=await api({...body,revision:v.revision,playerRevision:v.player.seq});assert.equal(r.status,200,JSON.stringify(r.data));return r.data;}
 assert.equal((await api(undefined,'guest')).status,401);assert.equal((await api({action:'reset',revision:0},'observer')).status,403);assert.equal((await api({action:'reset',revision:0},'producer','wrong')).status,403);
 await change({action:'schedule',schedule:{programId:'god-morgen-bomlo',date:'2026-10-19',time:'07:00'}});
 for(const t of tracks)await change({action:'policy',trackId:t.id,sha256:t.sha256,policy:{approved:true,blocked:false,programs:['god-morgen-bomlo'],weekdays:[1,2,3,4,5,6,7],periods:[],fields:{energy:'moderat'}}});
 const denied=await api({action:'profile',revision:(await get()).revision,profile:{}},'presenter');assert.equal(denied.status,400,'presenter cannot change approval/profile');
 const revision=(await get()).revision;const add={action:'add',revision,trackId:tracks[0].id,sha256:tracks[0].sha256};const race=await Promise.all([api(add),api(add)]);assert.deepEqual(race.map(r=>r.status).sort(),[200,409]);
 let v=await get();const first=v.entries[0].id;await change({action:'lock',id:first,locked:true});assert.equal((await api({action:'remove',id:first,revision:(await get()).revision})).status,400);await change({action:'lock',id:first,locked:false});await change({action:'reset'});
 const proposal=await change({action:'suggest',commit:false});assert.equal(proposal.proposal.suggestions.length,3);await change({action:'suggest',commit:true,trackIds:proposal.proposal.suggestions.map(t=>t.trackId)});
 const launch=process.env.RR_CHROMIUM_EXECUTABLE?{executablePath:process.env.RR_CHROMIUM_EXECUTABLE,args:['--no-sandbox']}:{args:['--no-sandbox']};browser=await chromium.launch(launch);
 const page=await browser.newPage({viewport:{width:1280,height:900}});page.on('pageerror',e=>failures.push(e.message));
 await page.addInitScript(()=>{const original=AudioContext.prototype.createBufferSource;AudioContext.prototype.createBufferSource=function(){const s=original.call(this),start=s.start;s.start=function(...args){const samples=this.buffer.getChannelData(0);window.__testAudible=Math.max(window.__testAudible||0,...samples.slice(0,1000).map(Math.abs));return start.apply(this,args);};return s;};});
 await page.goto(base+'/music-studio.php');await page.getByText('Synthetic tone',{exact:false}).first().waitFor();
 assert.equal(await page.locator('#studio-entries li').count(),3);
 for(const width of [1280,390]){await page.setViewportSize({width,height:900});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'no horizontal overflow '+width);if(process.env.RR_STUDIO_SCREENSHOTS){fs.mkdirSync(process.env.RR_STUDIO_SCREENSHOTS,{recursive:true});await page.screenshot({path:path.join(process.env.RR_STUDIO_SCREENSHOTS,`music-studio-${width}.png`),fullPage:true});}}
 function launchWorker(){const w=spawn(php,[path.join(repo,'scripts/music-playout.php'),bus]);w.stderr.on('data',d=>failures.push(d.toString()));return w;}
 worker=launchWorker();await until(async()=>(await get()).player.online,'worker online');
 const duplicate=spawn(php,[path.join(repo,'scripts/music-playout.php'),bus]);duplicate.stderr.on('data',()=>{});await once(duplicate,'exit');assert.equal(duplicate.exitCode,3,'one active player');
 await change({action:'start'});await until(async()=>(await get()).player.current?.startedAt,'first sample started');
 await until(async()=>(await get()).player.segments.length>0,'actual WAV output');await page.locator('#refresh').click();await page.locator('#monitor').click();await page.waitForFunction(()=>window.__testAudible>0.001, null, {timeout:8000}).catch(async e=>{throw Error(e.message+' monitor='+await page.locator('#monitor-status').innerText()+' errors='+JSON.stringify(failures));});
 const libraryIndex=path.join(app,'media/music/library.json');const savedLibrary=fs.readFileSync(libraryIndex);fs.writeFileSync(libraryIndex,'broken');assert((await get()).libraryError,'library failure is visible without losing player controls');await change({action:'pause'});await until(async()=>(await get()).player.mode==='paused','paused');const paused=(await get()).player.position;await wait(700);assert.equal((await get()).player.position,paused,'pause holds actual sample position');
 fs.writeFileSync(libraryIndex,savedLibrary);await change({action:'start'});await page.close();await until(async()=>Object.values((await get()).player.outcomes).filter(e=>e.status==='completed').length===3,'audio continues with browser closed',24000);
 v=await get();assert.equal(v.player.lastCompleted.status,'completed');const wav=fs.readdirSync(path.join(bus,'monitor')).find(n=>n.endsWith('.wav'));const output=fs.readFileSync(path.join(bus,'monitor',wav));assert.equal(output.toString('ascii',0,4),'RIFF');assert(output.subarray(44).some(b=>b!==0),'real non-silent output');
 const entryIds=v.entries.map(e=>e.id);const outcomes=v.player.outcomes;assert(entryIds.every(id=>outcomes[id].startedAt&&outcomes[id].endedAt),'durable started and completed timestamps');
 // New queue preserves history: same songs are blocked by repetition.
 await change({action:'reset'});const nowParts=Object.fromEntries(new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Oslo',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(new Date()).map(p=>[p.type,p.value]));await change({action:'schedule',schedule:{programId:'god-morgen-bomlo',date:`${nowParts.year}-${nowParts.month}-${nowParts.day}`,time:`${nowParts.hour}:${nowParts.minute}`,occurrence:'first'}});const next=await change({action:'suggest',commit:false});assert.equal(next.proposal.suggestions.length,2);assert(next.proposal.missing.length,'small remaining library explains deficit');
 // Use an unused approved song; kill the daemon while decoding, then restart safely.
 await change({action:'suggest',commit:true,trackIds:next.proposal.suggestions.map(t=>t.trackId)});await change({action:'start'});await until(async()=>(await get()).player.current?.startedAt,'restart scenario started');
 const killedId=(await get()).player.current.id;await stop(worker,'SIGKILL');worker=launchWorker();await until(async()=>(await get()).player.outcomes[killedId]?.status==='interrupted','restart records interruption');assert.equal((await get()).player.mode,'stopped');
 const remaining=(await get()).entries.find(e=>e.id!==killedId);fs.unlinkSync(path.join(bus,'audio',remaining.sha256+'.mp3'));
 // Direct local command emulates a file disappearing after the API prepared it.
 const ctl=JSON.parse(fs.readFileSync(path.join(bus,'control.json')));ctl.command={id:'missing-file-test',action:'start'};fs.writeFileSync(path.join(bus,'control.json'),JSON.stringify(ctl));
 await until(async()=>(await get()).player.outcomes[remaining.id]?.status==='failed','missing file fails closed');assert.equal((await get()).player.mode,'blocked');assert(!(await get()).player.outcomes[remaining.id].startedAt,'failed before first sample is never played');
 fs.writeFileSync(libraryIndex,'broken');await change({action:'stop'});await until(async()=>(await get()).player.mode==='stopped','stop despite library failure');fs.writeFileSync(libraryIndex,savedLibrary);
 const events=fs.readFileSync(path.join(bus,'events.jsonl'),'utf8').trim().split('\n').map(JSON.parse);assert(events.every(e=>e.output==='isolated-test'));assert(events.some(e=>e.event==='planned'));assert(events.some(e=>e.event==='started'));assert(events.some(e=>e.event==='completed'));assert(events.some(e=>e.event==='interrupted'));assert(events.some(e=>e.event==='failed'));
 assert.deepEqual(tracks.map(t=>crypto.createHash('sha256').update(fs.readFileSync(path.join(app,'media/music/originals',t.id+'.mp3'))).digest('hex')),originalHashes,'originals unchanged');
 assert.deepEqual(failures,[]);
 console.log('PASS real Studio integration: HTTP auth/CSRF/revisions, concurrent operators, locks, proposal commit, desktop/mobile UI, actual audible monitor, single worker, sample-accurate pause, browser close, durable outcomes, repeat history, restart, missing audio, original preservation');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(async()=>{await stop(worker);await stop(server);if(browser)await browser.close();fs.rmSync(temp,{recursive:true,force:true});});
