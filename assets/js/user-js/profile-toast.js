document.addEventListener('DOMContentLoaded', () => {
  if (window.__PS_PROFILE__.toastSuccess) showToast(window.__PS_PROFILE__.toastSuccess, 'success');
  if (window.__PS_PROFILE__.toastError)   showToast(window.__PS_PROFILE__.toastError, 'error');
});
