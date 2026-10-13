import { authApi } from './api/auth-api.js';
import { requireSession, hasPermission, handleAdminError } from './core/session.js';
import { initializeMenus, status, element } from './core/ui.js';

initializeMenus();
const main = document.querySelector('.admin-content');
const message = element('p', { role: 'status', tabindex: '-1', className: 'form-status' });
main.prepend(message);
main.setAttribute('aria-busy', 'true');
try {
  const identity = await requireSession('admin');
  if (identity) {
    document.querySelector('#admin-display-name').textContent = identity.admin.displayName;
    document.querySelector('#admin-account').textContent = identity.admin.account;
    document.querySelectorAll('.admin-nav').forEach(link => {
      link.hidden = !hasPermission(identity, link.dataset.module, 'view');
      if (link.dataset.module === document.body.dataset.module) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
      }
    });
    document.querySelector('#admin-logout').addEventListener('click', async event => {
      event.currentTarget.disabled = true;
      try { await authApi('admin').logout(); location.assign('/admin/login.html'); }
      catch (error) { handleAdminError(error, message); document.querySelector('#admin-logout').disabled = false; }
    });
    const { module, action, page } = document.body.dataset;
    if (!hasPermission(identity, module, action)) {
      main.replaceChildren(element('h1', {}, '無法存取'), element('p', {}, '你尚未取得此功能的權限，請聯絡管理員。'));
    }
  }
} catch (error) { status(message, error.message, true); message.focus(); }
finally { main.removeAttribute('aria-busy'); }
