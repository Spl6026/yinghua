import { escapeHtml, jsonForHtml } from '../assets/js/core/content-view.js';
import { resolveSeo } from '../assets/js/core/seo-resolver.js';
import { structuredDataGraph } from '../assets/js/core/structured-data.js';

// HTML 中每個 SEO 標籤只有一個負責寫入的地方；所有動態值都會編碼。
export function renderSeoHead(html, context, meta = resolveSeo(context)) {
  html = html
    .replace(/<title\b[^>]*>[\s\S]*?<\/title>/gi, '')
    .replace(/<meta\b[^>]*>/gi, tag =>
      /(?:name|property)\s*=\s*["'](?:description|keywords|robots|googlebot|twitter:[^"']*|og:[^"']*|google-site-verification|msvalidate\.01)["']/i.test(
        tag
      )
        ? ''
        : tag
    )
    .replace(/<link\b[^>]*>/gi, tag => (/rel\s*=\s*["']canonical["']/i.test(tag) ? '' : tag));
  html = html.replace(/<script type="application\/json" id="public-list-seo">[\s\S]*?<\/script>/g, '');
  // 建置時的原型與舊 graph 不能疊加到實際已發布的資料上。
  html = html.replace(
    /<script\b[^>]*\btype\s*=\s*["']application\/ld\+json["'][^>]*>[\s\S]*?<\/script>/gi,
    ''
  );
  if (context.unavailable) meta = { ...meta, robots: 'noindex,follow' };
  const name = (key, value) => `<meta name="${key}" content="${escapeHtml(value)}">`;
  const property = (key, value) => `<meta property="${key}" content="${escapeHtml(value)}">`;
  let head =
    `<title>${escapeHtml(meta.title)}</title>` +
    name('description', meta.description) +
    name('keywords', meta.keywords) +
    name('robots', meta.robots) +
    name('googlebot', meta.robots) +
    `<link rel="canonical" href="${escapeHtml(meta.canonical)}">`;
  for (const [key, value] of Object.entries({
    title: meta.title,
    description: meta.description,
    type: meta.type,
    url: meta.canonical,
    image: meta.imageUrl,
    site_name: meta.siteName,
    locale: meta.locale,
  }))
    head += property('og:' + key, value);
  for (const [key, value] of Object.entries({
    card: meta.twitterCard,
    title: meta.title,
    description: meta.description,
    image: meta.imageUrl,
  }))
    head += name('twitter:' + key, value);
  if (meta.twitterSite) head += name('twitter:site', meta.twitterSite);
  if (meta.googleVerification) head += name('google-site-verification', meta.googleVerification);
  if (meta.bingVerification) head += name('msvalidate.01', meta.bingVerification);
  if (!/<link\b[^>]*rel=["'](?:shortcut )?icon["']/i.test(html))
    head += '<link rel="icon" href="/assets/favicon.svg">';
  const graph = structuredDataGraph(context, meta);
  if (graph) head += `<script type="application/ld+json">${jsonForHtml(graph)}</script>`;
  const pathname = new URL(context.path, 'https://seo.invalid').pathname;
  if (['/events.html', '/activities.html', '/city-news.html', '/offers.html'].includes(pathname)) {
    // 只帶目前主題及已發布的設定；不把其他內容覆寫或管理資料送到瀏覽器。
    const clientContext = {
      origin: context.origin,
      publicImages: context.publicImages,
      seo: {
        defaults: context.seo?.defaults,
        overrides: (context.seo?.overrides || []).filter(
          row => row.target?.type === 'page' && row.target.path === pathname
        ),
      },
      catalog: (context.catalog || []).filter(page => page.path === pathname),
    };
    head += `<script type="application/json" id="public-list-seo">${jsonForHtml(clientContext)}</script>`;
  }
  return html.replace('</head>', () => head + '</head>');
}
