// [S：SEO D5–D9／E16；E-28] 純資料解析，供建置、發布與管理預覽共用。
export const seoDefaults = {
  siteName: '映畫竹塹',
  titleSuffix: '｜映畫竹塹',
  description: '從新竹市影像博物館出發，探索電影、美食、活動與城市旅行。',
  keywords: '新竹旅遊,新竹景點,新竹美食,映畫竹塹',
  canonicalOrigin: '',
  robots: 'index,follow',
  ogImageUrl: '',
  twitterCard: 'summary_large_image',
  twitterSite: '',
  googleVerification: '',
  bingVerification: '',
};

export function canonicalPath(value) {
  const url = new URL(value, 'https://seo.invalid');
  let path = url.pathname === '/' ? '/index.html' : url.pathname;
  const query = new URLSearchParams();
  if (path === '/events.html' && ['event', 'city'].includes(url.searchParams.get('type')))
    query.set('type', url.searchParams.get('type'));
  const page = url.searchParams.get('page');
  if (
    ['/events.html', '/activities.html', '/city-news.html', '/offers.html'].includes(path) &&
    /^[1-9][0-9]{0,4}$/.test(page || '') &&
    Number(page) > 1
  )
    query.set('page', String(Number(page)));
  return path + (query.size ? '?' + query : '');
}

// [S SEO E7／G33；E-34] 發布與爬蟲索引共用保護邊界，不靠robots授權。
export function isProtectedSeoPage(path, catalog = []) {
  const pathname = new URL(path, 'https://seo.invalid').pathname;
  return (
    pathname.startsWith('/admin/') ||
    Boolean(catalog.find(page => page.path === pathname)?.protected) ||
    /^\/(?:login|register|forgot-password|reset-password|change-password|planner|favorites|search|404)\.html$/.test(
      pathname
    )
  );
}

export function isIndexablePublication(context, meta) {
  const path = new URL(context.path, 'https://seo.invalid').pathname;
  return (
    context.published === true &&
    !context.unavailable &&
    !isProtectedSeoPage(path, context.catalog) &&
    !/^\/(?:api|_content_pages)(?:\/|$)/.test(path) &&
    !/(?:^|[,\s])(?:noindex|none)(?:$|[,\s])/i.test(meta.robots)
  );
}

export function resolveSeo({
  path,
  title = '',
  description = '',
  item,
  seo = {},
  catalog = [],
  origin = 'http://localhost:8080',
  publicImages = [],
}) {
  const pathname = new URL(path, 'https://seo.invalid').pathname;
  const preset = catalog.find(page => page.path === pathname);
  const protectedPage = isProtectedSeoPage(path, catalog);
  const type =
    pathname === '/museum.html'
      ? 'attraction'
      : pathname.startsWith('/places/')
        ? 'attraction'
        : pathname.startsWith('/films/')
          ? 'film'
          : pathname.startsWith('/news/')
            ? 'news'
            : pathname.startsWith('/offers/')
              ? 'offer'
              : null;
  const pattern = type
    ? '/' + { attraction: 'places', film: 'films', news: 'news', offer: 'offers' }[type] + '/*.html'
    : null;
  const overrides = Array.isArray(seo.overrides) ? seo.overrides : [];
  const specific =
    type && item?.id ? overrides.find(row => row.target?.type === type && row.target.id === item.id) : null;
  const page =
    overrides.find(row => row.target?.type === 'page' && row.target.path === pathname) ||
    overrides.find(row => row.target?.type === 'page' && row.target.path === pattern);
  const defaults = { ...seoDefaults, ...seo.defaults };
  const nonempty = value => (typeof value === 'string' && value.trim() ? value : null);
  const chosenTitle = nonempty(specific?.title) || nonempty(type && item?.seoTitle) || nonempty(page?.title);
  let finalTitle = chosenTitle;
  if (!finalTitle) {
    const base = (title || preset?.title || item?.name || item?.title || '城市指南').replace(
      /｜映畫竹塹$/,
      ''
    );
    finalTitle =
      pathname.startsWith('/admin/') && nonempty(title)
        ? title
        : base + (defaults.titleSuffix && !base.endsWith(defaults.titleSuffix) ? defaults.titleSuffix : '');
  }
  const normalized = canonicalPath(path);
  const pageNumber = new URL(normalized, 'https://seo.invalid').searchParams.get('page');
  if (pageNumber && !finalTitle.includes('第 ' + pageNumber + ' 頁'))
    finalTitle += '・第 ' + pageNumber + ' 頁';
  const ownDescription =
    nonempty(specific?.description) || nonempty(type && item?.seoDescription) || nonempty(page?.description);
  let finalDescription =
    ownDescription ||
    nonempty(type ? description : preset?.description) ||
    nonempty(description) ||
    nonempty(defaults.description) ||
    `${finalTitle}的公開資訊。`;
  if (pageNumber && !finalDescription.includes('第 ' + pageNumber + ' 頁'))
    finalDescription += '（第 ' + pageNumber + ' 頁）';
  const base = defaults.canonicalOrigin || origin;
  const canonical = base.replace(/\/+$/, '') + normalized;
  const image =
    nonempty(specific?.ogImageUrl) ||
    nonempty(page?.ogImageUrl) ||
    (type ? item?.image : null) ||
    nonempty(defaults.ogImageUrl) ||
    item?.image ||
    '/assets/image-placeholder.svg';
  let imageUrl;
  try {
    const parsed = new URL(image, base);
    if (!['https:', 'http:'].includes(parsed.protocol) || parsed.username || parsed.password)
      throw new Error('Invalid image');
    const selfHosts = [new URL(base).hostname, new URL(origin).hostname];
    if (
      selfHosts.includes(parsed.hostname) &&
      parsed.pathname.startsWith('/media/') &&
      !publicImages.includes(parsed.pathname)
    )
      throw new Error('Unpublished media');
    imageUrl = parsed.href;
  } catch {
    imageUrl = base.replace(/\/+$/, '') + '/assets/image-placeholder.svg';
  }
  const keywords = specific?.keywords ?? page?.keywords ?? defaults.keywords;
  return {
    title: finalTitle,
    description: finalDescription,
    keywords,
    robots: protectedPage
      ? pathname.startsWith('/admin/')
        ? 'noindex,nofollow'
        : 'noindex,follow'
      : (specific?.robots ?? page?.robots ?? defaults.robots),
    canonical,
    imageUrl,
    siteName: defaults.siteName || seoDefaults.siteName,
    type: type ? 'article' : 'website',
    locale: 'zh_TW',
    twitterCard: defaults.twitterCard,
    twitterSite: defaults.twitterSite,
    googleVerification: defaults.googleVerification,
    bingVerification: defaults.bingVerification,
    source: {
      title: nonempty(specific?.title)
        ? '單筆 SEO 覆寫'
        : nonempty(type && item?.seoTitle)
          ? '內容編輯頁 SEO'
          : nonempty(page?.title)
            ? '頁面／範本覆寫'
            : '頁面主題＋全站後綴',
      description: nonempty(specific?.description)
        ? '單筆 SEO 覆寫'
        : nonempty(type && item?.seoDescription)
          ? '內容編輯頁 SEO'
          : nonempty(page?.description)
            ? '頁面／範本覆寫'
            : '頁面內容／預設',
    },
  };
}
