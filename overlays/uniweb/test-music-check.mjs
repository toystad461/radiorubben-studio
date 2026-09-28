import test from 'node:test';import assert from 'node:assert/strict';
import {catalogFiles,checkTrack,reserveCandidates,hourPreview} from './studio-public/assets/music-check.mjs';
const file=(name,path=name,broken=false)=>({name,webkitRelativePath:path,size:123,slice:()=>({arrayBuffer:async()=>{if(broken)throw Error('offline');return new ArrayBuffer(12);}})});
test('folder and filename formats preserve version names',()=>{
 const c=catalogFiles([file('Artist - Song (Live).mp3'),file('01 Track.mp3','Archive/Other/Album/01 Track.mp3'),file('cover.jpg')]);
 assert.equal(c.length,2);assert.equal(c[0].title,'Song (Live)');assert.equal(c[1].artist,'Other');assert.equal(c[1].title,'Track');
});
test('unconnected archive is unknown, not missing',async()=>assert.equal((await checkTrack({artist:'A',title:'T'},[],false)).status,'unknown'));
test('exact normalized match reads the file, not just its name',async()=>{
 const c=catalogFiles([file('A - T.mp3')]);assert.equal((await checkTrack({artist:'a',title:'t'},c,true)).status,'found');
 assert.equal((await checkTrack({artist:'A',title:'Different'},c,true)).status,'missing');
 assert.equal((await checkTrack({artist:'A',title:'T'},catalogFiles([file('A - T.mp3','A - T.mp3',true)]),true)).status,'unavailable');
});
test('multiple versions, empty files and unknown artist are not silently accepted',async()=>{
 const track={artist:'A',title:'T'};assert.equal((await checkTrack(track,catalogFiles([file('A - T.mp3'),file('A - T.wav')]),true)).status,'ambiguous');
 assert.equal((await checkTrack(track,catalogFiles([{...file('A - T.mp3'),size:0}]),true)).status,'unavailable');
 assert.equal(reserveCandidates(track,catalogFiles([file('Song.mp3')]),[]).length,0);
});
test('reserves exclude planned songs and prioritize same artist without claiming mood',()=>{
 const c=catalogFiles([file('A - T.mp3'),file('A - R.mp3'),file('B - U.mp3'),file('C - Planned.mp3')]);
 const r=reserveCandidates({artist:'A',title:'T'},c,[{artist:'C',title:'Planned'}]);assert.deepEqual(r.map(t=>t.title),['R','U']);
});
test('hour preview uses three songs in selected even hour; odd hours excluded',()=>{
 const p=[{artist:'A',title:'one',slot:'06:00'},{artist:'B',title:'two',slot:'08:00'},...['three','four','five'].map(title=>({artist:'C',title,slot:'06:00'}))];
 assert.deepEqual(hourPreview(p,'06:00').map(t=>t.title),['one','three','four']);assert.equal(hourPreview(p,'07:00').length,0);assert.equal(hourPreview(p,'06:00')[0].clipSeconds,6);
});
