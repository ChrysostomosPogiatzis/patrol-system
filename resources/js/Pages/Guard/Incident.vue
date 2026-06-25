<script setup lang="ts">
import { useGeolocation } from '@/Composables/useGeolocation';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { CapacitorBridge } from '@/Services/CapacitorBridge';
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps<{
    guard: any;
    activePatrol: any;
}>();

const emit = defineEmits<{
    (e: 'navigate', tab: string): void;
    (e: 'incident-reported'): void;
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

const fileInputRef = ref<HTMLInputElement | null>(null);

// Capture real photo using Capacitor camera or browser file fallback
async function handleCapturePhoto() {
    if (CapacitorBridge.isNative()) {
        try {
            const image = await Camera.getPhoto({
                quality: 85,
                allowEditing: false,
                resultType: CameraResultType.DataUrl,
                source: CameraSource.Camera,
            });
            if (image && image.dataUrl) {
                photos.value.push(image.dataUrl);
            }
        } catch (error: any) {
            console.error('Capacitor camera failed:', error);
            triggerWebCameraInput();
        }
    } else {
        triggerWebCameraInput();
    }
}

function triggerWebCameraInput() {
    if (fileInputRef.value) {
        fileInputRef.value.click();
    }
}

function handleFileUploaded(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files) {
        Array.from(target.files).forEach((file) => {
            const reader = new FileReader();
            reader.onload = (event) => {
                if (event.target && event.target.result) {
                    photos.value.push(event.target.result as string);
                }
            };
            reader.readAsDataURL(file);
        });
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
    const lat = gps ? gps.latitude : 34.6712;
    const lon = gps ? gps.longitude : 33.0412;

    const usePatrolId =
        attachToPatrol.value && props.activePatrol
            ? props.activePatrol.id
            : null;

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
            const url = usePatrolId
                ? `/api/guard/patrols/${usePatrolId}/incidents`
                : '/api/incidents/standalone';

            await axios.post(url, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            handleSuccessReset();
        } catch (e: any) {
            console.error(
                'Failed to submit incident online, falling back to queue:',
                e,
            );
            queueIncidentOffline(lat, lon, usePatrolId);
        }
    } else {
        queueIncidentOffline(lat, lon, usePatrolId);
    }
}

