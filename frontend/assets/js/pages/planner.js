import { memberResourcesApi } from '../api/member-resources-api.js';
import { CurrentTripState } from '../core/current-trip-state.js';
import { readMember, resolvePublicAttraction, clearMemberIntent } from '../core/member-actions.js';
import { loginLocation } from '../core/session.js';
import { element, status } from '../core/ui.js';
import { memberItineraryStop } from '../components/member-itinerary.js';

const trip = new CurrentTripState(memberResourcesApi, readMember);
const list = document.querySelector('#itinerary-list');
const message = document.querySelector('#trip-status');
const more = document.querySelector('#itinerary-more');
const reload = document.querySelector('#reload-trip');
let pendingGeneration = 0;

function controls() {
  const locked = trip.busy || trip.loading || !trip.loaded;
  list.querySelectorAll('button').forEach(button => { button.disabled = locked; });
  document.querySelector('#confirm-place')?.toggleAttribute('disabled', locked);
  reload.disabled = trip.busy || trip.loading;
  more.disabled = locked; more.hidden = !trip.nextCursor;
  list.setAttribute('aria-busy', String(trip.busy || trip.loading));
}
function render() {
  list.replaceChildren(...trip.items.map((item,index) => memberItineraryStop(item,index,!trip.loaded)));
  document.querySelector('#itinerary-empty').hidden = !trip.loaded || trip.items.length > 0;
  document.querySelector('#trip-title').value = trip.itinerary?.title || '';
  document.querySelector('#trip-date').value = trip.itinerary?.tripDate || '';
  document.querySelector('#planner-user-name').textContent = trip.member?.displayName || '城市旅人';
  const accountLink = document.querySelector('.planner-user .text-link');
  accountLink.href = '/change-password.html'; accountLink.textContent = '更改密碼 ↗';
  controls();
}
async function load(append = false, saved = false) {
  const task = trip.load(append); controls();
  status(message, saved ? '資料已保存，正在重新載入行程…' : '讀取本人行程中…');
  try {
    if (await task) { render(); status(message, saved ? '變更已保存。' : `已載入 ${trip.items.length} 個地點。`); }
  } catch (error) {
    render();
    if (error.status === 401) location.replace(loginLocation('member'));
    else status(message, saved ? '資料已保存，但畫面讀取失敗。請按「重新載入行程」。' : error.message, true);
  } finally { controls(); }
}
async function change(id, remove = false) {
  if (trip.busy || trip.loading || !trip.loaded) return;
  const writing = trip.change(id, remove); controls(); status(message, '保存行程變更中…');
  try {
    const result = await writing;
    if (result.busy) return;
    if (!remove) { clearMemberIntent('place'); document.querySelector('#trip-place-intent')?.remove(); }
    await load(false, true); message.focus();
  } catch (error) {
    if (!trip.loaded) render();
    if (error.status === 401) location.replace(loginLocation('member'));
    else status(message, error.message, true);
    message.focus();
  } finally { controls(); }
}
async function pendingPlace() {
  const id = new URLSearchParams(location.search).get('place');
  if (!id) return;
  const request = ++pendingGeneration;
  document.querySelector('#trip-place-intent')?.remove();
  const section = element('section', {id:'trip-place-intent',className:'member-intent','aria-label':'待確認行程地點'});
  const text = element('p', {role:'status'}, '確認原先選擇的地點中…'); section.append(text); list.before(section);
  const cancel = element('button', {type:'button',className:'button button-outline'}, '取消');
  cancel.addEventListener('click', () => { ++pendingGeneration; clearMemberIntent('place'); section.remove(); }); section.append(cancel);
  try {
    const place = await resolvePublicAttraction(id);
    if (request !== pendingGeneration || !section.isConnected) return;
    if (!place) { status(text, '此地點目前無法加入，請到城市散策重新選擇。', true); return; }
    status(text, `是否將「${place.name}」加入目前行程？`);
    const confirm = element('button', {type:'button',className:'button button-dark',id:'confirm-place'}, '確認加入');
    confirm.addEventListener('click', () => { void change(place.id); }); section.append(confirm); controls();
  } catch (error) {
    status(text, error.message, true);
    const retry = element('button', {type:'button',className:'button button-outline'}, '重新確認地點'); retry.addEventListener('click', () => { void pendingPlace(); }); section.append(retry);
  }
}
reload.addEventListener('click', () => { void load(); });
more.addEventListener('click', () => { void load(true); });
list.addEventListener('click', event => { const button = event.target.closest('[data-remove-stop]'); if (button && !button.disabled) void change(button.dataset.removeStop,true); });
const resume = () => { trip.reset(); render(); void load(); void pendingPlace(); };
window.addEventListener('pageshow', event => { if (event.persisted) resume(); });
window.addEventListener('focus', resume);
document.addEventListener('visibilitychange', () => { if (!document.hidden) resume(); });
await load();
await pendingPlace();
