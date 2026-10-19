import { element } from '../core/ui.js';
import { screeningStart,screeningFields } from '../core/screening-time.js';
import { confirmAction } from './dialog.js';

/** [S：功能規格 21；U-16] 編輯表單集合，按儲存才寫入；ID 與輸入不因重新編號遺失。 */
export function screeningEditor(container,onChange=()=>{}) {
  let rows=[],disabled=false,sequence=0;
  const list=element('div',{className:'film-screenings'});
  const add=element('button',{type:'button',className:'button button-outline','data-write':''},'＋ 新增場次');
  const empty=element('p',{},'尚未安排場次；可先儲存電影。');
  container.replaceChildren(list,empty,add);
  function renumber(){
    rows.forEach((row,index)=>{
      row.legend.textContent=`場次 ${index+1}`;
      row.date.name=`screenings.${index}.date`;row.time.name=`screenings.${index}.time`;row.ticket.name=`screenings.${index}.ticketUrl`;
      row.date.dataset.fieldName=`screenings.${index}.startsAt`;row.ticket.dataset.fieldName=`screenings.${index}.ticketUrl`;
      row.fieldset.dataset.fieldName=`screenings.${index}.id`;row.fieldset.tabIndex=-1;
      row.remove.setAttribute('aria-label',`移除場次 ${index+1}`);
    });
    empty.hidden=rows.length>0;
  }
  function append(value={}) {
    const number=++sequence,fieldset=element('fieldset',{className:'film-screening'}),legend=element('legend');
    const grid=element('div',{className:'admin-form-grid'});
    const input=(name,label,type)=>{
      const field=element('div',{className:'admin-field'+(name==='ticket'?' full':'')});
      const id=`screening-${number}-${name}`,control=element('input',{id,type,autocomplete:'off'});
      field.append(element('label',{for:id},label),control);grid.append(field);return control;
    };
    const date=input('date','放映日期','date'),time=input('time','放映時間（台北時間）','time'),ticket=input('ticket','此場售票連結（選填，留空沿用電影）','url');
    ticket.maxLength=2000;time.step='60';
    if(value.startsAt){const fields=screeningFields(value.startsAt);date.value=fields.date;time.value=fields.time;}
    ticket.value=value.ticketUrl||'';
    const remove=element('button',{type:'button',className:'button button-outline','data-write':''},'移除場次');
    const row={id:value.id||null,fieldset,legend,date,time,ticket,remove};
    remove.addEventListener('click',async()=>{
      if(disabled)return;
      if(!await confirmAction('移除場次','這筆場次會從表單移除，儲存電影後才正式刪除。',remove)||disabled)return;
      const index=rows.indexOf(row);rows.splice(index,1);fieldset.remove();renumber();onChange();
      (rows[index]?.date||rows[index-1]?.date||add).focus();
    });
    fieldset.append(legend,grid,remove);list.append(fieldset);rows.push(row);renumber();
    for(const control of [date,time,ticket,remove])control.disabled=disabled;
    return row;
  }
  add.addEventListener('click',()=>{if(!disabled){append().date.focus();onChange();}});
  container.addEventListener('input',()=>onChange());
  return {
    load(values=[]){rows=[];list.replaceChildren();for(const value of values)append(value);renumber();},
    read(){
      const result=[];
      for(const [index,row]of rows.entries()){
        const startsAt=screeningStart(row.date.value,row.time.value,index);
        if(startsAt===null){
          throw Object.assign(new Error('請填寫場次日期及時間，或按「移除場次」。'),{fieldErrors:{[`screenings.${index}.startsAt`]:'請填寫日期及時間，或移除此場次。'}});
        }
        result.push({...row.id?{id:row.id}:{},startsAt,ticketUrl:row.ticket.value.trim()});
      }
      return result;
    },
    setDisabled(value){disabled=value;add.disabled=value;for(const row of rows)for(const control of [row.date,row.time,row.ticket,row.remove])control.disabled=value;},
  };
}
