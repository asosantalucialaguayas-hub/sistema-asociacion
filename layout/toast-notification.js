// Toast Notification System
function showToast(message, type = 'success', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('toast-hide');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 500);
    }, duration);
}

// Optional: Listen for custom events
document.addEventListener('show-toast', function(e) {
    showToast(e.detail.message, e.detail.type, e.detail.duration);
});
