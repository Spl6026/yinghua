// [E-41] DB的無offset時間是UTC；顯示一律轉台北，不依訪客裝置時區猜測。
export function formatUtcDateTime(value){
  if(typeof value!=='string'||!value.trim())return '—';
  const stamp=/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(value)?value.replace(' ','T')+'Z':value;
  const date=new Date(stamp);if(!Number.isFinite(date.getTime()))return '—';
  const parts=Object.fromEntries(new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Taipei',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23'}).formatToParts(date).map(part=>[part.type,part.value]));
  return `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}:${parts.second}`;
}
