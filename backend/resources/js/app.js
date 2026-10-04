import Alpine from 'alpinejs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { aquaPos } from './offline/aqua-pos.js';
import { AquaDB } from './offline/aqua-db.js';
import { AquaSync } from './offline/aqua-sync.js';

if (import.meta.env.VITE_REVERB_APP_KEY) {
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb', key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

window.AquaDB = AquaDB;
window.AquaSync = AquaSync;
window.aquaPos = aquaPos;

if (typeof window.Alpine === 'undefined') {
    window.Alpine = Alpine;
    Alpine.data('aquaPos', aquaPos);
    Alpine.start();
} else {
    window.Alpine.data('aquaPos', aquaPos);
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('aquaPos', aquaPos);
});
