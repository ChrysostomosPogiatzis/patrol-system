<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { useGeolocation } from '@/Composables/useGeolocation';

const props = defineProps<{
    guard: any;
    activePatrol: any;
}>();

const emit = defineEmits<{
    (e: 'navigate', tab: string): void;
}>();

const { isOnline, addToQueue } = useOfflineSync();
const { updateLocation } = useGeolocation();

// Form states
const title = ref('');
const description = ref('');
const priority = ref<'low' | 'medium' | 'high' | 'critical'>('medium');
const attachToPatrol = ref(!!props.activePatrol);
const photos = ref<string[]>([]); // base64 strings
const isSubmitting = ref(false);
const showSuccessToast = ref(false);
const errorMsg = ref<string | null>(null);

// Simulate taking photo
function handleCapturePhoto() {
    const canvas = document.createElement('canvas');
    canvas.width = 640;
    canvas.height = 480;
    const ctx = canvas.getContext('2d');
    if (ctx) {
        // Background gradient
        const grad = ctx.createLinearGradient(0, 0, 640, 480);
        grad.addColorStop(0, '#7f1d1d'); // Dark Red
        grad.addColorStop(1, '#0f172a');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, 640, 480);
        
        ctx.fillStyle = '#f87171';
        ctx.font = 'bold 24px sans-serif';
        ctx.fillText('INCIDENT EVIDENCE SNAPSHOT', 50, 100);
        
        ctx.fillStyle = '#cbd5e1';
        ctx.font = '16px sans-serif';
        ctx.fillText(`Title: ${title.value || 'Untitled Incident'}`, 50, 150);
        ctx.fillText(`Priority: ${priority.value.toUpperCase()}`, 50, 180);
        ctx.fillText(`Timestamp: ${new Date().toLocaleString()}`, 50, 210);
        
        photos.value.push(canvas.toDataURL('image/jpeg'));
    }
}

// Remove photo from selection
function handleRemovePhoto(index: number) {
    photos.value.splice(index, 1);
}

