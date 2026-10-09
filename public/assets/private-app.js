'use strict';
(() => {
    const message = document.getElementById('rr-network-message');
    if (!message) return;
    const offline = () => {
        message.textContent = 'Nettleseren melder at du er frakoblet. Ikke lukk et ulagret manus.';
        message.hidden = false;
    };
    const online = () => {
        message.textContent = 'Nettleseren melder at nettet er tilbake. Kontroller at endringene blir lagret.';
        message.hidden = false;
    };
    window.addEventListener('offline', offline);
    window.addEventListener('online', online);
    if (navigator.onLine === false) offline();
    // No service worker, offline queue, localStorage or background publication.
})();
