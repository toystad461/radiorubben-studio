// Exercise production queue/refresh/monitor functions with controlled timing.
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(process.env.RR_STUDIO_SOURCE||path.join(__dirname,'../public/assets/music-studio.mjs'),'utf8');
function functions(start,end){const a=source.indexOf(start),b=source.indexOf(end,a);assert(a>=0&&b>a,'production function boundaries');return source.slice(a,b);}
function context(extra={}){return vm.createContext({...extra});}
async function main(){
  const saved=[{id:'one',title:'First'},{id:'two',title:'Missing duration'},{id:'three',title:'Later'}];
  const row=context({view:{entries:saved,plan:{rows:[{...saved[0],plannedAt:'2026-10-25T07:00:00+01:00'}]}}});
  const before=JSON.stringify(row.view);
  vm.runInContext(functions('function queueRows()', 'function renderQueueStatus()')+'\nrows=queueRows();',row);
  assert.deepEqual(Array.from(row.rows,e=>e.id),['one','two','three']);
  assert.equal(row.rows[0].plannedAt,'2026-10-25T07:00:00+01:00');
  assert.equal(row.rows[1].plannedAt,null);assert(row.rows[1].blocked.length);
  assert.equal(row.rows[2].plannedAt,null);assert.equal(JSON.stringify(row.view),before);
  row.view.plan.rows=[];vm.runInContext('rows=queueRows()',row);assert.equal(row.rows.length,3);

  let releaseGet;const pending=new Promise(resolve=>releaseGet=resolve),posts=[];
  const poll=context({view:{revision:1,player:{seq:5}},busy:false,stale:false,refreshing:null,proposal:null,
    control:true,$:()=>({hidden:false,textContent:''}),say:()=>{},render:()=>{},renderPlayer:()=>{},
    monitorContext:null,heldEntry:()=>false,
    request:body=>body?(posts.push(body),Promise.resolve({revision:2,entries:saved,player:{seq:5}})):pending});
  vm.runInContext(functions('async function refresh(', 'async function suggest('),poll);
  const refreshing=poll.refresh(true),click=poll.mutate({action:'add',trackId:'track'});
  assert.equal(posts.length,0,'click waits for the in-flight GET');
  releaseGet({revision:1,player:{seq:6}});await Promise.all([refreshing,click]);
  assert.equal(posts.length,1,'one click must produce one write, not disappear');
  assert.equal(posts[0].playerRevision,6,'write uses fresh server state');
  assert.equal(poll.view.revision,2);assert.equal(poll.busy,false);

  const starts=[],ctx={currentTime:0,destination:{},decodeAudioData:async()=>({duration:2}),
    createBufferSource:()=>({connect(){},start(t){starts.push(t);}})};
  const monitor=context({monitorContext:ctx,monitorBusy:false,monitorSeen:new Set(),monitorSources:[],monitorNext:0,
    view:{player:{engine:'php',segments:[]}},$:()=>({textContent:''}),
    fetch:async()=>({ok:true,arrayBuffer:async()=>new ArrayBuffer(0)}),stopMonitor:async()=>{throw Error('unexpected failure');}});
  vm.runInContext(functions('async function monitorPump()', "$('monitor').addEventListener('click'"),monitor);
  for(const [i,t] of [0,2.8,5.1].entries()){
    ctx.currentTime=t;monitor.view.player.segments=['segment-'+i];await monitor.monitorPump();
  }
  assert(starts[0]>=2.5,'initial jitter buffer');
  assert.equal(starts[1],starts[0]+2,'second segment has no gap despite network jitter');
  assert.equal(starts[2],starts[1]+2,'third segment remains sample-time contiguous');
  ctx.currentTime=10;monitor.view.player.segments=['segment-3'];await monitor.monitorPump();
  assert(starts[3]>=12.5,'an exhausted buffer rebuilds the margin, never overlaps');
  monitor.view.player.engine='liquidsoap';monitor.view.player.segments=['legacy-must-not-play'];await monitor.monitorPump();
  assert.equal(starts.length,4,'Liquidsoap never uses WAV fallback');
  console.log('PASS saved queue rows, click during polling, fresh revisions, contiguous jittered monitor, underrun recovery, Liquidsoap separation');
}
main().catch(e=>{console.error(e);process.exitCode=1;});
