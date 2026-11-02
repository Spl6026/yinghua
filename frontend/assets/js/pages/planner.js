import { attractionPicker } from '../components/attraction-picker.js';
import { itinerariesApi } from '../api/itineraries-api.js';
import { TripEditorState } from '../core/trip-editor-state.js';
import { tripStopEditor } from '../components/trip-stop-editor.js';
import { readMember, resolvePublicAttraction, clearMemberIntent } from '../core/member-actions.js';
import { handleSessionExpiry } from '../core/session.js';
import { element, status, formErrors, onPageReturn, toast, unsavedChanges } from '../core/ui.js';
import { confirmAction } from '../components/dialog.js';

// 完整的行程編輯器：伺服器版本與未保存草稿分開，不把 localStorage 當正式資料。
export async function initializePlanner() {
  const state = new TripEditorState(itinerariesApi, readMember),
    form = document.querySelector('#trip-form'),
    list = document.querySelector('#itinerary-list'),
    message = document.querySelector('#trip-status');
  const title = document.querySelector('#trip-title'),
    date = document.querySelector('#trip-date'),
    selector = document.querySelector('#trip-select');
  const save = document.querySelector('#save-trip'),
    reload = document.querySelector('#reload-trip'),
    create = document.querySelector('#new-trip'),
    remove = document.querySelector('#delete-trip');
  const previous = document.querySelector('#trip-list-previous'),
    next = document.querySelector('#trip-list-next'),
    apply = document.querySelector('#use-route'),
    add = document.querySelector('#add-stop');
  const routeMessage = document.querySelector('#route-preview-status');
  let working = false,
    suggestion = null,
    suggestionRequest = 0,
    pendingPlace = null;
  const locked = () => working || state.busy || state.loading;
  const picker = attractionPicker(document.querySelector('#trip-picker-host'), {
    isSelected: id => !!state.draft?.items.some(item => item.attractionId === id),
    isDisabled: () => locked() || state.stale || state.uncertain,
    onChoose: place =>
      act(async () => {
        if (!state.draft) await state.create();
        state.add(place);
      }, '地點已加入草稿；編排完成後請儲存行程。'),
  });
  function dirtyHint() {
    const node = document.querySelector('#trip-dirty'),
      text = !state.loaded
        ? '正在載入行程…'
        : state.dirty
          ? '有尚未儲存的修改。'
          : state.trip
            ? '目前內容已保存。'
            : '請先新增或開啟行程。';
    if (node.textContent !== text) node.textContent = text;
  }
  function controls() {
    const disabled = locked(),
      editable = !disabled && !!state.draft;
    title.disabled = date.disabled = !editable;
    selector.disabled = disabled;
    create.disabled = reload.disabled = disabled;
    remove.disabled = disabled || !state.trip;
    save.disabled = !editable || state.stale || state.uncertain;
    previous.disabled = disabled || state.page <= 1;
    next.disabled = disabled || state.page * 20 >= state.total;
    apply.disabled = disabled || !suggestion || state.stale || state.uncertain;
    add.disabled = disabled || state.stale || state.uncertain;
    document
      .querySelector('#confirm-place')
      ?.toggleAttribute('disabled', !editable || state.stale || state.uncertain || !pendingPlace);
    for (const input of list.querySelectorAll('input,textarea')) input.disabled = !editable;
    list.querySelectorAll('article').forEach((node, index) => {
      const buttons = node.querySelectorAll('button');
      buttons.forEach(button => {
        button.disabled = !editable;
      });
      if (buttons[0]) buttons[0].disabled = !editable || index === 0;
      if (buttons[1]) buttons[1].disabled = !editable || index === state.draft.items.length - 1;
    });
    form.setAttribute('aria-busy', String(disabled));
    dirtyHint();
  }
  function summaries() {
    selector.replaceChildren(
      element(
        'option',
        { value: '' },
        !state.loaded ? '正在載入行程…' : state.total ? '選擇要開啟的行程' : '尚無行程，請先新增'
      )
    );
    const choices = [...state.summaries];
    if (state.trip && !choices.some(trip => trip.id === state.trip.id)) choices.unshift(state.trip);
    for (const trip of choices)
      selector.append(
        element(
          'option',
          { value: trip.id },
          `${trip.title || '未命名行程'}${trip.isCurrent ? '（目前）' : ''}${trip.tripDate ? '｜' + trip.tripDate : ''}`
        )
      );
    selector.value = state.trip?.id || '';
    document.querySelector('.trip-list-pager').hidden = state.total <= 20;
    document.querySelector('#trip-list-page').textContent = `第 ${state.page} 頁，共 ${state.total} 份`;
  }
  function renderStops(focusIndex) {
    const items = state.draft?.items || [];
    list.replaceChildren(
      ...items.map((item, index) =>
        tripStopEditor(item, index, items.length, {
          disabled: locked(),
          onMove: (i, delta) => {
            if (!locked() && state.move(i, delta)) {
              renderStops(i + delta);
              status(message, '站點順序已調整，請儲存行程。');
            }
          },
          onRemove: i => {
            if (!locked() && state.remove(i)) {
              renderStops(Math.min(i, state.draft.items.length - 1));
              status(message, '站點已從草稿移除，請儲存行程。');
            }
          },
          onEdit: (field, value) => {
            if (locked()) return;
            item[field] = value;
            state.dirty = true;
            dirtyHint();
          },
        })
      )
    );
    document.querySelector('#itinerary-empty').hidden = !state.loaded || items.length > 0;
    controls();
    if (focusIndex !== undefined) (list.children[focusIndex]?.querySelector('input') || list).focus();
  }
  function render() {
    title.value = state.draft?.title || '';
    date.value = state.draft?.tripDate || '';
    document.querySelector('#current-trip-name').textContent = !state.loaded
      ? '載入行程中…'
      : state.trip
        ? state.trip.title || '未命名行程'
        : '先建立旅行計畫';
    document.querySelector('#planner-user-name').textContent = state.member?.displayName || '正在確認帳號…';
    const account = document.querySelector('.planner-user .text-link');
    account.hidden = !state.member;
    account.href = '/change-password.html';
    account.textContent = '更改密碼 ↗';
    summaries();
    renderStops();
  }
  function fail(error, { focusResult = true } = {}) {
    render();
    if (error.status === 401) {
      status(message, '登入已到期，草稿仍保留；重新驗證後請再操作。', true);
      handleSessionExpiry('member');
      return;
    }
    status(
      message,
      (error.saved ? '資料已保存，但清單讀取尚未完成；請重新載入確認。 ' : '') + error.message,
      true
    );
    if (focusResult && !formErrors(form, error)) message.focus();
  }
  async function act(task, text, { focusResult = true } = {}) {
    if (locked()) return;
    working = true;
    formErrors(form, { fieldErrors: {} });
    status(message, '處理中…');
    try {
      const promise = task();
      controls();
      await promise;
      render();
      status(message, text);
      if (focusResult) message.focus();
    } catch (error) {
      fail(error, { focusResult });
    } finally {
      working = false;
      controls();
    }
  }
  async function discard(trigger, text = '尚未儲存的修改將遺失，確定繼續？') {
    return !state.dirty || (await confirmAction('尚未儲存的行程', text, trigger));
  }
  title.addEventListener('input', () => {
    if (state.draft) {
      state.draft.title = title.value;
      state.dirty = true;
      dirtyHint();
    }
  });
  date.addEventListener('input', () => {
    if (state.draft) {
      state.draft.tripDate = date.value;
      state.dirty = true;
      dirtyHint();
    }
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    void act(() => state.save(), '行程已儲存至帳號。');
  });
  create.addEventListener('click', async () => {
    if (locked() || !(await discard(create))) return;
    await act(() => state.create(), '已建立新行程，可以開始編排。');
  });
  selector.addEventListener('change', async () => {
    const id = selector.value;
    if (!id || id === state.trip?.id) return;
    if (locked() || !(await discard(selector))) {
      summaries();
      return;
    }
    await act(() => state.open(id), '已開啟並選定目前行程。');
  });
  reload.addEventListener('click', async () => {
    if (locked() || !(await discard(reload))) return;
    await act(() => state.load(), '已重新載入帳號中的行程。');
  });
  remove.addEventListener('click', async () => {
    if (locked() || !state.trip) return;
    if (
      !(await confirmAction(
        '刪除行程',
        `確定刪除「${state.trip.title || '未命名行程'}」？${state.dirty ? '未儲存的修改也會捨棄。' : ''}`,
        remove
      ))
    )
      return;
    await act(() => state.removeTrip(), '行程已刪除。');
  });
  previous.addEventListener('click', () => {
    if (state.page > 1) void act(() => state.list(state.page - 1), '已載入上一頁行程清單。');
  });
  next.addEventListener('click', () => {
    if (state.page * 20 < state.total) void act(() => state.list(state.page + 1), '已載入下一頁行程清單。');
  });
  add.addEventListener('click', () => {
    if (!locked()) void picker.open(add);
  });
  apply.addEventListener('click', async () => {
    if (locked() || !suggestion) return;
    if (
      !(await confirmAction(
        '套用推薦行程',
        state.trip
          ? `將「${suggestion.title}」套用到目前行程，取代現有名稱與站點${state.dirty ? '及未儲存修改' : ''}；旅行日期保留。`
          : `建立一份新行程，並加入「${suggestion.title}」的可用站點。`,
        apply
      ))
    )
      return;
    await act(async () => {
      if (!state.trip) await state.create();
      await state.apply(suggestion.id);
      clearMemberIntent('route');
      document.querySelector('#cancel-route').hidden = true;
    }, '推薦路線已套用並保存。');
  });
  async function routePreview() {
    const request = ++suggestionRequest,
      id = new URLSearchParams(location.search).get('route');
    suggestion = null;
    controls();
    try {
      const result = id ? await itinerariesApi.route(id) : await itinerariesApi.routes();
      if (request !== suggestionRequest) return;
      suggestion = id ? result.route : result.items[0] || null;
      document.querySelector('#suggestion-title').textContent = suggestion?.title || '目前沒有推薦路線';
      document.querySelector('#suggestion-summary').textContent = suggestion
        ? [suggestion.summary, ...suggestion.stops.map(stop => stop.name)].filter(Boolean).join(' → ')
        : '可到推薦行程列表查看最新資訊。';
      document.querySelector('#cancel-route').hidden = !id;
      status(routeMessage, suggestion ? '套用會替換名稱與站點，日期保留；確認後才寫入帳號。' : '');
    } catch (error) {
      status(routeMessage, error.message, true);
    } finally {
      controls();
    }
  }
  document.querySelector('#cancel-route').addEventListener('click', () => {
    clearMemberIntent('route');
    void routePreview();
  });
  async function placeIntent() {
    const id = new URLSearchParams(location.search).get('place');
    if (!id) return;
    const section = element('section', {
        id: 'trip-place-intent',
        className: 'member-intent',
        'aria-label': '待確認行程地點',
      }),
      text = element('p', { role: 'status' }, '讀取待加入地點中…');
    section.append(text);
    list.before(section);
    const cancel = element('button', { type: 'button', className: 'button button-outline' }, '取消加入');
    cancel.addEventListener('click', () => {
      clearMemberIntent('place');
      pendingPlace = null;
      section.remove();
    });
    section.append(cancel);
    try {
      pendingPlace = await resolvePublicAttraction(id);
      if (!pendingPlace) {
        status(text, '此地點目前無法加入，請重新選擇。', true);
        return;
      }
      status(text, `選定或新增行程後，將「${pendingPlace.name}」加入並儲存。`);
      const button = element(
        'button',
        { type: 'button', className: 'button button-dark', id: 'confirm-place' },
        '加入並儲存至此行程'
      );
      button.addEventListener('click', () => {
        if (locked() || !state.draft || !pendingPlace) return;
        void act(async () => {
          state.add(pendingPlace);
          await state.save();
          clearMemberIntent('place');
          pendingPlace = null;
          section.remove();
        }, '地點與行程修改已保存。');
      });
      section.append(button);
      controls();
    } catch (error) {
      status(text, error.message, true);
    }
  }
  unsavedChanges(() => state.dirty);
  const resume = async () => {
    if (locked()) return;
    try {
      const member = await readMember();
      if (!member) {
        handleSessionExpiry('member');
        return;
      }
      if (state.member && member.id !== state.member.id) {
        state.reset();
        render();
        status(message, '登入身分已變更，原私人草稿已清除；請重新載入。', true);
        return;
      }
      if (state.dirty) {
        status(message, '草稿保留；其他分頁可能已更新，儲存時會檢查版本。');
        return;
      }
      await act(() => state.load(), '已重新確認帳號中的行程。', { focusResult: false });
    } catch (error) {
      fail(error, { focusResult: false });
    }
  };
  onPageReturn(() => void resume());
  render();
  await act(() => state.load(), '已載入你的行程。', { focusResult: false });
  await routePreview();
  await placeIntent();
}

// 初始化途中出錯（例如模組載入後 API 異常）時給使用者看得到的提示，而不是留下沒有反應的頁面。
initializePlanner().catch(error => {
  toast(error?.message || '行程頁載入失敗，請重新整理頁面。');
});
