// [U-09／E-20] 人工輸入與 Google 候選共用七天模型；不解析語系文字。
export const openingWeek = [1, 2, 3, 4, 5, 6, 0];
export const openingDayNames = ['週日', '週一', '週二', '週三', '週四', '週五', '週六'];
export function emptyOpeningHours(note = '') {
  return {
    timezone: 'Asia/Taipei',
    note,
    days: Array.from({ length: 7 }, (_, day) => ({ day, status: 'unknown', periods: [] })),
  };
}
export function openingDayText(day) {
  if (day.status === 'closed') return '公休';
  if (day.status === 'all_day') return '全天開放';
  if (day.status !== 'periods') return '依店家公告';
  return day.periods
    .map(period => `${period.opens}–${period.nextDay ? '次日 ' : ''}${period.closes}`)
    .join('、');
}
export function openingHoursLines(value, legacy = '') {
  if (!value) return [legacy || '依店家公告'];
  return [
    ...openingWeek.map(
      number => `${openingDayNames[number]}：${openingDayText(value.days.find(day => day.day === number))}`
    ),
    ...(value.note ? ['備註：' + value.note] : []),
  ];
}
