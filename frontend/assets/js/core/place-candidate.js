// [U-10／E-21] 候選套用只有一條路徑；不保留上個地點的行政區，不猜測缺值。
export function applyPlaceCandidate(form, item, openingEditor) {
  for (const name of ['name','address','latitude','longitude','hours','websiteUrl','mapUrl','googlePlaceId']) form.elements[name].value = String(item[name] ?? '');
  form.elements.district.value = ['北區','東區','香山區'].includes(item.district) ? item.district : '';
  if (form.elements.phone) form.elements.phone.value = ''; // 新地點尚無已核實電話，不沿用上一筆。
  openingEditor.load(item.openingHours, item.hours);
  const notes = [];
  // [U-24] 匯入時逐欄補空白；已有人工內容不覆寫，沒有 Google 簡介不填值。
  const introduction = typeof item.editorialSummary === 'string' ? item.editorialSummary : '';
  if (introduction.trim()) {
    for (const name of ['summary', 'description']) {
      const field = form.elements[name];
      if (field && !field.value.trim()) {
        // 不截斷 Google 原文；超出既有欄位容量時留空，避免帶入無法儲存的值。
        if (field.maxLength > 0 && introduction.length > field.maxLength) {
          notes.push(`${name === 'summary' ? '列表摘要' : '景點介紹'}未帶入：Google 簡介超過欄位長度，請自行撰寫。`);
        } else {
          field.value = introduction;
        }
      }
    }
  }
  if (!form.elements.district.value) notes.push('無法確認新竹市行政區，暫留空白，可稍後補填。');
  if (form.elements.websiteUrl.value.startsWith('http:')) notes.push('官網使用 HTTP，已保留原網址。');
  return notes;
}
