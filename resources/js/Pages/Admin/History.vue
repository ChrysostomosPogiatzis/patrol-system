<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, onUnmounted, ref, watch } from 'vue';

interface Guard {
    id: number;
    full_name: string;
    employee_id: string;
}

interface Route {
    id: number;
    name: string;
}

interface Incident {
    id: number;
    title: string;
    description?: string;
    priority: 'low' | 'medium' | 'high' | 'critical';
    status: string;
    created_at: string;
    resolved_at?: string;
    resolution_note?: string;
    security_guard?: Guard;
    checkpoint?: { name: string };
    location?: { name: string };
    tenant?: { name: string };
    media?: Array<{
        id: number;
        file_url: string;
        kind: string;
    }>;
}

interface SosAlert {
    id: number;
    status: 'active' | 'acknowledged' | 'resolved' | 'false_alarm';
    triggered_latitude?: number | string;
    triggered_longitude?: number | string;
    note?: string;
    resolution_note?: string;
    triggered_at: string;
    acknowledged_at?: string;
    resolved_at?: string;
    security_guard?: Guard;
    resolver?: { name: string };
    acknowledger?: { name: string };
    patrol?: {
        id: number;
        started_at: string;
        route?: Route;
    };
}

interface CheckpointLog {
    id: number;
    status: 'pending' | 'scanned' | 'skipped' | 'out_of_order_attempt';
    scanned_at?: string;
    skipped_at?: string;
    skip_reason?: string;
    note?: string;
    gps_within_fence?: boolean;
    gps_distance_metres?: number | null;
    battery_pct?: number | null;
    checkpoint: {
        name: string;
        description: string;
        latitude?: number | string;
        longitude?: number | string;
    };
    media?: Array<{
        id: number;
        file_url: string;
        kind: string;
    }>;
}

interface Patrol {
    id: number;
    status: 'pending' | 'in_progress' | 'completed' | 'abandoned';
    started_at: string;
    completed_at?: string;
    duration_seconds?: number;
    general_note?: string;
    completion_signature_url?: string;
    total_checkpoints: number;
    completed_checkpoints: number;
    skipped_checkpoints: number;
    route?: Route;
    security_guard?: Guard;
    incidents?: Incident[];
    checkpoint_logs?: CheckpointLog[];
    location_pings?: Array<{
        latitude: number | string;
        longitude: number | string;
        pinged_at: string;
    }>;
    tenant?: { name: string };
}

const patrols = ref<Patrol[]>([]);
const incidents = ref<Incident[]>([]);
const sosAlerts = ref<SosAlert[]>([]);
const guards = ref<Guard[]>([]);

// Filter states
const selectedGuard = ref<string>('');
const selectedTimeframe = ref<string>('7_days');
const searchQuery = ref<string>('');
const activeTab = ref<'patrols' | 'incidents' | 'sos'>('patrols');
const isLoading = ref<boolean>(false);

// Detail Modal state
const selectedPatrolDetails = ref<Patrol | null>(null);

let historyMapInstance: any = null;

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
        script.onerror = () =>
            reject(new Error('Leaflet library failed to load.'));
        document.head.appendChild(script);
    });
}

function initHistoryMap(patrol: Patrol) {
    const L = (window as any).L;
    if (!L) return;

    const mapEl = document.getElementById('history-patrol-map');
    if (!mapEl) return;

    if (historyMapInstance) {
        historyMapInstance.remove();
        historyMapInstance = null;
    }

    // Default center Cyprus
    let centerLat = 34.6786;
    let centerLon = 33.0413;
    let zoomLevel = 13;

    // Check if there are location pings to center the map
    const pings = patrol.location_pings || [];
    if (pings.length > 0) {
        centerLat = Number(pings[0].latitude);
        centerLon = Number(pings[0].longitude);
        zoomLevel = 15;
    } else if (patrol.checkpoint_logs && patrol.checkpoint_logs.length > 0) {
        const firstWithCoords = patrol.checkpoint_logs.find(
            (log) => log.checkpoint?.latitude && log.checkpoint?.longitude,
        );
        if (firstWithCoords) {
            centerLat = Number(firstWithCoords.checkpoint.latitude);
            centerLon = Number(firstWithCoords.checkpoint.longitude);
            zoomLevel = 15;
        }
    }

    historyMapInstance = L.map('history-patrol-map', {
        center: [centerLat, centerLon],
        zoom: zoomLevel,
        zoomControl: true,
    });

    L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        {
            attribution: '&copy; OpenStreetMap &copy; CARTO',
            subdomains: 'abcd',
            maxZoom: 20,
        },
    ).addTo(historyMapInstance);

    const markersGroup = L.layerGroup().addTo(historyMapInstance);

    // Draw checkpoint markers
    if (patrol.checkpoint_logs && patrol.checkpoint_logs.length > 0) {
        patrol.checkpoint_logs.forEach((log) => {
            if (log.checkpoint?.latitude && log.checkpoint?.longitude) {
                const lat = Number(log.checkpoint.latitude);
                const lon = Number(log.checkpoint.longitude);

                // Color based on status: scanned = green, skipped = yellow, pending/other = blue
                const color =
                    log.status === 'scanned'
                        ? '#10b981'
                        : log.status === 'skipped'
                          ? '#f59e0b'
                          : '#3b82f6';

                L.circleMarker([lat, lon], {
                    radius: 6,
                    color: color,
                    fillColor: '#ffffff',
                    fillOpacity: 1,
                    weight: 2,
                })
                    .bindTooltip(`${log.checkpoint.name} (${log.status})`, {
                        permanent: false,
                        direction: 'top',
                        className:
                            'font-mono text-[9px] font-bold px-1.5 py-0.5 rounded shadow-sm border border-slate-200',
                    })
                    .addTo(markersGroup);
            }
        });
    }

    // Draw GPS Polyline
    if (pings.length > 0) {
        const coords = pings.map((p) => [
            Number(p.latitude),
            Number(p.longitude),
        ]);
        L.polyline(coords, {
            color: '#ec4899', // Pink 500
            weight: 3.5,
            opacity: 0.85,
        }).addTo(historyMapInstance);

        // Zoom map to fit the polyline/bounds of pings
        try {
            const bounds = L.latLngBounds(coords);
            historyMapInstance.fitBounds(bounds, { padding: [20, 20] });
        } catch (e) {
            console.error('Failed to fit bounds:', e);
        }
    }
}

