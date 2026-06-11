<script setup lang="ts">
import { ref, watch, nextTick, onUnmounted } from 'vue';

interface Location {
    id: number;
    name: string;
    latitude?: number | string;
    longitude?: number | string;
}

interface Checkpoint {
    id: number;
    location_id: number;
    name: string;
    description?: string;
    scan_method: 'qr' | 'nfc' | 'both';
    qr_code?: string;
    nfc_tag_id?: string;
    latitude?: number;
    longitude?: number;
    gps_required: boolean;
    gps_fence_radius: number;
    photo_requirement: string;
    note_requirement: string;
    voice_requirement: string;
    signature_required: boolean;
    incident_enabled: boolean;
}

const props = defineProps<{
    show: boolean;
    locations: Location[];
    checkpoint?: Checkpoint | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', form: any): void;
}>();

const form = ref({
    location_id: '' as any,
    name: '',
    description: '',
    scan_method: 'qr',
    qr_code: '',
    nfc_tag_id: '',
    gps_required: true,
    gps_fence_radius: 15,
    latitude: 34.671200,
    longitude: 33.041200,
    photo_requirement: 'off',
    note_requirement: 'off',
    voice_requirement: 'off',
    signature_required: false,
    incident_enabled: true
});

const mapContainer = ref<HTMLDivElement | null>(null);
let mapInstance: any = null;
let markerInstance: any = null;
let circleInstance: any = null;

function loadLeaflet(): Promise<void> {
    return new Promise((resolve, reject) => {
        if ((window as any).L) {
            resolve();
            return;
        }
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);

        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load Leaflet'));
        document.head.appendChild(script);
    });
}

const getSelectedLocationCoords = () => {
    const loc = props.locations.find(l => l.id === Number(form.value.location_id));
    if (loc && loc.latitude && loc.longitude) {
        return {
            latitude: Number(loc.latitude),
            longitude: Number(loc.longitude)
        };
    }
    return null;
};

async function initMap() {
    await loadLeaflet();
    const L = (window as any).L;
    if (!L || !mapContainer.value) return;

    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }

    let lat = Number(form.value.latitude) || 34.671200;
    let lng = Number(form.value.longitude) || 33.041200;

    // Fallback to selected location coordinates if it is a new checkpoint and not yet overridden
    if (!props.checkpoint) {
        const locCoords = getSelectedLocationCoords();
        if (locCoords) {
            lat = locCoords.latitude;
            lng = locCoords.longitude;
            form.value.latitude = lat;
            form.value.longitude = lng;
        }
    }

    mapInstance = L.map(mapContainer.value).setView([lat, lng], 15);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO'
    }).addTo(mapInstance);

    markerInstance = L.marker([lat, lng], { draggable: true }).addTo(mapInstance);
    circleInstance = L.circle([lat, lng], {
        color: '#818cf8',
        fillColor: '#818cf8',
        fillOpacity: 0.15,
        radius: form.value.gps_fence_radius,
        weight: 1.5
    }).addTo(mapInstance);

    // Drag release
    markerInstance.on('dragend', () => {
        const pos = markerInstance.getLatLng();
        form.value.latitude = Number(pos.lat.toFixed(6));
        form.value.longitude = Number(pos.lng.toFixed(6));
        circleInstance.setLatLng(pos);
    });

    // Click map
    mapInstance.on('click', (e: any) => {
        const pos = e.latlng;
        form.value.latitude = Number(pos.lat.toFixed(6));
        form.value.longitude = Number(pos.lng.toFixed(6));
        markerInstance.setLatLng(pos);
        circleInstance.setLatLng(pos);
    });
}

function updateMapFromInputs() {
    if (mapInstance && markerInstance && circleInstance) {
        const lat = Number(form.value.latitude);
        const lng = Number(form.value.longitude);
        if (!isNaN(lat) && !isNaN(lng)) {
            const latlng = [lat, lng];
            markerInstance.setLatLng(latlng);
            circleInstance.setLatLng(latlng);
            circleInstance.setRadius(form.value.gps_fence_radius);
            mapInstance.panTo(latlng);
        }
    }
}

// Watch location_id change to center map on the new site coordinates
watch(() => form.value.location_id, (newVal) => {
    if (newVal) {
        const coords = getSelectedLocationCoords();
        if (coords) {
            form.value.latitude = coords.latitude;
            form.value.longitude = coords.longitude;
            updateMapFromInputs();
        }
    }
});

