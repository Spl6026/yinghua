import { seoSettingsApi } from '../api/seo-settings-api.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { element, status, formErrors, unsavedChanges } from '../core/ui.js';
import { confirmAction } from '../components/dialog.js';
import { seoField, seoPanel, robotsChoices } from '../components/seo-fields.js';
import { resolveSeo } from '../core/seo-resolver.js';

const defaultFields = [
  ['siteName', '網站名稱', 160],
  ['description', '全站預設摘要', 500, 'textarea'],
  ['titleSuffix', 'SEO 標題後綴', 160],
  ['robots', '全站預設索引政策', 40],
  ['keywords', '全站預設關鍵字（逗號分隔）', 1000],
  ['canonicalOrigin', '正式網站網址', 255, 'url'],
  ['ogImageUrl', '全站預設分享圖網址（建議 1200 × 630）', 2000, 'url'],
  ['twitterCard', 'X／Twitter 分享卡片', 40],
  ['twitterSite', 'X／Twitter 網站帳號（選填）', 40],
  ['googleVerification', 'Google Search Console 驗證碼', 255],
  ['bingVerification', 'Bing Webmaster 驗證碼', 255],
];
const overrideFields = [
  ['title', 'SEO 標題（建議約 30 中文字）', 160],
  ['description', '搜尋摘要（建議 70–100 中文字）', 500, 'textarea'],
  ['keywords', '頁面關鍵字', 1000],
  ['ogImageUrl', '分享圖 HTTPS 網址', 2000, 'url'],
  ['robots', '搜尋索引設定', 40],
  ['canonicalPath', '標準網址路徑（Canonical）', 180],
];

