// 公開頁共用的頁面外框：會員導覽、捲動淡入、首頁按鈕導覽、主視覺視差與收藏／加入行程按鈕。
// 景點、電影、消息、優惠與路線的內容都由發布流程寫進 HTML，或由各頁模組呼叫 API 載入。
import { memberNavigation } from './core/session.js';
import { mountMemberActions } from './core/member-actions.js';

void memberNavigation();

// 上一篇／下一篇的縮圖是靜態發布的 HTML；圖片載入失敗時和主圖一樣換成替代圖，不留下破圖。
const placeholder = '/assets/image-placeholder.svg';
function useImageFallback(image) {
  if (!image.closest?.('.article-neighbor') || image.dataset.fallback) return;
  image.dataset.fallback = 'true';
  image.src = placeholder;
}
document.addEventListener(
  'error',
  event => {
    if (event.target instanceof HTMLImageElement) useImageFallback(event.target);
  },
  true
);
document.querySelectorAll('.article-neighbor img').forEach(image => {
  if (image.complete && !image.naturalWidth) useImageFallback(image);
});

// 進入畫面時才淡入；減少動態效果的使用者或不支援的瀏覽器直接顯示。
function reveal() {
  const nodes = document.querySelectorAll('.page.active .reveal,.page.active .image-reveal');
  if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) {
    nodes.forEach(node => node.classList.add('in-view'));
    return;
  }
  const observer = new IntersectionObserver(
    entries =>
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in-view');
          observer.unobserve(entry.target);
        }
      }),
    { threshold: 0.12 }
  );
  nodes.forEach(node => observer.observe(node));
}
reveal();

// 原型首頁用 <button data-page> 做區塊導覽，保留原本的按鈕樣式，點擊後換成一般頁面導覽。
const pageLinks = {
  home: '/index.html',
  explore: '/explore.html',
  cinema: '/cinema.html',
  events: '/events.html',
  planner: '/planner.html',
};
document.addEventListener('click', event => {
  const button = event.target.closest('button[data-page]');
  const href = button && pageLinks[button.dataset.page];
  if (href) {
    event.preventDefault();
    location.assign(href);
  }
});

void mountMemberActions();

const backdrop = document.querySelector('.hero-backdrop');
if (backdrop)
  window.addEventListener(
    'scroll',
    () => {
      if (innerWidth >= 761 && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
        backdrop.style.transform = `scale(1.03) translateY(${Math.min(scrollY * 0.11, 75)}px)`;
      }
    },
    { passive: true }
  );
