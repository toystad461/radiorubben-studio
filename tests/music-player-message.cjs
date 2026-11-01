// Adapt PR #113's renderer regression to the L01 interface and stale/offline data.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/music-studio.mjs'), 'utf8');
const start = source.indexOf('function playerMessage('), end = source.indexOf('function render()');
assert(start >= 0 && end > start, 'load the actual production renderer');
const actual = source.slice(start, end);
function render(player, stale = false) {
  const nodes = new Map();
  const $ = id => {
    if (!nodes.has(id)) nodes.set(id, {textContent:'', disabled:false, replaceChildren(){}});
    return nodes.get(id);
  };
  const p = {online:true,ready:true,outcomes:{},...player};
  const before = JSON.stringify(p);
  vm.runInNewContext(actual + '\nrenderPlayer();', {
    $,view:{player:p},control:true,busy:false,stale,monitorContext:null,monitorAudio:null,
    renderMonitorControl(){},renderQueueStatus(){},
    title: () => 'Testlåt',duration: () => '0:01',when: () => '12:00',txt: () => ({})
  });
  assert.equal(JSON.stringify(p), before, 'rendering must preserve queue and journal data');
  return {detail:nodes.get('player-detail').textContent,mode:nodes.get('player-mode').textContent};
}
for (const engine of ['php', 'liquidsoap']) {
  for (const message of ['Køen er ferdig.', 'Fullført på isolert testutgang.']) {
    const playing = {engine,mode:'playing',current:{},message};
    assert.equal(render(playing).detail, 'Avspilling pågår på testserveren.');
    for (const mode of ['stopped','paused','starting','blocked']) {
      assert.equal(render({...playing,mode}).detail, message);
    }
    assert.equal(render({...playing,current:null}).detail, message);
    for (const result of [render({...playing,online:false}), render(playing,true)]) {
      assert.match(result.detail, /^Avspillingen kan ikke bekreftes/);
      assert(result.detail.includes(message), 'keep last known message identifiable');
      assert(!result.detail.includes('pågår'));
      assert.notEqual(result.mode, 'Spiller på testserveren');
    }
    const pending = render({...playing,pendingCommand:'stop',ready:false}).detail;
    assert.match(pending,/Venter på kommandobekreftelse/);
    assert.match(pending,/Køen må synkroniseres/);
  }
  for (const stale of [false,true]) {
    assert.equal(render({engine,mode:'playing',current:{},message:'Dekoderfeil'},stale).detail,'Dekoderfeil');
  }
}
console.log('PASS: PHP/Liquidsoap rendering, previous outcomes, stale/offline status, errors, pending commands and unchanged data');
