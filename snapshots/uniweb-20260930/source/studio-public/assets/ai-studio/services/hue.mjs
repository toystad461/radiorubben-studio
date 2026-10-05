export class HueFollower {
  constructor(csrf,onStatus,request=(...args)=>fetch(...args)){this.csrf=csrf;this.onStatus=onStatus;this.request=request;this.tab=crypto.randomUUID();this.enabled=false;this.live=null;this.busy=false;this.timer=null;}
  allOff(){this.command='off';this.enabled=false;clearInterval(this.timer);return this.sync();}
  setEnabled(enabled){if(enabled)this.command='resume';this.enabled=enabled;clearInterval(this.timer);this.timer=enabled?setInterval(()=>void this.sync(),4000):null;void this.sync();}
  update(live){if(this.live===live)return;this.live=live;if(this.enabled)void this.sync();}
  async sync(){
    if(this.busy){this.pending=true;return;}this.busy=true;this.pending=false;
    const live=this.enabled?this.live:null; const action=this.command||'sync';this.command=null;
    try{
      const r=await this.request('/hue-api.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':this.csrf},body:JSON.stringify({live,tab:this.tab,action}),signal:AbortSignal.timeout(12000)});
      const result=await r.json();if(!r.ok)throw Error(result.error||'Hue svarte ikke.');
      if(result.manualOff){this.enabled=false;clearInterval(this.timer);this.onStatus(result.message);}
      else this.onStatus(this.enabled?(live===true?result.message:'Klar · tidligere lys beholdes'):'Hue-følging er av. Tidligere lys er gjenopprettet.');
    }catch(e){this.onStatus(e.message || 'Hue svarte ikke.');}
    finally{this.busy=false;if(this.pending)void this.sync();}
  }
  close(){clearInterval(this.timer);if(this.enabled)navigator.sendBeacon('/hue-api.php',new Blob([JSON.stringify({live:null,tab:this.tab,csrf:this.csrf})],{type:'application/json'}));}
}
