import { itinerariesApi } from '../api/itineraries-api.js';
import { tripCard } from '../components/trip-card.js';
import { readMember, resolvePublicAttraction, clearMemberIntent } from '../core/member-actions.js';
import { handleSessionExpiry } from '../core/session.js';
import { tripEditUrl } from '../core/planner-links.js';
import { status, onPageReturn, consumeFlash, toast } from '../core/ui.js';

const pageSize = 20;

// 行程總覽：列出本人所有行程，點進去看內容，或新增一份後直接進入編輯。
// 從景點「加入行程」或推薦行程「套用」帶來的待辦，會在這裡先選擇要加到哪一份行程。
export async function initializeTripOverview({ intent }) {
  const section = document.querySelector('#trip-overview'),
    list = document.querySelector('#trip-overview-list'),
    empty = document.querySelector('#trip-overview-empty'),
    message = document.querySelector('#trip-overview-status'),
    owner = document.querySelector('#trip-overview-owner'),
    create = document.querySelector('#overview-new-trip');
  const pager = document.querySelector('#trip-overview-pager'),
    previous = document.querySelector('#trip-overview-previous'),
    next = document.querySelector('#trip-overview-next'),
    pageLabel = document.querySelector('#trip-overview-page');
  section.hidden = false;
  let page = 1,
    total = 0,
    busy = false,
    request = 0,
    memberId = null;
  const pending = { ...intent };

  function controls() {
    create.disabled = busy;
    previous.disabled = busy || page <= 1;
    next.disabled = busy || page * pageSize >= total;
    section.setAttribute('aria-busy', String(busy));
  }
  function expired(error) {
    if (error.status !== 401) return false;
    handleSessionExpiry('member');
    return true;
  }
  async function load(target = page) {
    const current = ++request;
    busy = true;
    controls();
    try {
      const member = await readMember();
      if (!member) {
        handleSessionExpiry('member');
        return;
      }
      // 換了登入身分時，先清掉前一人的行程，避免短暫顯示別人的資料。
      if (memberId && member.id !== memberId) list.replaceChildren();
      memberId = member.id;
      const data = await itinerariesApi.list(target);
      if (current !== request) return;
      page = data.page;
      total = data.total;
      list.replaceChildren(...data.items.map(trip => tripCard(trip, pending)));
      empty.hidden = total > 0;
      pager.hidden = total <= pageSize;
      pageLabel.textContent = `第 ${page} 頁，共 ${total} 份`;
      owner.textContent = `${member.displayName || '會員'}・共 ${total} 份行程`;
      status(message, total ? '' : '按「新增行程」開始安排第一份行程。');
    } catch (error) {
      if (current === request && !expired(error)) status(message, error.message, true);
    } finally {
      if (current === request) {
        busy = false;
        controls();
      }
    }
  }

  // 待加入的地點或待套用的推薦行程：說明下一步，並讓「新增行程」直接帶著待辦進入編輯。
  async function showIntent() {
    const box = document.querySelector('#trip-overview-intent'),
      text = document.querySelector('#trip-overview-intent-text');
    if (!pending.place && !pending.route) return;
    box.hidden = false;
    create.textContent = pending.place ? '＋ 新增行程並加入' : '＋ 新增行程並套用';
    document.querySelector('#trip-overview-intent-cancel').addEventListener('click', () => {
      clearMemberIntent('place');
      clearMemberIntent('route');
      pending.place = pending.route = '';
      box.hidden = true;
      create.textContent = '＋ 新增行程';
      void load();
    });
    status(text, '讀取待辦內容中…');
    try {
      if (pending.place) {
        const place = await resolvePublicAttraction(pending.place);
        status(
          text,
          place
            ? `要把「${place.name}」加入哪一份行程？選擇後會進入編輯畫面確認。`
            : '這個地點目前無法加入，請取消後重新選擇。',
          !place
        );
      } else {
        const { route } = await itinerariesApi.route(pending.route);
        status(
          text,
          `要把推薦行程「${route.title}」套用到哪一份行程？套用會取代該行程的名稱與地點，旅行日期保留。`
        );
      }
    } catch (error) {
      status(text, error.message, true);
    }
  }

  create.addEventListener('click', async () => {
    if (busy) return;
    busy = true;
    controls();
    status(message, '建立行程中…');
    try {
      const { itinerary } = await itinerariesApi.create({ id: crypto.randomUUID() });
      location.assign(tripEditUrl(itinerary.id, pending));
    } catch (error) {
      if (!expired(error)) status(message, error.message, true);
      busy = false;
      controls();
    }
  });
  previous.addEventListener('click', () => {
    if (!busy && page > 1) void load(page - 1);
  });
  next.addEventListener('click', () => {
    if (!busy && page * pageSize < total) void load(page + 1);
  });
  onPageReturn(() => {
    if (!busy) void load();
  });
  controls();
  // 例如從行程內容刪除後回到總覽，顯示上一頁留下的結果。
  const notice = consumeFlash();
  if (notice) toast(notice);
  await Promise.all([load(1), showIntent()]);
}
