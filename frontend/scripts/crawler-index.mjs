import { isIndexablePublication } from '../assets/js/core/seo-resolver.js';
import { validTimestamp } from '../assets/js/core/structured-data.js';

const xml = value =>
  String(value).replace(
    /[&<>"']/g,
    char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' })[char]
  );
const namespace = 'http://www.sitemaps.org/schemas/sitemap/0.9';
const header = kind => `<?xml version="1.0" encoding="UTF-8"?>\n<${kind} xmlns="${namespace}">\n`;

function crawlerOrigin(value) {
  const root = new URL(value);
  if (
    !['http:', 'https:'].includes(root.protocol) ||
    root.username ||
    root.password ||
    root.pathname !== '/' ||
    root.search ||
    root.hash
  )
    throw new Error('Invalid crawler origin');
  return root.origin;
}

export function crawlerEntry(context, meta) {
  if (!isIndexablePublication(context, meta)) return null;
  const url = new URL(meta.canonical),
    origin = new URL(context.seo?.defaults?.canonicalOrigin || context.origin).origin;
  if (
    !['http:', 'https:'].includes(url.protocol) ||
    url.origin !== origin ||
    url.username ||
    url.password ||
    url.hash
  )
    throw new Error('Invalid sitemap canonical');
  // 集合頁與固定頁沒有可靠的內容修改時間，不用建置時間冒充。
  const detail =
    url.pathname === '/museum.html' || /^\/(?:places|films|news|offers)\/[^/]+\.html$/.test(url.pathname);
  const content = detail ? validTimestamp(context.item?.updatedAt || context.modifiedAt) : null;
  const seo = validTimestamp(context.seo?.updatedAt);
  const lastmod = content ? [content, seo].filter(Boolean).sort().at(-1) : null;
  return { url: url.href, ...(lastmod ? { lastmod } : {}) };
}

export function robotsText(origin) {
  const root = crawlerOrigin(origin);
  // 私人頁面由 noindex 與授權機制處理，robots 不能取代存取控制。
  return `User-agent: *\nDisallow: /admin/\nSitemap: ${root}/sitemap.xml\n`;
}

export function buildSitemaps(entries, origin, { maxUrls = 50000, maxBytes = 50 * 1024 * 1024 } = {}) {
  if (
    !Number.isInteger(maxUrls) ||
    maxUrls < 1 ||
    maxUrls > 50000 ||
    !Number.isSafeInteger(maxBytes) ||
    maxBytes > 50 * 1024 * 1024 ||
    maxBytes < 200
  )
    throw new Error('Invalid sitemap capacity');
  const root = crawlerOrigin(origin);
  const unique = new Map();
  for (const entry of entries.filter(Boolean)) {
    const url = new URL(entry.url);
    if (
      url.origin !== root ||
      url.username ||
      url.password ||
      url.hash ||
      !['http:', 'https:'].includes(url.protocol)
    )
      throw new Error('Cross-origin sitemap entry');
    const previous = unique.get(url.href),
      lastmod = validTimestamp(entry.lastmod);
    unique.set(url.href, {
      url: url.href,
      ...(previous?.lastmod || lastmod
        ? { lastmod: [previous?.lastmod, lastmod].filter(Boolean).sort().at(-1) }
        : {}),
    });
  }
  const chunks = [];
  let rows = [],
    bytes = Buffer.byteLength(header('urlset') + '</urlset>\n');
  for (const entry of [...unique.values()].sort((a, b) => a.url.localeCompare(b.url, 'en'))) {
    const row = `<url><loc>${xml(entry.url)}</loc>${entry.lastmod ? `<lastmod>${xml(entry.lastmod)}</lastmod>` : ''}</url>\n`,
      size = Buffer.byteLength(row);
    if (size + Buffer.byteLength(header('urlset') + '</urlset>\n') > maxBytes)
      throw new Error('Sitemap entry too large');
    if (rows.length && (rows.length >= maxUrls || bytes + size > maxBytes)) {
      chunks.push(rows);
      rows = [];
      bytes = Buffer.byteLength(header('urlset') + '</urlset>\n');
    }
    rows.push(row);
    bytes += size;
  }
  chunks.push(rows);
  const files = new Map();
  if (chunks.length === 1) {
    files.set('sitemap.xml', header('urlset') + chunks[0].join('') + '</urlset>\n');
    return files;
  }
  if (chunks.length > 50000) throw new Error('Sitemap index too large');
  const index = [];
  chunks.forEach((chunk, i) => {
    const name = `sitemap-${i + 1}.xml`;
    files.set(name, header('urlset') + chunk.join('') + '</urlset>\n');
    index.push(`<sitemap><loc>${xml(root + '/' + name)}</loc></sitemap>\n`);
  });
  const document = header('sitemapindex') + index.join('') + '</sitemapindex>\n';
  if (Buffer.byteLength(document) > 50 * 1024 * 1024) throw new Error('Sitemap index too large');
  files.set('sitemap.xml', document);
  return files;
}
