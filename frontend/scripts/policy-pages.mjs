import {escapeHtml} from '../assets/js/core/content-view.js';

// [S：G16/G28；E-36] 純文字政策永不當作 HTML 執行；空白保留既有開發說明。
export function renderPolicyVersion(html,version='local-development-v1',general){
  html=html.replaceAll('{{TERMS_VERSION}}',()=>escapeHtml(version));
  const kind=html.includes('data-policy-kind="terms"')?'terms':html.includes('data-policy-kind="privacy"')?'privacy':null;
  if(!kind||!general)return html;
  const text=general[kind+'Text'],label=general[kind+'Version']||version;
  html=html.replace(/(<span data-policy-version>)[\s\S]*?(<\/span>)/,(_,a,b)=>a+escapeHtml(label)+b);
  if(text){
    const paragraphs=String(text).split(/\r?\n/).map(line=>'<p>'+escapeHtml(line)+'</p>').join('');
    html=html.replace(/<!-- POLICY_CONTENT_START -->[\s\S]*?<!-- POLICY_CONTENT_END -->/,()=>paragraphs);
    html=html.replace('DEVELOPMENT NOTICE','POLICY');
    html=html.replaceAll(kind==='terms'?'開發測試服務條款':'開發測試隱私說明',kind==='terms'?'會員服務條款':'隱私權政策');
  }
  if(general.termsText)html=html.replaceAll('開發測試服務條款','會員服務條款');
  if(general.privacyText)html=html.replaceAll('開發測試隱私說明','隱私權政策');
  return html;
}
