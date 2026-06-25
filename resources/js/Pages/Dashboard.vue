<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, onUnmounted, ref, watch } from 'vue';

// Structures
interface Guard {
    id: number;
    full_name: string;
    phone: string;
    employee_id: string;
    is_active: boolean;
    last_login_at: string | null;
    last_seen_at: string | null;
}

interface Location {
    id: number;
    name: string;
    address?: string;
    city?: string;
    latitude: number;
    longitude: number;
    geofence_radius: number;
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
    location?: Location;
}

interface RouteCheckpoint {
    id: number;
    position: number;
    checkpoint: Checkpoint;
}

interface Route {
    id: number;
    name: string;
    description?: string;
    enforce_order: boolean;
    allow_skip: boolean;
    expected_duration_mins?: number;
    route_checkpoints?: RouteCheckpoint[];
}

interface Patrol {
    id: number;
    started_at: string;
    completed_at: string | null;
    status: string;
    total_checkpoints: number;
    completed_checkpoints: number;
    skipped_checkpoints: number;
    incident_count: number;
    route?: Route;
    security_guard?: Guard;
}

interface Incident {
    id: number;
    title: string;
    description?: string;
    priority: 'low' | 'medium' | 'high' | 'critical';
    status: string;
    incident_latitude?: number;
    incident_longitude?: number;
    device_timestamp: string;
    resolution_note?: string;
    security_guard?: Guard;
    checkpoint?: Checkpoint;
    location?: Location;
}

interface SosAlert {
    id: number;
    status: string;
    triggered_latitude: number;
    triggered_longitude: number;
    note?: string;
    triggered_at: string;
    security_guard?: Guard;
}

interface Contact {
    id: number;
    name: string;
    role_label: string;
    phone?: string;
    email?: string;
    notify_channels: string[];
    notify_on: string[];
}

// State variables
const activeTab = ref<'live' | 'guards' | 'locations' | 'routes' | 'contacts'>(
    'live',
);

// Data state
const stats = ref<any>({});
const activePatrols = ref<Patrol[]>([]);
const recentIncidents = ref<Incident[]>([]);
const activeSos = ref<SosAlert[]>([]);
const guards = ref<Guard[]>([]);
const locations = ref<Location[]>([]);
const checkpoints = ref<Checkpoint[]>([]);
const routes = ref<Route[]>([]);
const contacts = ref<Contact[]>([]);

// Forms & Modals state
const showResolveModal = ref<'incident' | 'sos' | null>(null);
const resolveTargetId = ref<number | null>(null);
const resolutionNote = ref('');

const showAddGuardModal = ref(false);
const guardForm = ref({
    full_name: '',
    phone: '',
    employee_id: '',
    pin: '1234',
});

const showAddLocationModal = ref(false);
const locationForm = ref({
    name: '',
    address: '',
    city: 'Limassol',
    country: 'Cyprus',
    latitude: 34.671234,
    longitude: 33.041234,
    geofence_radius: 100,
});

const showAddCheckpointModal = ref(false);
const checkpointForm = ref({
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
});

const showAddRouteModal = ref(false);
const routeForm = ref({
    name: '',
    description: '',
    enforce_order: true,
    allow_skip: true,
    expected_duration_mins: 30,
    checkpoints: [] as number[],
});

const showAddContactModal = ref(false);
const contactForm = ref({
    name: '',
    role_label: 'Operator',
    phone: '',
    email: '',
    notify_channels: ['email'],
    notify_on: ['sos_triggered'],
});

// Loading
const isRefreshing = ref(false);
let refreshInterval: any = null;

// Fetch Live Dispatch feed (overview stats, active patrols, incidents, SOS alerts)
async function fetchOverviewData() {
    try {
        const response = await axios.get('/admin/api/overview');
        stats.value = response.data.stats;
        activePatrols.value = response.data.active_patrols;
        recentIncidents.value = response.data.recent_incidents;
        activeSos.value = response.data.active_sos;
    } catch (e) {
        console.error('Failed to load overview data:', e);
    }
}

// Fetch lists
async function fetchGuards() {
    try {
        const res = await axios.get('/admin/api/guards');
        guards.value = res.data.guards;
    } catch (e) {
        console.error(e);
    }
}

async function fetchLocationsAndRoutes() {
    try {
        const res = await axios.get('/admin/api/locations-data');
        locations.value = res.data.locations;
        checkpoints.value = res.data.checkpoints;
        routes.value = res.data.routes;
    } catch (e) {
        console.error(e);
    }
}

async function fetchContacts() {
    try {
        const res = await axios.get('/admin/api/contacts');
        contacts.value = res.data.contacts;
    } catch (e) {
        console.error(e);
    }
}

// Acknowledge SOS
async function submitResolveSos() {
    if (!resolveTargetId.value || !resolutionNote.value.trim()) return;
    try {
        await axios.post(`/admin/api/sos/${resolveTargetId.value}/resolve`, {
            resolution_note: resolutionNote.value,
        });
        showResolveModal.value = null;
        resolveTargetId.value = null;
        resolutionNote.value = '';
        fetchOverviewData();
    } catch (e) {
        alert('Failed to resolve SOS alert.');
    }
}

// Resolve Incident
async function submitResolveIncident() {
    if (!resolveTargetId.value || !resolutionNote.value.trim()) return;
    try {
        await axios.post(
            `/admin/api/incidents/${resolveTargetId.value}/resolve`,
            {
                resolution_note: resolutionNote.value,
            },
        );
        showResolveModal.value = null;
        resolveTargetId.value = null;
        resolutionNote.value = '';
        fetchOverviewData();
    } catch (e) {
        alert('Failed to resolve incident.');
    }
}

// Create Actions
async function submitAddGuard() {
    try {
        await axios.post('/admin/api/guards', guardForm.value);
        showAddGuardModal.value = false;
        guardForm.value = {
            full_name: '',
            phone: '',
            employee_id: '',
            pin: '1234',
        };
        fetchGuards();
    } catch (e: any) {
        alert(
            e.response?.data?.message || 'Phone or Employee ID already exists.',
        );
    }
}

