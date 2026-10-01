// "Notifications on this device": subscribe this browser to Web Push.
(function () {
    const card = document.getElementById('push-card');
    if (!card) return;

    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const button = card.querySelector('[data-push-toggle]');
    const testButton = card.querySelector('[data-push-test]');
    const status = card.querySelector('[data-push-status]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };

    if (!supported) {
        status.textContent = 'This browser does not support notifications. On iPhone, add the app to your home screen first.';
        button.hidden = true;
        return;
    }

    const toKey = (base64) => {
        const padded = (base64 + '='.repeat((4 - base64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
    };

    async function current() {
        const registration = await navigator.serviceWorker.ready;
        return registration.pushManager.getSubscription();
    }

    async function render() {
        const subscription = await current();
        const on = !!subscription && Notification.permission === 'granted';
        status.textContent = Notification.permission === 'denied'
            ? 'Notifications are blocked for this site. Allow them in your browser settings.'
            : (on ? 'On for this device.' : 'Off for this device.');
        button.textContent = on ? 'Turn off on this device' : 'Turn on for this device';
        button.dataset.on = on ? '1' : '';
        testButton.hidden = !on;
    }

    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const registration = await navigator.serviceWorker.ready;
            if (button.dataset.on) {
                const subscription = await current();
                if (subscription) {
                    await fetch(card.dataset.destroyUrl, { method: 'DELETE', headers, body: JSON.stringify({ endpoint: subscription.endpoint }) });
                    await subscription.unsubscribe();
                }
            } else {
                if (await Notification.requestPermission() !== 'granted') return;
                const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toKey(card.dataset.key) });
                const json = subscription.toJSON();
                json.contentEncoding = (PushManager.supportedContentEncodings || ['aes128gcm'])[0];
                await fetch(card.dataset.storeUrl, { method: 'POST', headers, body: JSON.stringify(json) });
            }
        } finally {
            button.disabled = false;
            render();
        }
    });

    testButton.addEventListener('click', async () => {
        await fetch(card.dataset.testUrl, { method: 'POST', headers });
        testButton.textContent = 'Sent — check your device';
    });

    render();
})();
