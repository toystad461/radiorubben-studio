// Opt-in local integration test: public RSS reads; real local login, controller and storage.
// The temporary server permits only intake/login/logout and static assets, never production routes.
const fs=require('fs'),path=require('path'),os=require('os'),crypto=require('crypto'),{spawn,spawnSync}=require('child_process');
const {chromium}=require(process.env.RR_PLAYWRIGHT_MODULE||'playwright');
const assert=(ok,label)=>{if(!ok)throw Error(label);};
(async()=>{
 const project=path.dirname(__dirname),parent=path.resolve(process.env.RR_INPUT_TEMP||os.tmpdir());
 const root=fs.mkdtempSync(path.join(parent,'rr-input-http-')),password=crypto.randomBytes(24).toString('hex');
 let server,browser;const failures=[],requests=[];
 try{
  for(const dir of ['app','public'])fs.cpSync(path.join(project,dir),path.join(root,dir),{recursive:true});
  for(const dir of ['config','feeds','sessions'])fs.mkdirSync(path.join(root,dir));
  fs.copyFileSync(path.join(project,'config/example.php'),path.join(root,'config/example.php'));
  const urls={nrk_vestland_toppsaker:'https://www.nrk.no/vestland/toppsaker.rss',nrk_vestland_siste:'https://www.nrk.no/vestland/siste.rss',bomlo:'https://www.bomlo.kommune.no/ArtikkelRSS.ashx?NyhetsKategoriId=26&Spraak=Nynorsk'};
  for(const [id,url] of Object.entries(urls)){
   const response=await fetch(url,{signal:AbortSignal.timeout(20000),redirect:'error'});assert(response.ok,'RSS fetch failed: '+id);
   const body=await response.text();assert(Buffer.byteLength(body)<1000000,'RSS too large');fs.writeFileSync(path.join(root,'feeds',id+'.xml'),body);
  }
  const env={...process.env,RR_INPUT_TEST_PASSWORD:password,STUDIO_SITE_MODE:'app',STUDIO_AUTH_MODE:'entra',STUDIO_BASE_URL:'http://127.0.0.1:8099',ENTRA_TENANT_ID:'00000000-0000-0000-0000-000000000001',ENTRA_CLIENT_ID:'00000000-0000-0000-0000-000000000002',ENTRA_CLIENT_SECRET:'synthetic-test-only',OPENAI_API_KEY:'',OPENAI_MODEL:'',ELEVENLABS_API_KEY:'',VIPPS_CLIENT_ID:'',VIPPS_CLIENT_SECRET:'',VIPPS_ALLOWED_PHONES:''};
  const php=process.env.RR_PHP;assert(php,'Set RR_PHP to the local PHP executable');
  const args=['-n'];if(process.env.RR_PHP_EXT)args.push('-d','extension_dir='+process.env.RR_PHP_EXT);args.push('-d','extension=mbstring','-d','session.save_path='+path.join(root,'sessions'));
  const seeded=spawnSync(php,[...args,path.join(__dirname,'news-input-http-seed.php'),root],{env,encoding:'utf8'});
  assert(seeded.status===0,'Seed failed: '+seeded.stderr+seeded.stdout);const feedCounts=JSON.parse(seeded.stdout).feeds;
  fs.writeFileSync(path.join(root,'router.php'),`<?php $p=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)); if(str_contains($p,'..')){http_response_code(404);exit;} if(str_starts_with($p,'/assets/')&&$_SERVER['REQUEST_METHOD']==='GET')return false; if(!in_array($p,['/news-input.php','/local/login.php','/logout.php'],true)){http_response_code(404);exit('Isolert test: handlingen er sperret.');} require __DIR__.'/public'.$p;`);
  server=spawn(php,[...args,'-S','127.0.0.1:8099','-t',path.join(root,'public'),path.join(root,'router.php')],{env,stdio:['ignore','ignore','pipe'],windowsHide:true});
  server.stderr.on('data',b=>{if(/Fatal error/.test(b.toString()))failures.push('PHP fatal error');});
  for(let i=0;i<40;i++){try{await fetch('http://127.0.0.1:8099/local/login.php');break;}catch(e){await new Promise(r=>setTimeout(r,100));}}
  assert(server.exitCode===null,'Local test server failed');
  browser=await chromium.launch({channel:process.env.RR_BROWSER_CHANNEL||'msedge',headless:true});
  const page=await browser.newPage({viewport:{width:1280,height:900}});
  await page.route('**/*',route=>{const url=new URL(route.request().url());if(url.hostname!=='127.0.0.1'){requests.push(url.origin);return route.abort();}return route.continue();});
  let response=await page.goto('http://127.0.0.1:8099/news-input.php');assert(response.status()===404&&page.url().endsWith('/login.php'),'Guest did not leave protected intake');
  await page.goto('http://127.0.0.1:8099/local/login.php');await page.locator('[name=email]').fill('editor@example.test');await page.locator('[name=password]').fill(password);
  await Promise.all([page.waitForURL('http://127.0.0.1:8099/'),page.getByRole('button',{name:'Logg inn',exact:true}).click()]);
  response=await page.goto('http://127.0.0.1:8099/news-input.php');assert(response.status()===200,'Authenticated intake failed');
  for(const id of Object.keys(urls)){await page.locator('[name=feed]').selectOption(id);await Promise.all([page.waitForURL('**/news-input.php?feed='+id),page.getByRole('button',{name:'Vis',exact:true}).click()]);assert(await page.locator('.ni-stories a').count()===feedCounts[id],'Feed count mismatch: '+id);}
  await page.goto('http://127.0.0.1:8099/news-input.php');assert(await page.locator('.ni-stories a').count()>0,'No available RSS stories');
  const rundown=fs.readFileSync(path.join(root,'config/sending-board.json'),'utf8');
  await page.locator('[name=channel][value=web]').check();await page.locator('[name=reason]').fill('Teknisk test av kanalvalg. Dette er ikke en redaksjonell godkjenning.');
  await Promise.all([page.waitForNavigation(),page.getByRole('button',{name:'Lagre vurdering'}).click()]);
  assert(await page.getByRole('status').filter({hasText:'Vurderingen er lagret'}).count()===1,'Judgement not saved');
  assert(fs.readFileSync(path.join(root,'config/sending-board.json'),'utf8')===rundown,'Rundown changed');
  assert(fs.existsSync(path.join(root,'config/rss-input.private.json')),'Separate intake storage absent');
  for(const route of ['/newsdesk.php','/sending.php','/audio.php','/config/local.php']){response=await page.request.get('http://127.0.0.1:8099'+route);assert(response.status()===404,'Unsafe route exposed: '+route);}
  await page.setViewportSize({width:390,height:844});await page.goto('http://127.0.0.1:8099/news-input.php');await page.locator('.ni-stories a').first().click();assert(await page.locator('.ni-reader').isVisible()&&!await page.locator('.ni-list').isVisible(),'Authenticated mobile view failed');
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Full Studio mobile overflow');
  assert(requests.length===0&&failures.length===0,'Unexpected request or PHP error');
  const report={at:new Date().toISOString(),feeds:feedCounts,authentication:'Real local password login with temporary synthetic account; Microsoft login not tested',judgementSaved:true,rundownUnchanged:true,blockedProductionRoutes:true,externalBrowserRequests:requests.length,mobileWidth:390};
  if(process.env.RR_INPUT_REPORT)fs.writeFileSync(process.env.RR_INPUT_REPORT,JSON.stringify(report,null,2));
  console.log(JSON.stringify(report));
 }finally{
  if(browser)await browser.close();if(server){server.kill();await new Promise(r=>server.exitCode!==null?r():server.once('exit',r));}
  const resolved=fs.realpathSync(root);assert(path.dirname(resolved).toLowerCase()===fs.realpathSync(parent).toLowerCase()&&path.basename(resolved).startsWith('rr-input-http-'),'Unexpected cleanup path');fs.rmSync(resolved,{recursive:true,force:true});
 }
})().catch(e=>{console.error(e.message);process.exit(1);});
