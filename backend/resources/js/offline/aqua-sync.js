/**
 * AquaOptom CRM — Offline PWA Synchronization Engine
 * Handles automatic sync, push queue, atomic ACK processing,
 * cursor delta pull, pending overlay, multi-tab mutex locking and recovery.
 */

import { AquaDB } from './aqua-db.js';

export class AquaSync {
    constructor(db = null, options = {}) {
        this.db = db || new AquaDB();
        this.apiBase = options.apiBase || '/api';
        this.tabId = options.tabId || `tab_${Math.random().toString(36).substring(2, 9)}`;
        this.isSyncing = false;
        this.lastSyncTime = null;
        this.broadcastChannel = null;

        if (typeof window !== 'undefined' && window.BroadcastChannel) {
            this.broadcastChannel = new BroadcastChannel('aqua_pos_channel');
        }
    }

    /**
     * 1. Server bilan aloqani (Health check) tekshirish
     */
    async checkHealth() {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 6000);

            const res = await fetch(`${this.apiBase}/health`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                signal: controller.signal
            });
            clearTimeout(timeoutId);

            if (res.ok) {
                const data = await res.json();
                return { isOnline: true, serverTime: data.server_time, data };
            }
            return { isOnline: false, status: res.status };
        } catch (err) {
            return { isOnline: false, error: err.message };
        }
    }

    /**
     * 2. To'liq Sinxronizatsiya sikli: Push + Pull + Multi-tab Mutex
     */
    async syncNow(triggerSource = 'manual') {
        if (this.isSyncing) {
            return { status: 'IN_PROGRESS', message: "Sinxronlash allaqachon bajarilmoqda." };
        }

        // A. Multi-tab lokal lock olish
        const lockAcquired = await this.db.acquireSyncLock(this.tabId, 30000);
        if (!lockAcquired) {
            return { status: 'LOCKED', message: "Boshqa tab yoki fon jarayoni sinxronlamoqda." };
        }

        this.isSyncing = true;
        try {
            // B. Server mavjudligini tekshirish
            const health = await this.checkHealth();
            if (!health.isOnline) {
                return { status: 'OFFLINE', message: "Server bilan aloqa yo'q. Sinxronlash kechiktirildi." };
            }

            // C. 1-bosqich: Pending amallarni Push qilish
            const pushResult = await this.pushPendingQueue();

            // D. 2-bosqich: Kursor bo'yicha yangi o'zgarishlarni Pull qilish
            const pullResult = await this.pullServerChanges();

            // E. Oxirgi sinxronlash vaqtini qayd etish
            this.lastSyncTime = new Date().toISOString();
            await this.db.put('meta', { key: 'last_successful_sync', value: this.lastSyncTime });

            // F. Boshqa tablarga xabar yuborish
            if (this.broadcastChannel) {
                this.broadcastChannel.postMessage({
                    type: 'SYNC_COMPLETED',
                    sourceTab: this.tabId,
                    pushResult,
                    pullResult,
                    timestamp: this.lastSyncTime
                });
            }

            return {
                status: 'SUCCESS',
                pushResult,
                pullResult,
                lastSyncTime: this.lastSyncTime
            };

        } catch (err) {
            console.error("Sync siklida xatolik:", err);
            return { status: 'ERROR', message: err.message };
        } finally {
            this.isSyncing = false;
            await this.db.releaseSyncLock(this.tabId);
        }
    }

    /**
     * 3. Pending navbatni serverga yuborish (Batch Push)
     */
    async pushPendingQueue() {
        const outbox = await this.db.getAll('sync_outbox');
        const pendingItems = outbox
            .filter(item => item.status === 'PENDING')
            .sort((a, b) => {
                // CREATE_CUSTOMER birinchi navbatda, keyin CREATE_SALE, keyin VOID_SALE
                const order = { 'CREATE_CUSTOMER': 1, 'CREATE_SALE': 2, 'VOID_SALE': 3, 'CUSTOMER_PAYMENT': 4 };
                const orderA = order[a.type] || 99;
                const orderB = order[b.type] || 99;
                if (orderA !== orderB) return orderA - orderB;
                return (new Date(a.created_at)).getTime() - (new Date(b.created_at)).getTime();
            });

        if (pendingItems.length === 0) {
            return { pushedCount: 0, appliedCount: 0, message: "Yuboriladigan navbat bo'sh." };
        }

        const lease = await this.db.get('device_lease', 'current');
        const deviceUuid = lease?.device_uuid;
        const leaseToken = lease?.token || lease?.lease_token;
        const authToken = lease?.api_token || lease?.token;

        if (!deviceUuid) {
            throw new Error("Qurilma identifikatori (device_uuid) topilmadi. Qurilmani qayta ro'yxatdan o'tkazing.");
        }

        // Operatsiyalar massivi
        const operationsPayload = pendingItems.map(item => ({
            operation_id: item.operation_id,
            type: item.type,
            device_created_at: item.device_created_at || item.created_at,
            payload: item.payload
        }));

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Device-UUID': deviceUuid
        };
        const csrfToken = globalThis.document?.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
        if (leaseToken) headers['X-Lease-Token'] = leaseToken;
        if (authToken) headers['Authorization'] = `Bearer ${authToken}`;

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 20000); // 20s timeout

        let response;
        try {
            response = await fetch(`${this.apiBase}/sync/push`, {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    device_uuid: deviceUuid,
                    lease_token: leaseToken,
                    operations: operationsPayload
                }),
                signal: controller.signal
            });
        } catch (fetchErr) {
            clearTimeout(timeoutId);
            // Tarmoq uzilishi yoki timeout:
            // "Retry original operation_id/payload bilan; timeoutda operation status yoki ayni request"
            // Xatolikda outbox dagi PENDING amallarni o'chirmaymiz yoki failed qilmaymiz;
            // Keyingi urinishda aynan shu operation_id va kanonik payload qayta yuboriladi!
            throw new Error("Tarmoq vaqti tugadi yoki aloqa uzildi. Operatsiyalar xavfsiz saqlanib qoldi.");
        }
        clearTimeout(timeoutId);

        if (!response.ok) {
            const errJson = await response.json().catch(() => ({}));
            throw new Error(errJson.message || `Server xatosi (#${response.status})`);
        }

        const pushData = await response.json();
        const results = pushData.results || [];

        // ACK ni lokal atomik yozish (Atomik tranzaksiya)
        const stats = await this.db.applyPushResults(results);

        return {
            pushedCount: operationsPayload.length,
            stats,
            results
        };
    }

    /**
     * 4. Serverdagi o'zgarishlarni tortib olish (Cursor Delta Pull)
     */
    async pullServerChanges() {
        const lastCursorObj = await this.db.get('meta', 'last_cursor');
        const cursor = lastCursorObj ? (parseInt(lastCursorObj.value) || 0) : 0;

        const lease = await this.db.get('device_lease', 'current');
        const authToken = lease?.api_token || lease?.token;

        const headers = { 'Accept': 'application/json' };
        if (authToken) headers['Authorization'] = `Bearer ${authToken}`;

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);

        let res;
        try {
            res = await fetch(`${this.apiBase}/sync/pull?cursor=${cursor}&limit=50`, {
                method: 'GET',
                headers,
                signal: controller.signal
            });
        } catch (e) {
            clearTimeout(timeoutId);
            return { pulledCount: 0, skipped: true, reason: 'Network timeout during pull' };
        }
        clearTimeout(timeoutId);

        if (!res.ok) {
            return { pulledCount: 0, skipped: true, status: res.status };
        }

        const json = await res.json();
        const feed = json.data || {};
        const events = feed.events || [];
        const nextCursor = feed.next_cursor || cursor;

        if (events.length > 0) {
            await this.db.applyPulledChanges(events, nextCursor);
        } else if (nextCursor > cursor) {
            await this.db.put('meta', { key: 'last_cursor', value: nextCursor });
        }

        return {
            pulledCount: events.length,
            cursor,
            nextCursor,
            hasMore: feed.has_more || false
        };
    }

    /**
     * 5. Bitta operatsiyaning server holatini tekshirish (Status check)
     */
    async checkOperationStatus(operationId) {
        try {
            const lease = await this.db.get('device_lease', 'current');
            const authToken = lease?.api_token || lease?.token;
            const headers = { 'Accept': 'application/json' };
            if (authToken) headers['Authorization'] = `Bearer ${authToken}`;

            const res = await fetch(`${this.apiBase}/sync/status/${operationId}`, {
                method: 'GET',
                headers
            });

            if (res.ok) {
                return await res.json();
            }
            return { success: false, status: res.status };
        } catch (e) {
            return { success: false, error: e.message };
        }
    }
}
