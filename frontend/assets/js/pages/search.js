import {api} from '../core/api-client.js';
import {element} from '../core/ui.js';
import {memberNavigation} from '../core/session.js';
import {searchTypes,searchState,searchUrl,latestSearch} from '../core/search-state.js';
import {searchCard} from '../components/search-card.js';

void memberNavigation();
const form=document.querySelector('#standalone-search'),input=document.querySelector('#query-input'),type=document.querySelector('#search-type');
const results=document.querySelector('#search-results'),status=document.querySelector('#search-status'),pagination=document.querySelector('#search-pagination'),retry=document.querySelector('#search-retry');
const submit=form.querySelector('button[type=submit]');
let state=searchState(location.search);
const run=latestSearch(async value=>{
  if(!value.q)return {items:[],total:0,page:1,pageSize:20};
  return api('/api/v1/search?'+new URLSearchParams({...value,pageSize:'20'}));
});

function groupResults(items){
  for(const [kind,label]of Object.entries(searchTypes)){
    const matches=items.filter(item=>item.type===kind);if(!matches.length)continue;
    const section=element('section',{className:'search-group'});section.append(element('h2',{},label));
    for(const item of matches)section.append(searchCard(item,label));
    results.append(section);
  }
}
function pageLink(label,page){return element('a',{className:'button button-outline',href:searchUrl({...state,page:String(page)})},label);}
function complete(){results.setAttribute('aria-busy','false');submit.disabled=false;}
function show(focus=false){
  input.value=state.q;type.value=state.type;input.removeAttribute('aria-invalid');input.setAttribute('aria-describedby','search-help');input.setCustomValidity('');type.removeAttribute('aria-invalid');type.removeAttribute('aria-describedby');
  results.replaceChildren();pagination.replaceChildren();retry.hidden=true;status.dataset.error='false';
  results.setAttribute('aria-busy','true');submit.disabled=true;status.classList.toggle('empty-results',!state.q);status.textContent=state.q?'正在搜尋…':'輸入關鍵字，即可搜尋景點、美食、片單與城市消息。';
  return run({...state},data=>{
    complete();groupResults(data.items);
    status.classList.toggle('empty-results',!state.q||data.total===0);
    if(state.q)status.textContent=data.total?`共找到 ${data.total} 筆結果。`:'沒有找到符合條件的資料，請試試景點名稱、電影片名或活動關鍵字。';
    if(data.total&&!data.items.length){status.textContent=`此頁沒有結果，共找到 ${data.total} 筆。請回到第一頁。`;pagination.append(pageLink('回到第一頁',1));}
    else if(data.total){if(data.page>1)pagination.append(pageLink('上一頁',data.page-1));pagination.append(element('span',{},`第 ${data.page}／${Math.ceil(data.total/data.pageSize)} 頁`));if(data.page*data.pageSize<data.total)pagination.append(pageLink('下一頁',data.page+1));}
    if(focus)status.focus();
  },error=>{
    complete();status.dataset.error='true';status.textContent=error.fieldErrors?.q||error.fieldErrors?.type||error.message||'搜尋暫時無法使用，請重新搜尋。';retry.hidden=false;
    if(error.fieldErrors?.q){input.setAttribute('aria-invalid','true');input.setAttribute('aria-describedby','search-help search-status');input.setCustomValidity(error.fieldErrors.q);input.focus();}else if(error.fieldErrors?.type){type.setAttribute('aria-invalid','true');type.setAttribute('aria-describedby','search-status');type.focus();}else if(focus)status.focus();
  });
}
function navigate(next){state=next;history.pushState(null,'',searchUrl(state));void show(true);}
form.addEventListener('submit',event=>{event.preventDefault();navigate({q:input.value.trim(),type:type.value,page:'1'});});
type.addEventListener('change',()=>navigate({q:input.value.trim(),type:type.value,page:'1'}));
input.addEventListener('input',()=>{input.setCustomValidity('');input.removeAttribute('aria-invalid');});
pagination.addEventListener('click',event=>{const link=event.target.closest('a');if(!link||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;event.preventDefault();navigate(searchState(new URL(link.href).search));});
retry.addEventListener('click',()=>void show(true));
window.addEventListener('popstate',()=>{state=searchState(location.search);void show(true);});
void show();
