import Alpine from 'alpinejs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { aquaPos } from './offline/aqua-pos.js';
import { AquaDB } from './offline/aqua-db.js';
import { AquaSync } from './offline/aqua-sync.js';
import { searchableSelect } from './searchable-select.js';

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
window.aquaSearchSelect = searchableSelect;

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab') {
        return;
    }
    const dialog = [...document.querySelectorAll('[role="dialog"][aria-modal="true"]')]
        .filter((element) => element.getClientRects().length).at(-1);
    if (!dialog) {
        return;
    }
    const controls = [...dialog.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled):not([type="hidden"]), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]')]
        .filter((element) => element.getClientRects().length);
    const first = controls[0];
    const last = controls.at(-1);
    if (!first) {
        return;
    }
    if (!dialog.contains(document.activeElement) || (event.shiftKey && document.activeElement === first)) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});

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
