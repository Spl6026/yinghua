import { recommendedRoutesApi as service } from '../api/recommended-routes-api.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { element, status, formErrors, submitForm, flash } from '../core/ui.js';
import { confirmAction } from '../components/dialog.js';
import { adminFormErrors, confirmDiscard, createFormState, redirectBackLinks } from '../core/admin-form.js';

// 陣列順序就是站點順序；排序按鈕同時支援滑鼠與鍵盤，不需要拖曳。
export async function initializeRouteForm(identity, page) {
  const form = document.querySelector('#route-form'),
    message = document.querySelector('#route-form-status');
  const selected = document.querySelector('#route-stops'),
    choices = document.querySelector('#route-candidates'),
    query = document.querySelector('#route-place-query');
  const previous = document.querySelector('#route-place-previous'),
    next = document.querySelector('#route-place-next');
  const search = document.querySelector('#route-place-search'),
    reload = document.querySelector('#route-reload');
  const creating = page === 'route-add';
  let id = creating ? null : new URLSearchParams(location.search).get('id');
  let version = 0,
    stops = [],
    candidatePage = 1,
    hasNext = false,
    candidateBusy = false,
    candidateRequest = 0;
  const state = createFormState({
    writable: () => !identity.preview?.active && hasPermission(identity, 'routes', id ? 'edit' : 'create'),
    loaded: creating,
    render: () => controls(),
  });
  const { available } = state;
  const cancel = document.querySelector('#route-cancel');
  redirectBackLinks(identity, 'routes', cancel, '/admin/route-add.html');
  function controls() {
    for (const node of form.querySelectorAll('input,select,textarea,button')) node.disabled = !available();
    previous.disabled = !available() || candidateBusy || candidatePage <= 1;
    next.disabled = !available() || candidateBusy || !hasNext;
    search.disabled = !available() || candidateBusy;
    reload.disabled = state.busy;
    for (const button of choices.querySelectorAll('button'))
      button.disabled = !available() || stops.some(stop => stop.attractionId === button.dataset.id);
    selected.querySelectorAll('[data-move]').forEach(button => {
      const index = Number(button.dataset.index);
      button.disabled =
        !available() || (button.dataset.move === 'up' ? index === 0 : index === stops.length - 1);
    });
  }
  function renderStops(focusIndex) {
    selected.replaceChildren();
    stops.forEach((stop, index) => {
      const row = element('li', { className: 'route-stop' }),
        heading = element(
          'h2',
          {},
          `${index + 1}. ${stop.name}${stop.available === false ? '（不可用，請移除或更換）' : ''}`
        );
      row.append(heading);
      const fields = element('div', { className: 'admin-form-grid' });
      for (const [name, label, type] of [
        ['suggestedTime', '建議時間（選填）', 'time'],
        ['note', '備註（選填）', 'text'],
      ]) {
        const field = element('div', { className: 'admin-field' }),
          key = `stop-${index}-${name}`,
          input = element('input', {
            id: key,
            name: key,
            type,
            value: stop[name] || '',
            autocomplete: 'off',
          });
        if (name === 'note') input.maxLength = 2000;
        input.addEventListener('input', () => {
          stop[name] = input.value;
          state.markDirty({ refresh: false });
        });
        field.append(element('label', { for: key }, label), input);
        fields.append(field);
      }
      row.append(fields);
      const actions = element('div', { className: 'route-stop-actions' });
      for (const [move, label, delta] of [
        ['up', '上移', -1],
        ['down', '下移', 1],
        ['remove', '移除', 0],
      ]) {
        const button = element(
          'button',
          { type: 'button', className: 'button button-outline', 'aria-label': `${label} ${stop.name}` },
          label
        );
        if (delta) {
          button.dataset.move = move;
          button.dataset.index = String(index);
        }
        button.addEventListener('click', () => {
          if (!available()) return;
          if (delta) {
            const other = index + delta;
            if (other < 0 || other >= stops.length) return;
            [stops[index], stops[other]] = [stops[other], stops[index]];
          } else stops.splice(index, 1);
          state.markDirty({ refresh: false });
          renderStops(Math.max(0, index + delta));
          status(message, `${stop.name}已${label}。`);
        });
        actions.append(button);
      }
      row.append(actions);
      selected.append(row);
    });
    if (!stops.length)
      selected.append(element('li', { className: 'route-stops-empty' }, '尚未加入景點，路線至少需要一站。'));
    controls();
    if (focusIndex !== undefined) (selected.children[focusIndex]?.querySelector('input') || selected).focus();
  }
  async function candidates() {
    const request = ++candidateRequest;
    candidateBusy = true;
    controls();
    try {
      const result = await service.candidates(query.value, candidatePage);
      if (request !== candidateRequest) return;
      hasNext = result.hasNext;
      choices.replaceChildren();
      for (const place of result.items) {
        const button = element(
          'button',
          { type: 'button', className: 'button button-outline' },
          '＋ ' + place.name
        );
        button.dataset.id = place.id;
        button.addEventListener('click', () => {
          if (!available() || stops.some(stop => stop.attractionId === place.id)) return;
          stops.push({
            attractionId: place.id,
            name: place.name,
            suggestedTime: '',
            note: '',
            available: true,
          });
          state.markDirty({ refresh: false });
          renderStops();
          status(message, `已加入 ${place.name}，共 ${stops.length} 站。`);
        });
        choices.append(button);
      }
      if (!result.items.length) choices.append(element('p', {}, '沒有可加入的已發布景點。'));
      document.querySelector('#route-place-page').textContent = `景點第 ${candidatePage} 頁`;
    } catch (error) {
      if (request === candidateRequest) handleAdminError(error, message);
    } finally {
      if (request === candidateRequest) {
        candidateBusy = false;
        controls();
      }
    }
  }
  async function load() {
    await state.load(
      async () => {
        if (!id) throw new Error('找不到要編輯的推薦行程，請返回清單重新開啟。');
        return service.get(id);
      },
      {
        apply: ({ record }) => {
          for (const name of ['title', 'theme', 'summary', 'durationMinutes', 'sortOrder', 'status'])
            form.elements.namedItem(name).value = record[name] ?? '';
          version = record.version;
          stops = record.stops.map(stop => ({ ...stop, suggestedTime: stop.suggestedTime || '' }));
          formErrors(form, { fieldErrors: {} });
          renderStops();
          status(message, '已載入推薦行程。');
        },
        onError: error => handleAdminError(error, message),
      }
    );
  }
  form.addEventListener('input', () => state.markDirty({ refresh: false }));
  form.addEventListener('change', () => state.markDirty({ refresh: false }));
  cancel.addEventListener('click', async event => {
    if (!state.dirty) return;
    event.preventDefault();
    if (await confirmAction('離開編輯', '尚未儲存的修改將遺失，確定離開？', cancel)) {
      state.reset();
      location.assign(cancel.href);
    }
  });
  reload.addEventListener('click', async () => {
    if (!(await confirmDiscard(state.dirty, reload, '重新載入推薦行程'))) return;
    if (creating && !id) {
      form.reset();
      stops = [];
      state.reset();
      candidatePage = 1;
      formErrors(form, { fieldErrors: {} });
      renderStops();
    } else await load();
    await candidates();
  });
  search.addEventListener('click', () => {
    candidatePage = 1;
    void candidates();
  });
  query.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
      event.preventDefault();
      candidatePage = 1;
      void candidates();
    }
  });
  previous.addEventListener('click', () => {
    if (!candidateBusy && candidatePage > 1) {
      candidatePage--;
      void candidates();
    }
  });
  next.addEventListener('click', () => {
    if (!candidateBusy && hasNext) {
      candidatePage++;
      void candidates();
    }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!available()) return;
    await state.run(() =>
      submitForm(
        form,
        async () => {
          const value = name => form.elements.namedItem(name).value;
          const payload = {
            title: value('title'),
            theme: value('theme'),
            summary: value('summary'),
            durationMinutes: value('durationMinutes') === '' ? null : Number(value('durationMinutes')),
            sortOrder: Number(value('sortOrder')),
            status: value('status'),
            stops: stops.map(stop => ({
              attractionId: stop.attractionId,
              suggestedTime: stop.suggestedTime || '',
              note: stop.note || '',
            })),
            ...(id ? { version } : {}),
          };
          const result = await service.save(id, payload);
          id = result.record.id;
          version = result.record.version;
          state.complete();
          status(message, result.publication.message, result.publication.status !== 'succeeded');
          if (result.publication.status === 'succeeded' && hasPermission(identity, 'routes', 'view')) {
            flash('推薦行程已儲存，公開頁面已同步。');
            location.assign('/admin/routes.html');
          }
        },
        { statusNode: message, canSubmit: () => available(), onError: adminFormErrors(message) }
      )
    );
  });
  renderStops();
  if (!creating) await load();
  await candidates();
}
