<script setup lang="ts">
import { nextTick, onUnmounted, ref, watch } from 'vue';

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
    latitude: 34.6712,
    longitude: 33.0412,
    photo_requirement: 'off',
    note_requirement: 'off',
    voice_requirement: 'off',
    signature_required: false,
    incident_enabled: true,
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
    const loc = props.locations.find(
        (l) => l.id === Number(form.value.location_id),
    );
    if (loc && loc.latitude && loc.longitude) {
        return {
            latitude: Number(loc.latitude),
            longitude: Number(loc.longitude),
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

    let lat = Number(form.value.latitude) || 34.6712;
    let lng = Number(form.value.longitude) || 33.0412;

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
        color: '#818cf8',
        fillColor: '#818cf8',
        fillOpacity: 0.15,
        radius: form.value.gps_fence_radius,
        weight: 1.5,
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
watch(
    () => form.value.location_id,
    (newVal) => {
        if (newVal) {
            const coords = getSelectedLocationCoords();
            if (coords) {
                form.value.latitude = coords.latitude;
                form.value.longitude = coords.longitude;
                updateMapFromInputs();
            }
        }
    },
);

// Watch inputs to update map markers
watch(
    () => [
        form.value.latitude,
        form.value.longitude,
        form.value.gps_fence_radius,
    ],
    () => {
        updateMapFromInputs();
    },
);

watch(
    () => props.show,
    (newVal) => {
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
                    gps_fence_radius: Number(
                        props.checkpoint.gps_fence_radius ?? 15,
                    ),
                    latitude: props.checkpoint.latitude
                        ? Number(props.checkpoint.latitude)
                        : 34.6712,
                    longitude: props.checkpoint.longitude
                        ? Number(props.checkpoint.longitude)
                        : 33.0412,
                    photo_requirement:
                        props.checkpoint.photo_requirement || 'off',
                    note_requirement:
                        props.checkpoint.note_requirement || 'off',
                    voice_requirement:
                        props.checkpoint.voice_requirement || 'off',
                    signature_required: !!props.checkpoint.signature_required,
                    incident_enabled: !!props.checkpoint.incident_enabled,
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
                    latitude: 34.6712,
                    longitude: 33.0412,
                    photo_requirement: 'off',
                    note_requirement: 'off',
                    voice_requirement: 'off',
                    signature_required: false,
                    incident_enabled: true,
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
        class="bg-slate-955/80 fixed inset-0 z-50 flex items-center justify-center p-6 backdrop-blur-sm transition-all duration-300"
    >
        <div
            class="max-h-[90vh] w-full max-w-4xl space-y-4 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800"
            >
                <h4
                    class="font-mono text-sm font-black uppercase tracking-widest text-slate-800 dark:text-slate-100"
                >
                    {{ checkpoint ? 'Edit Checkpoint' : 'Add New Checkpoint' }}
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

            <!-- Two-Column Grid Setup -->
            <div
                class="grid grid-cols-1 gap-6 text-xs text-slate-700 lg:grid-cols-2 dark:text-slate-300"
            >
                <!-- Left Column: Checkpoint Details Form -->
                <div class="space-y-4">
                    <!-- Site Location Selection -->
                    <div class="space-y-1">
                        <label
                            class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                            >Location Site *</label
                        >
                        <select
                            v-model="form.location_id"
                            class="border-slate-250 min-h-[44px] w-full rounded-xl border bg-slate-50 p-3 text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                        >
                            <option value="">
                                Select a registered location...
                            </option>
                            <option
                                v-for="l in locations"
                                :key="l.id"
                                :value="l.id"
                            >
                                {{ l.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Checkpoint Name & Description -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >Checkpoint Name *</label
                            >
                            <input
                                v-model="form.name"
                                type="text"
                                class="border-slate-250 text-slate-850 min-h-[44px] w-full rounded-xl border bg-slate-50 p-3 focus:border-indigo-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                                placeholder="e.g. Server Room Entrance"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >Description</label
                            >
                            <input
                                v-model="form.description"
                                type="text"
                                class="border-slate-250 text-slate-850 min-h-[44px] w-full rounded-xl border bg-slate-50 p-3 focus:border-indigo-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                                placeholder="Verify locks, sensors, etc."
                            />
                        </div>
                    </div>

                    <!-- Hardware Scanning Setup -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >Scan Method</label
                            >
                            <select
                                v-model="form.scan_method"
                                class="border-slate-250 min-h-[44px] w-full rounded-xl border bg-slate-50 p-3 text-slate-800 focus:border-indigo-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="qr">QR Code Only</option>
                                <option value="nfc">NFC Tag Only</option>
                                <option value="both">Both Methods</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >QR Value</label
                            >
                            <input
                                v-model="form.qr_code"
                                type="text"
                                class="border-slate-250 text-slate-850 min-h-[44px] w-full rounded-xl border bg-slate-50 p-3 focus:border-indigo-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                                placeholder="Auto-gen if empty"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >NFC Tag ID</label
                            >
                            <input
                                v-model="form.nfc_tag_id"
                                type="text"
                                class="dark:bg-slate-955 border-slate-250 text-slate-850 min-h-[44px] w-full rounded-xl border bg-slate-50 p-3 focus:border-indigo-500 focus:outline-none dark:border-slate-800 dark:text-slate-100"
                                placeholder="e.g. tag-109a"
                            />
                        </div>
                    </div>

                    <!-- GPS Coordinates Inputs -->
                    <div
                        class="border-slate-150 grid grid-cols-1 gap-4 rounded-xl border bg-slate-50 p-4 md:grid-cols-3 dark:border-slate-800 dark:bg-slate-950/40"
                    >
                        <div class="flex flex-col justify-center space-y-1.5">
                            <span
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >GPS Verification</span
                            >
                            <label
                                class="inline-flex min-h-[40px] cursor-pointer items-center"
                            >
                                <input
                                    type="checkbox"
                                    v-model="form.gps_required"
                                    class="peer sr-only"
                                />
                                <div
                                    class="peer-checked:bg-indigo-650 peer relative h-6 w-11 rounded-full bg-slate-200 after:absolute after:start-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none dark:bg-slate-800"
                                ></div>
                                <span
                                    class="text-slate-650 ms-3 text-xs font-bold dark:text-slate-300"
                                    >Required</span
                                >
                            </label>
                        </div>
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >Latitude</label
                            >
                            <input
                                v-model.number="form.latitude"
                                type="number"
                                step="0.000001"
                                class="dark:bg-slate-955 min-h-[40px] w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-slate-800 focus:outline-none dark:border-slate-800 dark:text-slate-100"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >Longitude</label
                            >
                            <input
                                v-model.number="form.longitude"
                                type="number"
                                step="0.000001"
                                class="dark:bg-slate-955 min-h-[40px] w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-slate-800 focus:outline-none dark:border-slate-800 dark:text-slate-100"
                            />
                        </div>
                        <div
                            v-if="form.gps_required"
                            class="space-y-1 pt-2 md:col-span-3"
                        >
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >GPS Fence Radius (meters)</label
                            >
                            <div class="flex items-center space-x-3">
                                <input
                                    type="range"
                                    min="5"
                                    max="100"
                                    step="5"
                                    v-model.number="form.gps_fence_radius"
                                    class="dark:bg-slate-850 h-2 flex-1 cursor-pointer appearance-none rounded-lg bg-slate-200 accent-indigo-600"
                                />
                                <span
                                    class="w-12 text-center font-mono text-xs font-bold text-indigo-500 dark:text-indigo-400"
                                >
                                    {{ form.gps_fence_radius }}m
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Media requirements & switches -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >Photo Required</label
                            >
                            <select
                                v-model="form.photo_requirement"
                                class="border-slate-250 min-h-[44px] w-full rounded-xl border bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="off">Off</option>
                                <option value="optional">Optional</option>
                                <option value="required">Required</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >Note Required</label
                            >
                            <select
                                v-model="form.note_requirement"
                                class="border-slate-250 min-h-[44px] w-full rounded-xl border bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="off">Off</option>
                                <option value="optional">Optional</option>
                                <option value="required">Required</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500"
                                >Voice Required</label
                            >
                            <select
                                v-model="form.voice_requirement"
                                class="border-slate-250 min-h-[44px] w-full rounded-xl border bg-slate-50 p-3 text-slate-800 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option value="off">Off</option>
                                <option value="optional">Optional</option>
                                <option value="required">Required</option>
                            </select>
                        </div>
                    </div>

                    <!-- Toggle options -->
                    <div class="grid grid-cols-2 gap-4">
                        <label
                            class="border-slate-150 flex min-h-[44px] cursor-pointer items-center rounded-xl border bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/40"
                        >
                            <input
                                type="checkbox"
                                v-model="form.signature_required"
                                class="accent-indigo-650 h-4 w-4 rounded"
                            />
                            <span
                                class="text-slate-650 ms-3 text-xs font-bold dark:text-slate-300"
                                >Requires Signature</span
                            >
                        </label>
                        <label
                            class="border-slate-150 flex min-h-[44px] cursor-pointer items-center rounded-xl border bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/40"
                        >
                            <input
                                type="checkbox"
                                v-model="form.incident_enabled"
                                class="accent-indigo-650 h-4 w-4 rounded"
                            />
                            <span
                                class="text-slate-650 ms-3 text-xs font-bold dark:text-slate-300"
                                >Allow Incident Reports</span
                            >
                        </label>
                    </div>
                </div>

                <!-- Right Column: Checkpoint Map selector -->
                <div class="flex h-full flex-col justify-between">
                    <div class="space-y-2">
                        <label
                            class="text-slate-450 block text-[10px] font-black uppercase tracking-widest dark:text-slate-500"
                            >Select Checkpoint Location on Map</label
                        >
                        <div
                            ref="mapContainer"
                            class="z-10 h-[360px] w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-800"
                        ></div>
                        <p
                            class="text-slate-450 dark:text-slate-550 text-[10px] font-medium"
                        >
                            Click on the map or drag the pin to set the exact
                            coordinates of the checkpoint.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div
                class="flex space-x-3 border-t border-slate-100 pt-4 dark:border-slate-800"
            >
                <button
                    @click="$emit('close')"
                    class="dark:hover:bg-slate-750 min-h-[48px] flex-1 rounded-xl bg-slate-100 py-3 text-xs font-black uppercase tracking-wider text-slate-700 transition-all hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                >
                    Cancel
                </button>
                <button
                    @click="handleSubmit"
                    class="to-purple-650 min-h-[48px] flex-1 rounded-xl bg-gradient-to-r from-indigo-500 py-3 text-xs font-black uppercase tracking-wider text-white shadow-md transition-all hover:from-indigo-600 hover:to-purple-700 active:scale-95"
                >
                    {{ checkpoint ? 'Save Changes' : 'Create Checkpoint' }}
                </button>
            </div>
        </div>
    </div>
</template>
