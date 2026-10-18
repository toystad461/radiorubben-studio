const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net');
const {spawn,execFileSync}=require('node:child_process');
const php=process.env.PHP_BIN||'php',root=path.resolve(__dirname,'..');
(async()=>{
 const tmp=execFileSync(php,[path.join(root,'tests/fixtures/music-queue.php'),'server'],{encoding:'utf8'}).trim();
 assert(tmp.startsWith(os.tmpdir()+'/rr-queue-view-'),'isolated test root');
 const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
 const proc=spawn(php,['-S',`127.0.0.1:${port}`,'-t',tmp+'/public'],{stdio:'ignore'});
 const url=`http://127.0.0.1:${port}/music-queue-api.php`;
 async function call(body,role='producer',csrf='synthetic',method=body?'POST':'GET'){
   const response=await fetch(url,{method,headers:{'X-Test-Role':role,'X-CSRF-Token':csrf,'Content-Type':'application/json'},...(body?{body:JSON.stringify(body)}:{})});
   return {status:response.status,data:await response.json()};
 }
 try {
   let ready=false;for(let i=0;i<50;i++){try{await call();ready=true;break;}catch{await new Promise(resolve=>setTimeout(resolve,100));}}
   assert(ready,'native test server starts');
   assert.equal((await call(null,'guest')).status,401);
   assert.equal((await call(null,'observer')).status,200);
   assert.equal((await call(null,'producer','synthetic','DELETE')).status,405);
   const initial=(await call()).data;assert.equal(initial.entries.length,0);assert.equal(initial.catalog.length,4);
   const song=initial.catalog[0],add={action:'add',revision:0,trackId:song.trackId,sha256:song.sha256};
   assert.equal((await call(add,'observer')).status,403);
   assert.equal((await call(add,'producer','wrong')).status,403);
   assert.equal((await call({...add,sha256:'f'.repeat(64)})).status,400);
   assert.equal((await call()).data.revision,0);
   const added=await call(add);assert.equal(added.status,200);assert.equal(added.data.entries.length,1);
   assert.equal((await call(add)).status,409,'concurrent stale add is not duplicated');
   const file=tmp+'/media/music/originals/'+song.trackId+'.mp3',original=fs.readFileSync(file);
   const removed=await call({action:'remove',revision:1,id:added.data.entries[0].id});
   assert.equal(removed.status,200);assert.equal(removed.data.entries.length,0);assert.deepEqual(fs.readFileSync(file),original);
   const page=await fetch(url.replace('-api',''),{headers:{'X-Test-Role':'observer'}});assert.equal(page.status,200);assert.match(await page.text(),/data-can-control="0"/);
   fs.writeFileSync(tmp+'/config/music-play-queue.private.json','broken');
   assert.equal((await call()).status,503);assert.equal((await call({action:'clear',revision:2})).status,503);
   assert.equal(fs.readFileSync(tmp+'/config/music-play-queue.private.json','utf8'),'broken');
   console.log('PASS real queue HTTP: auth, CSRF, roles, revision, mutation, observer view, originals, corrupt state');
 } finally {proc.kill();await new Promise(resolve=>proc.once('close',resolve));fs.rmSync(tmp,{recursive:true,force:true});}
})().catch(error=>{console.error(error);process.exit(1);});
