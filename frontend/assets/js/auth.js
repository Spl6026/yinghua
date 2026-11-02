import { authApi } from './api/auth-api.js';
import { authConfiguration } from './core/api-client.js';
import {
  returnTo,
  requireSession,
  memberNavigation,
  redirectAuthenticated,
  adminReturnTo,
  adminDestination,
} from './core/session.js';
import { submitForm, status } from './core/ui.js';

const { authKind: kind, authMode: mode } = document.body.dataset;
// 在登入與註冊之間切換時，保留已通過白名單檢查的收藏或行程待辦，但不自動執行。
if (kind === 'member' && (mode === 'login' || mode === 'register')) {
  const switchLink = document.querySelector('.account-switch a');
  if (switchLink)
    switchLink.href =
      (mode === 'login' ? '/register.html' : '/login.html') +
      '?returnTo=' +
      encodeURIComponent(returnTo(kind));
}
const api = authApi(kind);
const form = document.querySelector('#auth-form');
let checkingSession = mode === 'login' || mode === 'register' || mode === 'change-password';
const returnLink = document.querySelector('[data-auth-return]');
const logoutButton = document.querySelector('[data-auth-logout]');
let credentialsChanged = false;
let challengeToken;
let termsVersion;
let refreshingCaptcha = false;
let captchaByEmail = false;
let submitting = false;
let signingOut = false;
let completed = false;
let retryUntil = 0;
const submit = form.querySelector('[type="submit"]');
function canSubmit() {
  return (
    !checkingSession &&
    !signingOut &&
    !completed &&
    Date.now() >= retryUntil &&
    (mode !== 'register' || (Boolean(challengeToken && termsVersion) && !refreshingCaptcha))
  );
}
function updateSubmit() {
  submit.disabled = submitting || !canSubmit();
  if (logoutButton) logoutButton.disabled = checkingSession || submitting || signingOut || completed;
}
updateSubmit();
// 更改密碼頁可以登出換帳號；登出與送出共用同一個互斥狀態。
logoutButton?.addEventListener('click', async () => {
  if (checkingSession || submitting || signingOut || completed) return;
  signingOut = true;
  updateSubmit();
  form.setAttribute('aria-busy', 'true');
  status(form.querySelector('.form-status'), '登出中…');
  try {
    await api.logout();
    completed = true;
    location.replace('/admin/login.html');
  } catch (error) {
    if (error.status === 401) {
      completed = true;
      location.replace('/admin/login.html');
    } else status(form.querySelector('.form-status'), error.message, true);
  } finally {
    signingOut = false;
    form.removeAttribute('aria-busy');
    updateSubmit();
  }
});
// 一次性憑證只放在記憶體；先清掉網址的 fragment，再發出任何網路請求，避免憑證外洩。
const resetToken =
  mode === 'reset-password' ? new URLSearchParams(location.hash.slice(1)).get('token') : null;
if (mode === 'reset-password') history.replaceState(null, '', location.pathname);

// 使用者手動換驗證碼時清除舊答案的欄位錯誤；送出失敗後自動換新圖，但保留錯誤說明。
function clearAnswerError() {
  const answer = form.elements.answer;
  answer?.removeAttribute?.('aria-invalid');
  answer?.removeAttribute?.('aria-describedby');
  answer?.closest?.('.form-field')?.querySelector('.field-error')?.remove();
}

