import { ref, onUnmounted } from 'vue';
import axios from 'axios';
import { CapacitorBridge, GpsPosition } from '../Services/CapacitorBridge';
import { useOfflineSync } from './useOfflineSync';

const currentPosition = ref<GpsPosition | null>(null);
const batteryPct = ref<number | null>(null);
const isTracking = ref(false);
let trackingIntervalId: any = null;

export function useGeolocation() {
    const { isOnline, addToQueue } = useOfflineSync();

    // Fetch coordinates and battery status
    async function updateLocation(): Promise<GpsPosition | null> {
        try {
            const pos = await CapacitorBridge.getCurrentPosition();
            currentPosition.value = pos;

            const battery = await CapacitorBridge.getBatteryInfo();
            batteryPct.value = battery.level;

            return pos;
        } catch (error) {
            console.error('Failed to update location:', error);
            return null;
        }
    }

    // Ping location to server or queue offline
    async function pingLocation(patrolId: number | null = null): Promise<void> {
        const pos = await updateLocation();
        if (!pos) return;

        const payload = {
            latitude: pos.latitude,
            longitude: pos.longitude,
            accuracy_m: pos.accuracy,
            battery_pct: batteryPct.value,
            is_online: isOnline.value,
            patrol_id: patrolId
        };

        if (isOnline.value) {
            try {
                await axios.post('/api/guard/location/ping', payload);
            } catch (error) {
                console.warn('Failed to ping location online, queuing instead:', error);
                addToQueue('guard_location_ping', payload);
            }
        } else {
            addToQueue('guard_location_ping', payload);
        }
    }

    // Start background tracking interval (every 60 seconds)
    function startTracking(patrolId: number | null = null, intervalMs: number = 60000) {
        if (isTracking.value) return;

        isTracking.value = true;
        // Run first ping immediately
        pingLocation(patrolId);

        trackingIntervalId = setInterval(() => {
            pingLocation(patrolId);
        }, intervalMs);
    }

    // Stop background tracking interval
    function stopTracking() {
        if (trackingIntervalId) {
            clearInterval(trackingIntervalId);
            trackingIntervalId = null;
        }
        isTracking.value = false;
    }

    return {
        currentPosition,
        batteryPct,
        isTracking,
        updateLocation,
        pingLocation,
        startTracking,
        stopTracking
    };
}
