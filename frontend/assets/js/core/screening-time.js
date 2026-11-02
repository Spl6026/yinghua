// [U-16／E-25] 表單日期時間屬台北；不依操作電腦時區猜測日期。
export function screeningStart(date, time, index = 0) {
  if (!date && !time) return null;
  const fail = message => {
    throw Object.assign(new Error(message), { fieldErrors: { [`screenings.${index}.startsAt`]: message } });
  };
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(time))
    fail('請填寫完整放映日期與開始時間。');
  const stamp = `${date}T${time}:00+08:00`,
    parsed = new Date(stamp);
  if (!Number.isFinite(parsed.getTime())) fail('請填寫有效放映日期與時間。');
  const local = new Date(parsed.getTime() + 8 * 3600000).toISOString().slice(0, 16);
  if (local !== `${date}T${time}` || Number(date.slice(0, 4)) < 1000) fail('請填寫有效放映日期與時間。');
  return stamp;
}

export function screeningFields(stamp) {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Taipei',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
    })
      .formatToParts(new Date(stamp))
      .map(p => [p.type, p.value])
  );
  return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}` };
}
