// 只在首次繪製前選定已就緒的字型，之後不再切換，避免畫面閃動。
(() => {
  const root = document.documentElement;
  if (!document.fonts?.load || !window.requestAnimationFrame) return;
  let ready = false;
  Promise.all([
    document.fonts.load('400 16px "Noto Sans TC"', '映畫竹塹ABC'),
    document.fonts.load('500 16px "Noto Serif TC"', '映畫竹塹ABC'),
  ]).then(
    () => {
      ready = true;
    },
    () => {}
  );
  window.requestAnimationFrame(() => {
    if (ready) root.classList.add('fonts-ready');
  });
})();
