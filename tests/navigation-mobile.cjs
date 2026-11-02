const path = require('node:path'), fs = require('node:fs'), assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.RR_PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
(async () => {
 const browser = await chromium.launch(process.env.RR_BROWSER_CHANNEL ? {channel:process.env.RR_BROWSER_CHANNEL} : {});
 try {
  for (const role of ['admin','producer','presenter','observer']) {
   const html = execFileSync(process.env.PHP_BIN || 'php', [path.join(root,'tests/fixtures/navigation.php'),role,'sending'], {encoding:'utf8'});
   for (const width of [320,390,800,1440]) {
    const page = await browser.newPage({viewport:{width,height:1000},javaScriptEnabled:false});
    await page.route('**/*', route => {
     const req=route.request(), url=new URL(req.url());
     assert.equal(req.method(),'GET','navigation never submits content');
     if(url.pathname.startsWith('/assets/')) return route.fulfill({path:path.join(root,'public',url.pathname)});
     return route.fulfill({contentType:'text/html',body:html});
    });
    await page.goto('https://fixture.test/sending.php');
    if(width<=800) {
     const summary=page.locator('.studio-mobile-menu > summary');
     await summary.focus();await page.keyboard.press('Enter');
    }
    const menu=page.locator(width<=800?'.studio-mobile-menu .studio-navigation':'.studio-desktop-menu .studio-navigation');
    assert(await menu.getByRole('link',{name:'Redaksjonell kjøreplan',exact:true}).isVisible(),'current editorial destination stays visible');
    assert(await menu.getByRole('link',{name:'Sendekø',exact:true}).isVisible(),'real queue remains directly accessible');
    assert.equal(await menu.locator('[data-nav-group="editorial"]').getAttribute('open'),'');
    const library=menu.locator('[data-nav-group="library"]');
    assert.equal(await library.getAttribute('open'),null);
    await library.locator('summary').focus();await page.keyboard.press('Enter');
    assert(await library.getByRole('link',{name:'Musikk',exact:true}).isVisible(),'library expands with keyboard without JS');
    await library.locator('summary').focus();await page.keyboard.press('Space');
    assert.equal(await library.getAttribute('open'),null);
    assert.equal(await menu.getByRole('link',{name:'Brukere',exact:true,includeHidden:true}).count(),role==='admin'?1:0);
    assert.equal(await menu.locator('[data-nav-group="settings"]').count(),['admin','producer'].includes(role)?1:0);
    assert.equal(await menu.locator('[data-nav-group="cooperation"]').count(),role==='admin'?1:0);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,role+' overflow at '+width);
    for(const summary of await menu.locator('.nav-group-title').all()) {
     const box=await summary.boundingBox();assert(box.height>=44,'touch target at least 44px');
    }
    if(role==='admin'&&process.env.RR_MENU_SCREENSHOT_DIR) {
     fs.mkdirSync(process.env.RR_MENU_SCREENSHOT_DIR,{recursive:true});
     await page.screenshot({path:path.join(process.env.RR_MENU_SCREENSHOT_DIR,'menu-'+width+'.png'),fullPage:true});
    }
    await page.close();
   }
  }
  console.log('PASS menu: 4 roles, 320/390/800/1440px, keyboard without JS, visible current route, preserved restrictions, no overflow');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
