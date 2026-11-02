export function confirmAction(title, message, trigger = document.activeElement) {
  const dialog = document.querySelector('#confirm-dialog');
  document.querySelector('#confirm-title').textContent = title;
  document.querySelector('#confirm-message').textContent = message;
  return new Promise(resolve => {
    // 明確處理焦點在頭尾兩端的循環，避免原生 Tab 把焦點移到瀏覽器工具列。
    const cycleFocus = event => {
      if (event.key !== 'Tab') return;
      const buttons = [...dialog.querySelectorAll('button:not([disabled])')];
      const first = buttons[0],
        last = buttons.at(-1);
      const target =
        event.shiftKey && document.activeElement === first
          ? last
          : !event.shiftKey && document.activeElement === last
            ? first
            : null;
      if (target) {
        event.preventDefault();
        target.focus();
      }
    };
    dialog.addEventListener('keydown', cycleFocus);
    dialog.addEventListener(
      'close',
      () => {
        dialog.removeEventListener('keydown', cycleFocus);
        resolve(dialog.returnValue === 'confirm');
        trigger?.focus();
      },
      { once: true }
    );
    dialog.returnValue = 'cancel';
    dialog.showModal();
  });
}