// Convert base64 signature/photo to File object
function base64ToFile(base64Data: string, filename: string): File {
    const arr = base64Data.split(',');
    const mime = arr[0].match(/:(.*?);/)![1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new File([u8arr], filename, { type: mime });
}

// Submit Incident Form
async function handleSubmitIncident() {
    if (!title.value.trim()) {
        errorMsg.value = 'Please enter an incident title.';
        return;
    }

    isSubmitting.value = true;
    errorMsg.value = null;

    // Get current device coordinates
    const gps = await updateLocation();
    const lat = gps ? gps.latitude : 34.671200;
    const lon = gps ? gps.longitude : 33.041200;

    const usePatrolId = (attachToPatrol.value && props.activePatrol) ? props.activePatrol.id : null;

    if (isOnline.value) {
        try {
            const formData = new FormData();
            formData.append('title', title.value);
            formData.append('priority', priority.value);
            formData.append('latitude', lat.toString());
            formData.append('longitude', lon.toString());
            
            if (description.value) {
                formData.append('description', description.value);
            }
            
            // Attach photos
            photos.value.forEach((photo, idx) => {
                const file = base64ToFile(photo, `incident_media_${idx}.jpg`);
                formData.append(`media_files[${idx}]`, file);
            });

            // Endpoint selection
            const url = usePatrolId ? 
                `/api/guard/patrols/${usePatrolId}/incidents` : 
                '/api/incidents/standalone';

            await axios.post(url, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            handleSuccessReset();
        } catch (e: any) {
            console.error('Failed to submit incident online, falling back to queue:', e);
            queueIncidentOffline(lat, lon, usePatrolId);
        }
    } else {
        queueIncidentOffline(lat, lon, usePatrolId);
    }
}

// Queue offline helper
function queueIncidentOffline(lat: number, lon: number, patrolId: number | null) {
    const payload = {
        title: title.value,
        description: description.value || null,
        priority: priority.value,
        latitude: lat,
        longitude: lon,
        patrol_id: patrolId,
        base64_media: photos.value.map((photo, idx) => ({
            data: photo.split(',')[1],
            filename: `incident_media_${idx}.jpg`
        }))
    };

    addToQueue('incident', payload);
    
    // Increment local incident count if patrolling
    if (patrolId && props.activePatrol) {
        props.activePatrol.incident_count++;
    }

    handleSuccessReset();
}

function handleSuccessReset() {
    isSubmitting.value = false;
    showSuccessToast.value = true;
    
    // Reset Form
    title.value = '';
    description.value = '';
    priority.value = 'medium';
    photos.value = [];
    
    // Clear toast
    setTimeout(() => {
        showSuccessToast.value = false;
    }, 4000);
}
</script>

<template>
    <div class="space-y-6">
        <!-- Toast Notification Alert -->
        <div 
            v-if="showSuccessToast" 
            class="bg-emerald-600 border border-emerald-500 text-white rounded-2xl p-4 text-xs font-bold text-center shadow-lg shadow-emerald-600/20 animate-fadeIn"
        >
            <span v-if="!isOnline">Incident recorded offline! queued for auto-sync.</span>
            <span v-else>Incident reported successfully to server.</span>
        </div>

        <div class="bg-slate-900 border border-slate-850 rounded-3xl p-5 space-y-5 shadow-xl">
            <div>
                <h3 class="text-sm font-bold text-slate-100 uppercase tracking-wider">Log Security Incident</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">Report safety violations, property damage, water leaks, or unlocked fire doors directly to management.</p>
            </div>

            <!-- Form Error message -->
            <div v-if="errorMsg" class="bg-rose-500/10 border border-rose-500/20 text-rose-300 rounded-xl p-3 text-xs text-center font-semibold">
                {{ errorMsg }}
            </div>

            <!-- Incident Title -->
            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Incident Headline / Title</label>
                <input 
                    v-model="title" 
                    type="text" 
                    class="w-full bg-slate-950 border border-slate-850 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-slate-100 px-4 py-3 rounded-xl text-xs focus:outline-none"
                    placeholder="E.g. Broken fence, fuel leak on Dock A, water damage..."
                />
            </div>

            <!-- Color-coded Custom Priority Selector -->
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Severity / Priority</label>
                <div class="grid grid-cols-4 gap-2">
                    <button 
                        v-for="p in ['low', 'medium', 'high', 'critical']" 
                        :key="p"
                        type="button"
                        @click="priority = p as any"
                        class="py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-wider border transition-all active:scale-95"
                        :class="[
                            priority === p ? {
                                'low': 'bg-sky-500/15 text-sky-400 border-sky-500/30 ring-1 ring-sky-500/20',
                                'medium': 'bg-amber-500/15 text-amber-400 border-amber-500/30 ring-1 ring-amber-500/20',
                                'high': 'bg-orange-500/15 text-orange-400 border-orange-500/30 ring-1 ring-orange-500/20',
                                'critical': 'bg-rose-500/15 text-rose-400 border-rose-500/30 ring-1 ring-rose-500/20 animate-pulse'
                            }[p] : 'bg-slate-950 text-slate-500 border-slate-850 hover:bg-slate-850/50'
                        ]"
                    >
                        {{ p }}
                    </button>
                </div>
            </div>

            <!-- Patrol Context Binding Option -->
            <div v-if="activePatrol" class="flex items-center space-x-3 bg-slate-950 p-3 rounded-2xl border border-slate-850">
                <input 
                    v-model="attachToPatrol"
                    id="attach-patrol"
                    type="checkbox"
                    class="w-4.5 h-4.5 bg-slate-900 border border-slate-800 focus:ring-0 focus:ring-offset-0 text-indigo-600 rounded-md"
                />
                <label for="attach-patrol" class="text-xs text-slate-400 font-medium select-none cursor-pointer">
                    Link with active patrol: <span class="text-indigo-400 font-bold ml-1 font-mono">{{ activePatrol.route?.name }}</span>
                </label>
            </div>

            <!-- Description -->
            <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Detailed Description</label>
                <textarea 
                    v-model="description" 
                    rows="4"
                    class="w-full bg-slate-950 border border-slate-850 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-slate-100 p-3 rounded-xl text-xs focus:outline-none"
                    placeholder="Provide step-by-step observations, hazard dimensions, actions taken, security risks..."
                ></textarea>
            </div>

            <!-- Simulated Multi-media Photo Capture -->
            <div class="space-y-3">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Media Evidence (Photos)</label>
                
                <div class="flex items-center space-x-3">
                    <button 
                        @click="handleCapturePhoto" 
                        type="button"
                        class="py-2.5 px-4 bg-slate-850 hover:bg-slate-800 text-slate-200 border border-slate-800 text-xs font-bold rounded-xl active:scale-95 transition-all flex items-center space-x-2"
                    >
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Simulate Camera Photo</span>
                    </button>
                    <span class="text-[10px] text-slate-500 font-mono">{{ photos.length }} attachments loaded</span>
                </div>

                <!-- Grid of loaded attachments -->
                <div v-if="photos.length > 0" class="grid grid-cols-3 gap-3">
                    <div 
                        v-for="(photo, index) in photos" 
                        :key="index"
                        class="relative w-full aspect-video border border-slate-800 rounded-xl overflow-hidden bg-slate-950 group"
                    >
                        <img :src="photo" class="w-full h-full object-cover" />
                        <button 
                            @click="handleRemovePhoto(index)"
                            type="button"
                            class="absolute top-1.5 right-1.5 bg-slate-950/80 backdrop-blur w-5 h-5 rounded-full flex items-center justify-center border border-slate-800 text-rose-500 text-xs hover:bg-slate-900 focus:outline-none"
                        >
                            ×
                        </button>
                    </div>
                </div>
            </div>

            <!-- Submit button -->
            <button 
                @click="handleSubmitIncident"
                :disabled="isSubmitting"
                class="w-full py-4 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white font-bold rounded-2xl shadow-xl shadow-indigo-650/20 transition-all flex items-center justify-center space-x-2 text-xs uppercase tracking-widest active:scale-98"
            >
                <span v-if="isSubmitting" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                <span v-else>SUBMIT INCIDENT REPORT</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.3s ease forwards;
}
</style>
