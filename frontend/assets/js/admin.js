import { initializeFilmList } from './pages/film-list.js';
import { initializeFilmForm } from './pages/film-form.js';
import { authApi } from './api/auth-api.js';
import { requireSession, hasPermission, handleAdminError, adminNavigation, adminDestination } from './core/session.js';
import { initializeMenus, status, element } from './core/ui.js';
import { initializeAdmins } from './pages/admin-list.js';
import { initializeAdminForm } from './pages/admin-form.js';
import { initializePermissions } from './pages/permissions.js';
import { initializeAdminPreview } from './components/admin-preview.js';
import { initializeAttractionList } from './pages/attraction-list.js';
import { initializeAttractionForm } from './pages/attraction-form.js';
import { initializeSeoSettings } from './pages/seo-settings.js';
import { initializeContentList } from './pages/content-list.js';
import { initializeContentForm } from './pages/content-form.js';
import { initializeRouteList } from './pages/route-list.js';
import { initializeRouteForm } from './pages/route-form.js';

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
    document.querySelector('.admin-brand').href = adminDestination(identity);
    document.querySelectorAll('.admin-nav').forEach(link => {
      const entry = adminNavigation(identity, link.dataset.module);
      link.hidden = !entry;
      if (entry) {
        link.href = entry.href;
        if (entry.label) link.textContent = entry.label;
      }
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
    await initializeAdminPreview(identity);
    const { module, action, page } = document.body.dataset;
    if (!hasPermission(identity, module, action)) {
      main.replaceChildren(element('h1', {}, '無法存取'), element('p', {}, '你尚未取得此功能的權限，請聯絡管理員。'));
    } else if (page === 'admins') await initializeAdmins(identity);
    else if (page === 'admin-add' || page === 'admin-edit') await initializeAdminForm(identity, page);
    else if (page === 'permissions') await initializePermissions(identity);
    else if (page === 'films') await initializeFilmList(identity);
    else if (page === 'film-add' || page === 'film-edit') await initializeFilmForm(identity, page);
    else if (page === 'places') await initializeAttractionList(identity);
    else if (page === 'place-add' || page === 'place-edit') await initializeAttractionForm(identity, page);
    else if (page === 'settings') await initializeSeoSettings(identity);
    else if (page === 'news' || page === 'offers') await initializeContentList(identity, module);
    else if (['news-add','news-edit','offer-add','offer-edit'].includes(page)) await initializeContentForm(identity, module, page);
    else if (page === 'routes') await initializeRouteList(identity);
    else if (page === 'route-add' || page === 'route-edit') await initializeRouteForm(identity, page);
  }
} catch (error) { status(message, error.message, true); message.focus(); }
finally { main.removeAttribute('aria-busy'); }
