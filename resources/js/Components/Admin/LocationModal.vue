<script setup lang="ts">
import { nextTick, onUnmounted, ref, watch } from 'vue';

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
    geofence_radius: 200,
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

    L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        {
            attribution: '&copy; OpenStreetMap &copy; CARTO',
        },
    ).addTo(mapInstance);

    markerInstance = L.marker([lat, lng], { draggable: true }).addTo(
        mapInstance,
    );
    circleInstance = L.circle([lat, lng], {
        color: '#4f46e5',
        fillColor: '#818cf8',
        fillOpacity: 0.15,
        radius: form.value.geofence_radius,
        weight: 1.5,
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
watch(
    () => [
        form.value.latitude,
        form.value.longitude,
        form.value.geofence_radius,
    ],
    () => {
        updateMapFromInputs();
    },
);

watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            if (props.location) {
                form.value = {
                    name: props.location.name,
                    address: props.location.address || '',
                    city: props.location.city || 'Limassol',
                    country: props.location.country || 'Cyprus',
                    latitude: Number(props.location.latitude),
                    longitude: Number(props.location.longitude),
                    geofence_radius: Number(props.location.geofence_radius),
                };
            } else {
                form.value = {
                    name: '',
                    address: '',
                    city: 'Limassol',
                    country: 'Cyprus',
                    latitude: 34.671234,
                    longitude: 33.041234,
                    geofence_radius: 200,
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
    },
);

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
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm transition-all duration-300"
    >
        <div
            class="w-full max-w-3xl space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800"
            >
                <h4
                    class="font-mono text-sm font-black uppercase tracking-widest text-slate-800 dark:text-slate-100"
                >
                    {{ location ? 'Edit Site Location' : 'Add Site Location' }}
                </h4>
                <button
                    @click="$emit('close')"
                    class="hover:text-slate-650 text-slate-400 dark:hover:text-slate-200"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- Two Column Layout -->
            <div
                class="dark:text-slate-350 grid grid-cols-1 gap-6 text-xs text-slate-700 md:grid-cols-2"
            >
                <!-- Left Column: Input Fields -->
                <div class="space-y-3.5">
                    <div class="space-y-1">
                        <label
                            class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                            >Location Name *</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            class="dark:bg-slate-955 min-h-[44px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:text-slate-100"
                            placeholder="Marina Port Hub"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-slate-455 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                            >Address</label
                        >
                        <input
                            v-model="form.address"
                            type="text"
                            class="dark:bg-slate-955 min-h-[44px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:text-slate-100"
                            placeholder="Marina Street 12"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label
                                class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                                >Latitude</label
                            >
                            <input
                                v-model.number="form.latitude"
                                type="number"
                                step="0.000001"
                                class="dark:bg-slate-955 min-h-[44px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:text-slate-100"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                                >Longitude</label
                            >
                            <input
                                v-model.number="form.longitude"
                                type="number"
                                step="0.000001"
                                class="dark:bg-slate-955 min-h-[44px] w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:text-slate-100"
                            />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                            >Geofence Radius (meters)</label
                        >
                        <div class="flex items-center space-x-3">
                            <input
                                type="range"
                                min="50"
                                max="1000"
                                step="50"
                                v-model.number="form.geofence_radius"
                                class="accent-indigo-650 h-2 flex-1 cursor-pointer appearance-none rounded-lg bg-slate-200 dark:bg-slate-800"
                            />
                            <span
                                class="w-12 text-center font-mono font-bold text-indigo-500 dark:text-indigo-400"
                            >
                                {{ form.geofence_radius }}m
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Map Selector -->
                <div class="flex flex-col">
                    <label
                        class="text-slate-450 mb-1.5 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                        >Select Fence Area on Map</label
                    >
                    <div
                        ref="mapContainer"
                        class="z-10 h-[230px] w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-800"
                    ></div>
                    <p
                        class="text-slate-450 dark:text-slate-550 mt-2 text-[10px] font-medium"
                    >
                        Click map or drag marker to set center coordinates and
                        view geofence coverage.
                    </p>
                </div>
            </div>

            <div
                class="flex space-x-3 border-t border-slate-100 pt-3 dark:border-slate-800"
            >
                <button
                    @click="$emit('close')"
                    class="dark:hover:bg-slate-750 dark:text-slate-350 min-h-[48px] flex-1 rounded-xl bg-slate-100 py-3 text-xs font-black uppercase tracking-wider text-slate-700 transition-all hover:bg-slate-200 dark:bg-slate-800"
                >
                    Cancel
                </button>
                <button
                    @click="handleSubmit"
                    class="to-purple-650 min-h-[48px] flex-1 rounded-xl bg-gradient-to-r from-indigo-500 py-3 text-xs font-black uppercase tracking-wider text-white shadow-md transition-all hover:from-indigo-600 hover:to-purple-700 active:scale-95"
                >
                    {{ location ? 'Save Changes' : 'Add Site' }}
                </button>
            </div>
        </div>
    </div>
</template>
