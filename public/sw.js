self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : { demandId: null };
    event.waitUntil(
        self.registration.showNotification('Missao Publica', {
            body: 'Nova atualizacao de missao. Abra para consultar os detalhes.',
            data: { url: payload.demandId ? `/?mission=${payload.demandId}` : '/' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data.url || '/'));
});