watch(selectedPatrolDetails, async (newVal) => {
    if (newVal) {
        await loadLeaflet();
        setTimeout(() => {
            initHistoryMap(newVal);
        }, 150);
    } else {
        if (historyMapInstance) {
            historyMapInstance.remove();
            historyMapInstance = null;
        }
    }
});

async function fetchHistory() {
    isLoading.value = true;
    try {
        const response = await axios.get('/admin/api/history', {
            params: {
                guard_id: selectedGuard.value || undefined,
                timeframe: selectedTimeframe.value,
            },
        });
        patrols.value = response.data.patrols || [];
        incidents.value = response.data.incidents || [];
        sosAlerts.value = response.data.sos_alerts || [];
        guards.value = response.data.guards || [];
    } catch (e) {
        console.error('Failed to load history data:', e);
    } finally {
        isLoading.value = false;
    }
}

// Watch filters
watch([selectedGuard, selectedTimeframe], () => {
    fetchHistory();
});

onMounted(() => {
    fetchHistory();
});

onUnmounted(() => {
    if (historyMapInstance) {
        historyMapInstance.remove();
        historyMapInstance = null;
    }
});

function formatDuration(seconds?: number): string {
    if (seconds === undefined || seconds === null) return 'N/A';
    const cleanSeconds = Math.max(0, seconds);
    const h = Math.floor(cleanSeconds / 3600);
    const m = Math.floor((cleanSeconds % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    return `${m} mins`;
}

function formatDate(dateStr: string): string {
    const d = new Date(dateStr);
    return (
        d.toLocaleDateString() +
        ' ' +
        d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    );
}

function getPriorityClass(priority: string) {
    switch (priority) {
        case 'critical':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'high':
            return 'bg-red-50 text-red-600 border-red-200';
        case 'medium':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        default:
            return 'bg-slate-100 text-slate-650 border-slate-200';
    }
}
</script>

<template>
    <Head title="Patrol & Incident History" />

    <AdminLayout title="Patrol & Incident History">
        <!-- FILTER BAR -->
        <div
            class="mb-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
        >
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <!-- Guard Filter -->
                <div class="flex flex-col">
                    <label
                        class="text-slate-450 mb-1.5 font-mono text-[10px] font-black uppercase tracking-wider"
                        >Security Guard</label
                    >
                    <select
                        v-model="selectedGuard"
                        class="min-h-[44px] cursor-pointer rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    >
                        <option value="">All Security Guards</option>
                        <option v-for="g in guards" :key="g.id" :value="g.id">
                            {{ g.full_name }} ({{ g.employee_id }})
                        </option>
                    </select>
                </div>

                <!-- Timeframe Filter -->
                <div class="flex flex-col">
                    <label
                        class="text-slate-450 mb-1.5 font-mono text-[10px] font-black uppercase tracking-wider"
                        >Time Period</label
                    >
                    <select
                        v-model="selectedTimeframe"
                        class="min-h-[44px] cursor-pointer rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    >
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="7_days">Last 7 Days</option>
                        <option value="30_days">Last 30 Days</option>
                        <option value="all">All Time</option>
                    </select>
                </div>

                <!-- Search Query -->
                <div class="flex flex-col">
                    <label
                        class="text-slate-450 mb-1.5 font-mono text-[10px] font-black uppercase tracking-wider"
                        >Search text</label
                    >
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search routes, guards, notes..."
                        class="min-h-[44px] rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                </div>
            </div>
        </div>

        <!-- TABS BAR -->
        <div class="mb-6 flex border-b border-slate-200">
            <button
                @click="activeTab = 'patrols'"
                class="border-b-2 px-5 py-3 font-mono text-xs font-black uppercase tracking-wider transition-all"
                :class="
                    activeTab === 'patrols'
                        ? 'border-indigo-650 text-indigo-650'
                        : 'border-transparent text-slate-400 hover:text-slate-600'
                "
            >
                📋 Patrol Shifts ({{ patrols.length }})
            </button>
            <button
                @click="activeTab = 'incidents'"
                class="border-b-2 px-5 py-3 font-mono text-xs font-black uppercase tracking-wider transition-all"
                :class="
                    activeTab === 'incidents'
                        ? 'border-indigo-650 text-indigo-650'
                        : 'border-transparent text-slate-400 hover:text-slate-600'
                "
            >
                ⚠️ All Incidents Log ({{ incidents.length }})
            </button>
            <button
                @click="activeTab = 'sos'"
                class="border-b-2 px-5 py-3 font-mono text-xs font-black uppercase tracking-wider transition-all"
                :class="
                    activeTab === 'sos'
                        ? 'border-indigo-650 text-indigo-650'
                        : 'border-transparent text-slate-400 hover:text-slate-600'
                "
            >
                🚨 Emergency SOS Log ({{ sosAlerts.length }})
            </button>
        </div>

        <!-- LOADING INDICATOR -->
        <div
            v-if="isLoading"
            class="flex flex-col items-center justify-center gap-3 py-20 text-slate-400"
        >
            <div
                class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-500/20 border-t-indigo-600"
            ></div>
            <span class="font-mono text-xs font-bold uppercase tracking-wider"
                >Fetching History logs...</span
            >
        </div>

        <!-- NO DATA STATE -->
        <div
            v-else-if="activeTab === 'patrols' && patrols.length === 0"
            class="rounded-3xl border border-slate-200/80 bg-white p-12 py-16 text-center"
        >
            <p
                class="font-mono text-xs font-bold uppercase tracking-widest text-slate-400"
            >
                No patrol history logs match the filters.
            </p>
        </div>
        <div
            v-else-if="activeTab === 'incidents' && incidents.length === 0"
            class="rounded-3xl border border-slate-200/80 bg-white p-12 py-16 text-center"
        >
            <p
                class="font-mono text-xs font-bold uppercase tracking-widest text-slate-400"
            >
                No incidents recorded in this timeframe.
            </p>
        </div>
        <div
            v-else-if="activeTab === 'sos' && sosAlerts.length === 0"
            class="rounded-3xl border border-slate-200/80 bg-white p-12 py-16 text-center"
        >
            <p
                class="font-mono text-xs font-bold uppercase tracking-widest text-slate-400"
            >
                No emergency SOS alarms logged in this timeframe.
            </p>
        </div>

        <!-- TABS VIEWS -->
        <div v-else class="space-y-4">
            <!-- PATROL SHIFTS VIEW -->
            <template v-if="activeTab === 'patrols'">
                <div
                    v-for="patrol in patrols"
                    :key="patrol.id"
                    class="space-y-4 rounded-2xl border border-slate-200/85 bg-white p-5 shadow-sm transition-colors hover:border-slate-300"
                >
                    <!-- Header -->
                    <div
                        class="flex flex-col justify-between gap-2 border-b border-slate-100 pb-3 sm:flex-row sm:items-center"
                    >
                        <div class="space-y-0.5">
                            <span
                                class="text-xs font-black uppercase tracking-wide text-slate-800"
                            >
                                {{ patrol.route?.name || 'Manual Patrol' }}
                            </span>
                            <div
                                class="text-slate-450 flex items-center space-x-2 font-mono text-[10px] font-bold"
                            >
                                <span
                                    >Guard:
                                    {{
                                        patrol.security_guard?.full_name ||
                                        'Deleted Guard'
                                    }}</span
                                >
                                <span>•</span>
                                <span
                                    >Started:
                                    {{ formatDate(patrol.started_at) }}</span
                                >
                                <span
                                    v-if="patrol.tenant"
                                    class="ml-1 rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[8px] font-black uppercase text-slate-600"
                                >
                                    {{ patrol.tenant.name }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <!-- Progress Badge -->
                            <span
                                class="rounded-lg border border-indigo-100 bg-indigo-50 px-2 py-0.5 font-mono text-[9px] font-bold text-indigo-600"
                            >
                                {{ patrol.completed_checkpoints }}/{{
                                    patrol.total_checkpoints
                                }}
                                Checkpoints
                            </span>
                            <!-- Status Badge -->
                            <span
                                class="rounded-lg border px-2 py-0.5 font-mono text-[9px] font-black uppercase tracking-wider"
                                :class="[
                                    patrol.status === 'completed'
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-600'
                                        : '',
                                    patrol.status === 'in_progress'
                                        ? 'border-indigo-200 bg-indigo-50 text-indigo-600'
                                        : '',
                                    patrol.status === 'abandoned'
                                        ? 'border-rose-200 bg-rose-50 text-rose-600'
                                        : '',
                                ]"
                            >
                                {{ patrol.status }}
                            </span>
                        </div>
                    </div>

                    <!-- Statistics & Notes -->
                    <div class="grid grid-cols-1 gap-4 text-xs sm:grid-cols-3">
                        <div>
                            <span
                                class="block font-mono text-[9px] font-black uppercase tracking-wider text-slate-400"
                                >Completion Time</span
                            >
                            <span class="text-slate-650 font-mono font-bold">{{
                                patrol.completed_at
                                    ? formatDate(patrol.completed_at)
                                    : 'Active Shift'
                            }}</span>
                        </div>
                        <div>
                            <span
                                class="block font-mono text-[9px] font-black uppercase tracking-wider text-slate-400"
                                >Total Duration</span
                            >
                            <span class="text-slate-650 font-mono font-bold">{{
                                formatDuration(patrol.duration_seconds)
                            }}</span>
                        </div>
                        <div class="flex items-center justify-end">
                            <button
                                @click="selectedPatrolDetails = patrol"
                                class="rounded-xl bg-indigo-50/50 px-3.5 py-2 font-mono text-[10px] font-black uppercase tracking-widest text-indigo-600 transition-all hover:bg-indigo-50 hover:text-indigo-500"
                            >
                                View Log Details
                            </button>
                        </div>
                    </div>

                    <!-- NESTED INCIDENTS LOG FOR THIS PATROL -->
                    <div
                        v-if="patrol.incidents && patrol.incidents.length > 0"
                        class="border-amber-250/30 space-y-3 rounded-xl border bg-amber-50/40 p-4"
                    >
                        <h5
                            class="flex items-center space-x-1.5 font-mono text-[10px] font-black uppercase tracking-wider text-amber-700"
                        >
                            <svg
                                class="h-3.5 w-3.5"
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
                            <span
                                >Patrol Incidents Recorded ({{
                                    patrol.incidents.length
                                }})</span
                            >
                        </h5>
                        <div class="space-y-2.5">
                            <div
                                v-for="inc in patrol.incidents"
                                :key="inc.id"
                                class="shadow-xs flex flex-col justify-between gap-2 rounded-lg border border-amber-200/60 bg-white p-3 sm:flex-row sm:items-center"
                            >
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >{{ inc.title }}</span
                                        >
                                        <span
                                            class="rounded border px-1.5 py-0.5 font-mono text-[8px] font-black uppercase tracking-wider"
                                            :class="
                                                getPriorityClass(inc.priority)
                                            "
                                        >
                                            {{ inc.priority }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        {{
                                            inc.description ||
                                            'No description provided.'
                                        }}
                                    </p>
                                    <!-- Incident Photos -->
                                    <div
                                        v-if="inc.media && inc.media.length > 0"
                                        class="mt-2 flex flex-wrap gap-2"
                                    >
                                        <a
                                            v-for="m in inc.media"
                                            :key="m.id"
                                            :href="m.file_url"
                                            target="_blank"
                                            class="flex h-16 w-16 cursor-pointer items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100 transition-opacity hover:opacity-90"
                                        >
                                            <img
                                                :src="m.file_url"
                                                alt="Incident Media"
                                                class="h-full w-full object-cover"
                                            />
                                        </a>
                                    </div>
                                    <div
                                        class="mt-1.5 flex items-center space-x-2 font-mono text-[9px] font-bold text-slate-400"
                                    >
                                        <span v-if="inc.checkpoint"
                                            >Checkpoint:
                                            {{ inc.checkpoint.name }}</span
                                        >
                                    </div>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-1.5 text-right sm:flex-col sm:items-end sm:justify-center"
                                >
                                    <span
                                        class="rounded border px-1.5 py-0.5 font-mono text-[8px] font-black uppercase tracking-widest"
                                        :class="
                                            inc.status === 'resolved'
                                                ? 'border-emerald-150 bg-emerald-50 text-emerald-600'
                                                : 'border-rose-150 bg-rose-50 text-rose-600'
                                        "
                                    >
                                        {{ inc.status }}
                                    </span>
                                    <span
                                        class="font-mono text-[9px] font-semibold text-slate-400"
                                        >{{ formatDate(inc.created_at) }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template v-else-if="activeTab === 'incidents'">
                <div
                    v-for="inc in incidents"
                    :key="inc.id"
                    class="space-y-3 rounded-2xl border border-slate-200/85 bg-white p-5 shadow-sm transition-colors hover:border-slate-300"
                >
                    <div
                        class="flex flex-col justify-between gap-2 border-b border-slate-100 pb-3 sm:flex-row sm:items-center"
                    >
                        <div class="space-y-0.5">
                            <div
                                class="flex flex-wrap items-center gap-y-1 space-x-2"
                            >
                                <span
                                    class="text-xs font-black uppercase tracking-wide text-slate-800"
                                    >{{ inc.title }}</span
                                >
                                <span
                                    class="rounded border px-1.5 py-0.5 font-mono text-[8px] font-black uppercase tracking-wider"
                                    :class="getPriorityClass(inc.priority)"
                                >
                                    {{ inc.priority }}
                                </span>
                                <span
                                    v-if="inc.tenant"
                                    class="text-slate-650 rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[8px] font-black uppercase"
                                >
                                    {{ inc.tenant.name }}
                                </span>
                            </div>
                            <div
                                class="text-slate-450 flex items-center space-x-2 font-mono text-[10px] font-bold"
                            >
                                <span
                                    >Guard:
                                    {{
                                        inc.security_guard?.full_name ||
                                        'Deleted Guard'
                                    }}</span
                                >
                                <span>•</span>
                                <span
                                    >Time:
                                    {{ formatDate(inc.created_at) }}</span
                                >
                            </div>
                        </div>
                        <div>
                            <span
                                class="rounded-lg border px-2.5 py-1 font-mono text-[9px] font-black uppercase tracking-widest"
                                :class="
                                    inc.status === 'resolved'
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-600'
                                        : 'border-rose-200 bg-rose-50 text-rose-600'
                                "
                            >
                                {{ inc.status }}
                            </span>
                        </div>
                    </div>

                    <div class="text-slate-650 space-y-2 text-xs">
                        <p>
                            {{ inc.description || 'No description provided.' }}
                        </p>
                        <!-- Incident Photos -->
                        <div
                            v-if="inc.media && inc.media.length > 0"
                            class="flex flex-wrap gap-2 py-1"
                        >
                            <a
                                v-for="m in inc.media"
                                :key="m.id"
                                :href="m.file_url"
                                target="_blank"
                                class="flex h-20 w-20 cursor-pointer items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 transition-opacity hover:opacity-90"
                            >
                                <img
                                    :src="m.file_url"
                                    alt="Incident Media"
                                    class="h-full w-full object-cover"
                                />
                            </a>
                        </div>
                        <div
                            class="text-slate-450 grid grid-cols-1 gap-3 font-mono text-[10px] font-bold uppercase sm:grid-cols-2"
                        >
                            <div>
                                Site Location: {{ inc.location?.name || 'N/A' }}
                            </div>
                            <div>
                                Checkpoint:
                                {{
                                    inc.checkpoint?.name ||
                                    'General Route Incident'
                                }}
                            </div>
                        </div>
                    </div>

                    <!-- Resolution logs -->
                    <div
                        v-if="inc.status === 'resolved'"
                        class="border-emerald-150/40 space-y-1 rounded-xl border bg-emerald-50/30 p-3.5 text-xs text-emerald-950"
                    >
                        <span
                            class="block font-mono text-[9px] font-black uppercase tracking-wider text-emerald-700"
                            >Resolution details</span
                        >
                        <p class="italic text-slate-600">
                            {{
                                inc.resolution_note ||
                                'Resolved by Administrator.'
                            }}
                        </p>
                        <span
                            class="mt-1 block font-mono text-[8px] font-bold uppercase text-slate-400"
                            >Resolved At:
                            {{ formatDate(inc.resolved_at!) }}</span
                        >
                    </div>
                </div>
            </template>

            <!-- STANDALONE SOS ALERTS LOG VIEW -->
            <template v-else-if="activeTab === 'sos'">
                <div
                    v-for="sos in sosAlerts"
                    :key="sos.id"
                    class="space-y-4 rounded-2xl border border-slate-200/85 bg-white p-5 shadow-sm transition-colors hover:border-slate-300"
                >
                    <!-- Header -->
                    <div
                        class="flex flex-col justify-between gap-2 border-b border-slate-100 pb-3 sm:flex-row sm:items-center"
                    >
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-xs font-black uppercase tracking-wide text-slate-800"
                                >
                                    Emergency SOS Alarm #{{ sos.id }}
                                </span>
                                <span
                                    class="rounded-full border px-1.5 py-0.5 font-mono text-[8px] font-black uppercase tracking-wider"
                                    :class="[
                                        sos.status === 'active'
                                            ? 'border-rose-200 bg-rose-50 text-rose-600'
                                            : '',
                                        sos.status === 'resolved'
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-600'
                                            : '',
                                        sos.status === 'acknowledged'
                                            ? 'border-amber-200 bg-amber-50 text-amber-600'
                                            : '',
                                        sos.status === 'false_alarm'
                                            ? 'border-slate-200 bg-slate-50 text-slate-600'
                                            : '',
                                    ]"
                                >
                                    {{ sos.status.replace('_', ' ') }}
                                </span>
                            </div>
                            <div
                                class="text-slate-450 flex items-center space-x-2 font-mono text-[10px] font-bold"
                            >
                                <span
                                    >Guard:
                                    {{
                                        sos.security_guard?.full_name ||
                                        'Deleted Guard'
                                    }}
                                    ({{
                                        sos.security_guard?.employee_id ||
                                        'N/A'
                                    }})</span
                                >
                                <span>•</span>
                                <span
                                    >Triggered:
                                    {{ formatDate(sos.triggered_at) }}</span
                                >
                            </div>
                        </div>
                        <div
                            v-if="
                                sos.triggered_latitude &&
                                sos.triggered_longitude
                            "
                            class="flex items-center gap-2"
                        >
                            <span
                                class="font-mono text-[10px] font-bold text-slate-400"
                            >
                                GPS:
                                {{ Number(sos.triggered_latitude).toFixed(6) }},
                                {{ Number(sos.triggered_longitude).toFixed(6) }}
                            </span>
                            <a
                                :href="`https://www.google.com/maps/search/?api=1&query=${sos.triggered_latitude},${sos.triggered_longitude}`"
                                target="_blank"
                                class="rounded-xl border border-indigo-200 bg-indigo-50/50 px-3 py-1.5 font-mono text-[9px] font-black uppercase tracking-widest text-indigo-600 hover:bg-indigo-50"
                            >
                                Map Link
                            </a>
                        </div>
                    </div>

                    <!-- Details & Resolution -->
                    <div class="text-slate-650 space-y-3 text-xs">
                        <div
                            v-if="sos.patrol"
                            class="flex items-center gap-1 font-mono text-[10px] text-slate-500"
                        >
                            <span>Associated Shift:</span>
                            <span class="font-bold text-slate-700">
                                {{ sos.patrol.route?.name || 'Manual Patrol' }}
                                (Started:
                                {{ formatDate(sos.patrol.started_at) }})
                            </span>
                        </div>

                        <!-- Map View (using Google Maps Embed) -->
                        <div
                            v-if="
                                sos.triggered_latitude &&
                                sos.triggered_longitude
                            "
                            class="h-44 w-full overflow-hidden rounded-xl border border-slate-200"
                        >
                            <iframe
                                width="100%"
                                height="100%"
                                frameborder="0"
                                style="border: 0"
                                :src="`https://maps.google.com/maps?q=${sos.triggered_latitude},${sos.triggered_longitude}&z=16&output=embed`"
                                allowfullscreen
                            ></iframe>
                        </div>

                        <!-- Resolution Notes -->
                        <div
                            v-if="
                                sos.status === 'resolved' || sos.resolution_note
                            "
                            class="border-emerald-150/40 space-y-1 rounded-xl border bg-emerald-50/30 p-3.5 text-xs text-emerald-950"
                        >
                            <span
                                class="block font-mono text-[9px] font-black uppercase tracking-wider text-emerald-700"
                            >
                                Resolution details
                            </span>
                            <p class="italic text-slate-600">
                                {{
                                    sos.resolution_note ||
                                    'Resolved by Administrator.'
                                }}
                            </p>
                            <div
                                class="mt-1.5 flex flex-wrap gap-x-2 text-[8px] font-black uppercase text-slate-400"
                            >
                                <span v-if="sos.resolver"
                                    >Resolved By: {{ sos.resolver.name }}</span
                                >
                                <span v-if="sos.resolved_at"
                                    >At: {{ formatDate(sos.resolved_at) }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- DETAILS MODAL FOR COMPLETE SHIFT LOG -->
        <div
            v-if="selectedPatrolDetails"
            class="backdrop-blur-xs fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-6"
        >
            <div
                class="animate-fade-in flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl"
            >
                <!-- Header -->
                <div
                    class="border-slate-150 flex items-center justify-between border-b bg-slate-50 px-6 py-4"
                >
                    <div class="space-y-0.5">
                        <h4
                            class="font-mono text-xs font-black uppercase tracking-wider text-slate-800"
                        >
                            Patrol Checkpoints Log
                        </h4>
                        <p
                            class="font-mono text-[10px] font-bold uppercase text-slate-500"
                        >
                            Route: {{ selectedPatrolDetails.route?.name }}
                        </p>
                    </div>
                    <button
                        @click="selectedPatrolDetails = null"
                        class="text-lg text-slate-400 hover:text-slate-700 focus:outline-none"
                    >
                        ×
                    </button>
                </div>

                <!-- Scrollable Body -->
                <div class="flex-1 space-y-6 overflow-y-auto p-6">
                    <!-- GPS Route Map -->
                    <div class="space-y-2">
                        <span
                            class="text-slate-450 block font-mono text-[9px] font-black uppercase tracking-widest"
                            >Patrol GPS Route Map</span
                        >
                        <div
                            id="history-patrol-map"
                            class="z-10 h-[220px] w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-slate-50"
                        ></div>
                    </div>

                    <!-- Checkpoints Checklist Sequence -->
                    <div class="space-y-3">
                        <span
                            class="text-slate-450 block font-mono text-[9px] font-black uppercase tracking-widest"
                            >Scanned Checklist Status</span
                        >
                        <div class="space-y-2.5">
                            <div
                                v-for="log in selectedPatrolDetails.checkpoint_logs"
                                :key="log.id"
                                class="flex flex-col gap-2 rounded-xl border bg-slate-50 p-3"
                                :class="[
                                    log.status === 'scanned'
                                        ? 'border-emerald-250 bg-emerald-50/10'
                                        : '',
                                    log.status === 'skipped'
                                        ? 'border-amber-250 bg-amber-50/10'
                                        : '',
                                ]"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs font-bold text-slate-800"
                                        >{{ log.checkpoint?.name }}</span
                                    >
                                    <span
                                        class="rounded border px-1.5 py-0.5 font-mono text-[8px] font-black uppercase tracking-wider"
                                        :class="[
                                            log.status === 'scanned'
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-600'
                                                : 'border-amber-200 bg-amber-50 text-amber-600',
                                        ]"
                                    >
                                        {{ log.status }}
                                    </span>
                                </div>
                                <p class="text-[10px] text-slate-500">
                                    {{ log.checkpoint?.description }}
                                </p>

                                <!-- Scanned / Skipped Time -->
                                <div
                                    v-if="log.status === 'scanned'"
                                    class="font-mono text-[9px] text-slate-400"
                                >
                                    Scanned At:
                                    {{
                                        formatDate(
                                            log.scanned_at ||
                                                (log as any).updated_at ||
                                                (log as any).created_at,
                                        )
                                    }}
                                </div>
                                <!-- Geofence warning -->
                                <div
                                    v-if="
                                        log.status === 'scanned' &&
                                        log.gps_within_fence === false
                                    "
                                    class="text-red-650 mt-1 inline-flex items-center gap-1 rounded-md border border-red-200 bg-red-50 px-2 py-1 text-[9px] font-bold"
                                >
                                    <span>⚠️ Geofence Breach</span>
                                    <span
                                        v-if="
                                            log.gps_distance_metres !== null &&
                                            log.gps_distance_metres !==
                                                undefined
                                        "
                                    >
                                        ({{
                                            Math.round(log.gps_distance_metres)
                                        }}m away)
                                    </span>
                                </div>
                                <!-- Battery at scan time -->
                                <div
                                    v-if="log.status === 'scanned' && log.battery_pct != null"
                                    class="mt-1 inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[9px] font-semibold"
                                    :class="
                                        log.battery_pct <= 15
                                            ? 'border-red-200 bg-red-50 text-red-600'
                                            : log.battery_pct <= 30
                                              ? 'border-amber-200 bg-amber-50 text-amber-600'
                                              : 'border-emerald-200 bg-emerald-50 text-emerald-600'
                                    "
                                >
                                    <svg class="h-3 w-3 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M15.67 4H14V2h-4v2H8.33C7.6 4 7 4.6 7 5.33v15.33C7 21.4 7.6 22 8.33 22h7.33c.74 0 1.34-.6 1.34-1.33V5.33C17 4.6 16.4 4 15.67 4z"/>
                                    </svg>
                                    <span>{{ log.battery_pct }}% battery at scan</span>
                                </div>
                                <div
                                    v-else-if="log.status === 'skipped'"
                                    class="font-mono text-[9px] text-slate-400"
                                >
                                    Skipped At:
                                    {{
                                        formatDate(
                                            log.skipped_at ||
                                                (log as any).updated_at ||
                                                (log as any).created_at,
                                        )
                                    }}
                                </div>
                                <!-- Skip Details -->
                                <div
                                    v-if="log.status === 'skipped'"
                                    class="mt-1 space-y-1 border-t border-amber-200/40 pt-1.5"
                                >
                                    <span
                                        class="block font-mono text-[8px] font-black uppercase tracking-wider text-amber-700"
                                        >Skip Reason:</span
                                    >
                                    <p
                                        class="text-[10px] italic text-amber-950"
                                    >
                                        {{
                                            log.skip_reason ||
                                            'No reason specified.'
                                        }}
                                    </p>
                                </div>
                                <!-- Note -->
                                <div
                                    v-if="log.note"
                                    class="mt-1 space-y-1 border-t border-slate-200/50 pt-1.5"
                                >
                                    <span
                                        class="text-slate-450 block font-mono text-[8px] font-black uppercase tracking-wider"
                                        >Checkpoint Note:</span
                                    >
                                    <p class="text-slate-650 text-[10px]">
                                        {{ log.note }}
                                    </p>
                                </div>
                                <!-- Scanned Photo / Media -->
                                <div
                                    v-if="
                                        log.media &&
                                        log.media.filter(
                                            (m) => m.kind === 'photo',
                                        ).length > 0
                                    "
                                    class="mt-1 space-y-1.5 border-t border-slate-200/50 pt-1.5"
                                >
                                    <span
                                        class="text-slate-450 block font-mono text-[8px] font-black uppercase tracking-wider"
                                        >Scanned Photos:</span
                                    >
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        <a
                                            v-for="m in log.media.filter(
                                                (m) => m.kind === 'photo',
                                            )"
                                            :key="m.id"
                                            :href="m.file_url"
                                            target="_blank"
                                            class="flex h-20 w-20 cursor-pointer items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100 transition-opacity hover:opacity-90"
                                        >
                                            <img
                                                :src="m.file_url"
                                                alt="Checkpoint media"
                                                class="h-full w-full object-cover"
                                            />
                                        </a>
                                    </div>
                                </div>

                                <!-- Scanned Voice Memos -->
                                <div
                                    v-if="
                                        log.media &&
                                        log.media.filter(
                                            (m) => m.kind === 'voice_memo',
                                        ).length > 0
                                    "
                                    class="mt-1 space-y-1.5 border-t border-slate-200/50 pt-1.5"
                                >
                                    <span
                                        class="text-slate-450 block font-mono text-[8px] font-black uppercase tracking-wider"
                                        >Voice Memos:</span
                                    >
                                    <div class="mt-1 space-y-1.5">
                                        <div
                                            v-for="m in log.media.filter(
                                                (m) => m.kind === 'voice_memo',
                                            )"
                                            :key="m.id"
                                            class="rounded-lg border border-slate-200 bg-slate-50 p-2"
                                        >
                                            <audio
                                                :src="m.file_url"
                                                controls
                                                class="h-8 w-full max-w-xs text-xs"
                                            ></audio>
                                        </div>
                                    </div>
                                </div>

                                <!-- Checkpoint Signature -->
                                <div
                                    v-if="
                                        log.media &&
                                        log.media.find(
                                            (m) => m.kind === 'signature',
                                        )
                                    "
                                    class="mt-1 space-y-1.5 border-t border-slate-200/50 pt-1.5"
                                >
                                    <span
                                        class="text-slate-450 block font-mono text-[8px] font-black uppercase tracking-wider"
                                        >Checkpoint Signature:</span
                                    >
                                    <div class="mt-1">
                                        <a
                                            :href="
                                                log.media.find(
                                                    (m) =>
                                                        m.kind === 'signature',
                                                )?.file_url
                                            "
                                            target="_blank"
                                            class="inline-block overflow-hidden rounded-xl border border-slate-200 bg-slate-100 p-2 transition-opacity hover:opacity-95"
                                        >
                                            <img
                                                :src="
                                                    log.media.find(
                                                        (m) =>
                                                            m.kind ===
                                                            'signature',
                                                    )?.file_url
                                                "
                                                alt="Checkpoint Signature"
                                                class="max-h-[60px] max-w-[180px] object-contain"
                                            />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- General Note -->
                    <div
                        v-if="selectedPatrolDetails.general_note"
                        class="space-y-2"
                    >
                        <span
                            class="text-slate-450 block font-mono text-[9px] font-black uppercase tracking-widest"
                            >General Shift Note</span
                        >
                        <p
                            class="text-slate-650 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs italic leading-relaxed"
                        >
                            "{{ selectedPatrolDetails.general_note }}"
                        </p>
                    </div>

                    <!-- Completion Signature -->
                    <div
                        v-if="selectedPatrolDetails.completion_signature_url"
                        class="space-y-2"
                    >
                        <span
                            class="text-slate-450 block font-mono text-[9px] font-black uppercase tracking-widest"
                            >Guard Digital Sign-Off</span
                        >
                        <div
                            class="flex aspect-[2/1] w-full max-w-[240px] items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-2"
                        >
                            <img
                                :src="
                                    selectedPatrolDetails.completion_signature_url
                                "
                                alt="Guard Signature"
                                class="max-h-full max-w-full object-contain"
                            />
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div
                    class="border-slate-150 flex justify-end border-t bg-slate-50 px-6 py-4"
                >
                    <button
                        @click="selectedPatrolDetails = null"
                        class="hover:bg-slate-350 rounded-xl bg-slate-200 px-5 py-2.5 font-mono text-xs font-black uppercase tracking-wider text-slate-700 transition-all active:scale-95"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
