import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const source=readFileSync(new URL('../../public/assets/music-track-actions.js',import.meta.url),'utf8');
async function scenario(kind) {
  let click;const calls=[];const status={textContent:''};
  const button={dataset:{trackQueue:'track',trackVersion:'hash'},disabled:false,
    addEventListener:(_,fn)=>{click=fn;},closest:()=>null};
  const before={revision:7,entries:[],studio:{schedule:kind==='unscheduled'?null:{programId:'morning'}}};
  const document={querySelectorAll:s=>s==='[data-track-queue]'?[button]:[],addEventListener:()=>{},
    getElementById:()=>status,querySelector:()=>({dataset:{queueCsrf:'csrf'}})};
  vm.runInNewContext(source,{document,window:{addEventListener:()=>{}},location:{hash:''},
    fetch:async(url,options)=>{calls.push({url,options});
      if(options.method==='POST') {
        if(kind==='network')throw Error('Network error');
        if(kind==='conflict')return {ok:false,json:async()=>({error:'Køen er endret.'})};
        return {ok:true,json:async()=>({revision:8,entries:[{id:'new',trackId:'track',sha256:'hash'}]})};
      }
      return {ok:true,json:async()=>before};
    }});
  const pending=click();await click();await pending;
  assert.equal(calls.filter(c=>c.options.method==='POST').length,kind==='unscheduled'?0:1);
  assert.equal(button.disabled,false);
  if(kind==='success') {
    assert.match(status.textContent,/lagt sist/);
    const post=calls[1];assert.deepEqual(JSON.parse(post.options.body),{action:'add',revision:7,trackId:'track',sha256:'hash'});
    assert.equal(post.options.headers['X-CSRF-Token'],'csrf');
  } else if(kind==='unscheduled')assert.match(status.textContent,/ikke lagt til/);
  else assert.match(status.textContent,/endringen kan være lagret/);
}
for(const kind of ['success','unscheduled','conflict','network'])await scenario(kind);
console.log('PASS: append-only queue action, CSRF/revision, duplicate clicks, missing schedule and uncertain writes without retry.');
