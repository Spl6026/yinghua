import { element } from '../core/ui.js';
import { confirmAction } from './dialog.js';

// [U-10／E-21] 本次確認只屬表單命令；已保存內容的比較仍由後端政策執行。
export function googlePlaceLink(form, container, onDetach = () => {}) {
  const place = form.elements.googlePlaceId;
  const snapshot = () =>
    JSON.stringify(
      ['name', 'address', 'latitude', 'longitude'].map(key => {
        const value = form.elements[key].value.trim();
        return ['latitude', 'longitude'].includes(key) ? (value === '' ? null : Number(value)) : value;
      })
    );
  let baseline = snapshot(),
    confirmed = false,
    disabled = true;
  const message = element('span', {
    className: 'google-place-link-message',
    role: 'status',
    'aria-live': 'polite',
  });
  const identifier = element('code', {});
  const details = element('details', {});
  const summary = element('summary', { className: 'google-place-link-summary' });
  summary.append(message, element('span', { className: 'google-place-link-toggle' }, '關聯詳細資訊'));
  const actions = element('div', { className: 'google-place-link-details' });
  const reconfirm = element(
    'button',
    { type: 'button', className: 'button button-outline', 'data-write': 'true' },
    '重新確認此地點'
  );
  const detach = element(
    'button',
    { type: 'button', className: 'button button-outline', 'data-write': 'true' },
    '解除 Google 關聯'
  );
  actions.append(identifier, detach);
  details.append(summary, actions);
  container.replaceChildren(details, reconfirm);
  const stale = () => Boolean(place.value) && snapshot() !== baseline;
  function render() {
    const linked = Boolean(place.value);
    // [U-10] 人工建立不顯示關聯區；技術識別碼及解除操作按需展開。
    container.hidden = !linked;
    if (!linked) details.open = false;
    identifier.textContent = linked ? 'Place ID：' + place.value : '';
    message.textContent = !linked
      ? ''
      : stale()
        ? '地點資料已修改，請重新確認，或在詳細資訊解除關聯。'
        : confirmed
          ? '本次已確認 Google 地點，儲存後生效。'
          : '已關聯 Google 地點';
    message.dataset.error = String(stale());
    reconfirm.hidden = !stale();
    detach.hidden = !linked;
    reconfirm.disabled = disabled;
    detach.disabled = disabled;
  }
  function changed() {
    render();
    container.dispatchEvent(new Event('input', { bubbles: true }));
  }
  for (const key of ['name', 'address', 'latitude', 'longitude']) {
    const update = () => {
      if (stale()) confirmed = false;
      render();
    };
    form.elements[key].addEventListener('input', update);
    form.elements[key].addEventListener('change', update);
  }
  reconfirm.addEventListener('click', async () => {
    if (disabled || !stale()) return;
    const pending = snapshot(),
      linkedId = place.value;
    if (
      !(await confirmAction(
        '重新確認 Google 地點',
        `請確認目前「${form.elements.name.value}」的名稱、地址與座標仍屬於此 Place ID。這是人工確認，不是 Google 驗證結果。`,
        reconfirm
      ))
    )
      return;
    if (disabled || pending !== snapshot() || linkedId !== place.value) return;
    baseline = pending;
    confirmed = true;
    changed();
    container.focus();
  });
  detach.addEventListener('click', () => {
    if (disabled) return;
    place.value = '';
    baseline = snapshot();
    confirmed = false;
    onDetach();
    changed();
    form.elements.name.focus();
  });
  render();
  return {
    load: () => {
      baseline = snapshot();
      confirmed = false;
      render();
    },
    acceptCandidate: () => {
      baseline = snapshot();
      confirmed = Boolean(place.value);
      render();
    },
    setDisabled: value => {
      disabled = value;
      render();
    },
    canSave: () => !stale(),
    confirmed: () => Boolean(place.value) && confirmed && !stale(),
  };
}