// Watch inputs to update map markers
watch(() => [form.value.latitude, form.value.longitude, form.value.gps_fence_radius], () => {
    updateMapFromInputs();
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.checkpoint) {
            form.value = {
                location_id: props.checkpoint.location_id,
                name: props.checkpoint.name,
                description: props.checkpoint.description || '',
                scan_method: props.checkpoint.scan_method,
                qr_code: props.checkpoint.qr_code || '',
                nfc_tag_id: props.checkpoint.nfc_tag_id || '',
                gps_required: !!props.checkpoint.gps_required,
                gps_fence_radius: Number(props.checkpoint.gps_fence_radius ?? 15),
                latitude: props.checkpoint.latitude ? Number(props.checkpoint.latitude) : 34.671200,
                longitude: props.checkpoint.longitude ? Number(props.checkpoint.longitude) : 33.041200,
                photo_requirement: props.checkpoint.photo_requirement || 'off',
                note_requirement: props.checkpoint.note_requirement || 'off',
                voice_requirement: props.checkpoint.voice_requirement || 'off',
                signature_required: !!props.checkpoint.signature_required,
                incident_enabled: !!props.checkpoint.incident_enabled
            };
        } else {
            form.value = {
                location_id: '',
                name: '',
                description: '',
                scan_method: 'qr',
                qr_code: '',
                nfc_tag_id: '',
                gps_required: true,
                gps_fence_radius: 15,
                latitude: 34.671200,
                longitude: 33.041200,
                photo_requirement: 'off',
                note_requirement: 'off',
                voice_requirement: 'off',
                signature_required: false,
                incident_enabled: true
            };
        }
        nextTick(() => {
            setTimeout(initMap, 250);
        });
    } else {
        if (mapInstance) {
            mapInstance.remove();
            mapInstance = null;
        }
    }
});

onUnmounted(() => {
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }
});

function handleSubmit() {
    if (!form.value.location_id || !form.value.name) {
        alert('Please fill out the location and checkpoint name.');
        return;
    }
    emit('submit', { ...form.value });
}
</script>

