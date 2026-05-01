showToast("You do not have permission to access this page.", "error", "Unauthorized"); setTimeout(() => history.back(), 2000);

document.addEventListener('DOMContentLoaded', function() {
        showToast('You do not have permission to access that page.', 'error', 'Unauthorized');
      });

window.PS_RT_PAGE = 'dashboard';
