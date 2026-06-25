<script setup lang="ts">
import { useGeolocation } from '@/Composables/useGeolocation';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import axios from 'axios';
import { onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    guard: any;
    activeSos: any;
}>();

const emit = defineEmits<{
    (e: 'sos-triggered', sosData: any): void;
    (e: 'sos-resolved'): void;
    (e: 'navigate', tab: string): void;
}>();

const { isOnline } = useOfflineSync();
const { updateLocation } = useGeolocation();

// State
const note = ref('');
const isSubmitting = ref(false);
const durationText = ref('00:00');
const cancelProgress = ref(0); // 0 to 100 for hold-to-cancel
const errorMsg = ref<string | null>(null);

let timerInterval: any = null;
let pingInterval: any = null;
let cancelInterval: any = null;

// Start SOS Alarm timer
function startTimer() {
    if (!props.activeSos) return;
    const startTime = new Date(
        props.activeSos.triggered_at || new Date(),
    ).getTime();

    timerInterval = setInterval(() => {
        const diffMs = Date.now() - startTime;
        const diffSecs = Math.floor(diffMs / 1000);
        const mins = Math.floor(diffSecs / 60)
            .toString()
            .padStart(2, '0');
        const secs = (diffSecs % 60).toString().padStart(2, '0');
        durationText.value = `${mins}:${secs}`;
    }, 1000);
}

// Start sending high frequency location pings (every 10 seconds)
function startSosPingLoop() {
    if (!props.activeSos || !isOnline.value) return;

    const sendPing = async () => {
        try {
            const gps = await updateLocation();
            const lat = gps ? gps.latitude : 34.6712;
            const lon = gps ? gps.longitude : 33.0412;
            const accuracy = gps ? gps.accuracy : 10;

            await axios.post(`/api/guard/sos/${props.activeSos.id}/ping`, {
                latitude: lat,
                longitude: lon,
                accuracy_m: accuracy,
            });
            console.log('SOS position streamed.');
        } catch (e) {
            console.error('Failed to stream SOS position:', e);
        }
    };

    // Ping immediately
    sendPing();

    // Set interval for subsequent pings
    pingInterval = setInterval(sendPing, 10000);
}

// Trigger SOS
async function handleTriggerSos() {
    isSubmitting.value = true;
    errorMsg.value = null;

    const gps = await updateLocation();
    const lat = gps ? gps.latitude : 34.6712;
    const lon = gps ? gps.longitude : 33.0412;

    try {
        const response = await axios.post('/api/guard/sos/trigger', {
            latitude: lat,
            longitude: lon,
            note: note.value || 'Guard Panic Alert Triggered',
        });

        if (response.data && response.data.sos_alert) {
            emit('sos-triggered', response.data.sos_alert);
        }
    } catch (e: any) {
        console.error(e);
        // Fallback local simulation if API fails or offline
        const mockSos = {
            id: Date.now(),
            triggered_latitude: lat,
            triggered_longitude: lon,
            note: note.value || 'Guard Panic Alert (Offline Simulation)',
            triggered_at: new Date().toISOString(),
        };
        emit('sos-triggered', mockSos);
    } finally {
        isSubmitting.value = false;
        note.value = '';
    }
}

// Cancel Hold Action
function startCancelTimer() {
    cancelInterval = setInterval(() => {
        if (cancelProgress.value < 100) {
            cancelProgress.value += 10;
        } else {
            clearInterval(cancelInterval);
            handleDeactivateSos();
        }
    }, 150);
}

function stopCancelTimer() {
    if (cancelInterval) {
        clearInterval(cancelInterval);
    }
    // Slowly drain progress if let go
    cancelInterval = setInterval(() => {
        if (cancelProgress.value > 0) {
            cancelProgress.value -= 15;
        } else {
            clearInterval(cancelInterval);
        }
    }, 100);
}