<template>
    <div 
        v-if="show"
        class="fixed inset-0 z-50 bg-slate-955/80 backdrop-blur-sm flex items-center justify-center p-6 transition-all duration-300"
    >
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 w-full max-w-4xl space-y-4 shadow-2xl overflow-y-auto max-h-[90vh]">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-800">
                <h4 class="text-sm font-black uppercase tracking-widest text-slate-800 dark:text-slate-100 font-mono">
                    {{ checkpoint ? 'Edit Checkpoint' : 'Add New Checkpoint' }}
                </h4>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-650 dark:hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Two-Column Grid Setup -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-xs text-slate-700 dark:text-slate-300">
                <!-- Left Column: Checkpoint Details Form -->
                <div class="space-y-4">
                    <!-- Site Location Selection -->
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">Location Site *</label>
                        <select 
                            v-model="form.location_id" 
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-250 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 min-h-[44px]"
                        >
                            <option value="">Select a registered location...</option>
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>

                    <!-- Checkpoint Name & Description -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">Checkpoint Name *</label>
                            <input 
                                v-model="form.name" 
                                type="text" 
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-250 dark:border-slate-800 p-3 rounded-xl text-slate-850 dark:text-slate-100 focus:border-indigo-500 focus:outline-none min-h-[44px]" 
                                placeholder="e.g. Server Room Entrance" 
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">Description</label>
                            <input 
                                v-model="form.description" 
                                type="text" 
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-250 dark:border-slate-800 p-3 rounded-xl text-slate-850 dark:text-slate-100 focus:border-indigo-500 focus:outline-none min-h-[44px]" 
                                placeholder="Verify locks, sensors, etc." 
                            />
                        </div>
                    </div>

                    <!-- Hardware Scanning Setup -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">Scan Method</label>
                            <select 
                                v-model="form.scan_method" 
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-250 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:border-indigo-500 focus:outline-none min-h-[44px]"
                            >
                                <option value="qr">QR Code Only</option>
                                <option value="nfc">NFC Tag Only</option>
                                <option value="both">Both Methods</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">QR Value</label>
                            <input 
                                v-model="form.qr_code" 
                                type="text" 
                                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-250 dark:border-slate-800 p-3 rounded-xl text-slate-850 dark:text-slate-100 focus:border-indigo-500 focus:outline-none min-h-[44px]" 
                                placeholder="Auto-gen if empty" 
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">NFC Tag ID</label>
                            <input 
                                v-model="form.nfc_tag_id" 
                                type="text" 
                                class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-250 dark:border-slate-800 p-3 rounded-xl text-slate-850 dark:text-slate-100 focus:border-indigo-500 focus:outline-none min-h-[44px]" 
                                placeholder="e.g. tag-109a" 
                            />
                        </div>
                    </div>

                    <!-- GPS Coordinates Inputs -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 dark:bg-slate-950/40 p-4 rounded-xl border border-slate-150 dark:border-slate-800">
                        <div class="flex flex-col justify-center space-y-1.5">
                            <span class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">GPS Verification</span>
                            <label class="inline-flex items-center cursor-pointer min-h-[40px]">
                                <input type="checkbox" v-model="form.gps_required" class="sr-only peer" />
                                <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-650"></div>
                                <span class="ms-3 text-xs font-bold text-slate-650 dark:text-slate-300">Required</span>
                            </label>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">Latitude</label>
                            <input v-model.number="form.latitude" type="number" step="0.000001" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 p-2.5 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[40px]" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">Longitude</label>
                            <input v-model.number="form.longitude" type="number" step="0.000001" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 p-2.5 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[40px]" />
                        </div>
                        <div v-if="form.gps_required" class="space-y-1 md:col-span-3 pt-2">
                            <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">GPS Fence Radius (meters)</label>
                            <div class="flex items-center space-x-3">
                                <input 
                                    type="range" 
                                    min="5" 
                                    max="100" 
                                    step="5"
                                    v-model.number="form.gps_fence_radius" 
                                    class="flex-1 accent-indigo-600 h-2 bg-slate-200 dark:bg-slate-850 rounded-lg appearance-none cursor-pointer" 
                                />
                                <span class="w-12 text-center font-mono font-bold text-indigo-500 dark:text-indigo-400 text-xs">
                                    {{ form.gps_fence_radius }}m
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Media requirements & switches -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">Photo Required</label>
                            <select v-model="form.photo_requirement" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-250 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[44px]">
                                <option value="off">Off</option>
                                <option value="optional">Optional</option>
                                <option value="required">Required</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">Note Required</label>
                            <select v-model="form.note_requirement" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-250 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[44px]">
                                <option value="off">Off</option>
                                <option value="optional">Optional</option>
                                <option value="required">Required</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-widest font-black">Voice Required</label>
                            <select v-model="form.voice_requirement" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-250 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[44px]">
                                <option value="off">Off</option>
                                <option value="optional">Optional</option>
                                <option value="required">Required</option>
                            </select>
                        </div>
                    </div>

                    <!-- Toggle options -->
                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center p-3 bg-slate-50 dark:bg-slate-950/40 border border-slate-150 dark:border-slate-800 rounded-xl cursor-pointer min-h-[44px]">
                            <input type="checkbox" v-model="form.signature_required" class="accent-indigo-650 w-4 h-4 rounded" />
                            <span class="ms-3 text-xs font-bold text-slate-650 dark:text-slate-300">Requires Signature</span>
                        </label>
                        <label class="flex items-center p-3 bg-slate-50 dark:bg-slate-950/40 border border-slate-150 dark:border-slate-800 rounded-xl cursor-pointer min-h-[44px]">
                            <input type="checkbox" v-model="form.incident_enabled" class="accent-indigo-650 w-4 h-4 rounded" />
                            <span class="ms-3 text-xs font-bold text-slate-650 dark:text-slate-300">Allow Incident Reports</span>
                        </label>
                    </div>
                </div>

                <!-- Right Column: Checkpoint Map selector -->
                <div class="flex flex-col h-full justify-between">
                    <div class="space-y-2">
                        <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Select Checkpoint Location on Map</label>
                        <div 
                            ref="mapContainer" 
                            class="w-full h-[360px] rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 overflow-hidden z-10"
                        ></div>
                        <p class="text-[10px] text-slate-450 dark:text-slate-550 font-medium">Click on the map or drag the pin to set the exact coordinates of the checkpoint.</p>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="flex space-x-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button 
                    @click="$emit('close')" 
                    class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-300 text-xs font-black uppercase tracking-wider rounded-xl transition-all min-h-[48px]"
                >
                    Cancel
                </button>
                <button 
                    @click="handleSubmit" 
                    class="flex-1 py-3 bg-gradient-to-r from-indigo-500 to-purple-650 hover:from-indigo-600 hover:to-purple-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-all active:scale-95 min-h-[48px]"
                >
                    {{ checkpoint ? 'Save Changes' : 'Create Checkpoint' }}
                </button>
            </div>
        </div>
    </div>
</template>
