// 只在記憶體中保存登入期限與本人識別資訊；不保存密碼、Cookie 或 token。
const sessions = new Map();
const listeners = new Set();
const epochs = new Map();
export const sessionEpoch = kind => epochs.get(kind) || 0;
export const sessionSnapshot = kind => sessions.get(kind);
export function subscribeSession(listener) {
  listeners.add(listener);
  for (const [kind, value] of sessions) listener(kind, value);
  return () => listeners.delete(listener);
}
function emit(kind, value) {
  sessions.set(kind, value);
  for (const listener of listeners) listener(kind, value);
}
export function recordSessionResponse(path, response, payload, kind, epoch = sessionEpoch(kind)) {
  if (epoch !== sessionEpoch(kind)) return;
  const previous = sessions.get(kind) || {};
  if (response.ok && /\/auth\/(logout|change-password|reset-password)$/.test(path)) {
    epochs.set(kind, sessionEpoch(kind) + 1);
    emit(kind, {});
    return;
  }
  if (response.ok && /\/auth\/(login|register)$/.test(path)) epochs.set(kind, sessionEpoch(kind) + 1);
  if (response.status === 401 && payload.code === 'UNAUTHENTICATED' && previous.known) {
    emit(kind, { ...previous, expired: true });
    return;
  }
  if (!response.ok && response.status !== 403 && response.status !== 422 && response.status !== 409) return;
  let identity = previous.identity;
  // 管理員編輯 API 的 admin 是「被編輯者」，不能當成目前登入者。
  const account = /\/auth\/(me|login|register)$/.test(path)
    ? kind === 'admin'
      ? payload.admin
      : payload.member
    : null;
  if (account) {
    const operator = payload.preview?.operator;
    identity = {
      id: operator?.id || account.id,
      identifier: operator?.account || account.account || account.email,
      preview: Boolean(payload.preview?.active),
    };
  }
  const values = ['X-Session-Server-Time', 'X-Session-Expires-At', 'X-Session-Idle-Expires-At'].map(name =>
    response.headers.get(name)
  );
  const valid =
    response.headers.get('X-Session-Kind') === kind && values.every(value => /^\d+$/.test(value || ''));
  if (!valid && !account) return;
  const headerOwner = response.headers.get('X-Session-Owner');
  const changed =
    previous.changed ||
    Boolean(
      previous.identity &&
      ((identity && previous.identity.id !== identity.id) ||
        (headerOwner && previous.identity.id !== headerOwner))
    );
  emit(kind, {
    ...previous,
    identity,
    changed,
    ...(valid
      ? {
          known: true,
          expired: false,
          timing: {
            serverTime: Number(values[0]),
            expiresAt: Number(values[1]),
            idleExpiresAt: Number(values[2]),
          },
        }
      : {}),
  });
}