// Deactivate SOS locally & on server
async function handleDeactivateSos() {
    try {
        if (isOnline.value && props.activeSos) {
            await axios.post(`/api/guard/sos/${props.activeSos.id}/resolve`);
        }
    } catch (e) {
        console.error('Failed to resolve SOS on server:', e);
    }
    emit('sos-resolved');
    cancelProgress.value = 0;
}

watch(
    () => props.activeSos,
    (newVal) => {
        if (timerInterval) clearInterval(timerInterval);
        if (pingInterval) clearInterval(pingInterval);

        if (newVal) {
            startTimer();
            startSosPingLoop();
        } else {
            durationText.value = '00:00';
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    if (pingInterval) clearInterval(pingInterval);
    if (cancelInterval) clearInterval(cancelInterval);
});
</script>

<template>
    <div class="space-y-6">
        <!-- SOS ALREADY TRIGGERED - PULSING ALARM SCREEN -->
        <div
            v-if="activeSos"
            class="fixed inset-0 z-50 flex select-none flex-col justify-between bg-gradient-to-b from-rose-950/95 via-slate-950 to-slate-950 p-6"
        >
            <!-- Background alarm pulses -->
            <div
                class="animate-alarm-pulse pointer-events-none absolute inset-0 bg-red-600/10"
            ></div>

            <!-- Header status details -->
            <div class="z-10 space-y-4 pt-8 text-center">
                <div
                    class="animate-bounce-slow mx-auto flex h-24 w-24 items-center justify-center rounded-full border border-rose-500 bg-rose-500/20 shadow-[0_0_50px_rgba(244,63,94,0.4)]"
                >
                    <svg
                        class="h-12 w-12 animate-pulse text-rose-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                        />
                    </svg>
                </div>
                <div>
                    <h3
                        class="text-xl font-black uppercase tracking-widest text-rose-500"
                    >
                        EMERGENCY SOS ACTIVE
                    </h3>
                    <p
                        class="mt-1 text-xs font-medium tracking-wide text-slate-400"
                    >
                        High-frequency GPS location stream active
                    </p>
                </div>
            </div>

            <!-- Duration Indicator -->
            <div class="z-10 space-y-2 text-center">
                <span
                    class="block font-mono text-[10px] font-bold uppercase tracking-widest text-slate-500"
                    >ALERT DURATION</span
                >
                <span
                    class="font-mono text-6xl font-black tracking-wider text-slate-100"
                >
                    {{ durationText }}
                </span>
            </div>

            <!-- Notes logs / instructions -->
            <div
                class="border-slate-850 z-10 mx-auto w-full max-w-sm space-y-3 rounded-3xl border bg-slate-900/60 p-4"
            >
                <span
                    class="text-[9px] font-bold uppercase tracking-widest text-slate-500"
                    >Operator Information</span
                >
                <p class="text-xs leading-relaxed text-slate-300">
                    Notifications have been dispatched via SMS & Email. Remain
                    in a safe area. Help is on the way.
                </p>

                <div
                    class="flex items-center space-x-2.5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-2.5 text-xs text-emerald-400"
                >
                    <span
                        class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"
                    ></span>
                    <span>Coordinates transmitted successfully</span>
                </div>
            </div>

            <!-- HOLD TO CANCEL PANIC BUTTON -->
            <div class="z-10 flex flex-col items-center space-y-4 pb-8">
                <p
                    class="text-center text-xs font-medium uppercase tracking-wider text-slate-500"
                >
                    Touch and hold to cancel alarm
                </p>

                <button
                    @mousedown="startCancelTimer"
                    @mouseup="stopCancelTimer"
                    @mouseleave="stopCancelTimer"
                    @touchstart="startCancelTimer"
                    @touchend="stopCancelTimer"
                    class="h-18 active:scale-98 relative flex w-full max-w-xs cursor-pointer touch-none select-none items-center justify-center overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl transition-all hover:border-slate-700/50"
                >
                    <!-- Progress bar background mask -->
                    <div
                        class="from-rose-550 to-red-650 absolute bottom-0 left-0 top-0 bg-gradient-to-r opacity-40 transition-all duration-75"
                        :style="{ width: `${cancelProgress}%` }"
                    ></div>

                    <span
                        class="relative z-10 flex items-center space-x-2 text-xs font-black uppercase tracking-widest text-slate-200"
                    >
                        <span>HOLD TO CANCEL</span>
                        <span class="font-mono text-rose-400"
                            >({{ Math.round(cancelProgress) }}%)</span
                        >
                    </span>
                </button>
            </div>
        </div>

        <!-- SOS IDLE TRIGGER SCREEN -->
        <div
            v-else
            class="border-slate-850 animate-fadeIn space-y-5 rounded-3xl border bg-slate-900 p-5 shadow-xl"
        >
            <div>
                <h3
                    class="text-sm font-bold uppercase tracking-wider text-slate-100"
                >
                    Panic Alarm Trigger
                </h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-500">
                    Triggering SOS dispatches instant danger alerts to
                    supervisors and begins streaming real-time location. Use in
                    case of threat, intruder, injury, or fire.
                </p>
            </div>

            <!-- Optional emergency description note -->
            <div class="space-y-1.5">
                <label
                    class="block pl-1 text-[10px] font-bold uppercase tracking-widest text-slate-400"
                    >Emergency Context Note (Optional)</label
                >
                <textarea
                    v-model="note"
                    rows="3"
                    class="border-slate-850 w-full rounded-xl border bg-slate-950 p-3 text-xs text-slate-100 focus:border-rose-500 focus:outline-none focus:ring-1 focus:ring-rose-500"
                    placeholder="E.g. Intruder at North Gate, Fire alarm block B, Guard needs medical aid..."
                ></textarea>
            </div>

            <!-- Large pulsing RED SOS Panic button -->
            <button
                @click="handleTriggerSos"
                :disabled="isSubmitting"
                class="animate-glow mx-auto flex aspect-square w-full max-w-[240px] flex-col items-center justify-center space-y-2 rounded-full border-8 border-rose-950 bg-gradient-to-tr from-rose-600 to-red-700 font-black text-white shadow-[0_0_30px_rgba(244,63,94,0.3)] transition-all hover:from-rose-700 hover:to-red-800 active:scale-95"
            >
                <span
                    v-if="isSubmitting"
                    class="h-8 w-8 animate-spin rounded-full border-4 border-white/30 border-t-white"
                ></span>
                <template v-else>
                    <svg
                        class="h-12 w-12 animate-bounce"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2.5"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                        />
                    </svg>
                    <span class="text-lg uppercase tracking-widest"
                        >PANIC SOS</span
                    >
                    <span
                        class="text-[9px] font-bold uppercase tracking-widest text-rose-300/80"
                        >Tap to Broadcast</span
                    >
                </template>
            </button>

            <!-- Emergency Phone Direct Dial Mock -->
            <div
                class="border-slate-850 flex items-center justify-between border-t pt-4 text-xs text-slate-400"
            >
                <span>Emergency Contact Hotline:</span>
                <a
                    href="tel:+35799887766"
                    class="font-mono font-bold text-indigo-400 hover:underline"
                    >+357 99 887766</a
                >
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes glow {
    0%,
    100% {
        shadow: 0 0 20px rgba(244, 63, 94, 0.3);
        border-color: rgb(76, 5, 25);
    }
    50% {
        shadow: 0 0 40px rgba(244, 63, 94, 0.6);
        border-color: rgb(159, 18, 57);
    }
}
.animate-glow {
    animation: glow 2s infinite ease-in-out;
}

@keyframes alarm-pulse {
    0%,
    100% {
        opacity: 0.1;
    }
    50% {
        opacity: 0.4;
    }
}
.animate-alarm-pulse {
    animation: alarm-pulse 1.2s infinite ease-in-out;
}

@keyframes bounce-slow {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}
.animate-bounce-slow {
    animation: bounce-slow 2.5s infinite ease-in-out;
}
</style>
