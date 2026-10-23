import {escapeHtml} from '../assets/js/core/content-view.js';

// [S：G16；E-28] 沿用認證設定的真實同意版本，文字輸出不執行 HTML。
export function renderPolicyVersion(html,version='local-development-v1'){
  return html.replaceAll('{{TERMS_VERSION}}',()=>escapeHtml(version));
}