async function submitAddLocation() {
    try {
        await axios.post('/admin/api/locations', locationForm.value);
        showAddLocationModal.value = false;
        locationForm.value = {
            name: '',
            address: '',
            city: 'Limassol',
            country: 'Cyprus',
            latitude: 34.671234,
            longitude: 33.041234,
            geofence_radius: 100,
        };
        fetchLocationsAndRoutes();
    } catch (e) {
        alert('Failed to add location.');
    }
}

async function submitAddCheckpoint() {
    try {
        await axios.post('/admin/api/checkpoints', checkpointForm.value);
        showAddCheckpointModal.value = false;
        checkpointForm.value = {
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
        };
        fetchLocationsAndRoutes();
    } catch (e) {
        alert('Failed to add checkpoint.');
    }
}

async function submitAddRoute() {
    if (routeForm.value.checkpoints.length === 0) {
        alert('Please select at least one checkpoint.');
        return;
    }
    try {
        await axios.post('/admin/api/routes', routeForm.value);
        showAddRouteModal.value = false;
        routeForm.value = {
            name: '',
            description: '',
            enforce_order: true,
            allow_skip: true,
            expected_duration_mins: 30,
            checkpoints: [],
        };
        fetchLocationsAndRoutes();
    } catch (e) {
        alert('Failed to create route.');
    }
}

async function submitAddContact() {
    try {
        await axios.post('/admin/api/contacts', contactForm.value);
        showAddContactModal.value = false;
        contactForm.value = {
            name: '',
            role_label: 'Operator',
            phone: '',
            email: '',
            notify_channels: ['email'],
            notify_on: ['sos_triggered'],
        };
        fetchContacts();
    } catch (e) {
        alert('Failed to add contact.');
    }
}

// Refresh triggers
function refreshAll() {
    isRefreshing.value = true;
    fetchOverviewData();
    if (activeTab.value === 'guards') fetchGuards();
    if (activeTab.value === 'locations' || activeTab.value === 'routes')
        fetchLocationsAndRoutes();
    if (activeTab.value === 'contacts') fetchContacts();
    setTimeout(() => {
        isRefreshing.value = false;
    }, 600);
}

onMounted(() => {
    fetchOverviewData();
    // Poll active patrols and SOS alerts every 5 seconds to simulate a live dispatcher panel
    refreshInterval = setInterval(() => {
        fetchOverviewData();
    }, 5000);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});

// Watch tab switches to fetch relevant lists
watch(activeTab, (newTab) => {
    if (newTab === 'guards') fetchGuards();
    if (newTab === 'locations' || newTab === 'routes')
        fetchLocationsAndRoutes();
    if (newTab === 'contacts') fetchContacts();
});
</script>

