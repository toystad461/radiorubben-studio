import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const nodes=new Map(),intervals=[],listeners={};
const node=id=>{if(!nodes.has(id))nodes.set(id,{dataset:{},textContent:'',paused:true,hidden:false,src:'',events:{},replaceChildren(...items){this.children=items;},addEventListener(e,f){this.events[e]=f;},removeAttribute(k){delete this[k];},load(){},async play(){this.paused=false;this.events.playing?.();},pause(){this.paused=true;this.events.pause?.();}});return nodes.get(id);};
let now=Date.now(),fail=false,view={schemaVersion:1,generatedAt:new Date(now).toISOString(),status:'playing',now:{artist:'Artist',title:'<script>unsafe</script>',duration:120},position:12,next:[{artist:'Next',title:'Track',duration:80}],queueKnown:true,engineOnline:true,streamConnected:true,streamUrl:'/music-test-stream/'};
class Clock extends Date {static now(){return now;}}
const context={document:{getElementById:node,createElement:()=>({textContent:''}),addEventListener(e,f){listeners[e]=f;},hidden:false},window:{addEventListener(){}},fetch:async()=>{if(fail)throw Error();return {ok:true,json:async()=>view};},AbortSignal,Date:Clock,setInterval(f,ms){intervals.push({f,ms});return intervals.length;},clearInterval(){}};
vm.runInNewContext(fs.readFileSync(new URL('../public/assets/ai-studio/controlroom.mjs',import.meta.url),'utf8'),context);
await new Promise(r=>setImmediate(r));
assert.equal(node('server-now').textContent,'Artist – <script>unsafe</script>');assert.equal(node('server-progress').value,12);assert.equal(node('server-audio').src,'','never starts listening on load');
await node('server-listen').events.click();assert.equal(node('server-audio').src,'/music-test-stream/');assert.equal(node('server-audio').paused,false);
await node('server-listen').events.click();assert.equal(node('server-audio').paused,true);assert.equal(node('server-audio').src,undefined);
const poll=intervals.find(i=>i.ms===2000).f;
for(const status of ['stopped','disconnected','unknown','paused','blocked']){view={...view,status};await poll();assert.equal(node('server-progress').hidden,true);assert(!node('server-now').textContent.includes('Artist'));}
view={...view,status:'playing',streamUrl:'https://evil.invalid/'};await poll();assert.equal(node('server-listen').disabled,true,'reject arbitrary stream source');
now+=6000;intervals.find(i=>i.ms===500).f();assert.equal(node('server-air-status').textContent,'Ukjent · ingen fersk bekreftelse');assert.equal(node('server-next').children[0].textContent,'Køstatus ukjent');
await poll();assert.equal(node('server-progress').hidden,true,'stale response rejected');
view={...view,generatedAt:new Date(now).toISOString()};await poll();assert.equal(node('server-progress').hidden,false,'recovers on fresh response');
fail=true;await poll();assert.equal(node('server-progress').hidden,true);assert.equal(node('server-next').children[0].textContent,'Køstatus ukjent');
console.log('PASS controlroom: stale/error/unknown recovery, text escaping, listening isolation, source allowlist');
