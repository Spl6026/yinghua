// [U-22／E-39] Only choose a ready font before the first paint; never swap later.
(() => {
  const root = document.documentElement;
  if (!document.fonts?.load || !window.requestAnimationFrame) return;
  let ready = false;
  Promise.all([
    document.fonts.load('400 16px "Noto Sans TC"', '映畫竹塹ABC'),
    document.fonts.load('500 16px "Noto Serif TC"', '映畫竹塹ABC'),
  ]).then(() => { ready = true; }, () => {});
  window.requestAnimationFrame(() => {
    if (ready) root.classList.add('fonts-ready');
  });
})();
