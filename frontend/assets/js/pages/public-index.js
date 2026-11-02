// [S G34／B15；E-29] 分類瀏覽與名稱尋找是兩種入口；只搜尋同一公開 HTML 的標題，不取私人或草稿資料。
export const normalizedIndexQuery = value => value.normalize('NFKC').trim().toLocaleLowerCase('zh-TW');
if(typeof document!=='undefined') {
  const form=document.querySelector('#public-index-search'),input=document.querySelector('#public-index-query');
  const rows=[...document.querySelectorAll('[data-index-item]')],message=document.querySelector('#public-index-status');
  function filter() {
    const query=normalizedIndexQuery(input.value);let count=0;
    for(const row of rows){row.hidden=!normalizedIndexQuery(row.textContent).includes(query);if(!row.hidden)count++;}
    for(const group of document.querySelectorAll('.public-index-group'))group.hidden=Boolean(query)&&![...group.querySelectorAll('[data-index-item]')].some(row=>!row.hidden);
    message.textContent=query?`找到 ${count} 項內容。`:`共 ${count} 項內容，可依分類瀏覽或按名稱尋找。`;
    const url=new URL(location.href);if(query)url.searchParams.set('q',input.value.trim());else url.searchParams.delete('q');
    history.replaceState(null,'',url.pathname+url.search);
  }
  form.addEventListener('submit',event=>{event.preventDefault();filter();});
  form.addEventListener('reset',event=>{event.preventDefault();input.value='';filter();input.focus();});
  for(const link of document.querySelectorAll('a[href^="#index-"]'))link.addEventListener('click',()=>{input.value='';filter();});
  input.value=new URLSearchParams(location.search).get('q')||'';filter();
}
