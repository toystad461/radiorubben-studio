import fs from 'node:fs';import vm from 'node:vm';import assert from 'node:assert/strict';
let code=fs.readFileSync(new URL('../../public/case.php', import.meta.url),'utf8').split('<script>')[1].split('</script>')[0];
code=code.replace(/<\?=json_encode\(\$id\)\?>/g,'"test"').replace(/<\?=\(int\)\$item\[\'revision\'\]\?>/g,'1').replace(/<\?=json_encode\(\$_SESSION\[\'csrf\'\]\)\?>/g,'"csrf"').replace(/<\?php if\(\$autoPrepare\):\?>[\s\S]*?<\?php endif;\?>/,'');
async function simulate(fail){
 const handlers={},calls=[],progress={textContent:'',after(){}},button={addEventListener:(n,f)=>handlers[n]=f},created={addEventListener(){}};
 const doc={querySelector:s=>s==='#case-progress'?progress:button,querySelectorAll:()=>[],createElement:()=>created};
 const context={document:doc,window:{addEventListener(){}},FormData,fetch:async(url,o)=>{const action=o.body.get('action');calls.push([action,o.body.get('revision')]);if(fail)throw Error('transport');return{ok:true,json:async()=>({ok:true,revision:calls.length+1})};},location:{replace:url=>{context.done=url;}},console};
 vm.runInNewContext(code,context);handlers.click();
 for(let i=0;i<20;i++)await new Promise(resolve=>setImmediate(resolve));
 if(fail){assert.equal(calls.length,1);assert.equal(context.done,undefined);assert.match(progress.textContent,/Lagrede delresultater/);}
 else{assert.deepEqual(calls,[['prepare_radio','1'],['prepare_web','2']]);assert.equal(context.done,'/case.php?item=test');assert(!calls.some(([a])=>/approve|publish|ready/.test(a)));}
}
await simulate(false);await simulate(true);console.log('OK automatic preparation uses fresh revisions, never approves or publishes, stops without retry on failure');
