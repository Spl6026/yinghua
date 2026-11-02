import { escapeHtml } from '../assets/js/core/content-view.js';

// 純文字政策內容絕不當作 HTML 執行；後台未填寫時保留頁面預設、面向大眾的條款文案。
export function renderPolicyVersion(html, version = '2026-11-02', general) {
  html = html.replaceAll('{{TERMS_VERSION}}', () => escapeHtml(version));
  const kind = html.includes('data-policy-kind="terms"')
    ? 'terms'
    : html.includes('data-policy-kind="privacy"')
      ? 'privacy'
      : null;
  if (!kind || !general) return html;
  const text = general[kind + 'Text'],
    label = general[kind + 'Version'] || version;
  html = html.replace(
    /(<span data-policy-version>)[\s\S]*?(<\/span>)/,
    (_, a, b) => a + escapeHtml(label) + b
  );
  if (text) {
    const paragraphs = String(text)
      .split(/\r?\n/)
      .map(line => '<p>' + escapeHtml(line) + '</p>')
      .join('');
    html = html.replace(/<!-- POLICY_CONTENT_START -->[\s\S]*?<!-- POLICY_CONTENT_END -->/, () => paragraphs);
  }
  return html;
}
