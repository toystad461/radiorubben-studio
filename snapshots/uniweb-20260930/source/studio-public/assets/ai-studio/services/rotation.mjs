// Playback sequencing is independent of AI availability and the browser player.
export class Rotation {
  constructor(){this.enabled=false;this.interval=3;this.completed=0;this.lastBreak=null;}
  setEnabled(value){this.enabled=Boolean(value);if(!value)this.completed=0;}
  setInterval(value){this.interval=[1,3,5].includes(Number(value))?Number(value):3;}
  ended(track,breaks){
    if(!this.enabled)return null;
    if(track?.kind==='break'){this.completed=0;return null;}
    this.completed++;
    if(this.completed<this.interval||!breaks.length)return null;
    const next=breaks[(Math.max(-1,breaks.findIndex(t=>t.id===this.lastBreak))+1)%breaks.length];
    this.lastBreak=next.id;this.completed=0;return next;
  }
}
