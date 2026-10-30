import {recommendedRoutesApi as service} from '../api/recommended-routes-api.js';
import {hasPermission,handleAdminError} from '../core/session.js';
import {element,status} from '../core/ui.js';
import {confirmAction} from '../components/dialog.js';

export async function initializeRouteList(identity){
  const message=document.querySelector('#route-list-status'),rows=document.querySelector('#route-rows');
  const q=document.querySelector('#route-query'),filter=document.querySelector('#route-status');
  const previous=document.querySelector('#route-previous'),next=document.querySelector('#route-next');
  let page=1,total=0,busy=false,request=0,timer;
  const allowed=action=>!identity.preview?.active&&hasPermission(identity,'routes',action);
  document.querySelector('#new-route').hidden=!allowed('create');
  function controls(){previous.disabled=busy||page<=1;next.disabled=busy||page*20>=total;}
  async function load(){
    const generation=++request;busy=true;controls();status(message,'載入中…');
    try{
      const result=await service.list({q:q.value,status:filter.value,page:String(page)});
      if(generation!==request)return;
      total=result.total;rows.replaceChildren();
      for(const record of result.items){
        const row=element('tr');
        for(const text of [record.title,record.theme||'—',String(record.stops.length),String(record.sortOrder),{draft:'草稿',published:'已發布',archived:'下架'}[record.status]])row.append(element('td',{},text));
        const actions=element('td');
        if(allowed('edit'))actions.append(element('a',{className:'table-actions',href:'/admin/route-edit.html?id='+encodeURIComponent(record.id)},'編輯'));
        if(allowed('delete')){
          const remove=element('button',{type:'button',className:'table-actions'},'刪除');
          remove.addEventListener('click',async()=>{
            if(busy||!await confirmAction('刪除推薦行程',`確定刪除「${record.title}」？`,remove))return;
            busy=true;controls();remove.disabled=true;
            try{const result=await service.delete(record.id,record.version);await load();status(message,result.publication.message,result.publication.status!=='succeeded');message.focus();}
            catch(error){handleAdminError(error,message);}finally{busy=false;controls();remove.disabled=false;}
          });actions.append(document.createTextNode('　'),remove);
        }
        if(!actions.childNodes.length)actions.textContent='唯讀';row.append(actions);rows.append(row);
      }
      if(!result.items.length){const row=element('tr');row.append(element('td',{colspan:'6'},'目前沒有符合條件的推薦行程。'));rows.append(row);}
      document.querySelector('#route-page').textContent=`第 ${page} 頁，共 ${total} 筆`;status(message,`已載入 ${result.items.length} 筆推薦行程。`);
    }catch(error){if(generation===request)handleAdminError(error,message);}finally{if(generation===request){busy=false;controls();}}
  }
  q.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;void load();},250);});
  filter.addEventListener('change',()=>{page=1;void load();});
  previous.addEventListener('click',()=>{if(!busy&&page>1){page--;void load();}});
  next.addEventListener('click',()=>{if(!busy&&page*20<total){page++;void load();}});
  await load();
}
