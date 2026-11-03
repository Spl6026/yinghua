// 網站上所有日期時間都以台北時間呈現，不依訪客或管理者電腦的時區。
// Node 的發布流程與瀏覽器共用這個模組，產生的文字必須兩邊一致。
// Intl.DateTimeFormat 建立成本不低，同一組設定只建立一次重複使用。

const formatters = new Map();

/** 取得以台北時區設定好的 Intl.DateTimeFormat，相同的語系與選項共用同一個實例。 */
export function taipeiFormatter(locale, options = {}) {
  const key = locale + JSON.stringify(options);
  if (!formatters.has(key))
    formatters.set(key, new Intl.DateTimeFormat(locale, { timeZone: 'Asia/Taipei', ...options }));
  return formatters.get(key);
}

/**
 * 拆成年月日（與時分秒）的兩位數字字串，例如 { year: '2026', month: '11', day: '02', hour: '15', ... }。
 * 只要日期時傳 { time: false }。
 */
export function taipeiParts(value, { time = true } = {}) {
  const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
  if (time)
    Object.assign(options, { hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' });
  return Object.fromEntries(
    taipeiFormatter('en-CA', options)
      .formatToParts(new Date(value))
      .map(part => [part.type, part.value])
  );
}

/** 原型的英文月份、星期縮寫（大寫），例如 { month: 'short' } → 'NOV'。 */
export function taipeiEnglish(value, options) {
  return taipeiFormatter('en-US', options).format(new Date(value)).toUpperCase();
}

/** YYYY-MM-DD HH:mm:ss，後台列表與紀錄使用。 */
export function formatTaipeiDateTime(value) {
  const p = taipeiParts(value);
  return `${p.year}-${p.month}-${p.day} ${p.hour}:${p.minute}:${p.second}`;
}

/** 今天的台北日期 YYYY-MM-DD。 */
export function taipeiToday() {
  const p = taipeiParts(Date.now(), { time: false });
  return `${p.year}-${p.month}-${p.day}`;
}

/** 伺服器時間 → datetime-local 欄位的值（台北時間，含秒）。 */
export function toTaipeiInput(value) {
  if (!value) return '';
  const p = taipeiParts(value);
  return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}:${p.second}`;
}

/** datetime-local 欄位的值（視為台北時間）→ 帶 +08:00 的 ISO 字串；瀏覽器可能省略秒數。 */
export function fromTaipeiInput(value) {
  return value ? (value.length === 16 ? value + ':00' : value.slice(0, 19)) + '+08:00' : '';
}
