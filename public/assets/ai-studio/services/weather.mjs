export function weatherLabel(symbol='unknown') {
  const night=symbol.endsWith('_night');
  if(symbol.includes('thunder'))return ['⛈','Tordenbyger'];
  if(symbol.includes('sleet'))return ['🌨','Sludd'];
  if(symbol.includes('snow'))return ['❄','Snø'];
  if(symbol.includes('rain'))return ['🌧',symbol.includes('showers')?'Regnbyger':'Regn'];
  if(symbol.includes('fog'))return ['🌫','Tåke'];
  if(symbol.startsWith('partlycloudy'))return [night?'☁':'🌤','Delvis skyet'];
  if(symbol.startsWith('fair'))return [night?'🌙':'🌤','Lettskyet'];
  if(symbol.startsWith('clearsky'))return [night?'🌙':'☀',night?'Klarvær':'Sol'];
  if(symbol==='cloudy')return ['☁','Skyet'];
  return ['–','Værprognose'];
}
export function initWeather(){
 const el=id=>document.getElementById(id);let busy=false;
 const refresh=async()=>{
  if(busy || document.hidden)return;busy=true;
  try{
   const response=await fetch('/weather-api.php',{signal:AbortSignal.timeout(12000)});
   const data=await response.json();if(!response.ok || !Number.isFinite(data.temperature))throw Error();
   const [icon,label]=weatherLabel(data.symbol);
   el('weather-icon').textContent=icon;el('weather-temperature').textContent=`${Math.round(data.temperature)}°C`;
   el('weather-condition').textContent=label;el('weather-place').textContent=data.place;
   el('weather-time').textContent='Prognose kl. '+new Date(data.time).toLocaleTimeString('nb-NO',{timeZone:'Europe/Oslo',hour:'2-digit',minute:'2-digit'});
  }catch{el('weather-icon').textContent='–';el('weather-temperature').textContent='—';el('weather-condition').textContent='Vær utilgjengelig';el('weather-time').textContent='Prøver igjen automatisk';}
  finally{busy=false;}
 };
 void refresh();setInterval(refresh,600000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)void refresh();});
}
