// [U-22／E-37] 在主樣式前發現兩套字型，不經CSS @import再發起字型樣式請求。
const paths = ['/assets/fonts/NotoSansTC-common.woff2', '/assets/fonts/NotoSerifTC-common.woff2'];
export function renderFontHead(html) {
  html = html.replace(
    /<link\b[^>]*href="\/assets\/(?:fonts\/Noto(?:Sans|Serif)TC-common\.woff2|css\/fonts\.css)"[^>]*>\s*/g,
    ''
  );
  html = html.replace(/<script src="\/assets\/js\/font-boot\.js"><\/script>\s*/g, '');
  const links =
    paths
      .map(path => `<link rel="preload" href="${path}" as="font" type="font/woff2" crossorigin>`)
      .join('\n') +
    '\n<link rel="stylesheet" href="/assets/css/fonts.css">\n<script src="/assets/js/font-boot.js"></script>\n';
  if (/<meta\s+charset=[^>]+>/i.test(html))
    return html.replace(/(<meta\s+charset=[^>]+>)\s*/i, (_match, charset) => charset + '\n' + links);
  return html.replace(/<head>\s*/i, () => '<head>\n' + links);
}
