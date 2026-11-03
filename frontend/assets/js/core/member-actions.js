import { authApi } from '../api/auth-api.js';
import { memberResourcesApi } from '../api/member-resources-api.js';
import { element, onPageReturn, status, toast, unsavedChanges } from './ui.js';
import { MemberResourceState } from './member-resource-state.js';
import { recoverSession } from './session-expiry.js';
import { PendingWrites } from './pending-writes.js';
import { publicApi } from '../api/public-api.js';
import { FAVORITE_CHANGED, emitPageEvent } from './page-events.js';

// 只處理受管景點的 UUID；原型陣列的索引不能當成正式資料的識別碼。
const isAttractionId = id => /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(id || '');
export const readMember = async () => {
  try {
    return (await authApi().me()).member;
  } catch (error) {
    if (error.status === 401) return null;
    throw error;
  }
};
const state = new MemberResourceState(memberResourcesApi, readMember);
let bound = false,
  pendingGeneration = 0;
const favoriteWrites = new PendingWrites();
let navigationGeneration = 0;
async function navigateAfterFavorites(target) {
  const request = ++navigationGeneration;
  if (favoriteWrites.active) {
    toast('正在保存收藏，完成後前往頁面…');
    const confirmed = await favoriteWrites.confirmed();
    if (request !== navigationGeneration) return;
    if (!confirmed) {
      toast('無法確認收藏已保存，請留在本頁重新確認收藏狀態後再前往。');
      return;
    }
  }
  location.assign(target);
}
const favoriteButtons = () => [...document.querySelectorAll('[data-favorite]')];
const knownIds = () =>
  favoriteButtons()
    .map(button => button.dataset.favorite)
    .filter(isAttractionId);

function paint() {
  for (const button of favoriteButtons()) {
    const id = button.dataset.favorite;
    if (!isAttractionId(id)) {
      button.disabled = true;
      continue;
    }
    button.dataset.favoriteLabel ||= button.getAttribute('aria-label') || '收藏此景點';
    const known = state.statuses.has(id);
    const saved = state.statuses.get(id) === true;
    button.disabled = state.busy.has(id);
    button.classList.toggle('active', known && saved);
    if (known) button.setAttribute('aria-pressed', String(saved));
    else button.removeAttribute('aria-pressed');
    button.setAttribute('aria-label', known ? button.dataset.favoriteLabel : '重新讀取收藏狀態');
    button.textContent = button.classList.contains('favorite-button')
      ? known
        ? saved
          ? '♥'
          : '♡'
        : '↻'
      : known
        ? saved
          ? '已收藏 ♥'
          : '收藏此景點 ♡'
        : '重新讀取收藏狀態';
    button.removeAttribute('aria-busy');
  }
}

function loginForMemberAction(kind, id) {
  if (recoverSession('member')) return;
  const target = new URL(location.href);
  if (kind !== 'favorite') {
    target.pathname = '/planner.html';
    target.search = '';
  }
  target.searchParams.set(kind === 'favorite' ? 'pendingFavorite' : 'place', id);
  location.assign('/login.html?returnTo=' + encodeURIComponent(target.pathname + target.search));
}

// 登入後帶回的待辦（planner.html?place=<景點 id>）才需要查公開景點資料；一般卡片狀態走本人資源的批次 API。
export async function resolvePublicAttraction(id) {
  if (!isAttractionId(id)) return null;
  for (let page = 1; ; page++) {
    const data = await publicApi.attractions({ page, pageSize: 100 });
    const item = data.items.find(item => item.id === id);
    if (item) return item;
    if (page * 100 >= data.total) return null;
  }
}

export function clearMemberIntent(name) {
  const url = new URL(location.href);
  url.searchParams.delete(name);
  history.replaceState(null, '', url.pathname + url.search + url.hash);
}

