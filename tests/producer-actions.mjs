import assert from 'node:assert/strict';
import {prepareDraft} from '../public/assets/ai-studio/services/producer.mjs';
import {defaults, ACTIONS} from '../public/assets/ai-studio/services/mapping.mjs';
const context={nowPlaying:'CURRENT SONG',next:'NEXT SONG'};
for(const action of ['weather','traffic','local','sponsor']) {
  const draft=prepareDraft(action,context,'OLD MUSIC BREAK');
  for(const music of ['CURRENT SONG','NEXT SONG','OLD MUSIC BREAK']) assert(!draft.includes(music));
  assert(draft.includes('['));
}
assert.deepEqual(defaults().map(p=>p.action),['new','shorter','local','weather','next','sponsor','refresh','open']);
assert.equal(ACTIONS.traffic,'Trafikk');
assert(prepareDraft('next',context).includes('NEXT SONG'));
console.log('PASS topic-specific local templates and unchanged existing PAD assignments');
