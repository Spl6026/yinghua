import { element } from '../core/ui.js';

// [S G34／B15；E-29] 專用模態框不取代表單，也不把未儲存內容寫入瀏覽器儲存空間。
export function sessionPrompt(onSubmit, onDismiss, onSettled) {
  const dialog = element('dialog', {id:'session-prompt',role:'alertdialog','aria-modal':'true','aria-labelledby':'session-prompt-title','aria-describedby':'session-prompt-message'});
  const form = element('form', {className:'session-prompt-form',method:'post'});
  // [S G34／B15；E-29] 初始焦點讀出提示與說明；Tab 才進入操作，不只先讀按鈕。
  const title = element('h2', {id:'session-prompt-title',tabindex:'-1','aria-describedby':'session-prompt-message session-prompt-owner'});
  const message = element('p', {id:'session-prompt-message'});
  const owner = element('p', {id:'session-prompt-owner',className:'helper-text'});
  const field = element('div', {className:'form-field'});
  const label = element('label', {for:'session-current-password'}, '目前密碼');
  const password = element('input', {id:'session-current-password',name:'sessionPassword',type:'password',autocomplete:'current-password','aria-describedby':'session-prompt-error'});
  const status = element('p', {id:'session-prompt-status',role:'status','aria-live':'polite',className:'form-status'});
  // [S G34／L34；E-29] 錯誤與處理進度分開，錯誤可獨立播報及由密碼欄關聯取得。
  const errorMessage = element('p', {id:'session-prompt-error',tabindex:'-1',role:'alert','aria-live':'assertive','aria-atomic':'true',className:'form-status'});
  const actions = element('div', {className:'session-prompt-actions'});
  const submit = element('button', {type:'submit',className:'button button-dark'});
  const cancel = element('button', {type:'button',className:'button button-outline'}, '暫不處理');
  field.append(label,password);actions.append(submit,cancel);form.append(title,message,owner,field,status,errorMessage,actions);dialog.append(form);
  document.body.append(dialog);
  let trigger, currentMode, busy = false;
  const clear = () => { password.value=''; };
  dialog.addEventListener('close', () => { clear();onDismiss();if(trigger?.isConnected)trigger.focus(); });
  cancel.addEventListener('click', () => dialog.close());
  dialog.addEventListener('keydown', event => {
    if(event.key!=='Tab')return;
    const controls=[...form.querySelectorAll('input,button')].filter(node=>!node.disabled&&!node.closest('[hidden]'));
    if(document.activeElement===title||document.activeElement===errorMessage){event.preventDefault();(event.shiftKey?controls.at(-1):controls[0])?.focus();return;}
    const target=event.shiftKey&&document.activeElement===controls[0]?controls.at(-1):!event.shiftKey&&document.activeElement===controls.at(-1)?controls[0]:null;
    if(target){event.preventDefault();target.focus();}
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();if(busy)return;
    busy=true;submit.disabled=true;errorMessage.textContent='';password.removeAttribute('aria-invalid');status.textContent='處理中…';
    try { await onSubmit(password.value); }
    catch(error){status.textContent='';password.setAttribute('aria-invalid','true');errorMessage.textContent=error.message;errorMessage.focus();}
    finally{clear();busy=false;submit.disabled=false;onSettled();}
  });
  return {
    get open(){return dialog.open;},
    show(mode, identity, previewControl=false) {
      if(busy)return;
      const needsPassword=mode==='reauth'||mode==='expired';
      const wasPasswordVisible=!field.hidden,modeChanged=currentMode!==mode;
      title.textContent=mode==='changed'?'登入身分已變更':mode==='expired'?'登入已到期':needsPassword?'請重新驗證登入':'登入即將到期';
      message.textContent=mode==='changed'?'本頁未儲存內容仍保留。請複製需要的內容，再重新開啟本頁；不會將舊表單自動套用到其他帳號。':
        previewControl?'請先結束唯讀模擬，再重新驗證原管理員身分。':needsPassword?'請輸入原帳號的密碼重新登入。本頁未儲存內容會保留，登入後請再按一次儲存。':'閒置登入將在兩分鐘內到期。按「繼續保持登入」可延長閒置時間；本頁未儲存內容不會被清除。';
      owner.textContent=identity?.identifier?'登入帳號：'+identity.identifier:'';
      field.hidden=!needsPassword||previewControl;password.required=!field.hidden;
      submit.textContent=mode==='changed'?'我已保留內容，重新開啟':previewControl?'結束唯讀模擬':needsPassword?'重新登入':'繼續保持登入';
      if(modeChanged){if(wasPasswordVisible!==!field.hidden)clear();status.textContent='';errorMessage.textContent='';password.removeAttribute('aria-invalid');currentMode=mode;}
      if(!dialog.open){trigger=document.activeElement;dialog.showModal();title.focus();}
      else if(modeChanged)title.focus();
    },
    close(text=''){if(dialog.open)dialog.close();status.textContent=text;},
  };
}
