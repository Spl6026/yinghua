import {element,status} from '../core/ui.js';
import {escapeHtml} from '../core/content-view.js';

// [S G10/G22；E-31] 原生工具列只協助編輯；伺服器Sanitizer仍是唯一安全邊界。
export function initializeContentBodyEditor(form,service,module,available,setBusy,changed) {
  const input=form.elements.body;if(!input)return;
  const toolbar=document.querySelector('#body-toolbar'),preview=document.querySelector('#body-preview');
  const message=document.querySelector('#content-media-status');
  function insert(open,close=''){
    if(!available())return;
    const selected=input.value.slice(input.selectionStart,input.selectionEnd);
    input.setRangeText(open+selected+close,input.selectionStart,input.selectionEnd,'end');input.focus();changed();render();
  }
  for(const [label,open,close]of [['段落','<p>','</p>'],['標題','<h2>','</h2>'],['粗體','<strong>','</strong>'],['斜體','<em>','</em>'],['項目清單','<ul><li>','</li></ul>'],['編號清單','<ol><li>','</li></ol>']]){
    const button=element('button',{type:'button',className:'button button-outline','data-write':''},label);button.addEventListener('click',()=>insert(open,close));toolbar.append(button);
  }
  const link=element('button',{type:'button',className:'button button-outline','data-write':''},'插入連結');
  link.addEventListener('click',()=>insert('<a href="https://">','</a>'));toolbar.append(link);
  const tags=new Set(['P','BR','H2','H3','H4','UL','OL','LI','STRONG','EM','B','I','BLOCKQUOTE','A','IMG']);
  function render(){
    const parsed=new DOMParser().parseFromString(input.value,'text/html');
    function copy(node){
      if(node.nodeType===Node.TEXT_NODE)return document.createTextNode(node.textContent);
      if(node.nodeType!==Node.ELEMENT_NODE||['SCRIPT','STYLE','IFRAME','OBJECT','SVG'].includes(node.tagName))return document.createDocumentFragment();
      const result=tags.has(node.tagName)?document.createElement(node.tagName.toLowerCase()):document.createDocumentFragment();
      if(result instanceof HTMLElement){
        for(const attr of node.tagName==='A'?['href']:node.tagName==='IMG'?['src']:[]){
          const value=node.getAttribute(attr)||'';
          try{const url=new URL(value,location.origin);if(['http:','https:'].includes(url.protocol))result.setAttribute(attr,url.href);}catch{}
        }
        if(node.tagName==='IMG')result.setAttribute('alt',node.getAttribute('alt')||'');
      }
      for(const child of node.childNodes)result.append(copy(child));return result;
    }
    preview.replaceChildren(...Array.from(parsed.body.childNodes,copy));
  }
  input.addEventListener('input',render);
  document.querySelector('#insert-body-image').addEventListener('click',async()=>{
    if(!available())return;
    const file=document.querySelector('#body-image'),alt=document.querySelector('#body-image-alt');
    if(!file.files[0]||!alt.value.trim()){status(message,'請選擇正文圖片並填寫替代文字。',true);alt.focus();return;}
    const body=new FormData();body.set('image',file.files[0]);body.set('alt',alt.value);body.set('source','');body.set('license','');body.set('module',module);body.set('action',document.body.dataset.action);
    setBusy(true);
    try{
      const {media}=await service.upload(body);const snippet=`<img src="${escapeHtml(media.url)}" alt="${escapeHtml(alt.value)}">`;
      input.setRangeText(snippet,input.selectionStart,input.selectionEnd,'end');file.value='';alt.value='';changed();render();status(message,'正文圖片已插入；儲存內容後才會公開。');
    }catch(error){status(message,error.message,true);message.focus();}finally{setBusy(false);}
  });
  return {render};
}
