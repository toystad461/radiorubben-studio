// Run tests/news-input.php with RR_INPUT_PREVIEW pointing to tests/news-input-preview.html first.
const {chromium}=require(process.env.RR_PLAYWRIGHT_MODULE||'playwright');
const path=require('path');
(async()=>{
 const browser=await chromium.launch({channel:process.env.RR_BROWSER_CHANNEL||'msedge',headless:true});
 try{for(const width of [390,768,1280,1920]){
  const page=await browser.newPage({viewport:{width,height:900}}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto('file:///'+path.join(__dirname,'news-input-preview.html').replace(/\\/g,'/'));
  if(width>750){
   const before=await page.locator('.ni-reader').boundingBox();
   await page.locator('.ni-stories').evaluate(el=>el.scrollTop=el.scrollHeight);
   const after=await page.locator('.ni-reader').boundingBox();
   if(Math.abs(before.y-after.y)>1||await page.locator('.ni-reader').evaluate(el=>el.scrollTop)!==0)throw Error('Reader moved with list');
   if(await page.locator('.ni-stories').evaluate(el=>el.scrollTop)<=0)throw Error('List cannot scroll');
  }else{
   await page.locator('.ni-stories a').last().click();
   if(!await page.locator('.ni-reader').isVisible()||await page.locator('.ni-list').isVisible())throw Error('Mobile selection failed');
   await page.getByRole('link',{name:'← Til nyhetslisten'}).click();
   if(!await page.locator('.ni-list').isVisible()||await page.locator('.ni-reader').isVisible())throw Error('Mobile return failed');
  }
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Horizontal overflow');
  if(errors.length)throw Error(errors.join('\n'));
  console.log('PASS: layout '+width+' px');await page.close();
 }}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
