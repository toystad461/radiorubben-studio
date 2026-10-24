const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/music-studio.mjs'), 'utf8');
const actual = source.slice(source.indexOf('function playerMessage('), source.indexOf('function render()'));
function render(player, pending = false) {
  const nodes = new Map();
  const $ = id => {
    if (!nodes.has(id)) nodes.set(id, {textContent:'', disabled:false, replaceChildren(){}});
    return nodes.get(id);
  };
  const p = {online:true,ready:true,outcomes:{},...player,pendingCommand:pending};
  const before = JSON.stringify(p);
  vm.runInNewContext(actual + '\nrenderPlayer();', {
    $,view:{player:p},control:true,busy:false,stale:false,monitorContext:null,
    title: () => 'Testlåt',duration: () => '0:01',when: () => '12:00',txt: () => ({})
  });
  assert.equal(JSON.stringify(p), before, 'UI must not alter player/journal data');
  return nodes.get('player-detail').textContent;
}
for (const message of ['Køen er ferdig.','Fullført på isolert testutgang.']) {
  assert.equal(render({mode:'playing',current:{},message}), 'Avspilling pågår på isolert testutgang.');
  assert.equal(render({mode:'stopped',current:null,message}), message);
  assert.equal(render({mode:'paused',current:{},message}), message);
}
assert.equal(render({mode:'playing',current:{},message:'Dekoderfeil'}),'Dekoderfeil');
assert.match(render({mode:'playing',current:{},message:'Køen er ferdig.'},true), /Venter på kommandobekreftelse/);
assert.equal(render({mode:'playing',current:null,message:'Køen er ferdig.'}),'Køen er ferdig.');
console.log('PASS: actual player rendering, stale outcome messages, errors, pending command, unchanged state');
