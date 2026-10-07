/**
 * Notification system for the world map.
 */
function showNotification(message, type) {
    type = type || 'info';

    const notification = document.createElement('div');
    notification.className = 'fleet-notification fleet-notification-' + type;
    notification.innerHTML = message;

    document.body.appendChild(notification);

    setTimeout(function () {
        notification.classList.add('is-leaving');
        setTimeout(function () { notification.remove(); }, 300);
    }, 3000);
}
