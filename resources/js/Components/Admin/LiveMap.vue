<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';

interface Checkpoint {
    id: number;
    name: string;
    description: string;
}

interface Location {
    id: number;
    name: string;
    address?: string;
    latitude: string | number;
    longitude: string | number;
    geofence_radius: number;
    checkpoints?: Checkpoint[];
}

interface Guard {
    id: number;
    full_name: string;
    employee_id: string;
}

interface GuardLocationPing {
    id: number;
    latitude: string | number;
    longitude: string | number;
    is_online: boolean;
    battery_pct: number;
    pinged_at: string;
    security_guard?: Guard;
}

const props = defineProps<{
    locations: Location[];
    guardLocations: GuardLocationPing[];
}>();

const mapContainer = ref<HTMLDivElement | null>(null);
const isMapLoaded = ref(false);
const loadError = ref<string | null>(null);

let mapInstance: any = null;
let markersLayerGroup: any = null;
let geofenceLayerGroup: any = null;

function loadLeaflet(): Promise<void> {
    return new Promise((resolve, reject) => {
        if ((window as any).L) {
            resolve();
            return;
        }

        // CSS
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);

        // JS
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Leaflet library failed to load.'));
        document.head.appendChild(script);
    });
}

function initMap() {
    if (!mapContainer.value) return;

    const L = (window as any).L;
    if (!L) return;

    // Standard fallback center if no sites: Limassol, Cyprus
    let centerLat = 34.6786;
    let centerLon = 33.0413;
    let zoomLevel = 13;

    // Center on the first location that has valid coordinates
    const firstValidLoc = props.locations.find(loc => loc.latitude && loc.longitude);
    if (firstValidLoc) {
        centerLat = Number(firstValidLoc.latitude);
        centerLon = Number(firstValidLoc.longitude);
        zoomLevel = 15;
    }

    mapInstance = L.map(mapContainer.value, {
        center: [centerLat, centerLon],
        zoom: zoomLevel,
        zoomControl: true
    });

    // Dark-themed premium map style from CartoDB
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(mapInstance);

    markersLayerGroup = L.layerGroup().addTo(mapInstance);
    geofenceLayerGroup = L.layerGroup().addTo(mapInstance);

    renderLayers();
}

