import { element } from '../core/ui.js';

export const robotsChoices = [
  ['index,follow', '允許索引'],
  ['noindex,follow', '不索引、可追蹤連結'],
  ['noindex,nofollow', '不索引、不追蹤'],
];

// 所有值都以 DOM property 寫入，避免把網站名稱或驗證 token 當成 HTML 解析。
export function seoField(
  label,
  name,
  value,
  { type = 'text', choices, max = 255, hint = '', placeholder = '', disabled = false } = {}
) {
  const wrapper = element('div', { className: 'admin-field' + (type === 'textarea' ? ' full' : '') });
  const id = 'seo-' + name.replaceAll('.', '-');
  const input = element(choices ? 'select' : type === 'textarea' ? 'textarea' : 'input', {
    id,
    name,
    'data-field-name': name,
  });
  input.setAttribute('autocomplete', 'off');
  if (type === 'url' || /Verification|twitterSite|canonicalPath/.test(name))
    input.setAttribute('spellcheck', 'false');
  if (choices) for (const [key, text] of choices) input.append(element('option', { value: key }, text));
  else if (type === 'textarea') {
    input.rows = 3;
    input.maxLength = max;
  } else {
    input.type = type;
    input.maxLength = max;
  }
  input.value = value ?? '';
  input.placeholder = placeholder;
  input.disabled = disabled;
  if (disabled) input.dataset.seoProtected = 'true';
  wrapper.append(element('label', { for: id }, label), input);
  if (hint) {
    const note = element('p', { className: 'helper-text', id: id + '-hint' }, hint);
    wrapper.append(note);
    input.setAttribute('aria-describedby', note.id);
  }
  return { wrapper, input };
}

export function seoPanel(title, description) {
  const panel = element('section', { className: 'admin-panel' });
  const heading = element('div', { className: 'admin-panel-heading' });
  heading.append(element('h2', {}, title), element('span', {}, description));
  const grid = element('div', { className: 'admin-form-grid' });
  panel.append(heading, grid);
  return { panel, grid };
}