<template>
    <Head title="Ops Control Center" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="flex items-center space-x-2 font-mono text-xl font-bold uppercase leading-tight tracking-widest text-slate-100"
                >
                    <span
                        class="h-2.5 w-2.5 rounded-lg bg-gradient-to-tr from-indigo-500 to-violet-600 shadow-[0_0_10px_rgba(99,102,241,0.5)]"
                    ></span>
                    <span>Ops Control Center</span>
                </h2>
                <div class="flex items-center space-x-3">
                    <span class="text-xs font-medium text-slate-400"
                        >Real-time dispatcher stream</span
                    >
                    <button
                        @click="refreshAll"
                        class="rounded-lg border border-slate-800 bg-slate-900 p-2 text-slate-400 transition-all hover:text-indigo-400 active:scale-95"
                        :class="
                            isRefreshing ? 'animate-spin text-indigo-400' : ''
                        "
                        title="Reload Dashboard Stats"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89M9 11l3 3L22 4"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </template>

        <div class="min-h-screen bg-slate-950 py-6 text-slate-100">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- ALARM SOS BANNER IF EMERGENCY ACTIVE -->
                <div
                    v-if="activeSos.length > 0"
                    class="animate-pulse-slow flex flex-col justify-between gap-4 rounded-3xl border border-rose-500/40 bg-rose-500/10 p-5 shadow-[0_0_30px_rgba(244,63,94,0.15)] md:flex-row md:items-center"
                >
                    <div class="flex items-start space-x-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl border border-rose-500 bg-rose-500/20 text-rose-500 shadow-lg shadow-rose-500/10"
                        >
                            <svg
                                class="h-6 w-6 animate-bounce"
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
                        </div>
                        <div>
                            <h3
                                class="text-base font-black tracking-wider text-rose-500"
                            >
                                CRITICAL: ACTIVE PANIC SOS ALARMS
                            </h3>
                            <p
                                class="mt-1 text-xs leading-relaxed text-slate-400"
                            >
                                {{ activeSos.length }} guard(s) have triggered
                                emergency alerts. Location telemetry is
                                broadcasting.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <div
                            v-for="sos in activeSos"
                            :key="sos.id"
                            class="flex flex-col gap-3 rounded-2xl border border-rose-500/30 bg-slate-900 p-4"
                        >
                            <div class="flex items-center justify-between">
                                <div class="text-left">
                                    <span
                                        class="block font-mono text-[10px] font-bold uppercase tracking-widest text-rose-400"
                                        >GUARD ID:
                                        {{
                                            sos.security_guard?.employee_id
                                        }}</span
                                    >
                                    <span
                                        class="block text-xs font-bold text-slate-200"
                                        >{{
                                            sos.security_guard?.full_name
                                        }}</span
                                    >
                                    <span
                                        class="mt-0.5 block font-mono text-[10px] text-slate-500"
                                        >Triggered:
                                        {{
                                            new Date(
                                                sos.triggered_at,
                                            ).toLocaleTimeString()
                                        }}</span
                                    >
                                </div>
                                <button
                                    @click="
                                        resolveTargetId = sos.id;
                                        showResolveModal = 'sos';
                                    "
                                    class="rounded-xl bg-rose-600 px-3.5 py-2 text-[10px] font-bold uppercase tracking-wider text-white shadow-md shadow-rose-600/10 transition-all hover:bg-rose-500 active:scale-95"
                                >
                                    Acknowledge
                                </button>
                            </div>

                            <!-- Minimap Iframe -->
                            <iframe
                                class="h-[120px] w-full rounded-lg border border-rose-500/20"
                                :src="`https://maps.google.com/maps?q=${sos.triggered_latitude},${sos.triggered_longitude}&t=&z=16&ie=UTF8&iwloc=&output=embed`"
                                frameborder="0"
                                scrolling="no"
                                marginheight="0"
                                marginwidth="0"
                            ></iframe>
                        </div>
                    </div>
                </div>

                <!-- OVERVIEW STATISTICS WIDGET DECK -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <!-- Stat 1 -->
                    <div
                        class="border-slate-850 relative overflow-hidden rounded-3xl border bg-slate-900 p-4 shadow-lg"
                    >
                        <span
                            class="block font-mono text-[9px] font-bold uppercase tracking-widest text-slate-500"
                            >Ongoing Shifts</span
                        >
                        <span
                            class="mt-2 block font-mono text-3xl font-black text-indigo-400"
                            >{{ stats.active_patrols_count ?? 0 }}</span
                        >
                        <div
                            class="absolute -bottom-4 -right-4 h-12 w-12 rounded-full bg-indigo-500/5 blur-lg"
                        ></div>
                    </div>
                    <!-- Stat 2 -->
                    <div
                        class="border-slate-850 relative overflow-hidden rounded-3xl border bg-slate-900 p-4 shadow-lg"
                    >
                        <span
                            class="block font-mono text-[9px] font-bold uppercase tracking-widest text-slate-500"
                            >Guards Active</span
                        >
                        <span
                            class="mt-2 block font-mono text-3xl font-black text-emerald-400"
                            >{{ stats.guards_count ?? 0 }}</span
                        >
                        <div
                            class="absolute -bottom-4 -right-4 h-12 w-12 rounded-full bg-emerald-500/5 blur-lg"
                        ></div>
                    </div>
                    <!-- Stat 3 -->
                    <div
                        class="border-slate-850 relative overflow-hidden rounded-3xl border bg-slate-900 p-4 shadow-lg"
                    >
                        <span
                            class="block font-mono text-[9px] font-bold uppercase tracking-widest text-slate-500"
                            >Incidents Today</span
                        >
                        <span
                            class="mt-2 block font-mono text-3xl font-black text-amber-500"
                            >{{ stats.incidents_today_count ?? 0 }}</span
                        >
                        <div
                            class="absolute -bottom-4 -right-4 h-12 w-12 rounded-full bg-amber-500/5 blur-lg"
                        ></div>
                    </div>
                    <!-- Stat 4 -->
                    <div
                        class="border-slate-850 relative overflow-hidden rounded-3xl border bg-slate-900 p-4 shadow-lg"
                    >
                        <span
                            class="block font-mono text-[9px] font-bold uppercase tracking-widest text-slate-500"
                            >Active SOS</span
                        >
                        <span
                            class="mt-2 block font-mono text-3xl font-black"
                            :class="
                                (stats.active_sos_count ?? 0) > 0
                                    ? 'animate-pulse text-rose-500'
                                    : 'text-slate-500'
                            "
                        >
                            {{ stats.active_sos_count ?? 0 }}
                        </span>
                        <div
                            class="absolute -bottom-4 -right-4 h-12 w-12 rounded-full bg-rose-500/5 blur-lg"
                        ></div>
                    </div>
                    <!-- Stat 5 -->
                    <div
                        class="border-slate-850 relative col-span-2 overflow-hidden rounded-3xl border bg-slate-900 p-4 shadow-lg lg:col-span-1"
                    >
                        <span
                            class="block font-mono text-[9px] font-bold uppercase tracking-widest text-slate-500"
                            >Protected Sites</span
                        >
                        <span
                            class="mt-2 block font-mono text-3xl font-black text-slate-200"
                            >{{ stats.locations_count ?? 0 }}</span
                        >
                        <div
                            class="absolute -bottom-4 -right-4 h-12 w-12 rounded-full bg-slate-500/5 blur-lg"
                        ></div>
                    </div>
                </div>

                <!-- SUBSECTION CONTROL TAB BAR -->
                <div
                    class="border-slate-850 flex flex-wrap gap-1 rounded-2xl border bg-slate-900 p-1.5"
                >
                    <button
                        @click="activeTab = 'live'"
                        class="flex items-center space-x-2 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-wider transition-all"
                        :class="
                            activeTab === 'live'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'hover:bg-slate-850/50 text-slate-400 hover:text-slate-200'
                        "
                    >
                        📡 Live Dispatch
                    </button>
                    <button
                        @click="activeTab = 'guards'"
                        class="flex items-center space-x-2 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-wider transition-all"
                        :class="
                            activeTab === 'guards'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'hover:bg-slate-850/50 text-slate-400 hover:text-slate-200'
                        "
                    >
                        🛡️ Security Guards
                    </button>
                    <button
                        @click="activeTab = 'locations'"
                        class="flex items-center space-x-2 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-wider transition-all"
                        :class="
                            activeTab === 'locations'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'hover:bg-slate-850/50 text-slate-400 hover:text-slate-200'
                        "
                    >
                        📍 Checkpoints & Sites
                    </button>
                    <button
                        @click="activeTab = 'routes'"
                        class="flex items-center space-x-2 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-wider transition-all"
                        :class="
                            activeTab === 'routes'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'hover:bg-slate-850/50 text-slate-400 hover:text-slate-200'
                        "
                    >
                        🗺️ Patrol Routes
                    </button>
                    <button
                        @click="activeTab = 'contacts'"
                        class="flex items-center space-x-2 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-wider transition-all"
                        :class="
                            activeTab === 'contacts'
                                ? 'bg-indigo-600 text-white shadow-md'
                                : 'hover:bg-slate-850/50 text-slate-400 hover:text-slate-200'
                        "
                    >
                        🔔 Alert Contacts
                    </button>
                </div>

                <!-- DYNAMIC TAB PANEL VIEWS -->
                <!-- TAB 1: LIVE DISPATCH PANEL -->
                <div
                    v-if="activeTab === 'live'"
                    class="grid grid-cols-1 gap-6 lg:grid-cols-3"
                >
                    <!-- Left: Ongoing Patrol Shifts -->
                    <div class="space-y-4 lg:col-span-2">
                        <div
                            class="border-slate-850 space-y-4 rounded-3xl border bg-slate-900 p-5 shadow-lg"
                        >
                            <h3
                                class="flex items-center space-x-2 text-xs font-black uppercase tracking-widest text-indigo-400"
                            >
                                <span
                                    class="h-1.5 w-1.5 animate-ping rounded-full bg-emerald-500"
                                ></span>
                                <span>ONGOING PATROL FEED</span>
                            </h3>

                            <div
                                v-if="activePatrols.length === 0"
                                class="rounded-2xl border border-dashed border-slate-800 py-16 text-center"
                            >
                                <svg
                                    class="mx-auto mb-3 h-10 w-10 text-slate-700"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                <p class="text-sm font-medium text-slate-400">
                                    No active patrol shifts currently running.
                                </p>
                                <p class="mt-1 text-[10px] text-slate-500">
                                    Guards on shift will appear here upon route
                                    initialization.
                                </p>
                            </div>

                            <div v-else class="space-y-4.5">
                                <div
                                    v-for="patrol in activePatrols"
                                    :key="patrol.id"
                                    class="border-slate-850 flex flex-col justify-between gap-4 rounded-2xl border bg-slate-950 p-4 md:flex-row md:items-center"
                                >
                                    <div class="flex-1 space-y-2">
                                        <div
                                            class="flex items-start justify-between"
                                        >
                                            <div>
                                                <h4
                                                    class="text-sm font-bold text-slate-100"
                                                >
                                                    {{ patrol.route?.name }}
                                                </h4>
                                                <span
                                                    class="font-mono text-[10px] text-slate-500"
                                                    >Duration:
                                                    {{
                                                        Math.floor(
                                                            (Date.now() -
                                                                new Date(
                                                                    patrol.started_at,
                                                                ).getTime()) /
                                                                60000,
                                                        )
                                                    }}
                                                    mins elapsed</span
                                                >
                                            </div>
                                            <span
                                                class="rounded border px-2 py-0.5 text-[9px] font-bold"
                                                :class="
                                                    patrol.status ===
                                                    'in_progress'
                                                        ? 'border-indigo-500/20 bg-indigo-500/10 text-indigo-400'
                                                        : 'border-slate-800 bg-slate-900 text-slate-400'
                                                "
                                            >
                                                {{
                                                    patrol.status.toUpperCase()
                                                }}
                                            </span>
                                        </div>

                                        <!-- Completion details -->
                                        <div
                                            class="flex items-center justify-between text-[10px] text-slate-400"
                                        >
                                            <span
                                                >Guard:
                                                <strong
                                                    class="text-slate-300"
                                                    >{{
                                                        patrol.security_guard
                                                            ?.full_name
                                                    }}</strong
                                                ></span
                                            >
                                            <span
                                                class="font-mono font-semibold text-indigo-300"
                                                >{{
                                                    patrol.completed_checkpoints
                                                }}/{{
                                                    patrol.total_checkpoints
                                                }}
                                                Checkpoints</span
                                            >
                                        </div>

                                        <!-- Progress Bar -->
                                        <div
                                            class="h-1.5 w-full overflow-hidden rounded-full bg-slate-900"
                                        >
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600 transition-all"
                                                :style="{
                                                    width: `${(patrol.completed_checkpoints / patrol.total_checkpoints) * 100}%`,
                                                }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Incident Logs Feed -->
                    <div class="space-y-4">
                        <div
                            class="border-slate-850 space-y-4 rounded-3xl border bg-slate-900 p-5 shadow-lg"
                        >
                            <h3
                                class="text-xs font-black uppercase tracking-widest text-indigo-400"
                            >
                                RECENT INCIDENTS
                            </h3>

                            <div
                                v-if="recentIncidents.length === 0"
                                class="rounded-2xl border border-dashed border-slate-800 py-16 text-center"
                            >
                                <p class="text-xs text-slate-500">
                                    No security incidents reported today.
                                </p>
                            </div>

                            <div
                                v-else
                                class="max-h-[460px] space-y-3 overflow-y-auto pr-1"
                            >
                                <div
                                    v-for="incident in recentIncidents"
                                    :key="incident.id"
                                    class="border-slate-850 space-y-3 rounded-xl border bg-slate-950 p-3.5"
                                >
                                    <div
                                        class="flex items-start justify-between"
                                    >
                                        <div>
                                            <h5
                                                class="text-xs font-bold text-slate-200"
                                            >
                                                {{ incident.title }}
                                            </h5>
                                            <span
                                                class="mt-0.5 block font-mono text-[9px] text-slate-500"
                                            >
                                                Reporter:
                                                {{
                                                    incident.security_guard
                                                        ?.full_name
                                                }}
                                            </span>
                                        </div>
                                        <span
                                            class="rounded border px-2 py-0.5 text-[8px] font-black uppercase"
                                            :class="
                                                {
                                                    low: 'border-sky-500/20 bg-sky-500/10 text-sky-400',
                                                    medium: 'border-amber-500/20 bg-amber-500/10 text-amber-400',
                                                    high: 'border-orange-500/20 bg-orange-500/10 text-orange-400',
                                                    critical:
                                                        'border-rose-500/20 bg-rose-500/10 text-rose-400',
                                                }[incident.priority]
                                            "
                                        >
                                            {{ incident.priority }}
                                        </span>
                                    </div>

                                    <p
                                        class="text-[11px] leading-relaxed text-slate-400"
                                    >
                                        {{
                                            incident.description ||
                                            'No description provided.'
                                        }}
                                    </p>

                                    <!-- Quick action to resolve open incidents -->
                                    <div
                                        class="flex items-center justify-between border-t border-slate-900 pt-2"
                                    >
                                        <span
                                            class="font-mono text-[9px] text-slate-500"
                                        >
                                            Status:
                                            <strong
                                                :class="
                                                    incident.status === 'open'
                                                        ? 'text-amber-500'
                                                        : 'text-emerald-500'
                                                "
                                                >{{
                                                    incident.status.toUpperCase()
                                                }}</strong
                                            >
                                        </span>

                                        <button
                                            v-if="incident.status === 'open'"
                                            @click="
                                                resolveTargetId = incident.id;
                                                showResolveModal = 'incident';
                                            "
                                            class="hover:bg-indigo-650/30 rounded-lg border border-indigo-500/20 bg-indigo-600/15 px-2.5 py-1.5 text-[9px] font-bold uppercase tracking-wider text-indigo-400 transition-all active:scale-95"
                                        >
                                            Resolve
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: GUARDS DIRECTORY -->
                <div
                    v-else-if="activeTab === 'guards'"
                    class="animate-fadeIn space-y-4"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="text-xs font-black uppercase tracking-widest text-slate-400"
                        >
                            Guards Registry
                        </h3>
                        <button
                            @click="showAddGuardModal = true"
                            class="hover:bg-indigo-505 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-md transition-all active:scale-95"
                        >
                            + Register Guard
                        </button>
                    </div>

                    <div
                        class="border-slate-850 overflow-hidden rounded-3xl border bg-slate-900 shadow-lg"
                    >
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr
                                    class="border-slate-850 border-b bg-slate-950 text-[10px] font-bold uppercase tracking-widest text-slate-400"
                                >
                                    <th class="p-4">Guard Details</th>
                                    <th class="p-4">Employee ID</th>
                                    <th class="p-4">Phone Number</th>
                                    <th class="p-4">Last Active</th>
                                    <th class="p-4 text-center">Duty Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-slate-850 divide-y text-xs">
                                <tr
                                    v-for="g in guards"
                                    :key="g.id"
                                    class="hover:bg-slate-950/40"
                                >
                                    <td class="p-4 font-bold text-slate-200">
                                        {{ g.full_name }}
                                    </td>
                                    <td class="p-4 font-mono text-indigo-400">
                                        {{ g.employee_id }}
                                    </td>
                                    <td class="p-4 font-mono text-slate-400">
                                        {{ g.phone }}
                                    </td>
                                    <td class="p-4 font-mono text-slate-500">
                                        {{
                                            g.last_seen_at
                                                ? new Date(
                                                      g.last_seen_at,
                                                  ).toLocaleString()
                                                : 'Never'
                                        }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                            :class="
                                                g.is_active
                                                    ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-400'
                                                    : 'border border-rose-500/20 bg-rose-500/10 text-rose-400'
                                            "
                                        >
                                            {{
                                                g.is_active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="guards.length === 0">
                                    <td
                                        colspan="5"
                                        class="p-8 text-center text-slate-500"
                                    >
                                        No guards registered under this tenant.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: CHECKPOINTS & SITES -->
                <div
                    v-else-if="activeTab === 'locations'"
                    class="animate-fadeIn space-y-6"
                >
                    <div
                        class="flex flex-col justify-between gap-4 md:flex-row"
                    >
                        <div class="flex gap-2">
                            <button
                                @click="showAddLocationModal = true"
                                class="rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-md transition-all hover:bg-indigo-500"
                            >
                                + Add Location
                            </button>
                            <button
                                @click="showAddCheckpointModal = true"
                                class="bg-violet-650 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-md transition-all hover:bg-violet-600"
                            >
                                + Add Checkpoint
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <!-- Left: Locations list -->
                        <div
                            class="border-slate-850 space-y-4 rounded-3xl border bg-slate-900 p-5 shadow-lg"
                        >
                            <h3
                                class="text-xs font-black uppercase tracking-widest text-indigo-400"
                            >
                                LOCATIONS
                            </h3>
                            <div class="space-y-3">
                                <div
                                    v-for="l in locations"
                                    :key="l.id"
                                    class="border-slate-850 rounded-2xl border bg-slate-950 p-4"
                                >
                                    <h4
                                        class="text-sm font-bold text-slate-100"
                                    >
                                        {{ l.name }}
                                    </h4>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ l.address }}, {{ l.city }}
                                    </p>
                                    <div
                                        class="mt-3 flex items-center justify-between font-mono text-[10px] text-indigo-400"
                                    >
                                        <span
                                            >Geofence:
                                            {{ l.geofence_radius }}m</span
                                        >
                                        <span
                                            >Coords: {{ l.latitude }},
                                            {{ l.longitude }}</span
                                        >
                                    </div>
                                </div>
                                <div
                                    v-if="locations.length === 0"
                                    class="p-6 text-center text-xs text-slate-500"
                                >
                                    No locations registered.
                                </div>
                            </div>
                        </div>

                        <!-- Right: Checkpoints sequence table -->
                        <div
                            class="border-slate-850 space-y-4 rounded-3xl border bg-slate-900 p-5 shadow-lg lg:col-span-2"
                        >
                            <h3
                                class="text-xs font-black uppercase tracking-widest text-indigo-400"
                            >
                                CHECKPOINTS DEFINITIONS
                            </h3>
                            <div
                                class="max-h-[500px] space-y-3 overflow-y-auto pr-1"
                            >
                                <div
                                    v-for="cp in checkpoints"
                                    :key="cp.id"
                                    class="border-slate-850 flex flex-col justify-between gap-4 rounded-2xl border bg-slate-950 p-4 md:flex-row md:items-center"
                                >
                                    <div>
                                        <h4
                                            class="text-sm font-bold text-slate-200"
                                        >
                                            {{ cp.name }}
                                        </h4>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{
                                                cp.description ||
                                                'No description'
                                            }}
                                        </p>
                                        <p
                                            class="mt-1 text-[10px] text-slate-500"
                                        >
                                            Location:
                                            <strong class="text-slate-400">{{
                                                cp.location?.name
                                            }}</strong>
                                        </p>
                                    </div>
                                    <div class="space-y-1 text-right">
                                        <span
                                            class="inline-block rounded border border-slate-800 bg-slate-900 px-2 py-0.5 font-mono text-[9px] text-indigo-400"
                                        >
                                            SCAN METHOD:
                                            {{ cp.scan_method.toUpperCase() }}
                                        </span>
                                        <div
                                            class="font-mono text-[9px] text-slate-500"
                                        >
                                            Photo req:
                                            {{ cp.photo_requirement }} • Sig:
                                            {{
                                                cp.signature_required
                                                    ? 'Yes'
                                                    : 'No'
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="checkpoints.length === 0"
                                    class="p-8 text-center text-xs text-slate-500"
                                >
                                    No checkpoints configured.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: PATROL ROUTES CONFIG -->
                <div
                    v-else-if="activeTab === 'routes'"
                    class="animate-fadeIn space-y-4"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="text-xs font-black uppercase tracking-widest text-slate-400"
                        >
                            Patrol Sequences
                        </h3>
                        <button
                            @click="showAddRouteModal = true"
                            class="rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
                        >
                            + Create Patrol Route
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div
                            v-for="route in routes"
                            :key="route.id"
                            class="border-slate-850 space-y-4 rounded-3xl border bg-slate-900 p-5 shadow-lg"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <h4
                                        class="text-sm font-bold text-slate-100"
                                    >
                                        {{ route.name }}
                                    </h4>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{
                                            route.description ||
                                            'No description provided.'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="space-y-1 text-right font-mono text-[10px] text-slate-400"
                                >
                                    <span
                                        class="block rounded border border-slate-800 bg-slate-950 px-2 py-0.5"
                                    >
                                        Expected:
                                        {{ route.expected_duration_mins || 30 }}
                                        mins
                                    </span>
                                    <span
                                        class="block font-bold text-indigo-400"
                                    >
                                        {{
                                            route.enforce_order
                                                ? 'Strict Order'
                                                : 'Flexible Sequence'
                                        }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h5
                                    class="pl-1 text-[9px] font-bold uppercase tracking-widest text-slate-400"
                                >
                                    Ordered Checkpoints Sequence
                                </h5>
                                <div
                                    class="space-y-1.5 border-l border-indigo-500/20 pl-2"
                                >
                                    <div
                                        v-for="rc in route.route_checkpoints"
                                        :key="rc.id"
                                        class="flex items-center space-x-2 text-xs text-slate-300"
                                    >
                                        <span
                                            class="flex h-4 w-4 items-center justify-center rounded border border-slate-800 bg-slate-950 font-mono text-[10px] text-indigo-400"
                                        >
                                            {{ rc.position }}
                                        </span>
                                        <span>{{ rc.checkpoint?.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="routes.length === 0"
                            class="border-slate-850 col-span-2 rounded-3xl border bg-slate-900/40 p-12 text-center text-sm text-slate-500"
                        >
                            No patrol routes defined.
                        </div>
                    </div>
                </div>

                <!-- TAB 5: ALERT CONTACTS -->
                <div
                    v-else-if="activeTab === 'contacts'"
                    class="animate-fadeIn space-y-4"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="text-xs font-black uppercase tracking-widest text-slate-400"
                        >
                            Emergency Notification List
                        </h3>
                        <button
                            @click="showAddContactModal = true"
                            class="rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
                        >
                            + Add Contact
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div
                            v-for="c in contacts"
                            :key="c.id"
                            class="border-slate-850 space-y-4 rounded-3xl border bg-slate-900 p-5 shadow-lg"
                        >
                            <div>
                                <h4 class="text-sm font-bold text-slate-100">
                                    {{ c.name }}
                                </h4>
                                <span
                                    class="mt-0.5 block text-[10px] font-bold uppercase tracking-wider text-indigo-400"
                                    >{{ c.role_label }}</span
                                >
                            </div>

                            <div
                                class="space-y-1 font-mono text-xs text-slate-400"
                            >
                                <p v-if="c.phone">📞 {{ c.phone }}</p>
                                <p v-if="c.email">✉️ {{ c.email }}</p>
                            </div>

                            <div
                                class="border-slate-850 space-y-2 border-t pt-3 font-mono text-[10px]"
                            >
                                <div>
                                    <span
                                        class="mb-1 block text-[9px] font-bold uppercase text-slate-500"
                                        >Alert Channels:</span
                                    >
                                    <span
                                        v-for="ch in c.notify_channels"
                                        :key="ch"
                                        class="mr-1 rounded border border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0.5 text-indigo-400"
                                    >
                                        {{ ch.toUpperCase() }}
                                    </span>
                                </div>
                                <div class="pt-2">
                                    <span
                                        class="mb-1 block text-[9px] font-bold uppercase text-slate-500"
                                        >Notify On:</span
                                    >
                                    <span
                                        v-for="evt in c.notify_on"
                                        :key="evt"
                                        class="mr-1 mt-1 block rounded border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.5 text-amber-400"
                                    >
                                        {{ evt.toUpperCase() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="contacts.length === 0"
                            class="border-slate-850 col-span-3 rounded-3xl border bg-slate-900/40 p-12 text-center text-sm text-slate-500"
                        >
                            No dispatch contacts configured.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RESOLVE MODAL FOR INCIDENTS & SOS -->
        <div
            v-if="showResolveModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm"
        >
            <div
                class="border-slate-850 animate-fadeIn w-full max-w-sm space-y-4 rounded-3xl border bg-slate-900 p-6 shadow-2xl"
            >
                <h4 class="text-sm font-bold text-slate-100">
                    Resolve
                    {{
                        showResolveModal === 'incident'
                            ? 'Incident Report'
                            : 'Emergency SOS Alert'
                    }}
                </h4>
                <p class="text-xs leading-relaxed text-slate-400">
                    Type a supervisor resolution statement or logging note. This
                    will mark the alarm as resolved and close the dispatch
                    window.
                </p>

                <textarea
                    v-model="resolutionNote"
                    rows="3"
                    class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    placeholder="E.g. Security dispatch team dispatched to North Pier. Intruder caught and police notified..."
                ></textarea>

                <div class="flex space-x-3">
                    <button
                        @click="
                            showResolveModal = null;
                            resolveTargetId = null;
                            resolutionNote = '';
                        "
                        class="hover:bg-slate-750 flex-1 rounded-xl bg-slate-800 py-3 text-xs font-bold uppercase tracking-wider text-slate-300 active:scale-95"
                    >
                        Cancel
                    </button>
                    <button
                        @click="
                            showResolveModal === 'incident'
                                ? submitResolveIncident()
                                : submitResolveSos()
                        "
                        :disabled="!resolutionNote.trim()"
                        class="bg-indigo-650 flex-1 rounded-xl py-3 text-xs font-bold uppercase tracking-wider text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-600 active:scale-95 disabled:pointer-events-none disabled:opacity-40"
                    >
                        Confirm Close
                    </button>
                </div>
            </div>
        </div>

        <!-- REGISTER GUARD MODAL -->
        <div
            v-if="showAddGuardModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm"
        >
            <div
                class="border-slate-850 animate-fadeIn w-full max-w-sm space-y-4 rounded-3xl border bg-slate-900 p-6 shadow-2xl"
            >
                <h4 class="text-sm font-bold text-slate-100">
                    Register Security Guard
                </h4>

                <div class="space-y-3">
                    <div class="space-y-1">
                        <label
                            class="block pl-1 text-[10px] uppercase tracking-widest text-slate-400"
                            >Guard Full Name</label
                        >
                        <input
                            v-model="guardForm.full_name"
                            type="text"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:outline-none"
                            placeholder="John Doe"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="block pl-1 text-[10px] uppercase tracking-widest text-slate-400"
                            >Phone (Login)</label
                        >
                        <input
                            v-model="guardForm.phone"
                            type="text"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:outline-none"
                            placeholder="+35799123456"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="block pl-1 text-[10px] uppercase tracking-widest text-slate-400"
                            >Employee ID</label
                        >
                        <input
                            v-model="guardForm.employee_id"
                            type="text"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:outline-none"
                            placeholder="GD-007"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="block pl-1 text-[10px] uppercase tracking-widest text-slate-400"
                            >Pin Hash (Offline Mode Code)</label
                        >
                        <input
                            v-model="guardForm.pin"
                            type="password"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:outline-none"
                        />
                    </div>
                </div>

                <div class="flex space-x-3 pt-2">
                    <button
                        @click="showAddGuardModal = false"
                        class="flex-1 rounded-xl bg-slate-800 py-3 text-xs font-bold uppercase tracking-wider text-slate-300"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitAddGuard"
                        class="flex-1 rounded-xl bg-indigo-600 py-3 text-xs font-bold uppercase tracking-wider text-white shadow-lg shadow-indigo-600/20"
                    >
                        Register
                    </button>
                </div>
            </div>
        </div>

        <!-- ADD LOCATION MODAL -->
        <div
            v-if="showAddLocationModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm"
        >
            <div
                class="border-slate-850 animate-fadeIn w-full max-w-sm space-y-4 rounded-3xl border bg-slate-900 p-6 shadow-2xl"
            >
                <h4 class="text-sm font-bold text-slate-100">
                    Add Site Location
                </h4>

                <div class="space-y-3">
                    <div class="space-y-1">
                        <label
                            class="block pl-1 text-[10px] uppercase text-slate-400"
                            >Location Name</label
                        >
                        <input
                            v-model="locationForm.name"
                            type="text"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:outline-none"
                            placeholder="Limassol Marina Port"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="block pl-1 text-[10px] uppercase text-slate-400"
                            >Address</label
                        >
                        <input
                            v-model="locationForm.address"
                            type="text"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:outline-none"
                            placeholder="Marina Road 1"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label
                                class="block pl-1 text-[10px] uppercase text-slate-400"
                                >Latitude</label
                            >
                            <input
                                v-model="locationForm.latitude"
                                type="number"
                                step="0.000001"
                                class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                class="block pl-1 text-[10px] uppercase text-slate-400"
                                >Longitude</label
                            >
                            <input
                                v-model="locationForm.longitude"
                                type="number"
                                step="0.000001"
                                class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:outline-none"
                            />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label
                            class="block pl-1 text-[10px] uppercase text-slate-400"
                            >Geofence Radius (meters)</label
                        >
                        <input
                            v-model="locationForm.geofence_radius"
                            type="number"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-xs text-slate-100 focus:outline-none"
                        />
                    </div>
                </div>

                <div class="flex space-x-3 pt-2">
                    <button
                        @click="showAddLocationModal = false"
                        class="flex-1 rounded-xl bg-slate-800 py-3 text-xs font-bold uppercase text-slate-300"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitAddLocation"
                        class="flex-1 rounded-xl bg-indigo-600 py-3 text-xs font-bold uppercase text-white"
                    >
                        Add
                    </button>
                </div>
            </div>
        </div>

        <!-- ADD CHECKPOINT MODAL -->
        <div
            v-if="showAddCheckpointModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm"
        >
            <div
                class="border-slate-850 animate-fadeIn max-h-[90vh] w-full max-w-md space-y-4 overflow-y-auto rounded-3xl border bg-slate-900 p-6 shadow-2xl"
            >
                <h4 class="text-sm font-bold text-slate-100">Add Checkpoint</h4>

                <div class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label
                            class="block text-[10px] uppercase text-slate-400"
                            >Select Location Site</label
                        >
                        <select
                            v-model="checkpointForm.location_id"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                        >
                            <option value="">Choose Site...</option>
                            <option
                                v-for="l in locations"
                                :key="l.id"
                                :value="l.id"
                            >
                                {{ l.name }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label
                            class="block text-[10px] uppercase text-slate-400"
                            >Checkpoint Name</label
                        >
                        <input
                            v-model="checkpointForm.name"
                            type="text"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            placeholder="Server Room Gate"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="block text-[10px] uppercase text-slate-400"
                            >Scan Method Required</label
                        >
                        <select
                            v-model="checkpointForm.scan_method"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                        >
                            <option value="qr">QR Code Scanner Only</option>
                            <option value="nfc">NFC Tag Only</option>
                            <option value="both">Both Methods Allowed</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] uppercase text-slate-400"
                                >Latitude</label
                            >
                            <input
                                v-model="checkpointForm.latitude"
                                type="number"
                                step="0.000001"
                                class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] uppercase text-slate-400"
                                >Longitude</label
                            >
                            <input
                                v-model="checkpointForm.longitude"
                                type="number"
                                step="0.000001"
                                class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] uppercase text-slate-400"
                                >Photo Requirement</label
                            >
                            <select
                                v-model="checkpointForm.photo_requirement"
                                class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            >
                                <option value="off">Off</option>
                                <option value="optional">Optional</option>
                                <option value="required">Required</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] uppercase text-slate-400"
                                >Signature Check-in</label
                            >
                            <select
                                v-model="checkpointForm.signature_required"
                                class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            >
                                <option :value="false">Not Required</option>
                                <option :value="true">Required</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-3 pt-2">
                    <button
                        @click="showAddCheckpointModal = false"
                        class="flex-1 rounded-xl bg-slate-800 py-3 text-xs font-bold uppercase text-slate-300"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitAddCheckpoint"
                        class="flex-1 rounded-xl bg-indigo-600 py-3 text-xs font-bold uppercase text-white"
                    >
                        Add Checkpoint
                    </button>
                </div>
            </div>
        </div>

        <!-- CREATE ROUTE MODAL -->
        <div
            v-if="showAddRouteModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm"
        >
            <div
                class="border-slate-850 animate-fadeIn max-h-[90vh] w-full max-w-md space-y-4 overflow-y-auto rounded-3xl border bg-slate-900 p-6 shadow-2xl"
            >
                <h4 class="text-sm font-bold text-slate-100">
                    Create Patrol Route
                </h4>

                <div class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label
                            class="block text-[10px] uppercase text-slate-400"
                            >Route Name</label
                        >
                        <input
                            v-model="routeForm.name"
                            type="text"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            placeholder="Marina North Dock Shift"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="block text-[10px] uppercase text-slate-400"
                            >Description</label
                        >
                        <textarea
                            v-model="routeForm.description"
                            rows="2"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            placeholder="Covering docks, fuel office..."
                        ></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] uppercase text-slate-400"
                                >Enforce Strict Order</label
                            >
                            <select
                                v-model="routeForm.enforce_order"
                                class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            >
                                <option :value="true">
                                    Strict Sequence (1, 2, 3)
                                </option>
                                <option :value="false">Any Order</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label
                                class="block text-[10px] uppercase text-slate-400"
                                >Allow Skip Checkpoints</label
                            >
                            <select
                                v-model="routeForm.allow_skip"
                                class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            >
                                <option :value="true">
                                    Allowed with Reason
                                </option>
                                <option :value="false">Prohibited</option>
                            </select>
                        </div>
                    </div>

                    <!-- Checkpoint Sequence Selector -->
                    <div class="space-y-2">
                        <label
                            class="block pl-1 text-[10px] uppercase text-slate-400"
                            >Assign Checkpoints (Check to add in order)</label
                        >
                        <div
                            class="border-slate-850 max-h-40 space-y-2 overflow-y-auto rounded-xl border bg-slate-950 p-3"
                        >
                            <div
                                v-for="cp in checkpoints"
                                :key="cp.id"
                                class="flex items-center space-x-3"
                            >
                                <input
                                    type="checkbox"
                                    :value="cp.id"
                                    v-model="routeForm.checkpoints"
                                    class="h-4 w-4 rounded border-slate-800 bg-slate-900 text-indigo-600 focus:ring-0"
                                />
                                <span class="text-xs text-slate-300"
                                    >{{ cp.name }}
                                    <small class="text-slate-500"
                                        >({{ cp.location?.name }})</small
                                    ></span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-3 pt-2">
                    <button
                        @click="showAddRouteModal = false"
                        class="flex-1 rounded-xl bg-slate-800 py-3 text-xs font-bold uppercase text-slate-300"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitAddRoute"
                        class="flex-1 rounded-xl bg-indigo-600 py-3 text-xs font-bold uppercase text-white"
                    >
                        Create Route
                    </button>
                </div>
            </div>
        </div>

        <!-- ADD CONTACT MODAL -->
        <div
            v-if="showAddContactModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-6 backdrop-blur-sm"
        >
            <div
                class="border-slate-850 animate-fadeIn w-full max-w-sm space-y-4 rounded-3xl border bg-slate-900 p-6 shadow-2xl"
            >
                <h4 class="text-sm font-bold text-slate-100">
                    Add Alert Contact
                </h4>

                <div class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label
                            class="block text-[10px] uppercase text-slate-400"
                            >Contact Name</label
                        >
                        <input
                            v-model="contactForm.name"
                            type="text"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            placeholder="Operations Manager"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="block text-[10px] uppercase text-slate-400"
                            >Role Label</label
                        >
                        <input
                            v-model="contactForm.role_label"
                            type="text"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            placeholder="Supervisor"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="block text-[10px] uppercase text-slate-400"
                            >Phone (SMS Alert)</label
                        >
                        <input
                            v-model="contactForm.phone"
                            type="text"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            placeholder="+35799887766"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="block text-[10px] uppercase text-slate-400"
                            >Email</label
                        >
                        <input
                            v-model="contactForm.email"
                            type="email"
                            class="border-slate-850 w-full rounded-xl border bg-slate-950 p-2.5 text-slate-100 focus:outline-none"
                            placeholder="supervisor@witbo.com"
                        />
                    </div>
                </div>

                <div class="flex space-x-3 pt-2">
                    <button
                        @click="showAddContactModal = false"
                        class="flex-1 rounded-xl bg-slate-800 py-3 text-xs font-bold uppercase text-slate-300"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitAddContact"
                        class="flex-1 rounded-xl bg-indigo-600 py-3 text-xs font-bold uppercase text-white"
                    >
                        Add Contact
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
/* Dashboard Micro-animations */
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
    animation: fadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes pulse-slow {
    0%,
    100% {
        border-color: rgba(244, 63, 94, 0.4);
        shadow: 0 0 15px rgba(244, 63, 94, 0.1);
    }
    50% {
        border-color: rgba(244, 63, 94, 0.8);
        shadow: 0 0 30px rgba(244, 63, 94, 0.25);
    }
}
.animate-pulse-slow {
    animation: pulse-slow 2s infinite ease-in-out;
}
</style>
