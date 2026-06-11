<script setup lang="ts">
import { ref, watch, nextTick, onUnmounted } from 'vue';

interface Location {
    id: number;
    name: string;
    address?: string;
    city?: string;
    country?: string;
    latitude: number;
    longitude: number;
    geofence_radius: number;
}

const props = defineProps<{
    show: boolean;
    location?: Location | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submit', form: any): void;
}>();

const form = ref({
    name: '',
    address: '',
    city: 'Limassol',
    country: 'Cyprus',
    latitude: 34.671234,
    longitude: 33.041234,
    geofence_radius: 200
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

async function initMap() {
    await loadLeaflet();
    const L = (window as any).L;
    if (!L || !mapContainer.value) return;

    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }

    const lat = Number(form.value.latitude) || 34.671234;
    const lng = Number(form.value.longitude) || 33.041234;

    mapInstance = L.map(mapContainer.value).setView([lat, lng], 14);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO'
    }).addTo(mapInstance);

    markerInstance = L.marker([lat, lng], { draggable: true }).addTo(mapInstance);
    circleInstance = L.circle([lat, lng], {
        color: '#4f46e5',
        fillColor: '#818cf8',
        fillOpacity: 0.15,
        radius: form.value.geofence_radius,
        weight: 1.5
    }).addTo(mapInstance);

    // Update form on drag
    markerInstance.on('dragend', () => {
        const pos = markerInstance.getLatLng();
        form.value.latitude = Number(pos.lat.toFixed(6));
        form.value.longitude = Number(pos.lng.toFixed(6));
        circleInstance.setLatLng(pos);
    });

    // Update on click
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
            circleInstance.setRadius(form.value.geofence_radius);
            mapInstance.panTo(latlng);
        }
    }
}

// Watch inputs to update map
watch(() => [form.value.latitude, form.value.longitude, form.value.geofence_radius], () => {
    updateMapFromInputs();
});

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.location) {
            form.value = {
                name: props.location.name,
                address: props.location.address || '',
                city: props.location.city || 'Limassol',
                country: props.location.country || 'Cyprus',
                latitude: Number(props.location.latitude),
                longitude: Number(props.location.longitude),
                geofence_radius: Number(props.location.geofence_radius)
            };
        } else {
            form.value = {
                name: '',
                address: '',
                city: 'Limassol',
                country: 'Cyprus',
                latitude: 34.671234,
                longitude: 33.041234,
                geofence_radius: 200
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
    if (!form.value.name) {
        alert('Please fill out the location name.');
        return;
    }
    emit('submit', { ...form.value });
}
</script>

<template>
    <div 
        v-if="show"
        class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6 transition-all duration-300"
    >
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 w-full max-w-3xl space-y-4 shadow-2xl">
            <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-800">
                <h4 class="text-sm font-black uppercase tracking-widest text-slate-800 dark:text-slate-100 font-mono">
                    {{ location ? 'Edit Site Location' : 'Add Site Location' }}
                </h4>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-650 dark:hover:text-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs text-slate-700 dark:text-slate-350">
                <!-- Left Column: Input Fields -->
                <div class="space-y-3.5">
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Location Name *</label>
                        <input v-model="form.name" type="text" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[44px]" placeholder="Marina Port Hub" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-455 dark:text-slate-500 uppercase tracking-widest font-black">Address</label>
                        <input v-model="form.address" type="text" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[44px]" placeholder="Marina Street 12" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Latitude</label>
                            <input v-model.number="form.latitude" type="number" step="0.000001" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[44px]" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Longitude</label>
                            <input v-model.number="form.longitude" type="number" step="0.000001" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 p-3 rounded-xl text-slate-800 dark:text-slate-100 focus:outline-none min-h-[44px]" />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black">Geofence Radius (meters)</label>
                        <div class="flex items-center space-x-3">
                            <input 
                                type="range" 
                                min="50" 
                                max="1000" 
                                step="50" 
                                v-model.number="form.geofence_radius" 
                                class="flex-1 accent-indigo-650 h-2 bg-slate-200 dark:bg-slate-800 rounded-lg appearance-none cursor-pointer" 
                            />
                            <span class="w-12 text-center font-mono font-bold text-indigo-500 dark:text-indigo-400">
                                {{ form.geofence_radius }}m
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Map Selector -->
                <div class="flex flex-col">
                    <label class="block text-[10px] text-slate-450 dark:text-slate-500 uppercase tracking-widest font-black mb-1.5">Select Fence Area on Map</label>
                    <div 
                        ref="mapContainer" 
                        class="w-full h-[230px] rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 overflow-hidden z-10"
                    ></div>
                    <p class="text-[10px] text-slate-450 dark:text-slate-550 mt-2 font-medium">Click map or drag marker to set center coordinates and view geofence coverage.</p>
                </div>
            </div>

            <div class="flex space-x-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button 
                    @click="$emit('close')" 
                    class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-350 text-xs font-black uppercase tracking-wider rounded-xl transition-all min-h-[48px]"
                >
                    Cancel
                </button>
                <button 
                    @click="handleSubmit" 
                    class="flex-1 py-3 bg-gradient-to-r from-indigo-500 to-purple-650 hover:from-indigo-600 hover:to-purple-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition-all active:scale-95 min-h-[48px]"
                >
                    {{ location ? 'Save Changes' : 'Add Site' }}
                </button>
            </div>
        </div>
    </div>
</template>
