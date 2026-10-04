import Alpine from 'alpinejs';
import { aquaPos } from './offline/aqua-pos.js';
import { AquaDB } from './offline/aqua-db.js';
import { AquaSync } from './offline/aqua-sync.js';

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
