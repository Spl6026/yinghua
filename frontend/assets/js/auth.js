import { authApi } from './api/auth-api.js';
import { authConfiguration } from './core/api-client.js';
import { returnTo, requireSession, memberNavigation, redirectAuthenticated } from './core/session.js';
import { submitForm, status } from './core/ui.js';

const { authKind: kind, authMode: mode } = document.body.dataset;
const api = authApi(kind);
const form = document.querySelector('#auth-form');
let checkingSession = mode === 'login' || mode === 'register';
let challengeToken;
let termsVersion;
let refreshingCaptcha = false;
let submitting = false;
let completed = false;
let retryUntil = 0;
const submit = form.querySelector('[type="submit"]');
function canSubmit() {
  return !checkingSession && !completed && Date.now() >= retryUntil && (mode !== 'register' || Boolean(challengeToken && termsVersion) && !refreshingCaptcha);
}
function updateSubmit() { submit.disabled = submitting || !canSubmit(); }
updateSubmit();
// [E-07] 一次性憑證只在記憶體；先清掉網址 fragment，再發任何網路請求。

async function refreshCaptcha(email = false) {
  if (refreshingCaptcha) return;
  const message = document.querySelector('#captcha-status');
  const buttons = [document.querySelector('#refresh-captcha'), document.querySelector('#email-captcha')];
  refreshingCaptcha = true;
  challengeToken = undefined;
  updateSubmit();
  buttons.forEach(button => { button.disabled = true; });
  try {
    if (email && !form.elements.email.reportValidity()) return;
    termsVersion ??= (await authConfiguration()).termsVersion;
    const result = email ? await api.emailCaptcha(form.elements.email.value) : await api.captcha();
    challengeToken = result.challengeToken;
    const image = document.querySelector('#captcha-image');
    image.hidden = email;
    if (!email) image.src = result.imageDataUrl;
    form.elements.answer.value = '';
    status(message, email ? '驗證碼已寄出，請檢查信箱。有效期限 5 分鐘。' : '請輸入圖片中的驗證碼，有效期限 5 分鐘。');
    if (email) form.elements.answer.focus();
  } catch (error) { status(message, error.message, true); }
  finally { refreshingCaptcha = false; buttons.forEach(button => { button.disabled = false; }); updateSubmit(); }
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  if (checkingSession) { status(form.querySelector('.form-status'), '正在確認登入狀態，請稍候。'); return; }
  if (submitting || !canSubmit() || submit.disabled) return;
  // [E-14] 包含 Enter 的提交入口共用此鎖；成功後離頁／重新登入，不重送。
  submitting = true;
  try { await submitForm(form, async () => {
    const body = Object.fromEntries(new FormData(form));
    if (mode === 'register') {
      body.acceptTerms = form.elements.acceptTerms.checked;
      body.challengeToken = challengeToken;
      body.termsVersion = termsVersion;
      await api.register(body);
      completed = true;
      location.assign(returnTo(kind));
    } else if (mode === 'login') {
      const result = await api.login(body);
      completed = true;
      location.assign(kind === 'admin' && result.admin.mustChangePassword ? '/admin/change-password.html' : returnTo(kind));
    }
  }, { canSubmit, onError: error => {
    if (error.retryAfter > 0) retryUntil = Date.now() + Math.min(error.retryAfter, 86400) * 1000;
  } }); }
  finally { submitting = false; }
});

// 先綁定 submit 再發請求，避免網路等待時使用瀏覽器預設表單提交。
void memberNavigation();
if (mode === 'login' || mode === 'register') {
  submit.disabled = true;
  form.setAttribute('aria-busy', 'true');
  try { checkingSession = await redirectAuthenticated(kind); }
  catch (error) { checkingSession = false; status(form.querySelector('.form-status'), error.message, true); }
  finally { updateSubmit(); form.removeAttribute('aria-busy'); }
}
if (mode === 'register' && !checkingSession) {
  document.querySelector('#refresh-captcha').addEventListener('click', () => refreshCaptcha());
  document.querySelector('#email-captcha').addEventListener('click', () => refreshCaptcha(true));
  await refreshCaptcha();
}
