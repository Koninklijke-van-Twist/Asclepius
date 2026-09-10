self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = {};
    }

    var title = typeof data.title === 'string' && data.title !== '' ? data.title : 'Asclepius';
    var body = typeof data.body === 'string' ? data.body : '';
    var openUrl = typeof data.open_url === 'string' && data.open_url !== '' ? data.open_url : 'index.php';
    var tag = typeof data.tag === 'string' && data.tag !== '' ? data.tag : 'asclepius-ticket';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: body,
            tag: tag,
            renotify: true,
            icon: 'logo-website.png',
            badge: 'favicon-32x32.png',
            data: {
                open_url: openUrl
            }
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var targetUrl = (event.notification.data && event.notification.data.open_url) || 'index.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            for (var i = 0; i < windowClients.length; i++) {
                var client = windowClients[i];
                if ('focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }

            return Promise.resolve();
        })
    );
});
