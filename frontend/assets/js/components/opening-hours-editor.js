import { element } from '../core/ui.js';
import { emptyOpeningHours, openingWeek, openingDayNames } from '../core/opening-hours.js';

// [U-09／E-20] 單一編輯器處理手動建檔、讀回及 Google 候選；最後規則由 API 驗證。
export function openingHoursEditor(container) {
  let disabled = false;
  const rows = new Map();
  container.replaceChildren();
  const noteLabel = element('label', { for: 'opening-hours-note' }, '營業備註');
  const note = element('textarea', {
    id: 'opening-hours-note',
    name: 'openingHoursNote',
    maxlength: 500,
    rows: 2,
    autocomplete: 'off',
    'aria-describedby': 'opening-hours-help',
  });
  const changed = () => container.dispatchEvent(new Event('input', { bubbles: true }));

  function refresh(row) {
    row.state.disabled = disabled;
    row.add.disabled = disabled || row.state.value !== 'periods' || row.periods.length >= 4;
    row.slots.hidden = row.state.value !== 'periods';
    for (const period of row.periods) {
      for (const field of [period.opens, period.closes, period.nextDay])
        field.disabled = disabled || row.state.value !== 'periods';
      period.remove.disabled = disabled || row.state.value !== 'periods';
    }
    row.add.hidden = row.state.value !== 'periods';
  }
  function addPeriod(row, value = { opens: '09:00', closes: '17:00', nextDay: false }) {
    if (row.periods.length >= 4) return;
    const index = ++row.sequence;
    const prefix = 'opening-' + row.day + '-' + index;
    const group = element('div', { className: 'opening-period' });
    const opens = element('input', {
      type: 'time',
      name: prefix + '-opens',
      id: prefix + '-opens',
      step: 60,
      required: true,
      autocomplete: 'off',
    });
    const closes = element('input', {
      type: 'time',
      name: prefix + '-closes',
      id: prefix + '-closes',
      step: 60,
      required: true,
      autocomplete: 'off',
    });
    const nextDay = element('input', { type: 'checkbox', name: prefix + '-nextDay' });
    opens.value = value.opens;
    closes.value = value.closes;
    nextDay.checked = value.nextDay;
    const startLabel = element('label', { for: opens.id }, '開始');
    const endLabel = element('label', { for: closes.id }, '結束');
    const nextLabel = element('label', { className: 'opening-next-day' });
    nextLabel.append(nextDay, document.createTextNode('次日結束'));
    const remove = element(
      'button',
      {
        type: 'button',
        className: 'button button-outline',
        'data-write': 'true',
        'aria-label': openingDayNames[row.day] + '移除此營業時段',
      },
      '移除時段'
    );
    const period = { group, opens, closes, nextDay, remove };
    remove.addEventListener('click', () => {
      if (disabled) return;
      row.periods = row.periods.filter(item => item !== period);
      group.remove();
      refresh(row);
      row.add.focus();
      changed();
    });
    group.append(startLabel, opens, endLabel, closes, nextLabel, remove);
    row.slots.append(group);
    row.periods.push(period);
    refresh(row);
    return opens;
  }
  for (const day of openingWeek) {
    const group = element('fieldset', { className: 'opening-day' });
    group.append(element('legend', {}, openingDayNames[day]));
    const state = element('select', {
      id: 'opening-day-' + day,
      name: 'openingDay' + day,
      'aria-label': openingDayNames[day] + '營業狀態',
    });
    for (const [value, text] of [
      ['unknown', '依店家公告'],
      ['closed', '公休'],
      ['all_day', '全天開放'],
      ['periods', '自訂時段'],
    ])
      state.append(element('option', { value }, text));
    const slots = element('div', { className: 'opening-periods' });
    const add = element(
      'button',
      {
        type: 'button',
        className: 'button button-outline',
        'data-write': 'true',
        'aria-label': openingDayNames[day] + '新增營業時段',
      },
      '新增時段'
    );
    const row = { day, state, slots, add, periods: [], sequence: 0 };
    rows.set(day, row);
    state.addEventListener('change', () => {
      if (state.value === 'periods' && row.periods.length === 0) addPeriod(row);
      refresh(row);
      changed();
    });
    add.addEventListener('click', () => {
      if (!disabled) {
        addPeriod(row)?.focus();
        changed();
      }
    });
    group.append(state, slots, add);
    container.append(group);
  }
  container.append(noteLabel, note);
  function load(value, legacy = '') {
    const model = value || emptyOpeningHours(legacy);
    note.value = model.note;
    for (const [day, row] of rows) {
      const entry = model.days.find(item => item.day === day);
      row.state.value = entry?.status || 'unknown';
      row.periods = [];
      row.slots.replaceChildren();
      for (const period of entry?.periods || []) addPeriod(row, period);
      refresh(row);
    }
    note.disabled = disabled;
  }
  load(null);
  return {
    load,
    read: () => ({
      timezone: 'Asia/Taipei',
      note: note.value,
      days: [...rows]
        .sort(([a], [b]) => a - b)
        .map(([day, row]) => ({
          day,
          status: row.state.value,
          periods:
            row.state.value === 'periods'
              ? row.periods.map(period => ({
                  opens: period.opens.value,
                  closes: period.closes.value,
                  nextDay: period.nextDay.checked,
                }))
              : [],
        })),
    }),
    setDisabled: value => {
      disabled = value;
      for (const row of rows.values()) refresh(row);
      note.disabled = value;
    },
  };
}
