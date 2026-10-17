// [S：功能規格 E20／E-24] 試填只改基本資料；不複製 id、slug、圖片、發布或 Google 關聯。
export function applyAttractionSample(form, item, openingEditor) {
  for (const name of ['name','category','district','typeLabel','recommendationLabel','summary','description',
    'address','phone','transport','websiteUrl','mapUrl','latitude','longitude','hours']) {
    form.elements[name].value = String(item[name] ?? '');
  }
  form.elements.googlePlaceId.value = '';
  openingEditor.load(item.openingHours, item.hours);
}