async function refreshCaptcha(email = false, { keepErrors = false } = {}) {
  if (refreshingCaptcha) return;
  if (!keepErrors) clearAnswerError();
  const message = document.querySelector('#captcha-status');
  const buttons = [document.querySelector('#refresh-captcha'), document.querySelector('#email-captcha')];
  refreshingCaptcha = true;
  challengeToken = undefined;
  updateSubmit();
  buttons.forEach(button => {
    button.disabled = true;
  });
  try {
    if (email && !form.elements.email.reportValidity()) return;
    termsVersion ??= (await authConfiguration()).termsVersion;
    const termsLabel = document.querySelector('[data-terms-version]');
    if (termsLabel) termsLabel.textContent = termsVersion;
    const result = email ? await api.emailCaptcha(form.elements.email.value) : await api.captcha();
    challengeToken = result.challengeToken;
    captchaByEmail = email;
    const image = document.querySelector('#captcha-image');
    image.hidden = email;
    if (!email) image.src = result.imageDataUrl;
    form.elements.answer.value = '';
    status(
      message,
      email ? '驗證碼已寄出，請檢查信箱。有效期限 5 分鐘。' : '請輸入圖片中的驗證碼，有效期限 5 分鐘。'
    );
    if (email) form.elements.answer.focus();
  } catch (error) {
    status(message, error.message, true);
  } finally {
    refreshingCaptcha = false;
    buttons.forEach(button => {
      button.disabled = false;
    });
    updateSubmit();
  }
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  if (checkingSession) {
    status(form.querySelector('.form-status'), '正在確認登入狀態，請稍候。');
    return;
  }
  if (submitting || !canSubmit() || submit.disabled) return;
  // 所有送出入口（包含按 Enter）共用這個鎖；成功後會離開頁面或重新登入，不會重送。
  submitting = true;
  updateSubmit();
  try {
    await submitForm(
      form,
      async () => {
        const body = Object.fromEntries(new FormData(form));
        if (mode === 'register') {
          body.acceptTerms = form.elements.acceptTerms.checked;
          body.challengeToken = challengeToken;
          body.termsVersion = termsVersion;
          await api.register(body);
          completed = true;
          location.assign(returnTo(kind));
        } else if (mode === 'login') {
          const identity = await api.login(body);
          completed = true;
          location.assign(kind === 'admin' ? adminReturnTo(identity) : returnTo(kind));
        } else if (mode === 'forgot-password') {
          const result = await api.forgot(body);
          status(form.querySelector('.form-status'), result.message);
        } else {
          if (mode === 'reset-password') {
            body.token = resetToken;
            await api.reset(body);
          } else await api.change(body);
          completed = true;
          form.reset();
          credentialsChanged = true;
          if (logoutButton) logoutButton.hidden = true;
          if (returnLink) {
            returnLink.href = kind === 'admin' ? '/admin/login.html' : '/login.html';
            returnLink.textContent = '重新登入';
            returnLink.hidden = false;
          }
          void memberNavigation();
          status(form.querySelector('.form-status'), '密碼已更新，請使用新密碼重新登入。');
          form.querySelector('[type="submit"]').hidden = true;
        }
      },
      {
        canSubmit,
        onError: error => {
          if (error.retryAfter > 0) retryUntil = Date.now() + Math.min(error.retryAfter, 86400) * 1000;
          // 驗證碼只能使用一次，註冊失敗後舊 token 已失效；自動換新圖片，避免使用者重送必定失敗的請求。
          if (
            mode === 'register' &&
            !captchaByEmail &&
            !(error.retryAfter > 0) &&
            error.status !== 429 &&
            !error.fieldErrors?.password &&
            !error.fieldErrors?.passwordConfirmation
          )
            Promise.resolve().then(() => refreshCaptcha(false, { keepErrors: true }));
        },
      }
    );
  } finally {
    submitting = false;
    updateSubmit();
  }
});

// 先綁定 submit 再發請求，避免網路等待時使用瀏覽器預設表單提交。
void memberNavigation();
if (mode === 'change-password') {
  if (returnLink) returnLink.hidden = true;
  form.setAttribute('aria-busy', 'true');
  try {
    const identity = await requireSession(kind, true);
    const preview = kind === 'admin' && identity?.preview?.active;
    if (preview) location.replace('/admin/index.html');
    checkingSession = !identity || Boolean(preview);
    if (logoutButton) logoutButton.hidden = checkingSession;
    if (identity && returnLink && !credentialsChanged && !preview) {
      // 更改前可以返回；成功後 session 已被撤銷，改為顯示重新登入。
      returnLink.hidden = false;
      if (kind === 'admin') returnLink.href = adminDestination(identity);
    }
  } catch (error) {
    status(form.querySelector('.form-status'), error.message, true);
  } finally {
    form.removeAttribute('aria-busy');
    updateSubmit();
  }
}
if (mode === 'reset-password' && !/^[a-f0-9]{64}$/.test(resetToken || '')) {
  status(form.querySelector('.form-status'), '重設連結無效，請重新申請。', true);
  form.querySelector('[type="submit"]').disabled = true;
}
if (mode === 'login' || mode === 'register') {
  submit.disabled = true;
  form.setAttribute('aria-busy', 'true');
  try {
    checkingSession = await redirectAuthenticated(kind);
  } catch (error) {
    checkingSession = false;
    status(form.querySelector('.form-status'), error.message, true);
  } finally {
    updateSubmit();
    form.removeAttribute('aria-busy');
  }
}
if (mode === 'register' && !checkingSession) {
  document.querySelector('#refresh-captcha').addEventListener('click', () => refreshCaptcha());
  document.querySelector('#email-captcha').addEventListener('click', () => refreshCaptcha(true));
  await refreshCaptcha();
}
