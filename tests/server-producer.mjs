import assert from 'node:assert/strict';
import {serverProducerContext} from '../public/assets/ai-studio/services/server-producer.mjs';
import {prepareDraft} from '../public/assets/ai-studio/services/producer.mjs';
const now=Date.now(),v={schemaVersion:1,generatedAt:new Date(now).toISOString(),status:'playing',queueKnown:true,now:{kind:'music',artist:'A',title:'Song A'},next:[{kind:'music',artist:'B',title:'Song B'}]};
const c=serverProducerContext(v,now);assert.equal(c.next,'B – Song B');assert.equal(c.source,'vps');assert(prepareDraft('next',c).includes('B – Song B'));assert(!prepareDraft('new',c).includes('Nettopp hørte'));
for(const bad of [{status:'stopped'},{status:'disconnected'},{queueKnown:false},{generatedAt:new Date(now-6000).toISOString()}])assert.throws(()=>serverProducerContext({...v,...bad},now));
assert.equal(serverProducerContext({...v,next:[]},now).next,null);console.log('PASS VPS producer context, stale/disconnected rejection and current-song wording');