// Queue offline helper
function queueIncidentOffline(
    lat: number,
    lon: number,
    patrolId: number | null,
) {
    const payload = {
        title: title.value,
        description: description.value || null,
        priority: priority.value,
        latitude: lat,
        longitude: lon,
        patrol_id: patrolId,
        base64_media: photos.value.map((photo, idx) => ({
            data: photo.split(',')[1],
            filename: `incident_media_${idx}.jpg`,
        })),
    };

    addToQueue('incident', payload);

    // Increment local incident count if patrolling via event
    if (patrolId && props.activePatrol) {
        emit('incident-reported');
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
            class="animate-fadeIn rounded-2xl border border-emerald-500 bg-emerald-600 p-4 text-center text-xs font-bold text-white shadow-lg shadow-emerald-600/20"
        >
            <span v-if="!isOnline"
                >Incident recorded offline! queued for auto-sync.</span
            >
            <span v-else>Incident reported successfully to server.</span>
        </div>

        <div
            class="border-slate-850 space-y-5 rounded-3xl border bg-slate-900 p-5 shadow-xl"
        >
            <div>
                <h3
                    class="text-sm font-bold uppercase tracking-wider text-slate-100"
                >
                    Log Security Incident
                </h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-500">
                    Report safety violations, property damage, water leaks, or
                    unlocked fire doors directly to management.
                </p>
            </div>

            <!-- Form Error message -->
            <div
                v-if="errorMsg"
                class="rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-center text-xs font-semibold text-rose-300"
            >
                {{ errorMsg }}
            </div>

            <!-- Incident Title -->
            <div class="space-y-1.5">
                <label
                    class="block pl-1 text-[10px] font-bold uppercase tracking-widest text-slate-400"
                    >Incident Headline / Title</label
                >
                <input
                    v-model="title"
                    type="text"
                    class="border-slate-850 w-full rounded-xl border bg-slate-950 px-4 py-3 text-xs text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    placeholder="E.g. Broken fence, fuel leak on Dock A, water damage..."
                />
            </div>

            <!-- Color-coded Custom Priority Selector -->
            <div class="space-y-2">
                <label
                    class="block pl-1 text-[10px] font-bold uppercase tracking-widest text-slate-400"
                    >Severity / Priority</label
                >
                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="p in ['low', 'medium', 'high', 'critical']"
                        :key="p"
                        type="button"
                        @click="priority = p as any"
                        class="rounded-xl border py-2.5 text-[10px] font-bold uppercase tracking-wider transition-all active:scale-95"
                        :class="[
                            priority === p
                                ? {
                                      low: 'border-sky-500/30 bg-sky-500/15 text-sky-400 ring-1 ring-sky-500/20',
                                      medium: 'border-amber-500/30 bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/20',
                                      high: 'border-orange-500/30 bg-orange-500/15 text-orange-400 ring-1 ring-orange-500/20',
                                      critical:
                                          'animate-pulse border-rose-500/30 bg-rose-500/15 text-rose-400 ring-1 ring-rose-500/20',
                                  }[p]
                                : 'border-slate-850 hover:bg-slate-850/50 bg-slate-950 text-slate-500',
                        ]"
                    >
                        {{ p }}
                    </button>
                </div>
            </div>

            <!-- Patrol Context Binding Option -->
            <div
                v-if="activePatrol"
                class="border-slate-850 flex items-center space-x-3 rounded-2xl border bg-slate-950 p-3"
            >
                <input
                    v-model="attachToPatrol"
                    id="attach-patrol"
                    type="checkbox"
                    class="w-4.5 h-4.5 rounded-md border border-slate-800 bg-slate-900 text-indigo-600 focus:ring-0 focus:ring-offset-0"
                />
                <label
                    for="attach-patrol"
                    class="cursor-pointer select-none text-xs font-medium text-slate-400"
                >
                    Link with active patrol:
                    <span class="ml-1 font-mono font-bold text-indigo-400">{{
                        activePatrol.route?.name
                    }}</span>
                </label>
            </div>

            <!-- Description -->
            <div class="space-y-1.5">
                <label
                    class="block pl-1 text-[10px] font-bold uppercase tracking-widest text-slate-400"
                    >Detailed Description</label
                >
                <textarea
                    v-model="description"
                    rows="4"
                    class="border-slate-850 w-full rounded-xl border bg-slate-950 p-3 text-xs text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    placeholder="Provide step-by-step observations, hazard dimensions, actions taken, security risks..."
                ></textarea>
            </div>

            <!-- Real Multi-media Photo Capture -->
            <div class="space-y-3">
                <label
                    class="block pl-1 text-[10px] font-bold uppercase tracking-widest text-slate-400"
                    >Media Evidence (Photos)</label
                >

                <input
                    ref="fileInputRef"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="hidden"
                    multiple
                    @change="handleFileUploaded"
                />

                <div class="flex items-center space-x-3">
                    <button
                        @click="handleCapturePhoto"
                        type="button"
                        class="flex items-center space-x-2 rounded-xl bg-indigo-600 px-4 py-2.5 font-bold text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
                    >
                        <svg
                            class="h-4 w-4 text-white"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                        <span>Take Incident Photo</span>
                    </button>
                    <span class="font-mono text-[10px] text-slate-500"
                        >{{ photos.length }} attachments loaded</span
                    >
                </div>

                <!-- Grid of loaded attachments -->
                <div v-if="photos.length > 0" class="grid grid-cols-3 gap-3">
                    <div
                        v-for="(photo, index) in photos"
                        :key="index"
                        class="group relative aspect-video w-full overflow-hidden rounded-xl border border-slate-800 bg-slate-950"
                    >
                        <img :src="photo" class="h-full w-full object-cover" />
                        <button
                            @click="handleRemovePhoto(index)"
                            type="button"
                            class="absolute right-1.5 top-1.5 flex h-5 w-5 items-center justify-center rounded-full border border-slate-800 bg-slate-950/80 text-xs text-rose-500 backdrop-blur hover:bg-slate-900 focus:outline-none"
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
                class="shadow-indigo-650/20 active:scale-98 flex w-full items-center justify-center space-x-2 rounded-2xl bg-gradient-to-r from-indigo-500 to-violet-600 py-4 text-xs font-bold uppercase tracking-widest text-white shadow-xl transition-all hover:from-indigo-600 hover:to-violet-700"
            >
                <span
                    v-if="isSubmitting"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                ></span>
                <span v-else>SUBMIT INCIDENT REPORT</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.3s ease forwards;
}
</style>
