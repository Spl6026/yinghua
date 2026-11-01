import {api} from '../core/api-client.js';
import {element,status} from '../core/ui.js';
import {hasPermission,handleAdminError} from '../core/session.js';
const labels={attractions:'景點與店家',films:'本週映演',news:'消息與活動',offers:'店家優惠',members:'會員'};
const paths={attractions:'place',films:'film',news:'news',offers:'offer',routes:'route'};
export async function initializeOperations(identity){
  const host=document.querySelector('#operations'),message=document.querySelector('#operations-status');
  let busy=false;
  async function load(){
    if(busy)return;busy=true;status(message,'載入中…');host.setAttribute('aria-busy','true');
    try{
      const result=await api('/api/v1/admin/dashboard',{kind:'admin'});host.replaceChildren();
      const cards=element('div',{className:'stat-grid'});
      for(const [module,count]of Object.entries(result.counts)){const card=element('div',{className:'stat-card'});card.append(element('span',{},labels[module]),element('strong',{},String(count)),element('small',{},'未刪除資料總數'));cards.append(card);}
      if(!cards.childNodes.length)cards.append(element('p',{},'尚未取得內容模組檢視權限。'));host.append(cards);
      const columns=element('div',{className:'admin-two-col'}),recent=element('section',{className:'admin-panel'}),quick=element('section',{className:'admin-panel'});recent.append(element('h2',{},'最近更新'));quick.append(element('h2',{},'快速新增'));
      const scroll=element('div',{className:'table-scroll',tabindex:'0',role:'region','aria-label':'資料表，可水平捲動'}),table=element('table',{className:'admin-table'}),head=element('thead'),tr=element('tr');for(const label of ['內容','類型','狀態','更新時間'])tr.append(element('th',{scope:'col'},label));head.append(tr);table.append(head);const body=element('tbody');
      for(const item of result.recent){const row=element('tr');for(const value of [item.title,labels[item.module],{draft:'草稿',published:'已發布',archived:'下架'}[item.status]||item.status,item.updatedAt])row.append(element('td',{},value));body.append(row);}
      if(!result.recent.length){const row=element('tr');row.append(element('td',{colspan:'4'},'目前沒有可檢視的更新。'));body.append(row);}table.append(body);scroll.append(table);recent.append(scroll);
      const shortcuts=element('div',{className:'quick-actions'});
      for(const [module,path]of Object.entries(paths))if(hasPermission(identity,module,'create')&&!identity.preview?.active)shortcuts.append(element('a',{href:'/admin/'+path+'-add.html',className:'button button-outline'},'＋ '+(module==='routes'?'編輯行程':'新增'+labels[module])));
      quick.append(shortcuts);
      if(!shortcuts.childNodes.length)quick.append(element('p',{},'目前沒有新增權限。'));columns.append(recent,quick);host.append(columns);
      if(result.auditAvailable&&!identity.preview?.active)await auditPanel(host,message);
      status(message,'已載入有權檢視的即時統計。');
    }catch(error){handleAdminError(error,message);}finally{busy=false;host.removeAttribute('aria-busy');}
  }
  document.querySelector('#operations-retry').addEventListener('click',()=>void load());await load();
}
async function auditPanel(host,message){
  const panel=element('section',{className:'admin-panel'}),rows=element('tbody'),note=element('p',{role:'status'}),previous=element('button',{type:'button',className:'button button-outline'},'稽核上一頁'),next=element('button',{type:'button',className:'button button-outline'},'稽核下一頁');let page=1,busy=false,total=0;
  panel.append(element('h2',{},'操作稽核（系統管理員）'));const scroll=element('div',{className:'table-scroll',tabindex:'0',role:'region','aria-label':'資料表，可水平捲動'}),table=element('table',{className:'admin-table'}),head=element('thead'),row=element('tr');for(const title of ['時間','動作','資源類型','資源識別','操作者識別'])row.append(element('th',{scope:'col'},title));head.append(row);table.append(head,rows);scroll.append(table);panel.append(scroll,note,previous,next);host.append(panel);
  function controls(){previous.disabled=busy||page===1;next.disabled=busy||page*20>=total;}
  async function load(){if(busy)return;busy=true;controls();try{const result=await api('/api/v1/admin/audit-logs?page='+page,{kind:'admin'});total=result.total;rows.replaceChildren();for(const item of result.items){const row=element('tr');for(const value of [item.createdAt,item.action,item.resourceType,item.resourceId,item.actorId])row.append(element('td',{},value));rows.append(row);}note.textContent=`第 ${page} 頁，共 ${total} 筆`;if(!result.items.length)note.textContent+='，目前沒有稽核紀錄。';}catch(error){handleAdminError(error,message);}finally{busy=false;controls();}}
  previous.addEventListener('click',()=>{if(!busy&&page>1){page--;void load();}});next.addEventListener('click',()=>{if(!busy&&page*20<total){page++;void load();}});await load();
}
