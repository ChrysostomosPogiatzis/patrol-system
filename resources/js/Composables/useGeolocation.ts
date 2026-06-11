import { ref } from 'vue';
import axios from 'axios';
import { CapacitorBridge, GpsPosition } from '../Services/CapacitorBridge';
import { useOfflineSync } from './useOfflineSync';

const currentPosition = ref<GpsPosition | null>(null);
const batteryPct = ref<number | null>(null);
const isTracking = ref(false);
const activePatrolId = ref<number | null>(null);
let trackingIntervalId: any = null;

export function useGeolocation() {
    const { isOnline, addToQueue } = useOfflineSync();

    // Fetch coordinates and battery status
    async function updateLocation(): Promise<GpsPosition | null> {
        try {
            // Fetch battery info first so it is populated even if geolocation fails/times out
            try {
                const battery = await CapacitorBridge.getBatteryInfo();
                batteryPct.value = battery.level;
            } catch (batteryError) {
                console.error('Failed to update battery level:', batteryError);
            }

            const pos = await CapacitorBridge.getCurrentPosition();
            currentPosition.value = pos;
            return pos;
        } catch (error) {
            console.error('Failed to update location:', error);
            return null;
        }
    }

    // Ping location to server or queue offline
    async function pingLocation(): Promise<void> {
        const pos = await updateLocation();
        if (!pos) return;

        const payload = {
            latitude: pos.latitude,
            longitude: pos.longitude,
            accuracy_m: pos.accuracy,
            battery_pct: batteryPct.value,
            is_online: isOnline.value,
            patrol_id: activePatrolId.value
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
        activePatrolId.value = patrolId;
        if (isTracking.value) {
            // Already tracking, just trigger an immediate ping to sync status/battery
            pingLocation();
            return;
        }

        isTracking.value = true;
        // Run first ping immediately
        pingLocation();

        trackingIntervalId = setInterval(() => {
            pingLocation();
        }, intervalMs);
    }

    // Stop background tracking interval
    function stopTracking() {
        if (trackingIntervalId) {
            clearInterval(trackingIntervalId);
            trackingIntervalId = null;
        }
        isTracking.value = false;
        activePatrolId.value = null;
    }

    return {
        currentPosition,
        batteryPct,
        isTracking,
        activePatrolId,
        updateLocation,
        pingLocation,
        startTracking,
        stopTracking
    };
}

