// 靜態的認證／會員頁只更新共用的網站識別資訊，不覆寫各頁自己的 SEO。
export async function initializeSiteInformation() {
  if (!document.querySelector('.site-footer')) return;
  try {
    const response = await fetch('/api/v1/site-settings/public', {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) return;
    const settings = await response.json();
    for (const brand of document.querySelectorAll('.brand-copy strong'))
      brand.textContent = settings.siteName;
    const copyright = document.querySelector('[data-site-copyright]');
    if (copyright) copyright.textContent = '© 2026 ' + settings.siteName + '・新竹城市生活指南';
    const footer = document.querySelector('.footer-bottom');
    let contact = footer?.querySelector('[data-site-contact]');
    if (settings.general?.contactEmail && footer) {
      if (!contact) {
        contact = document.createElement('a');
        contact.dataset.siteContact = '';
        footer.prepend(contact);
      }
      contact.href = 'mailto:' + settings.general.contactEmail;
      contact.textContent = '聯絡我們';
    } else contact?.remove();
  } catch {
    /* 公開快照暫不可用時保留原本可操作的靜態頁。 */
  }
}
