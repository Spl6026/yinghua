// 同一版本的發布資料不可用時，保留各模組的錯誤狀態，不展示原型或舊內容。
export function homeErrorView(shell) {
  const blocks = [
    ['museum', '館舍介紹', '/museum.html'],
    ['featured', '精選景點', '/explore.html'],
    ['films', '本週映演', '/cinema.html'],
    ['news', '最新活動與消息', '/events.html'],
  ];
  const body = `<main id="main-content" tabindex="-1"><section class="section-pad"><p class="eyebrow">CONTENT / TEMPORARILY UNAVAILABLE</p><h1>首頁暫時無法載入</h1><p role="alert">首頁內容暫時無法顯示，請稍後再試。</p><a class="button button-dark" href="/index.html">重新載入首頁 ↻</a></section>${blocks.map(([key, title, path]) => `<section class="section-pad" aria-labelledby="home-error-${key}"><h2 id="home-error-${key}">${title}</h2><p class="empty-results">${title}暫時無法載入，請稍後重試。</p><a class="text-link" href="${path}">查看${title} ↗</a></section>`).join('')}</main>`;
  return shell
    .replace(/<main\b[^>]*>[\s\S]*?<\/main>/, () => body)
    .replace(/<title>[\s\S]*?<\/title>/, '<title>首頁暫時無法載入｜映畫竹塹</title>')
    .replace(
      /<meta name="(robots|googlebot)"[^>]*>/g,
      (_, name) => `<meta name="${name}" content="noindex,nofollow">`
    )
    .replace(/<script\b[^>]*type="application\/ld\+json"[^>]*>[\s\S]*?<\/script>/g, '');
}
