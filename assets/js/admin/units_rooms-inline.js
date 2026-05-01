window._psToastReady = true;
    document.addEventListener("DOMContentLoaded", function() {
        showToast("You do not have permission to access this page.", "error", "Unauthorized");
    });
    setTimeout(() => history.back(), 2000);
