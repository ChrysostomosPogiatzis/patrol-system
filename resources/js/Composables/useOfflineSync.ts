import { Network } from '@capacitor/network';
import axios from 'axios';
import { onMounted, onUnmounted, readonly, ref } from 'vue';

export interface QueueItem {
    entity_type:
        | 'patrol_checkpoint_log'
        | 'incident'
        | 'checkpoint_media'
        | 'guard_location_ping';
    entity_id: string; // local client UUID
    payload: any;
    captured_at: string;
}

const isOnline = ref(true);
const queue = ref<QueueItem[]>([]);
const isSyncing = ref(false);
const lastSyncTime = ref<string | null>(null);
const syncError = ref<string | null>(null);

// Load queue from localStorage
function loadQueue() {
    if (typeof window === 'undefined') return;
    try {
        const data = localStorage.getItem('patrol_offline_sync_queue');
        if (data) {
            queue.value = JSON.parse(data);
        }
    } catch (e) {
        console.error(
            'Failed to load offline sync queue from localStorage:',
            e,
        );
    }
}

// Save queue to localStorage
function saveQueue() {
    if (typeof window === 'undefined') return;
    try {
        localStorage.setItem(
            'patrol_offline_sync_queue',
            JSON.stringify(queue.value),
        );
    } catch (e) {
        console.error('Failed to save offline sync queue to localStorage:', e);
    }
}

// Helper to generate UUIDv4
export function generateUUID(): string {
    if (
        typeof window !== 'undefined' &&
        window.crypto &&
        window.crypto.randomUUID
    ) {
        return window.crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

export function useOfflineSync() {
    // Add item to offline queue
    function addToQueue(entityType: QueueItem['entity_type'], payload: any) {
        const item: QueueItem = {
            entity_type: entityType,
            entity_id: generateUUID(),
            payload,
            captured_at: new Date().toISOString(),
        };

        queue.value.push(item);
        saveQueue();

        // Try syncing immediately if online
        if (isOnline.value) {
            triggerSync();
        }
    }

    // Clear the queue
    function clearQueue() {
        queue.value = [];
        saveQueue();
    }

    // Trigger sync to the backend
    async function triggerSync(): Promise<boolean> {
        if (queue.value.length === 0) {
            syncError.value = null;
            return true;
        }
        if (isSyncing.value) return false;

        isSyncing.value = true;
        syncError.value = null;

        try {
            // Re-verify network state
            if (!navigator.onLine) {
                isOnline.value = false;
                throw new Error('Device is offline.');
            }

            const response = await axios.post('/api/guard/sync', {
                queue: queue.value,
            });

            if (response.data && response.data.results) {
                const results: Array<{
                    entity_id: string;
                    status: string;
                    error?: string;
                }> = response.data.results;

                // Filter out successfully processed/acknowledged items (even if failed on backend processing)
                const processedIds = new Set(results.map((r) => r.entity_id));
                queue.value = queue.value.filter(
                    (item) => !processedIds.has(item.entity_id),
                );
                saveQueue();

                lastSyncTime.value = new Date().toLocaleTimeString();
                isSyncing.value = false;
                return true;
            } else {
                throw new Error('Invalid server response format.');
            }
        } catch (error: any) {
            console.error('Offline synchronization failed:', error);
            syncError.value =
                error.response?.data?.message ||
                error.message ||
                'Sync failed.';
            isSyncing.value = false;
            return false;
        }
    }

    // Set up network listeners
    let networkListener: any = null;
    let pingInterval: any = null;

    // Helper to check actual internet availability
    async function checkInternetAvailability(): Promise<boolean> {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 3000);

            // Fetch a tiny asset with a cache-busting query parameter
            await fetch(`/favicon.ico?_=${Date.now()}`, {
                method: 'HEAD',
                signal: controller.signal,
                cache: 'no-store',
            });
            clearTimeout(timeoutId);
            return true;
        } catch (e) {
            return false;
        }
    }

    const updateOnlineStatus = async (connected: boolean) => {
        let actualOnline = false;
        if (connected) {
            actualOnline = await checkInternetAvailability();
        }

        const nextState = actualOnline;
        const changed = isOnline.value !== nextState;
        isOnline.value = nextState;

        if (changed && nextState) {
            console.log('Device returned online. Triggering sync...');
            triggerSync();
        }
    };

    onMounted(async () => {
        loadQueue();

        // Initialize status using Capacitor Network
        try {
            const status = await Network.getStatus();
            await updateOnlineStatus(status.connected);
        } catch (e) {
            // Fallback to browser navigator
            if (typeof navigator !== 'undefined') {
                await updateOnlineStatus(navigator.onLine);
            }
        }

        // Listen for status changes
        try {
            networkListener = await Network.addListener(
                'networkStatusChange',
                async (status: any) => {
                    await updateOnlineStatus(status.connected);
                },
            );
        } catch (e) {
            // Fallback to standard web listeners
            if (typeof window !== 'undefined') {
                window.addEventListener('online', () =>
                    updateOnlineStatus(true),
                );
                window.addEventListener('offline', () =>
                    updateOnlineStatus(false),
                );
            }
        }

        // Periodically verify internet connectivity every 10 seconds
        pingInterval = setInterval(async () => {
            let connected = false;
            try {
                const status = await Network.getStatus();
                connected = status.connected;
            } catch (e) {
                connected =
                    typeof navigator !== 'undefined' ? navigator.onLine : false;
            }
            await updateOnlineStatus(connected);
        }, 10000);

        // Sync on mount if online
        if (isOnline.value && queue.value.length > 0) {
            triggerSync();
        }
    });

    onUnmounted(() => {
        if (networkListener) {
            networkListener.remove();
        }
        if (pingInterval) {
            clearInterval(pingInterval);
        }
    });

    return {
        isOnline: readonly(isOnline),
        queue: readonly(queue),
        isSyncing: readonly(isSyncing),
        lastSyncTime: readonly(lastSyncTime),
        syncError: readonly(syncError),
        addToQueue,
        triggerSync,
        clearQueue,
    };
}
