const path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.RR_PLAYWRIGHT_MODULE||'playwright');const root=path.resolve(__dirname,'..');
const html=execFileSync(process.env.PHP_BIN||'php',[path.join(root,'tests/fixtures/studio-board.php')],{encoding:'utf8'});
(async()=>{const browser=await chromium.launch(process.env.RR_BROWSER_CHANNEL?{channel:process.env.RR_BROWSER_CHANNEL}:{});try{
 for(const width of [320,390,768,1280,1440]){const page=await browser.newPage({viewport:{width,height:1000}});const errors=[];let approvals=0,audioStreams=0;
 page.on('pageerror',e=>errors.push(e.message));
 const tracks=[1,2,3,4].map(n=>({id:String(n),artist:'Testartist',title:'Låt '+n,eligible:true}));
 await page.route('**/*',route=>{const r=route.request(),u=new URL(r.url());
 if(u.pathname.startsWith('/assets/'))return route.fulfill({path:path.join(root,'public',u.pathname)});
 if(u.pathname==='/studio-break-api.php'){approvals++;const b=r.postDataJSON();assert.equal(b.confirmed,'1');assert.match(b.text,/Radio Rubben/);return route.fulfill({json:{id:'0123456789abcdef',approved:true,url:'/case.php?item=0123456789abcdef#audio-material'}});}
 if(u.pathname==='/music-api.php'){if(u.searchParams.get('action')==='stream')audioStreams++;const b=r.postDataJSON?.();return route.fulfill({json:b?.action==='catalog'?{tracks,token:'synthetic',schedule:null,breakStatus:'Test'}:{schedule:null}});}
 if(u.pathname.endsWith('-api.php'))return route.fulfill({status:503,json:{error:'Syntetisk fixture'}});
 return route.fulfill({contentType:'text/html',body:html});});
 await page.goto('https://fixture.test/ai-studio.php');await page.getByRole('button',{name:'Les musikkmappen',exact:true}).click();
 for(const id of ['1','2','3']){await page.locator('#music-selection').selectOption(id);await page.getByRole('button',{name:'Legg valgt låt i kø',exact:true}).click();}
 assert.equal(await page.locator('#next-tracks li').count(),3);assert.equal(audioStreams,0,'queue edit never starts audio');
 await page.locator('#music-selection').selectOption('4');await page.getByRole('button',{name:'Legg valgt låt i kø',exact:true}).click();assert.equal(await page.locator('#next-tracks li').count(),3);
 await page.locator('#draft').fill('Du hører Radio Rubben. Snart får du en ny låt.');await page.getByRole('button',{name:'Godkjenn stikk',exact:true}).click();await page.getByText('Manus godkjent og lagret.',{exact:false}).waitFor();assert.equal(approvals,1);
 await page.locator('#draft').fill('Du hører Radio Rubben. Manuelt rettet tekst.');assert(await page.locator('#break-tts-link').isHidden());
 await page.locator('.manual-music summary').click();await page.locator('#manual-now').fill('Manuell artist – Nå');await page.locator('#manual-next-1').fill('Manuell artist – Neste');await page.getByRole('button',{name:'Bruk manuelt musikkgrunnlag',exact:true}).click();assert.match(await page.locator('#now-playing').innerText(),/Manuell artist/);assert.equal(audioStreams,0);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`overflow ${width}`);
 if(width===1440){const s=await page.locator('.status-card').boundingBox(),p=await page.locator('.producer').boundingBox();assert(s.x>p.x+p.width-1,'status at far right');}
 await page.locator('.pads-card summary').click();assert(await page.locator('#pad-board').isVisible());await page.locator('.log-card summary').click();assert(await page.locator('#export-log').isVisible());assert.deepEqual(errors,[]);await page.close();
 }console.log('PASS Studio queue, one-click approval, edit invalidation and responsive layout');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
