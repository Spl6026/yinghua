import { escapeHtml } from './content-view.js';
import { taipeiEnglish, taipeiFormatter, taipeiParts } from './taipei-time.js';
// Node 發布流程與瀏覽器共用原型的電影版面渲染。
export function screeningDate(stamp) {
  const parts = taipeiParts(stamp, { time: false });
  return {
    date: `${parts.month}.${parts.day}`,
    full: `${parts.year}-${parts.month}-${parts.day}`,
    day: taipeiEnglish(stamp, { weekday: 'short' }),
    month: taipeiEnglish(stamp, { month: 'short' }),
    time: taipeiFormatter('en-US', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(
      new Date(stamp)
    ),
  };
}
export function screeningPeriodLabel(period) {
  return `${screeningDate(period.from).date} — ${screeningDate(new Date(Date.parse(period.to) - 1).toISOString()).date}`;
}
const cover = (film, className) => {
  const url = film.image || '/assets/image-placeholder.svg';
  // 原型首頁與片單的海報各有不同的遮色層，詳情頁則維持原圖。
  const tint =
    className === 'film-art'
      ? 'linear-gradient(0deg,rgba(16,24,19,.28),rgba(16,24,19,.04)),'
      : className === 'cinema-poster'
        ? 'linear-gradient(0deg,rgba(18,24,20,.08),rgba(18,24,20,.08)),'
        : '';
  return `<div class="${className}" data-image-url="${escapeHtml(url)}" role="img" aria-label="${escapeHtml(film.imageAlt || film.title + '電影海報')}" style="background-image:${tint}url(${escapeHtml(JSON.stringify(url))})"></div>`;
};
export function weeklyFilms(films, period) {
  if (!period) return [];
  return films
    .map(f => ({
      ...f,
      screenings: f.screenings.filter(s => s.startsAt >= period.from && s.startsAt < period.to),
    }))
    .filter(f => f.screenings.length)
    .sort(
      (a, b) => a.screenings[0].startsAt.localeCompare(b.screenings[0].startsAt) || a.id.localeCompare(b.id)
    );
}
export function filmRows(films) {
  const rows = films
    .flatMap(film => film.screenings.map(screening => ({ film, screening })))
    .sort(
      (a, b) =>
        a.screening.startsAt.localeCompare(b.screening.startsAt) ||
        a.screening.id.localeCompare(b.screening.id)
    );
  return (
    rows
      .map(({ film, screening }) => {
        const d = screeningDate(screening.startsAt);
        return `<article class="cinema-row">${cover(film, 'cinema-poster')}<div class="cinema-date">${escapeHtml(d.date)}<small>${escapeHtml(d.day)} / ${escapeHtml(d.month)}</small></div><div class="cinema-info"><span>${escapeHtml(film.genre || '城市放映')}</span><h3>${escapeHtml(film.title)}</h3><p>${escapeHtml(film.synopsis)}</p></div><div class="cinema-time">◷ ${escapeHtml(d.time)}</div><a class="button button-outline" href="${escapeHtml(film.url)}">場次介紹 ↗</a></article>`;
      })
      .join('') || '<p class="empty-results">目前沒有本週映演場次。</p>'
  );
}
export function filmCards(films, total = films.length) {
  return (
    films
      .slice(0, 3)
      .map((film, index) => {
        const d = screeningDate(film.screenings[0].startsAt);
        return `<article class="film-card${index === 0 ? ' film-featured' : ''} reveal in-view">${cover(film, 'film-art')}<div class="film-meta"><span>${index === 0 ? '本週主打　・　' : ''}${escapeHtml(film.genre || '城市放映')}</span><span>${String(index + 1).padStart(2, '0')} / ${String(total).padStart(2, '0')}</span></div><h3>${escapeHtml(film.title)}</h3><p>${escapeHtml(film.synopsis)}</p><div class="film-footer"><span>${escapeHtml(d.date)}　${escapeHtml(d.time)}</span><a class="round-arrow small" aria-label="開啟${escapeHtml(film.title)}詳情頁" href="${escapeHtml(film.url)}">↗</a></div></article>`;
      })
      .join('') || '<p class="empty-results">目前沒有本週映演場次。</p>'
  );
}
export function filmDetail(film) {
  const screenings = film.screenings
    .map(s => {
      const d = screeningDate(s.startsAt);
      const ticket = s.effectiveTicketUrl;
      return `<div>${escapeHtml(film.genre || '城市放映')}・<time datetime="${escapeHtml(s.startsAt)}">${escapeHtml(d.full)}　${escapeHtml(d.time)}</time>${ticket ? `　<a class="text-link" href="${escapeHtml(ticket)}" target="_blank" rel="noopener noreferrer">前往售票 ↗</a>` : ''}</div>`;
    })
    .join('');
  // 沿用原型的公告段落與類型／場次資訊列；多個場次逐列呈現實際資料。
  return `<section class="section-pad detail-page">${cover(film, 'detail-cover')}<div class="detail-body"><p class="eyebrow">IMAGE MUSEUM / SCREENING</p><h1>${escapeHtml(film.title)}</h1><p class="detail-lead">${escapeHtml(film.synopsis).replaceAll('\n', '<br>')}</p><p>實際片單、票價與放映時間請以新竹市影像博物館公告為準。</p><div class="detail-meta film-screenings" role="group" aria-label="放映場次">${screenings || `<div>${escapeHtml(film.genre || '城市放映')}・目前尚未安排場次。</div>`}</div>${film.ticketUrl && !screenings ? `<p><a class="text-link" href="${escapeHtml(film.ticketUrl)}" target="_blank" rel="noopener noreferrer">前往售票 ↗</a></p>` : ''}<div class="detail-actions"><a class="button button-dark" href="/cinema.html">回到本週映演 ↗</a><a class="text-link" href="/planner.html">安排進旅行 <span>→</span></a></div></div></section>`;
}
