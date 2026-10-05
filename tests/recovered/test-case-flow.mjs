import fs from 'node:fs';import vm from 'node:vm';import assert from 'node:assert/strict';
const code=fs.readFileSync(new URL('../../public/assets/case.js', import.meta.url),'utf8');
const settings={dataset:{item:'test',revision:'1',csrf:'csrf',autoPrepare:'0'}};
async function simulate(fail){
 const handlers={},calls=[],progress={textContent:'',after(){}},button={addEventListener:(n,f)=>handlers[n]=f},created={addEventListener(){}};
 const doc={querySelector:s=>s==='#case-controller'?settings:s==='#case-progress'?progress:s==='#news-scope'?null:button,querySelectorAll:()=>[],createElement:()=>created};
 const context={document:doc,window:{addEventListener(){}},FormData,fetch:async(url,o)=>{const action=o.body.get('action');calls.push([action,o.body.get('revision')]);if(fail)throw Error('transport');return{ok:true,json:async()=>({ok:true,revision:calls.length+1})};},location:{replace:url=>{context.done=url;}},console};
 vm.runInNewContext(code,context);handlers.click();
 for(let i=0;i<20;i++)await new Promise(resolve=>setImmediate(resolve));
 if(fail){assert.equal(calls.length,1);assert.equal(context.done,undefined);assert.match(progress.textContent,/Lagrede delresultater/);}
 else{assert.deepEqual(calls,[['prepare_radio','1'],['prepare_web','2']]);assert.equal(context.done,'/case.php?item=test');assert(!calls.some(([a])=>/approve|publish|ready/.test(a)));}
}
await simulate(false);await simulate(true);console.log('OK automatic preparation uses fresh revisions, never approves or publishes, stops without retry on failure');

async function placementChange(){
 const handlers={},calls=[],progress={textContent:'',after(){}},finalButton={disabled:false};
 const scope={value:'local',disabled:false,addEventListener:(name,fn)=>handlers['scope:'+name]=fn};
 const form={dataset:{save:'web'},addEventListener:(name,fn)=>handlers['web:'+name]=fn};
 const finalForm={addEventListener:(name,fn)=>handlers['final:'+name]=fn};
 const button={addEventListener(){}};
 const doc={querySelector:s=>({'#case-controller':settings,'#case-progress':progress,'#news-scope':scope,'#web-editor':form}[s]??button),
  querySelectorAll:s=>s==='[data-save]'?[form]:s==='[data-final]'?[finalForm]:s==='[data-final] button'?[finalButton]:s==='button,select,input[type=checkbox]'?[scope,finalButton]:[],
  createElement:()=>({addEventListener(){}})};
 // Like a browser, disabled selects are omitted by FormData(form).
 class BrowserFormData extends FormData{constructor(f){super();if(f&&!scope.disabled)this.set('news_scope',scope.value);}}
 const context={document:doc,window:{addEventListener(){}},FormData:BrowserFormData,
  fetch:async(url,o)=>{calls.push([o.body.get('action'),o.body.get('news_scope')]);return{ok:true,json:async()=>({ok:true,revision:calls.length+1})};},
  location:{replace:url=>context.done=url},console};
 vm.runInNewContext(code,context);handlers['scope:change']();
 assert.equal(finalButton.disabled,true);let blocked=false;
 handlers['final:submit']({preventDefault(){blocked=true;}});assert(blocked);
 handlers['web:submit']({preventDefault(){}});
 for(let i=0;i<20;i++)await new Promise(resolve=>setImmediate(resolve));
 assert.deepEqual(calls,[['save_web','local'],['check_web',null]]);
 assert.equal(context.done,'/case.php?item=test');
}
await placementChange();console.log('OK category changes block approval and survive disabled controls during save/review');
