const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),net=require('node:net'),assert=require('node:assert/strict'),{spawn}=require('node:child_process');
const {chromium}=require(process.env.RR_PLAYWRIGHT_MODULE||'playwright');
const repo=path.resolve(__dirname,'..');
(async()=>{
 const tmp=fs.mkdtempSync(path.join(os.tmpdir(),'rr-music-browser-'));let browser,server;
 try{
  for(const d of ['app/views','public/assets'])fs.mkdirSync(path.join(tmp,d),{recursive:true});
  for(const f of ['public/music-library.php','app/music-library.php','app/views/head.php','app/views/sidebar.php','app/views/navigation.php','app/views/account.php'])fs.copyFileSync(path.join(repo,f),path.join(tmp,f));
  fs.cpSync(path.join(repo,'public/assets'),path.join(tmp,'public/assets'),{recursive:true});
  fs.writeFileSync(path.join(tmp,'app/bootstrap.php'),`<?php session_start(); if(isset($_GET['testRole']))$_SESSION['testRole']=$_GET['testRole']; $_SESSION['csrf']??='synthetic'; $config=['auth_mode'=>'entra']; function current_user(){return ['id'=>'synthetic','role'=>$_SESSION['testRole']??'producer','name'=>'Testprodusent'];} function studio_can($u,$p){return $u['role']!=='observer';} function studio_roles(){return ['producer'=>'Produsent','observer'=>'Observatør'];} function escape($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');} function icon(...$a){return '';} function redirect($url){header('Location: '.$url, true,303);exit;} header('Cache-Control: no-store');`);
  const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
  const args=process.env.RR_PHP_EXT_DIR?['-n','-d','extension_dir='+process.env.RR_PHP_EXT_DIR,'-d','extension=fileinfo']:[];
  server=spawn(process.env.PHP_BIN||'php',[...args,'-d','upload_max_filesize=20M','-d','post_max_size=64M','-S',`127.0.0.1:${port}`,'-t',path.join(tmp,'public')],{stdio:'ignore'});
  const url=`http://127.0.0.1:${port}/music-library.php`;let ready=false;for(let i=0;i<50;i++){try{if((await fetch(url)).ok){ready=true;break;}}catch{}await new Promise(r=>setTimeout(r,100));}assert(ready,'fixture server starts');
  browser=await chromium.launch(process.env.RR_BROWSER_CHANNEL?{channel:process.env.RR_BROWSER_CHANNEL}:{});
  const page=await browser.newPage({viewport:{width:1280,height:950}});const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(url);
  const mp3=Buffer.concat(Array.from({length:5},()=>Buffer.concat([Buffer.from('fffb9000','hex'),Buffer.alloc(413)])));
  await page.locator('#music-files').setInputFiles([{name:'Testartist – Testlåt.mp3',mimeType:'audio/mpeg',buffer:mp3},{name:'Ugyldig.mp3',mimeType:'audio/mpeg',buffer:Buffer.from('<?php echo 1;')}]);await page.getByRole('button',{name:'Last opp originaler'}).click();await page.getByRole('heading',{name:'Resultat av opplasting'}).waitFor();
  assert.equal(await page.locator('.library-tracks li').count(),1);assert.match(await page.locator('main').innerText(),/kunne ikke bekreftes som MP3/);assert.match(await page.locator('main').innerText(),/Original lagret/);
  const index=JSON.parse(fs.readFileSync(path.join(tmp,'media/music/library.json'),'utf8'));assert.equal(index.tracks.length,1);assert.equal(index.tracks[0].metadata,null);
  const asset=path.join(tmp,'media/music/originals',index.tracks[0].id+'.mp3');assert(fs.readFileSync(asset).equals(mp3));assert.equal((await fetch(`http://127.0.0.1:${port}/media/music/originals/${index.tracks[0].id}.mp3`)).status,404,'original inaccessible through public root');
  await page.locator('#music-files').setInputFiles({name:'Samme fil.mp3',mimeType:'audio/mpeg',buffer:mp3});await page.getByRole('button',{name:'Last opp originaler'}).click();await page.getByText('Finnes allerede – ingen ny kopi.',{exact:false}).waitFor();assert.equal(await page.locator('.library-tracks li').count(),1);
  for(const width of [320,390,768,1280]){await page.setViewportSize({width,height:950});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`overflow ${width}`);if(process.env.RR_MUSIC_PREVIEW&&width===1280){await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:process.env.RR_MUSIC_PREVIEW});}}
  await page.goto(url+'?testRole=observer');assert.equal(await page.locator('#music-files').count(),0);assert.match(await page.locator('main').innerText(),/Du kan lese biblioteket/);
  const response=await page.request.post(url,{multipart:{csrf:'synthetic','tracks[]':{name:'Test.mp3',mimeType:'audio/mpeg',buffer:mp3}}});assert.equal(response.status(),403);assert.equal(JSON.parse(fs.readFileSync(path.join(tmp,'media/music/library.json'),'utf8')).tracks.length,1);
  await page.goto(url+'?testRole=producer');const bad=await page.request.post(url,{multipart:{csrf:'wrong','tracks[]':{name:'Test.mp3',mimeType:'audio/mpeg',buffer:mp3}}});assert.equal(bad.status(),403);assert.equal(JSON.parse(fs.readFileSync(path.join(tmp,'media/music/library.json'),'utf8')).tracks.length,1);assert.deepEqual(errors,[]);
  console.log('PASS real multipart upload, rejected disguised file, duplicate, private storage, roles/CSRF and responsive library');
 }finally{if(browser)await browser.close();if(server){server.kill();await new Promise(resolve=>server.exitCode!==null?resolve():server.once('exit',resolve));}if(!path.basename(tmp).startsWith('rr-music-browser-')||path.dirname(tmp)!==os.tmpdir())throw Error('Unsafe fixture cleanup');fs.rmSync(tmp,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exitCode=1;});
