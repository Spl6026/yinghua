export function confirmAction(title, message, trigger = document.activeElement) {
  const dialog = document.querySelector('#confirm-dialog');
  document.querySelector('#confirm-title').textContent = title;
  document.querySelector('#confirm-message').textContent = message;
  return new Promise(resolve => {
    dialog.addEventListener('close', () => {
      resolve(dialog.returnValue === 'confirm');
      trigger?.focus();
    }, { once: true });
    dialog.returnValue = 'cancel';
    dialog.showModal();
  });
}