function renderLayers() {
    const L = (window as any).L;
    if (!L || !mapInstance || !markersLayerGroup || !geofenceLayerGroup) return;

    // Clear existing
    markersLayerGroup.clearLayers();
    geofenceLayerGroup.clearLayers();

    // 1. Draw Location Pins and Geofences
    props.locations.forEach(loc => {
        if (!loc.latitude || !loc.longitude) return;

        const lat = Number(loc.latitude);
        const lon = Number(loc.longitude);

        // Geofence Circle Overlay
        L.circle([lat, lon], {
            color: '#4f46e5', // Indigo 600
            fillColor: '#818cf8', // Indigo 400
            fillOpacity: 0.15,
            radius: loc.geofence_radius || 50,
            weight: 1.5
        }).addTo(geofenceLayerGroup);

        // Site Marker Icon (pure HTML/CSS)
        const siteIconHtml = `
            <div class="relative flex items-center justify-center w-8 h-8 rounded-full bg-indigo-600 border-2 border-white shadow-md text-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
        `;

        const siteIcon = L.divIcon({
            html: siteIconHtml,
            className: 'custom-leaflet-icon',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

        // Checkpoints popup details
        let checkpointsHtml = '';
        if (loc.checkpoints && loc.checkpoints.length > 0) {
            checkpointsHtml = `
                <div class="mt-2.5 pt-2.5 border-t border-slate-100">
                    <span class="block text-[9px] font-black uppercase tracking-wider text-slate-400 font-mono mb-1.5">Checkpoints (${loc.checkpoints.length})</span>
                    <ul class="space-y-1 pl-0 list-none m-0">
                        ${loc.checkpoints.map(cp => `
                            <li class="text-[10px] text-slate-650 flex items-center gap-1.5 font-bold">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                <span>${cp.name}</span>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            `;
        } else {
            checkpointsHtml = `
                <p class="text-[10px] text-slate-400 italic mt-2">No checkpoints registered on this site.</p>
            `;
        }

        const popupContent = `
            <div class="p-1 min-w-[200px]">
                <h5 class="text-xs font-black text-slate-900 uppercase font-mono tracking-wide m-0">${loc.name}</h5>
                <p class="text-[10px] text-slate-500 m-0 mt-0.5">${loc.address || 'No address details'}</p>
                <div class="mt-2 text-[9px] font-mono text-indigo-600 bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded inline-block">
                    Geofence: ${loc.geofence_radius}m radius
                </div>
                ${checkpointsHtml}
            </div>
        `;

        L.marker([lat, lon], { icon: siteIcon })
            .bindPopup(popupContent)
            .addTo(markersLayerGroup);
    });

    // 2. Draw Guards Live/Last-seen Coordinates
    props.guardLocations.forEach(ping => {
        if (!ping.latitude || !ping.longitude) return;

        const lat = Number(ping.latitude);
        const lon = Number(ping.longitude);

        // Pulse effect for online guards
        const statusColorClass = ping.is_online ? 'bg-emerald-500' : 'bg-slate-400';
        const pulseEffectHtml = ping.is_online 
            ? `<span class="absolute inset-0 rounded-full bg-emerald-500 animate-ping opacity-60"></span>` 
            : '';

        const guardIconHtml = `
            <div class="relative flex items-center justify-center w-8 h-8 rounded-full bg-white border-2 border-slate-800 shadow-lg">
                ${pulseEffectHtml}
                <div class="w-6 h-6 rounded-full ${statusColorClass} flex items-center justify-center text-white text-[9px] font-black font-mono">
                    ${ping.security_guard?.full_name ? ping.security_guard.full_name.slice(0, 2).toUpperCase() : 'GD'}
                </div>
            </div>
        `;

        const guardIcon = L.divIcon({
            html: guardIconHtml,
            className: 'custom-leaflet-icon',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

        const lastSeenDate = new Date(ping.pinged_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        const popupContent = `
            <div class="p-1 min-w-[180px]">
                <h5 class="text-xs font-black text-slate-950 uppercase font-mono tracking-wide m-0">
                    ${ping.security_guard?.full_name || 'Active Guard'}
                </h5>
                <p class="text-[10px] text-slate-500 m-0">ID: ${ping.security_guard?.employee_id || 'GD-00'}</p>
                <div class="mt-2.5 space-y-1 border-t border-slate-100 pt-2 text-[10px]">
                    <div class="flex justify-between">
                        <span class="text-slate-400 font-mono text-[9px] uppercase font-black">Status:</span>
                        <span class="font-bold ${ping.is_online ? 'text-emerald-600' : 'text-slate-500'}">
                            ${ping.is_online ? 'Online / Active' : 'Offline'}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400 font-mono text-[9px] uppercase font-black">Battery:</span>
                        <span class="font-bold font-mono">${ping.battery_pct}%</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400 font-mono text-[9px] uppercase font-black">Last Ping:</span>
                        <span class="font-bold font-mono text-slate-600">${lastSeenDate}</span>
                    </div>
                </div>
            </div>
        `;

        L.marker([lat, lon], { icon: guardIcon })
            .bindPopup(popupContent)
            .addTo(markersLayerGroup);
    });
}

// Watch locations or guard pings updates
watch(() => [props.locations, props.guardLocations], () => {
    renderLayers();
}, { deep: true });

onMounted(async () => {
    try {
        await loadLeaflet();
        isMapLoaded.value = true;
        // Small delay to ensure container height has fully rendered
        setTimeout(initMap, 150);
    } catch (e: any) {
        console.error(e);
        loadError.value = 'Failed to load live dispatch map tracking system.';
    }
});

onUnmounted(() => {
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }
});
</script>

<template>
    <div class="bg-white border border-slate-200/80 rounded-3xl p-5 shadow-sm space-y-3">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 font-mono flex items-center gap-1.5">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-600"></span>
                </span>
                <span>Live Operations Map</span>
            </h3>
            <span class="text-[9px] text-slate-400 font-mono uppercase tracking-widest">Real-time Location Tracking</span>
        </div>

        <div v-if="loadError" class="text-center py-10 text-xs text-rose-500 bg-rose-50/50 rounded-2xl border border-rose-100/80 font-mono">
            {{ loadError }}
        </div>

        <div 
            v-show="isMapLoaded"
            ref="mapContainer"
            class="w-full h-[380px] rounded-2xl overflow-hidden border border-slate-200/80 bg-slate-50 z-10"
        ></div>

        <!-- Map Legend -->
        <div v-if="isMapLoaded && !loadError" class="flex flex-wrap gap-4 text-[10px] text-slate-500 font-mono font-bold uppercase tracking-wider pt-2 border-t border-slate-100">
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600 border border-white shadow-xs"></span>
                <span>Site Locations</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 border border-white shadow-xs"></span>
                <span>Guards (Online)</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-slate-400 border border-white shadow-xs"></span>
                <span>Guards (Offline)</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-4 h-2 rounded bg-indigo-100 border border-indigo-400 opacity-60"></span>
                <span>Geofence Boundaries</span>
            </div>
        </div>
    </div>
</template>

<style>
/* Leaflet custom popup formatting overrides to look premium and match slate theme */
.leaflet-popup-content-wrapper {
    border-radius: 16px !important;
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02) !important;
}
.leaflet-popup-tip {
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
}
.custom-leaflet-icon {
    background: transparent !important;
    border: none !important;
}
</style>
