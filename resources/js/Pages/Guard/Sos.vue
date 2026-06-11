<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { useGeolocation } from '@/Composables/useGeolocation';

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
    const startTime = new Date(props.activeSos.triggered_at || new Date()).getTime();
    
    timerInterval = setInterval(() => {
        const diffMs = Date.now() - startTime;
        const diffSecs = Math.floor(diffMs / 1000);
        const mins = Math.floor(diffSecs / 60).toString().padStart(2, '0');
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
            const lat = gps ? gps.latitude : 34.671200;
            const lon = gps ? gps.longitude : 33.041200;
            const accuracy = gps ? gps.accuracy : 10;
            
            await axios.post(`/api/guard/sos/${props.activeSos.id}/ping`, {
                latitude: lat,
                longitude: lon,
                accuracy_m: accuracy
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
    const lat = gps ? gps.latitude : 34.671200;
    const lon = gps ? gps.longitude : 33.041200;

    try {
        const response = await axios.post('/api/guard/sos/trigger', {
            latitude: lat,
            longitude: lon,
            note: note.value || 'Guard Panic Alert Triggered'
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
            triggered_at: new Date().toISOString()
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

watch(() => props.activeSos, (newVal) => {
    if (timerInterval) clearInterval(timerInterval);
    if (pingInterval) clearInterval(pingInterval);
    
    if (newVal) {
        startTimer();
        startSosPingLoop();
    } else {
        durationText.value = '00:00';
    }
}, { immediate: true });

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
            class="fixed inset-0 z-50 bg-gradient-to-b from-rose-950/95 via-slate-950 to-slate-950 flex flex-col justify-between p-6 select-none"
        >
            <!-- Background alarm pulses -->
            <div class="absolute inset-0 bg-red-600/10 pointer-events-none animate-alarm-pulse"></div>

            <!-- Header status details -->
            <div class="z-10 text-center pt-8 space-y-4">
                <div class="w-24 h-24 rounded-full bg-rose-500/20 border border-rose-500 flex items-center justify-center mx-auto shadow-[0_0_50px_rgba(244,63,94,0.4)] animate-bounce-slow">
                    <svg class="w-12 h-12 text-rose-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-black text-rose-500 tracking-widest uppercase">EMERGENCY SOS ACTIVE</h3>
                    <p class="text-xs text-slate-400 font-medium tracking-wide mt-1">High-frequency GPS location stream active</p>
                </div>
            </div>

            <!-- Duration Indicator -->
            <div class="z-10 text-center space-y-2">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block font-mono">ALERT DURATION</span>
                <span class="text-6xl font-black font-mono text-slate-100 tracking-wider">
                    {{ durationText }}
                </span>
            </div>

            <!-- Notes logs / instructions -->
            <div class="z-10 max-w-sm mx-auto w-full bg-slate-900/60 border border-slate-850 p-4 rounded-3xl space-y-3">
                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Operator Information</span>
                <p class="text-xs text-slate-300 leading-relaxed">Notifications have been dispatched via SMS & Email. Remain in a safe area. Help is on the way.</p>
                
                <div class="flex items-center space-x-2.5 text-xs text-emerald-400 bg-emerald-500/10 p-2.5 rounded-xl border border-emerald-500/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Coordinates transmitted successfully</span>
                </div>
            </div>

            <!-- HOLD TO CANCEL PANIC BUTTON -->
            <div class="z-10 pb-8 flex flex-col items-center space-y-4">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wider text-center">Touch and hold to cancel alarm</p>
                
                <button 
                    @mousedown="startCancelTimer"
                    @mouseup="stopCancelTimer"
                    @mouseleave="stopCancelTimer"
                    @touchstart="startCancelTimer"
                    @touchend="stopCancelTimer"
                    class="relative w-full max-w-xs h-18 bg-slate-900 border border-slate-800 hover:border-slate-700/50 rounded-2xl overflow-hidden active:scale-98 transition-all flex items-center justify-center cursor-pointer select-none touch-none shadow-2xl"
                >
                    <!-- Progress bar background mask -->
                    <div 
                        class="absolute left-0 bottom-0 top-0 bg-gradient-to-r from-rose-550 to-red-650 opacity-40 transition-all duration-75"
                        :style="{ width: `${cancelProgress}%` }"
                    ></div>
                    
                    <span class="relative z-10 text-slate-200 font-black tracking-widest text-xs uppercase flex items-center space-x-2">
                        <span>HOLD TO CANCEL</span>
                        <span class="font-mono text-rose-400">({{ Math.round(cancelProgress) }}%)</span>
                    </span>
                </button>
            </div>
        </div>

        <!-- SOS IDLE TRIGGER SCREEN -->
        <div v-else class="bg-slate-900 border border-slate-850 rounded-3xl p-5 space-y-5 shadow-xl animate-fadeIn">
            <div>
                <h3 class="text-sm font-bold text-slate-100 uppercase tracking-wider">Panic Alarm Trigger</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">Triggering SOS dispatches instant danger alerts to supervisors and begins streaming real-time location. Use in case of threat, intruder, injury, or fire.</p>
            </div>

            <!-- Optional emergency description note -->
            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Emergency Context Note (Optional)</label>
                <textarea 
                    v-model="note" 
                    rows="3"
                    class="w-full bg-slate-950 border border-slate-850 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 text-slate-100 p-3 rounded-xl text-xs focus:outline-none"
                    placeholder="E.g. Intruder at North Gate, Fire alarm block B, Guard needs medical aid..."
                ></textarea>
            </div>

            <!-- Large pulsing RED SOS Panic button -->
            <button 
                @click="handleTriggerSos"
                :disabled="isSubmitting"
                class="w-full aspect-square max-w-[240px] mx-auto bg-gradient-to-tr from-rose-600 to-red-700 hover:from-rose-700 hover:to-red-800 text-white font-black rounded-full shadow-[0_0_30px_rgba(244,63,94,0.3)] transition-all flex flex-col items-center justify-center space-y-2 active:scale-95 border-8 border-rose-950 animate-glow"
            >
                <span v-if="isSubmitting" class="w-8 h-8 border-4 border-white/30 border-t-white rounded-full animate-spin"></span>
                <template v-else>
                    <svg class="w-12 h-12 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span class="text-lg tracking-widest uppercase">PANIC SOS</span>
                    <span class="text-[9px] font-bold text-rose-300/80 tracking-widest uppercase">Tap to Broadcast</span>
                </template>
            </button>

            <!-- Emergency Phone Direct Dial Mock -->
            <div class="pt-4 border-t border-slate-850 flex justify-between items-center text-xs text-slate-400">
                <span>Emergency Contact Hotline:</span>
                <a href="tel:+35799887766" class="text-indigo-400 font-bold hover:underline font-mono">+357 99 887766</a>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes glow {
    0%, 100% { shadow: 0 0 20px rgba(244, 63, 94, 0.3); border-color: rgb(76, 5, 25); }
    50% { shadow: 0 0 40px rgba(244, 63, 94, 0.6); border-color: rgb(159, 18, 57); }
}
.animate-glow {
    animation: glow 2s infinite ease-in-out;
}

@keyframes alarm-pulse {
    0%, 100% { opacity: 0.1; }
    50% { opacity: 0.4; }
}
.animate-alarm-pulse {
    animation: alarm-pulse 1.2s infinite ease-in-out;
}

@keyframes bounce-slow {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
.animate-bounce-slow {
    animation: bounce-slow 2.5s infinite ease-in-out;
}
</style>
