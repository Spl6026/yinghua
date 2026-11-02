import { authApi } from '../api/auth-api.js';
import { sessionSnapshot, subscribeSession } from './session-events.js';
import { SessionDeadline } from './session-deadline.js';
import { sessionPrompt } from '../components/session-prompt.js';
import { toast } from './ui.js';

let monitor;
export function startSessionMonitoring() {
  if (monitor || typeof document === 'undefined') return;
  const kind = location.pathname.startsWith('/admin/') ? 'admin' : 'member';
  const deadline = new SessionDeadline();
  let current = {},
    timer,
    dismissedMode = null,
    mode = 'active',
    previewControl = false;
  const prompt = sessionPrompt(
    async password => {
      if (mode === 'changed') {
        location.reload();
        return;
      }
      if (previewControl) {
        await authApi('admin').preview('');
        location.reload();
        return;
      }
      const expected = current.identity?.id,
        wasPreview = current.identity?.preview;
      if (mode === 'warn') await authApi(kind).me();
      else {
        if (!current.identity?.identifier)
          throw new Error('無法確認原登入帳號，請在另一分頁完成登入後再重試。');
        await authApi(kind).login({
          [kind === 'admin' ? 'account' : 'email']: current.identity.identifier,
          password,
        });
        await authApi(kind).me();
        if (sessionSnapshot(kind)?.identity?.id !== expected)
          throw new Error('登入身分不同，請重新開啟本頁。');
        if (wasPreview) {
          location.reload();
          return;
        }
      }
      dismissedMode = null;
      prompt.close();
      toast('登入已恢復，未儲存內容仍保留；請重新執行原操作。');
    },
    () => {
      dismissedMode = mode;
    },
    () => tick()
  );
  function tick(force = false) {
    clearTimeout(timer);
    if (!current.known) return;
    const state = deadline.state();
    mode = current.changed ? 'changed' : current.expired ? 'expired' : state.mode;
    previewControl = Boolean(current.identity?.preview && mode === 'reauth');
    if (mode !== 'active' && (dismissedMode !== mode || force || prompt.open))
      prompt.show(mode, current.identity, previewControl);
    if (mode === 'active' && prompt.open) {
      prompt.close();
      dismissedMode = null;
    }
    timer = setTimeout(
      () => tick(),
      Math.max(250, Math.min(30000, Math.min(state.idle, state.absolute) - (mode === 'active' ? 120000 : 0)))
    );
    if (mode === 'expired' || mode === 'changed') clearTimeout(timer);
  }
  subscribeSession((eventKind, value) => {
    if (eventKind !== kind) return;
    current = value;
    if (!value.known) {
      clearTimeout(timer);
      prompt.close();
      return;
    }
    if (value.timing) {
      deadline.update(value.timing);
      dismissedMode = null;
    }
    tick();
  });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) tick();
  });
  window.addEventListener('pageshow', () => tick());
  monitor = {
    kind,
    recover() {
      if (!current.known) return false;
      current = { ...current, expired: true };
      dismissedMode = null;
      tick(true);
      return true;
    },
  };
}
export function recoverSession(kind) {
  startSessionMonitoring();
  return monitor?.kind === kind ? monitor.recover() : false;
}
