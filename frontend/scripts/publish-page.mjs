// 每一頁共同的發布步驟：上一篇／下一篇、SEO 標頭、共用模板、品牌與聯絡資訊、樣式與受管理的腳本，
// 最後加上資產版本並寫入此 generation；同時收集 sitemap 項目。各類內容的發布器只負責組出頁面主體。
import { writeFile, mkdir } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { escapeHtml, jsonForHtml } from '../assets/js/core/content-view.js';
import { contentNeighbors } from '../assets/js/core/content-neighbors.js';
import { resolveSeo } from '../assets/js/core/seo-resolver.js';
import { renderSeoHead } from './seo-head.mjs';
import { DEFAULT_POLICY_VERSION, renderPolicyVersion } from './policy-pages.mjs';
import { annotateEnglishPassages } from './language-passages.mjs';
import { finalizePage, markActiveNavigation } from './page-pipeline.mjs';
import { crawlerEntry } from './crawler-index.mjs';
import { mustReplace } from './html-edit.mjs';

// 發布頁共用同一組內容樣式；ui-consistency 必須最後載入，才能統一前面各檔的字級與高度。
const publishedStylesheets = ['member-actions', 'places', 'films', 'public-content', 'ui-consistency'];
function withPublishedStylesheets(html) {
  const links = publishedStylesheets.map(name => `<link rel="stylesheet" href="/assets/css/${name}.css">`);
  return html
    .replace(/<link rel="stylesheet" href="\/assets\/css\/(?:member-actions|ui-consistency)\.css">\n?/g, '')
    .replace('</head>', `${links.join('')}</head>`);
}

/**
 * @returns {{ page: Function, indexEntries: object[] }} page(path, html, title, description, item, canonicalPath, managed)
 */
export function createPagePublisher({
  source,
  snapshot,
  root,
  templates,
  seoCatalog,
  publicImages,
  version,
  filmItems,
}) {
  const modulePreloadCache = new Map();
  const indexEntries = [];
  const filmEnabled = Array.isArray(snapshot.films);
  const newsEnabled = Array.isArray(snapshot.news);
  const offersEnabled = Array.isArray(snapshot.offers);
  async function page(path, html, title, description, item, canonicalPath = path, managed = true) {
    if (snapshot.detailNeighbors?.[path])
      html = mustReplace(html, '</main>', () => contentNeighbors(snapshot.detailNeighbors[path]) + '</main>');
    // 分兩步套用條款：版本號要在展開共用模板前填入，否則 {{TERMS_VERSION}} 會被當成未知模板；
    // 後台填寫的條款內文則要在展開模板之後才放進頁面，內文裡若寫了 {{SITE_HEADER}} 之類的字樣才會保持原文。
    html = renderPolicyVersion(html, snapshot.policyVersion || DEFAULT_POLICY_VERSION);
    html = html.replace(/<script type="application\/ld\+json">[\s\S]*?<\/script>/g, '');
    const context = {
      path: canonicalPath,
      title,
      description,
      item,
      seo: snapshot.seo,
      catalog: seoCatalog,
      origin: snapshot.origin,
      publicImages,
      published: true,
      unavailable: canonicalPath === '/museum.html' && !item,
      modifiedAt: snapshot.indexModifiedAt?.[canonicalPath],
    };
    const meta = resolveSeo(context);
    html = mustReplace(
      html,
      '</head>',
      '<noscript><style>.reveal,.image-reveal{opacity:1!important;transform:none!important}</style></noscript></head>'
    );
    if (managed)
      html = mustReplace(
        html,
        /<body\b([^>]*)>/,
        (_, attributes) =>
          `<body${attributes} data-managed-attractions="true"${filmEnabled ? ' data-managed-films="true"' : ''}${newsEnabled ? ' data-managed-news="true"' : ''}${offersEnabled ? ' data-managed-offers="true"' : ''}>`
      );
    html = withPublishedStylesheets(html);
    html = html.replace(/\{\{([A-Z_]+)\}\}/g, (_, name) => {
      const template = templates[name];
      if (template === undefined) throw new Error('Unknown template');
      // 消息與優惠的分頁檔放在內部路徑，選中項目依對外網址判斷。
      return name === 'SITE_HEADER' ? markActiveNavigation(template, canonicalPath) : template;
    });
    html = renderSeoHead(html, context, meta);
    html = renderPolicyVersion(
      html,
      snapshot.policyVersion || DEFAULT_POLICY_VERSION,
      snapshot.general?.values
    );
    html = mustReplace(
      html,
      /(<span class="brand-copy"><strong>)[\s\S]*?(<\/strong>)/g,
      (_, a, b) => a + escapeHtml(meta.siteName) + b
    );
    html = mustReplace(
      html,
      /(<span data-site-copyright>)[\s\S]*?(<\/span>)/,
      (_, a, b) => a + '© 2026 ' + escapeHtml(meta.siteName) + '・新竹城市生活指南' + b
    );
    if (snapshot.general?.values.contactEmail)
      html = mustReplace(
        html,
        '<a href="/site-map.html">',
        () =>
          '<a data-site-contact href="mailto:' +
          escapeHtml(snapshot.general.values.contactEmail) +
          '">聯絡我們</a><a href="/site-map.html">'
      );
    if (managed)
      html = mustReplace(
        html,
        '</body>',
        () => `<script type="module" src="/assets/js/pages/attractions.js"></script></body>`
      );
    if (managed && filmEnabled)
      html = mustReplace(
        html,
        '</body>',
        () =>
          `<script type="application/json" id="film-publication">${jsonForHtml({ total: filmItems.length })}</script><script type="module" src="/assets/js/pages/films.js"></script></body>`
      );
    const target = join(root, path.slice(1));
    await mkdir(dirname(target), { recursive: true });
    html = finalizePage(html, {
      assetsRoot: join(source, 'assets'),
      version,
      preloadCache: modulePreloadCache,
    });
    await writeFile(target, annotateEnglishPassages(html));
    const entry = crawlerEntry(context, meta);
    if (entry) indexEntries.push(entry);
  }
  return { page, indexEntries };
}
