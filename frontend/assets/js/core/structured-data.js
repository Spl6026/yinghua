import { isIndexablePublication } from './seo-resolver.js';

const text = value => (typeof value === 'string' ? value.trim() : '');

// [S E31/G31／SEO E13；E-34] 只取可見內容白名單；不傳整份DB或SEO覆寫作正文。
function contentEntity(path, item, canonical, context) {
  if (!item) return null;
  let entity;
  if (path === '/museum.html' || path.startsWith('/places/')) {
    entity = {
      '@type':
        path === '/museum.html'
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
  } else if (path.startsWith('/films/')) {
    entity = { '@type': 'Movie', name: text(item.title) };
    if (text(item.synopsis)) entity.description = text(item.synopsis);
    if (text(item.genre)) entity.genre = text(item.genre);
  } else if (path.startsWith('/news/')) {
    const event = item.type === 'event' && validTimestamp(item.eventStartAt) && text(item.eventLocation);
    entity = { '@type': event ? 'Event' : 'Article', name: text(item.title) };
    if (text(item.summary)) entity.description = text(item.summary);
    if (event) {
      entity.startDate = validTimestamp(item.eventStartAt);
      entity.location = { '@type': 'Place', name: text(item.eventLocation) };
      if (validTimestamp(item.eventEndAt)) entity.endDate = validTimestamp(item.eventEndAt);
    } else {
      entity.headline = text(item.title);
      if (validTimestamp(item.publishAt)) entity.datePublished = validTimestamp(item.publishAt);
    }
  } else if (path.startsWith('/offers/')) {
    entity = { '@type': 'Offer', name: text(item.title) };
    if (text(item.description) || text(item.discount))
      entity.description = text(item.description) || text(item.discount);
    if (text(item.merchant?.name))
      entity.seller = { '@type': 'Organization', name: text(item.merchant.name) };
    // [U-01／E-34] 日期欄為台北日曆日，validTo包含當日，不造價格／庫存。
    if (validDay(item.validFrom)) entity.validFrom = item.validFrom + 'T00:00:00+08:00';
    if (validDay(item.validTo)) entity.validThrough = item.validTo + 'T23:59:59+08:00';
  } else return null;
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

export function jsonLdForHtml(value) {
  return JSON.stringify(value)
    .replaceAll('<', '\\u003c')
    .replaceAll('>', '\\u003e')
    .replaceAll('&', '\\u0026')
    .replaceAll('\u2028', '\\u2028')
    .replaceAll('\u2029', '\\u2029');
}
