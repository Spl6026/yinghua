import { isIndexablePublication } from './seo-resolver.js';
import { MUSEUM_PATH, contentKindOfPath } from './content-kinds.js';

const text = value => (typeof value === 'string' ? value.trim() : '');

// 各類內容的 Schema.org 實體（Strategy）：只從白名單取用頁面上可見的內容，
// 不把整份資料庫內容或 SEO 覆寫值當成正文。新增內容類型時在這裡補一個建構函式。
const entityBuilders = {
  attraction(item, path) {
    const entity = {
      '@type':
        path === MUSEUM_PATH
          ? 'Museum'
          : ['美食', '在地店家'].includes(item.category)
            ? 'LocalBusiness'
            : 'TouristAttraction',
      name: text(item.name),
    };
    if (text(item.description) || text(item.summary))
      entity.description = text(item.description) || text(item.summary);
    if (text(item.address)) entity.address = text(item.address);
    if (text(item.phone)) entity.telephone = text(item.phone);
    return entity;
  },
  film(item) {
    const entity = { '@type': 'Movie', name: text(item.title) };
    if (text(item.synopsis)) entity.description = text(item.synopsis);
    if (text(item.genre)) entity.genre = text(item.genre);
    return entity;
  },
  news(item) {
    const event = item.type === 'event' && validTimestamp(item.eventStartAt) && text(item.eventLocation);
    const entity = { '@type': event ? 'Event' : 'Article', name: text(item.title) };
    if (text(item.summary)) entity.description = text(item.summary);
    if (event) {
      entity.startDate = validTimestamp(item.eventStartAt);
      entity.location = { '@type': 'Place', name: text(item.eventLocation) };
      if (validTimestamp(item.eventEndAt)) entity.endDate = validTimestamp(item.eventEndAt);
    } else {
      entity.headline = text(item.title);
      if (validTimestamp(item.publishAt)) entity.datePublished = validTimestamp(item.publishAt);
    }
    return entity;
  },
  offer(item) {
    const entity = { '@type': 'Offer', name: text(item.title) };
    if (text(item.description) || text(item.discount))
      entity.description = text(item.description) || text(item.discount);
    if (text(item.merchant?.name))
      entity.seller = { '@type': 'Organization', name: text(item.merchant.name) };
    // 日期欄位是台北的日曆日，validTo 包含當天；不自行產生價格或庫存資訊。
    if (validDay(item.validFrom)) entity.validFrom = item.validFrom + 'T00:00:00+08:00';
    if (validDay(item.validTo)) entity.validThrough = item.validTo + 'T23:59:59+08:00';
    return entity;
  },
};

function contentEntity(path, item, canonical, context) {
  if (!item) return null;
  const build = entityBuilders[contentKindOfPath(path)?.type];
  if (!build) return null;
  const entity = build(item, path);
  if (!entity.name) return null;
  entity['@id'] = canonical + '#content';
  entity.url = canonical;
  const image = visibleImage(item.image, context);
  if (image) entity.image = image;
  return entity;
}

function validDay(value) {
  return (
    /^\d{4}-\d{2}-\d{2}$/.test(value || '') &&
    !Number.isNaN(Date.parse(value)) &&
    new Date(value).toISOString().slice(0, 10) === value
  );
}

export function validTimestamp(value) {
  if (
    typeof value !== 'string' ||
    !/^\d{4}-\d{2}-\d{2}[T ](?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9](?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/.test(
      value
    ) ||
    !validDay(value.slice(0, 10))
  )
    return null;
  const normalized = value.replace(' ', 'T') + (/(?:Z|[+-]\d{2}:\d{2})$/.test(value) ? '' : 'Z');
  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

function visibleImage(value, context) {
  if (!text(value)) return null;
  try {
    const base = context.seo?.defaults?.canonicalOrigin || context.origin;
    const url = new URL(value, base);
    if (
      !['https:', 'http:'].includes(url.protocol) ||
      url.username ||
      url.password ||
      url.pathname === '/assets/image-placeholder.svg'
    )
      return null;
    const local = [new URL(base).hostname, new URL(context.origin).hostname].includes(url.hostname);
    const publishedImages = context.publicImages || [];
    if (
      local &&
      url.pathname.startsWith('/media/') &&
      !publishedImages.includes(value) &&
      !publishedImages.includes(url.pathname)
    )
      return null;
    return url.href;
  } catch {
    return null;
  }
}

export function structuredDataGraph(context, meta) {
  if (!isIndexablePublication(context, meta)) return null;
  const canonical = new URL(meta.canonical),
    origin = canonical.origin;
  const website = {
    '@type': 'WebSite',
    '@id': origin + '/#website',
    name: meta.siteName,
    url: origin + '/index.html',
    inLanguage: 'zh-Hant-TW',
  };
  const organization = {
    '@type': 'Organization',
    '@id': origin + '/#organization',
    name: meta.siteName,
    url: origin + '/index.html',
  };
  const webpage = {
    '@type': 'WebPage',
    '@id': meta.canonical + '#webpage',
    url: meta.canonical,
    name: meta.title,
    inLanguage: 'zh-Hant-TW',
    isPartOf: { '@id': website['@id'] },
  };
  website.publisher = { '@id': organization['@id'] };
  const entity = contentEntity(canonical.pathname, context.item, meta.canonical, context);
  if (entity) webpage.mainEntity = { '@id': entity['@id'] };
  return {
    '@context': 'https://schema.org',
    '@graph': [website, organization, webpage, ...(entity ? [entity] : [])],
  };
}
