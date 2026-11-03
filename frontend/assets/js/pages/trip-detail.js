import { itinerariesApi } from '../api/itineraries-api.js';
import { tripStopView } from '../components/trip-stop-view.js';
import { confirmAction } from '../components/dialog.js';
import { readMember } from '../core/member-actions.js';
import { handleSessionExpiry } from '../core/session.js';
import { tripDateLabel, tripEditUrl, tripOverviewUrl, tripTitle } from '../core/planner-links.js';
import { status, onPageReturn, flash } from '../core/ui.js';

// 行程內容：唯讀列出這份行程的地點、時間與備註；要修改時按「編輯行程」進入編輯畫面。
export async function initializeTripDetail({ tripId }) {
  const section = document.querySelector('#trip-detail'),
    heading = document.querySelector('#trip-detail-title'),
    meta = document.querySelector('#trip-detail-meta'),
    stops = document.querySelector('#trip-detail-stops'),
    empty = document.querySelector('#trip-detail-empty'),
    message = document.querySelector('#trip-detail-status');
  const edit = document.querySelector('#trip-detail-edit'),
    makeCurrent = document.querySelector('#trip-detail-current'),
    remove = document.querySelector('#trip-detail-delete');
  section.hidden = false;
  for (const link of [edit, document.querySelector('#trip-detail-empty-edit')])
    link.href = tripEditUrl(tripId);
  let trip = null,
    busy = false,
    request = 0,
    memberId = null;

  function controls() {
    edit.hidden = remove.hidden = !trip;
    makeCurrent.hidden = !trip || trip.isCurrent;
    makeCurrent.disabled = remove.disabled = busy;
    section.setAttribute('aria-busy', String(busy));
  }
  function render() {
    heading.textContent = trip ? tripTitle(trip) : '找不到這份行程';
    meta.textContent = trip
      ? [tripDateLabel(trip), `${trip.items.length} 個地點`, trip.isCurrent ? '目前行程' : '']
          .filter(Boolean)
          .join('・')
      : '';
    stops.replaceChildren(...(trip?.items || []).map(tripStopView));
    empty.hidden = !trip || trip.items.length > 0;
    controls();
  }
  function failed(error) {
    if (error.status === 401) {
      handleSessionExpiry('member');
      return;
    }
    status(message, error.message, true);
    message.focus({ preventScroll: true });
  }
  async function load() {
    const current = ++request;
    busy = true;
    controls();
    try {
      const member = await readMember();
      if (!member) {
        handleSessionExpiry('member');
        return;
      }
      if (memberId && member.id !== memberId) trip = null;
      memberId = member.id;
      const { itinerary } = await itinerariesApi.get(tripId);
      if (current !== request) return;
      trip = itinerary;
      status(message, '');
    } catch (error) {
      if (current !== request) return;
      if (error.status === 404) {
        trip = null;
        status(message, '這份行程不存在或已刪除，請回到我的行程重新選擇。', true);
      } else failed(error);
    } finally {
      if (current === request) {
        busy = false;
        render();
      }
    }
  }
  async function run(task) {
    if (busy || !trip) return;
    busy = true;
    controls();
    try {
      await task();
    } catch (error) {
      failed(error);
    } finally {
      busy = false;
      controls();
    }
  }
  makeCurrent.addEventListener('click', () =>
    run(async () => {
      const { itinerary } = await itinerariesApi.select(trip.id, trip.version);
      trip = itinerary;
      render();
      status(message, '已設為目前行程；在景點頁按「加入行程」會加到這份。');
    })
  );
  remove.addEventListener('click', () =>
    run(async () => {
      if (!(await confirmAction('刪除行程', `確定刪除「${tripTitle(trip)}」？刪除後無法復原。`, remove)))
        return;
      await itinerariesApi.delete(trip.id, trip.version);
      flash(`已刪除「${tripTitle(trip)}」。`);
      location.assign(tripOverviewUrl());
    })
  );
  onPageReturn(() => {
    if (!busy) void load();
  });
  render();
  await load();
}