export async function initializeSeoSettings(identity) {
  const form = document.querySelector('#seo-settings-form'),
    message = document.querySelector('#seo-status'),
    reload = document.querySelector('#reload-seo');
  let data = null,
    busy = false,
    dirty = false,
    generation = 0;
  let defaultInputs = {},
    rows = [],
    duplicateNote;
  const writable = () => hasPermission(identity, 'settings', 'edit') && !identity.preview?.active;
  unsavedChanges(() => dirty);
  function controls() {
    reload.disabled = busy;
    form.setAttribute('aria-busy', String(busy));
    form.querySelectorAll('input,select,textarea,button').forEach(node => {
      node.disabled =
        busy ||
        !data ||
        (!node.hasAttribute('data-seo-read') && !writable()) ||
        node.dataset.seoProtected === 'true';
    });
  }
  function targetKey(target) {
    return target.type + ':' + (target.path || target.id);
  }
  function draft() {
    return {
      defaults: Object.fromEntries(
        Object.entries(defaultInputs).map(([key, input]) => [key, input.value.trim()])
      ),
      overrides: rows.map(row => ({
        target: row.target,
        ...Object.fromEntries(
          Object.entries(row.inputs).map(([key, input]) => [
            key,
            input.dataset.seoProtected === 'true' ? null : input.value.trim() || null,
          ])
        ),
        ...(row.clearKeywords.checked ? { keywords: '' } : {}),
      })),
    };
  }
  function previews() {
    const seo = draft();
    const seenTitle = new Set(),
      seenDescription = new Set();
    let duplicate = false;
    for (const row of rows) {
      try {
        const meta = resolveSeo({
          path: row.previewPath,
          title: row.page.title || '',
          description: row.page.description || '',
          item: row.target.type === 'page' ? undefined : row.page,
          seo,
          catalog: data.pages,
          origin: location.origin,
        });
        row.preview.textContent = `標題：${meta.title}\n來源：${meta.source.title}\n摘要：${meta.description}\n來源：${meta.source.description}\n索引：${meta.robots}\nCanonical：${meta.canonical}`;
        if (!row.page.protected && !row.page.template) {
          duplicate = duplicate || seenTitle.has(meta.title) || seenDescription.has(meta.description);
          seenTitle.add(meta.title);
          seenDescription.add(meta.description);
        }
      } catch {
        row.preview.textContent = '請先填入有效的正式網站網址，再查看預覽。';
      }
    }
    if (duplicateNote)
      duplicateNote.textContent = duplicate
        ? '部分頁面的標題或摘要相同，請展開檢查是否符合各頁內容；此提示不會阻擋儲存。'
        : '';
  }
  function addOverride(container, target, existing = {}, label = '', page = {}) {
    const key = targetKey(target);
    if (rows.some(row => targetKey(row.target) === key)) return;
    const index = rows.length;
    const detail = element('details', { className: 'seo-page-config' });
    const summary = element('summary', {}, label || target.path || target.id);
    detail.append(summary);
    const grid = element('div', { className: 'admin-form-grid seo-page-fields' });
    detail.append(grid);
    const inputs = {};
    for (const [field, title, max, type] of overrideFields) {
      // [U-24] 保護頁為固定靜態 shell，發布不重新套用覆寫；全部欄位鎖定，避免預覽顯示實際不會生效的標題。
      const locked =
        Boolean(page.protected) ||
        (field === 'canonicalPath' && (target.type !== 'page' || target.path.includes('*')));
      const fallback =
        field === 'title'
          ? page.seoTitle || page.title
          : field === 'description'
            ? page.seoDescription || page.description
            : field === 'canonicalPath'
              ? target.path
              : '';
      const value = page.protected ? (field === 'robots' ? 'noindex,follow' : '') : (existing[field] ?? '');
      const control = seoField(title, `overrides.${index}.${field}`, value, {
        type,
        max,
        disabled: locked,
        choices: field === 'robots' ? [['', '繼承設定'], ...robotsChoices] : undefined,
        placeholder: fallback || '留空繼承',
        hint: page.protected
          ? field === 'title'
            ? '登入、會員、搜尋等保護頁使用固定標題與摘要且不索引，不提供頁面覆寫。'
            : ''
          : field === 'title'
            ? '留空時沿用內容或全站預設；建議字數僅供參考。'
            : '',
      });
      inputs[field] = control.input;
      grid.append(control.wrapper);
    }
    const clearKeywords = element('input', {
      type: 'checkbox',
      id: `seo-clear-keywords-${index}`,
      name: `clear-keywords-${index}`,
    });
    clearKeywords.checked = !page.protected && existing.keywords === '';
    clearKeywords.disabled = Boolean(page.protected);
    if (page.protected) clearKeywords.dataset.seoProtected = 'true';
    const clearLabel = element('label', { for: clearKeywords.id }, '清空此頁關鍵字（不繼承）');
    const clearWrap = element('div', { className: 'admin-field full' });
    clearWrap.append(clearKeywords, clearLabel);
    grid.append(clearWrap);
    const note = element(
      'p',
      { className: 'helper-text' },
      target.type === 'page'
        ? '逐欄留空可回到全站或模板預設。'
        : '單筆覆寫優先於內容編輯頁的 SEO；清除後才會沿用內容值。'
    );
    grid.append(note);
    const remove = element('button', { type: 'button', className: 'button button-outline' }, '清除本頁覆寫');
    remove.addEventListener('click', () => {
      for (const input of Object.values(inputs)) if (!input.disabled) input.value = '';
      clearKeywords.checked = false;
      dirty = true;
      previews();
      status(message, '已清除此頁的編輯值，儲存後生效。');
    });
    grid.append(remove);
    const preview = element('pre', { className: 'seo-preview-box', 'aria-label': '有效 SEO 預覽' });
    grid.append(preview);
    const prefix = { attraction: 'places', film: 'films', news: 'news', offer: 'offers' }[target.type];
    const previewPath =
      target.type === 'page' ? target.path.replace('*', 'example') : `/${prefix}/${page.slug}.html`;
    rows.push({ target, inputs, clearKeywords, detail, summary, preview, previewPath, page });
    container.append(detail);
  }
  function render() {
    form.replaceChildren();
    rows = [];
    defaultInputs = {};
    const basic = seoPanel('基本網站資訊', '共用於全站 SEO'),
      social = seoPanel('搜尋引擎與社群分享設定', '儲存後套用到網站各頁');
    for (const [key, label, max, type] of defaultFields) {
      const choices =
        key === 'robots'
          ? robotsChoices
          : key === 'twitterCard'
            ? [
                ['summary_large_image', '大型圖片卡片'],
                ['summary', '摘要卡片'],
              ]
            : undefined;
      const hint =
        key === 'canonicalOrigin'
          ? '以 https:// 開頭的網站首頁網址，不含頁面路徑；網站上線前請填寫。'
          : key === 'googleVerification' || key === 'bingVerification'
            ? '只填驗證碼本身，不要貼上整段 HTML、API 金鑰或帳號密碼。'
            : key === 'description'
              ? '每頁摘要以內容與覆寫區分；此欄為共用 fallback。'
              : '';
      const field = seoField(label, 'defaults.' + key, data.defaults[key], { type, max, choices, hint });
      defaultInputs[key] = field.input;
      if (['keywords', 'canonicalOrigin', 'ogImageUrl'].includes(key)) field.wrapper.className += ' full';
      (['siteName', 'description'].includes(key) ? basic : social).grid.append(field.wrapper);
    }
    basic.panel.hidden = Boolean(document.querySelector('#general-settings-form'));
    form.append(basic.panel, social.panel);
    const catalog = seoPanel('各頁 SEO 覆寫', '展開頁面設定；空欄位繼承，保護頁不可改為允許索引。');
    const list = element('div', { className: 'seo-pages-list' });
    catalog.panel.append(list);
    duplicateNote = element('p', { className: 'helper-text', role: 'status' });
    catalog.panel.append(duplicateNote);
    for (const page of data.pages) {
      const target = { type: 'page', path: page.path };
      addOverride(
        list,
        target,
        data.overrides.find(item => targetKey(item.target) === targetKey(target)),
        page.label + ' ' + page.path,
        page
      );
    }
    for (const item of data.overrides.filter(item => item.target.type !== 'page')) {
      const target = data.targets.find(
        target => target.type === item.target.type && target.id === item.target.id
      );
      addOverride(list, item.target, item, target?.title || item.target.id, target);
    }
    const search = seoField('搜尋單筆內容', 'seo-target-query', '', { max: 80 });
    search.input.setAttribute('data-seo-read', '');
    const searchButton = element(
      'button',
      { type: 'button', className: 'button button-outline', 'data-seo-read': '' },
      '搜尋內容'
    );
    const select = element('select', {
      id: 'seo-content-choice',
      name: 'content-choice',
      autocomplete: 'off',
      'aria-label': '選擇單筆內容',
    });
    function choices(targets) {
      select.replaceChildren(element('option', { value: '' }, '選擇景點、電影、消息或優惠'));
      for (const target of targets)
        select.append(
          element(
            'option',
            { value: target.type + ':' + target.id },
            target.title + (target.published ? '' : '（未公開）')
          )
        );
    }
    choices(data.targets);
    searchButton.addEventListener('click', async () => {
      if (busy) return;
      busy = true;
      controls();
      try {
        const result = await seoSettingsApi.read(search.input.value.trim());
        data.targets = result.targets;
        choices(data.targets);
        status(
          message,
          result.targetsTruncated ? '結果較多，請縮小關鍵字。' : '已更新內容選項，未改動編輯值。'
        );
      } catch (error) {
        status(message, error.message, true);
        handleAdminError(error, message);
      } finally {
        busy = false;
        controls();
      }
    });
    const add = element('button', { type: 'button', className: 'button button-outline' }, '加入單筆內容覆寫');
    add.addEventListener('click', () => {
      const target = data.targets.find(target => target.type + ':' + target.id === select.value);
      if (!target) return;
      addOverride(list, { type: target.type, id: target.id }, {}, target.title, target);
      const row = rows.find(row => targetKey(row.target) === select.value);
      if (row) {
        row.detail.open = true;
        row.summary.focus();
      }
      dirty = true;
      previews();
      controls();
    });
    catalog.grid.append(search.wrapper, searchButton, select, add);
    form.append(catalog.panel);
    const notice = element(
      'p',
      { className: 'helper-text' },
      '一般網站資訊與政策於上方分開儲存；兩區共用網站名稱與摘要。修改一區後，編輯另一區前請重新讀取，避免覆蓋他人變更。正式網址與外部平台驗證仍於上線確認。'
    );
    const actions = element('div', { className: 'admin-form-actions' });
    actions.append(element('button', { type: 'submit', className: 'button button-dark' }, '儲存網站設定 ↗'));
    form.append(notice, actions);
    previews();
    controls();
  }
  async function load() {
    if (busy) return;
    const request = ++generation;
    busy = true;
    controls();
    status(message, '讀取 SEO 設定中…');
    try {
      const result = await seoSettingsApi.read();
      if (request !== generation) return;
      data = result;
      dirty = false;
      render();
      status(
        message,
        `設定版本 ${data.version}。` +
          (data.publicationReady ? '網站標籤已同步。' : '設定已保存，網站更新待重試。')
      );
    } catch (error) {
      data = null;
      status(message, error.message, true);
      handleAdminError(error, message);
    } finally {
      busy = false;
      controls();
    }
  }
  // [U-24] 一般設定儲存後版本前進；SEO 區無未存修改時自動重讀，有修改則提示重新讀取以免覆蓋共用欄位。
  document.addEventListener('settings-section-saved', event => {
    if (event.detail?.section === 'seo') return;
    if (!dirty && !busy) void load();
    else if (dirty)
      status(message, '一般設定已更新；請重新讀取 SEO 設定後再儲存，以免覆蓋共用的網站名稱與摘要。', true);
  });
  reload.addEventListener('click', async event => {
    if (
      !busy &&
      (!dirty || (await confirmAction('重新讀取 SEO 設定', '這會放棄尚未儲存的修改。', event.currentTarget)))
    )
      await load();
  });
  form.addEventListener('input', event => {
    if (!writable() || event.target?.hasAttribute('data-seo-read')) return;
    dirty = true;
    previews();
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy || !data || !writable()) return;
    busy = true;
    controls();
    formErrors(form, { fieldErrors: {} });
    status(message, '儲存並更新網站標籤中…');
    const defaults = Object.fromEntries(
      Object.entries(defaultInputs).map(([key, input]) => [key, input.value.trim()])
    );
    const activeRows = rows.map(row => ({
      row,
      fields: Object.fromEntries(
        Object.entries(row.inputs).map(([key, input]) => [
          key,
          input.dataset.seoProtected === 'true' ? null : input.value.trim() || null,
        ])
      ),
    }));
    const selected = activeRows.filter(
      ({ row, fields }) => row.clearKeywords.checked || Object.values(fields).some(value => value !== null)
    );
    // DOM row 位置與提交清單位置不同，回應欄位錯誤映射回原欄位。
    const overrides = selected.map(({ row, fields }) => ({
      target: row.target,
      ...fields,
      keywords: row.clearKeywords.checked ? '' : fields.keywords,
    }));
    try {
      const result = await seoSettingsApi.save({ version: data.version, defaults, overrides });
      data = { ...data, ...result, pages: data.pages, targets: data.targets };
      dirty = false;
      render();
      status(
        message,
        result.publicationReady ? '已儲存，網站標籤已更新。' : '已儲存，網站更新待重試；請稍後重新讀取狀態。'
      );
      message.focus();
      document.dispatchEvent(new CustomEvent('settings-section-saved', { detail: { section: 'seo' } }));
    } catch (error) {
      const fields = {};
      for (const [name, value] of Object.entries(error.fieldErrors || {})) {
        const match = name.match(/^overrides\.(\d+)\.(.+)$/);
        fields[
          match && selected[Number(match[1])]
            ? `overrides.${rows.indexOf(selected[Number(match[1])].row)}.${match[2]}`
            : name
        ] = value;
      }
      for (const name of Object.keys(fields)) {
        const input = [...form.querySelectorAll('input,select,textarea')].find(node => node.name === name);
        const detail = input?.closest('details');
        if (detail) detail.open = true;
      }
      // [E-28] 請求已結束，先恢復控制項，再將焦點交給第一個錯誤欄位。
      busy = false;
      controls();
      status(message, error.message, true);
      handleAdminError(error, message);
      const focused = formErrors(form, { fieldErrors: fields });
      if (!focused) message.focus();
    } finally {
      busy = false;
      controls();
    }
  });
  await load();
}