async function showFavoriteIntent() {
  const id = new URLSearchParams(location.search).get('pendingFavorite');
  if (!id || document.querySelector('#member-favorite-intent')) return;
  const request = ++pendingGeneration;
  const section = element('section', {
    id: 'member-favorite-intent',
    className: 'member-intent section-pad',
    'aria-label': '待確認收藏',
  });
  const message = element('p', { role: 'status', tabindex: '-1' }, '讀取待收藏地點中…');
  const cancel = element('button', { type: 'button', className: 'button button-outline' }, '取消');
  cancel.addEventListener('click', () => {
    ++pendingGeneration;
    clearMemberIntent('pendingFavorite');
    section.remove();
  });
  section.append(message, cancel);
  document.querySelector('main')?.prepend(section);
  try {
    const item = await resolvePublicAttraction(id);
    if (request !== pendingGeneration || !section.isConnected) return;
    if (!item) {
      status(message, '原先選擇的地點目前無法收藏。', true);
      return;
    }
    status(message, `是否將「${item.name}」加入收藏？`);
    const confirm = element(
      'button',
      {
        type: 'button',
        className: 'button button-dark',
        'data-favorite': item.id,
        'aria-label': '收藏' + item.name,
      },
      '收藏此景點 ♡'
    );
    section.append(confirm);
    await refreshFavorites();
  } catch (error) {
    status(message, error.message, true);
    const retry = element('button', { type: 'button', className: 'button button-outline' }, '重新讀取');
    retry.addEventListener('click', () => {
      section.remove();
      void showFavoriteIntent();
    });
    section.append(retry);
  }
}

async function refreshFavorites() {
  const buttons = favoriteButtons();
  buttons.forEach(button => {
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
  });
  try {
    if (await state.refresh(knownIds())) paint();
  } catch (error) {
    paint();
    toast(error.message);
  }
}

async function toggleFavorite(button) {
  const id = button.dataset.favorite;
  if (!isAttractionId(id)) return;
  if (state.member === null) {
    loginForMemberAction('favorite', id);
    return;
  }
  if (!state.statuses.has(id) || state.member === undefined) {
    await refreshFavorites();
    toast('收藏狀態已重新讀取，請確認後再操作。');
    return;
  }
  if (new URLSearchParams(location.search).get('pendingFavorite') === id && state.statuses.get(id)) {
    clearMemberIntent('pendingFavorite');
    document.querySelector('#member-favorite-intent')?.remove();
    toast('此地點已在收藏中。');
    return;
  }
  button.disabled = true;
  return favoriteWrites.run(async () => {
    try {
      const result = await state.favorite(id);
      if (result.login) loginForMemberAction('favorite', id);
      else if (result.changed) {
        toast(result.saved ? '已加入收藏。' : '已取消收藏。');
        if (new URLSearchParams(location.search).get('pendingFavorite') === id) {
          clearMemberIntent('pendingFavorite');
          document.querySelector('#member-favorite-intent')?.remove();
        }
        emitPageEvent(FAVORITE_CHANGED, { attractionId: id });
      }
      return result.changed === true;
    } catch (error) {
      if (error.status === 401) loginForMemberAction('favorite', id);
      else toast(error.message);
      return false;
    } finally {
      paint();
    }
  });
}

async function addStop(button) {
  const id = button.dataset.addStop;
  if (!isAttractionId(id)) return;
  // 到 planner 確認選定的行程後才保存；未登入者由共用登入流程安全返回。
  await navigateAfterFavorites('/planner.html?place=' + encodeURIComponent(id));
}

export async function mountMemberActions() {
  if (!bound) {
    bound = true;
    document.addEventListener('click', event => {
      const button = event.target.closest('[data-favorite]');
      if (button && !button.disabled) {
        event.preventDefault();
        void toggleFavorite(button);
      }
      const stop = event.target.closest('[data-add-stop]');
      if (stop && !stop.disabled) {
        event.preventDefault();
        void addStop(stop);
      }
      const link = event.target.closest('a[href]');
      if (
        link &&
        favoriteWrites.active &&
        !event.defaultPrevented &&
        !event.ctrlKey &&
        !event.metaKey &&
        !event.shiftKey &&
        !event.altKey &&
        !event.button &&
        (!link.target || link.target === '_self') &&
        !link.hasAttribute('download')
      ) {
        const target = new URL(link.href, location.href);
        if (
          target.origin === location.origin &&
          (target.pathname !== location.pathname || target.search !== location.search)
        ) {
          event.preventDefault();
          void navigateAfterFavorites(target.href);
        }
      }
    });
    unsavedChanges(() => favoriteWrites.active);
    const resume = () => {
      ++state.generation;
      state.member = undefined;
      state.statuses.clear();
      paint();
      void refreshFavorites();
    };
    onPageReturn(resume);
  }
  await refreshFavorites();
  await showFavoriteIntent();
}
