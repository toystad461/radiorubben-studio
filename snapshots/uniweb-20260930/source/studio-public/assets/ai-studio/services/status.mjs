// Keep independent requests from overwriting another feature's active work.
export class AiStatus {
  constructor(render, ready) { this.render=render; this.jobs=new Map(); this.last=ready?'Konfigurert · venter på første forespørsel':'Ikke konfigurert · manuell drift'; this.show(); }
  update(source, busy, message) { if(busy)this.jobs.set(source,message);else{this.jobs.delete(source);this.last=message;}this.show(); }
  show(){this.render(this.jobs.size?[...this.jobs.values()].join(' · '):this.last);}
}
export function watchStudioServer(element) {
  let busy=false;
  const local=['localhost','127.0.0.1','[::1]'].includes(location.hostname);
  async function check(){
    if(busy)return;busy=true;
    try{
      const response=await fetch(location.pathname,{method:'HEAD',cache:'no-store',redirect:'error',signal:AbortSignal.timeout(5000)});
      if(!response.ok)throw Error();
      element.textContent=`${local?'Lokal studioserver':'Studioserver'} svarer · ${new Date().toLocaleTimeString('nb-NO',{hour12:false})}`;
    }catch{element.textContent='Studioserver svarer ikke · prøver igjen';}finally{busy=false;}
  }
  void check();const timer=setInterval(check,30000);
  window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
}
