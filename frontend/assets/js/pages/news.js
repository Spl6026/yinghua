import { listNews } from '../api/news-api.js';
import { newsCards, newsFeatured, newsListTitle, newsListDescription } from '../core/news-view.js';
import { initializePublicList } from '../core/public-list-controller.js';
import { fallbackContentImages } from '../core/content-view.js';

initializePublicList({
  stateSelector: '#public-news-state',
  listSelector: '#news-list',
  loadData: listNews,
  renderItems: newsCards,
  filterSelector: '.public-content-filter',
  renderFeatured: data => {
    const node = document.querySelector('.news-featured-lead');
    if (node) node.innerHTML = newsFeatured(data.featured);
  },
  titleFor: (data, type) => newsListTitle(location.pathname, type, data.page),
  descriptionFor: (data, type) => newsListDescription(location.pathname, type, data.page),
  typeFromUrl: () =>
    location.pathname === '/activities.html'
      ? 'event'
      : location.pathname === '/city-news.html'
        ? 'city'
        : new URLSearchParams(location.search).get('type') || '',
});
fallbackContentImages();
