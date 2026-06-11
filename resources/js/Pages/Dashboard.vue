<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';

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
const activeTab = ref<'live' | 'guards' | 'locations' | 'routes' | 'contacts'>('live');

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
const guardForm = ref({ full_name: '', phone: '', employee_id: '', pin: '1234' });

const showAddLocationModal = ref(false);
const locationForm = ref({ name: '', address: '', city: 'Limassol', country: 'Cyprus', latitude: 34.671234, longitude: 33.041234, geofence_radius: 100 });

const showAddCheckpointModal = ref(false);
const checkpointForm = ref({
    location_id: '', name: '', description: '', scan_method: 'qr', qr_code: '', nfc_tag_id: '',
    gps_required: true, gps_fence_radius: 15, latitude: 34.671200, longitude: 33.041200,
    photo_requirement: 'off', note_requirement: 'off', voice_requirement: 'off', signature_required: false
});

const showAddRouteModal = ref(false);
const routeForm = ref({ name: '', description: '', enforce_order: true, allow_skip: true, expected_duration_mins: 30, checkpoints: [] as number[] });

const showAddContactModal = ref(false);
const contactForm = ref({ name: '', role_label: 'Operator', phone: '', email: '', notify_channels: ['email'], notify_on: ['sos_triggered'] });

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
    } catch (e) { console.error(e); }
}

async function fetchLocationsAndRoutes() {
    try {
        const res = await axios.get('/admin/api/locations-data');
        locations.value = res.data.locations;
        checkpoints.value = res.data.checkpoints;
        routes.value = res.data.routes;
    } catch (e) { console.error(e); }
}

async function fetchContacts() {
    try {
        const res = await axios.get('/admin/api/contacts');
        contacts.value = res.data.contacts;
    } catch (e) { console.error(e); }
}

// Acknowledge SOS
async function submitResolveSos() {
    if (!resolveTargetId.value || !resolutionNote.value.trim()) return;
    try {
        await axios.post(`/admin/api/sos/${resolveTargetId.value}/resolve`, {
            resolution_note: resolutionNote.value
        });
        showResolveModal.value = null;
        resolveTargetId.value = null;
        resolutionNote.value = '';
        fetchOverviewData();
    } catch (e) { alert('Failed to resolve SOS alert.'); }
}

// Resolve Incident
async function submitResolveIncident() {
    if (!resolveTargetId.value || !resolutionNote.value.trim()) return;
    try {
        await axios.post(`/admin/api/incidents/${resolveTargetId.value}/resolve`, {
            resolution_note: resolutionNote.value
        });
        showResolveModal.value = null;
        resolveTargetId.value = null;
        resolutionNote.value = '';
        fetchOverviewData();
    } catch (e) { alert('Failed to resolve incident.'); }
}

// Create Actions
async function submitAddGuard() {
    try {
        await axios.post('/admin/api/guards', guardForm.value);
        showAddGuardModal.value = false;
        guardForm.value = { full_name: '', phone: '', employee_id: '', pin: '1234' };
        fetchGuards();
    } catch (e: any) { alert(e.response?.data?.message || 'Phone or Employee ID already exists.'); }
}

async function submitAddLocation() {
    try {
        await axios.post('/admin/api/locations', locationForm.value);
        showAddLocationModal.value = false;
        locationForm.value = { name: '', address: '', city: 'Limassol', country: 'Cyprus', latitude: 34.671234, longitude: 33.041234, geofence_radius: 100 };
        fetchLocationsAndRoutes();
    } catch (e) { alert('Failed to add location.'); }
}

async function submitAddCheckpoint() {
    try {
        await axios.post('/admin/api/checkpoints', checkpointForm.value);
        showAddCheckpointModal.value = false;
        checkpointForm.value = {
            location_id: '', name: '', description: '', scan_method: 'qr', qr_code: '', nfc_tag_id: '',
            gps_required: true, gps_fence_radius: 15, latitude: 34.671200, longitude: 33.041200,
            photo_requirement: 'off', note_requirement: 'off', voice_requirement: 'off', signature_required: false
        };
        fetchLocationsAndRoutes();
    } catch (e) { alert('Failed to add checkpoint.'); }
}

async function submitAddRoute() {
    if (routeForm.value.checkpoints.length === 0) {
        alert('Please select at least one checkpoint.');
        return;
    }
    try {
        await axios.post('/admin/api/routes', routeForm.value);
        showAddRouteModal.value = false;
        routeForm.value = { name: '', description: '', enforce_order: true, allow_skip: true, expected_duration_mins: 30, checkpoints: [] };
        fetchLocationsAndRoutes();
    } catch (e) { alert('Failed to create route.'); }
}

async function submitAddContact() {
    try {
        await axios.post('/admin/api/contacts', contactForm.value);
        showAddContactModal.value = false;
        contactForm.value = { name: '', role_label: 'Operator', phone: '', email: '', notify_channels: ['email'], notify_on: ['sos_triggered'] };
        fetchContacts();
    } catch (e) { alert('Failed to add contact.'); }
}

// Refresh triggers
function refreshAll() {
    isRefreshing.value = true;
    fetchOverviewData();
    if (activeTab.value === 'guards') fetchGuards();
    if (activeTab.value === 'locations' || activeTab.value === 'routes') fetchLocationsAndRoutes();
    if (activeTab.value === 'contacts') fetchContacts();
    setTimeout(() => { isRefreshing.value = false; }, 600);
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
    if (newTab === 'locations' || newTab === 'routes') fetchLocationsAndRoutes();
    if (newTab === 'contacts') fetchContacts();
});
</script>

<template>
    <Head title="Ops Control Center" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold leading-tight text-slate-100 uppercase tracking-widest font-mono flex items-center space-x-2">
                    <span class="w-2.5 h-2.5 bg-gradient-to-tr from-indigo-500 to-violet-600 rounded-lg shadow-[0_0_10px_rgba(99,102,241,0.5)]"></span>
                    <span>Ops Control Center</span>
                </h2>
                <div class="flex items-center space-x-3">
                    <span class="text-xs text-slate-400 font-medium">Real-time dispatcher stream</span>
                    <button 
                        @click="refreshAll" 
                        class="p-2 rounded-lg border border-slate-800 text-slate-400 bg-slate-900 hover:text-indigo-400 active:scale-95 transition-all"
                        :class="isRefreshing ? 'animate-spin text-indigo-400' : ''"
                        title="Reload Dashboard Stats"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89M9 11l3 3L22 4" />
                        </svg>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 min-h-screen bg-slate-950 text-slate-100">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- ALARM SOS BANNER IF EMERGENCY ACTIVE -->
                <div 
                    v-if="activeSos.length > 0" 
                    class="bg-rose-500/10 border border-rose-500/40 rounded-3xl p-5 shadow-[0_0_30px_rgba(244,63,94,0.15)] flex flex-col md:flex-row md:items-center justify-between gap-4 animate-pulse-slow"
                >
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 rounded-2xl bg-rose-500/20 border border-rose-500 flex items-center justify-center text-rose-500 shadow-lg shadow-rose-500/10">
                            <svg class="w-6 h-6 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-rose-500 tracking-wider">CRITICAL: ACTIVE PANIC SOS ALARMS</h3>
                            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                                {{ activeSos.length }} guard(s) have triggered emergency alerts. Location telemetry is broadcasting.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-2">
                        <div 
                            v-for="sos in activeSos" 
                            :key="sos.id"
                            class="bg-slate-900 border border-rose-500/30 rounded-2xl p-3 flex items-center space-x-4"
                        >
                            <div class="text-left">
                                <span class="block text-[10px] text-rose-400 font-bold uppercase tracking-widest font-mono">GUARD ID: {{ sos.security_guard?.employee_id }}</span>
                                <span class="text-xs text-slate-200 font-bold block">{{ sos.security_guard?.full_name }}</span>
                                <span class="text-[10px] text-slate-500 font-mono mt-0.5 block">Triggered: {{ new Date(sos.triggered_at).toLocaleTimeString() }}</span>
                            </div>
                            <button 
                                @click="resolveTargetId = sos.id; showResolveModal = 'sos'" 
                                class="bg-rose-600 hover:bg-rose-500 text-white font-bold text-[10px] uppercase tracking-wider px-3.5 py-2 rounded-xl transition-all shadow-md shadow-rose-600/10 active:scale-95"
                            >
                                Acknowledge
                            </button>
                        </div>
                    </div>
                </div>

                <!-- OVERVIEW STATISTICS WIDGET DECK -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <!-- Stat 1 -->
                    <div class="bg-slate-900 border border-slate-850 p-4 rounded-3xl relative overflow-hidden shadow-lg">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest block font-mono">Ongoing Shifts</span>
                        <span class="text-3xl font-black font-mono text-indigo-400 mt-2 block">{{ stats.active_patrols_count ?? 0 }}</span>
                        <div class="absolute -right-4 -bottom-4 w-12 h-12 bg-indigo-500/5 rounded-full blur-lg"></div>
                    </div>
                    <!-- Stat 2 -->
                    <div class="bg-slate-900 border border-slate-850 p-4 rounded-3xl relative overflow-hidden shadow-lg">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest block font-mono">Guards Active</span>
                        <span class="text-3xl font-black font-mono text-emerald-400 mt-2 block">{{ stats.guards_count ?? 0 }}</span>
                        <div class="absolute -right-4 -bottom-4 w-12 h-12 bg-emerald-500/5 rounded-full blur-lg"></div>
                    </div>
                    <!-- Stat 3 -->
                    <div class="bg-slate-900 border border-slate-850 p-4 rounded-3xl relative overflow-hidden shadow-lg">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest block font-mono">Incidents Today</span>
                        <span class="text-3xl font-black font-mono text-amber-500 mt-2 block">{{ stats.incidents_today_count ?? 0 }}</span>
                        <div class="absolute -right-4 -bottom-4 w-12 h-12 bg-amber-500/5 rounded-full blur-lg"></div>
                    </div>
                    <!-- Stat 4 -->
                    <div class="bg-slate-900 border border-slate-850 p-4 rounded-3xl relative overflow-hidden shadow-lg">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest block font-mono">Active SOS</span>
                        <span class="text-3xl font-black font-mono mt-2 block" :class="(stats.active_sos_count ?? 0) > 0 ? 'text-rose-500 animate-pulse' : 'text-slate-500'">
                            {{ stats.active_sos_count ?? 0 }}
                        </span>
                        <div class="absolute -right-4 -bottom-4 w-12 h-12 bg-rose-500/5 rounded-full blur-lg"></div>
                    </div>
                    <!-- Stat 5 -->
                    <div class="bg-slate-900 border border-slate-850 p-4 rounded-3xl relative overflow-hidden shadow-lg col-span-2 lg:col-span-1">
                        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest block font-mono">Protected Sites</span>
                        <span class="text-3xl font-black font-mono text-slate-200 mt-2 block">{{ stats.locations_count ?? 0 }}</span>
                        <div class="absolute -right-4 -bottom-4 w-12 h-12 bg-slate-500/5 rounded-full blur-lg"></div>
                    </div>
                </div>

                <!-- SUBSECTION CONTROL TAB BAR -->
                <div class="bg-slate-900 border border-slate-850 p-1.5 rounded-2xl flex flex-wrap gap-1">
                    <button 
                        @click="activeTab = 'live'"
                        class="px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex items-center space-x-2"
                        :class="activeTab === 'live' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-850/50'"
                    >
                        📡 Live Dispatch
                    </button>
                    <button 
                        @click="activeTab = 'guards'"
                        class="px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex items-center space-x-2"
                        :class="activeTab === 'guards' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-850/50'"
                    >
                        🛡️ Security Guards
                    </button>
                    <button 
                        @click="activeTab = 'locations'"
                        class="px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex items-center space-x-2"
                        :class="activeTab === 'locations' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-850/50'"
                    >
                        📍 Checkpoints & Sites
                    </button>
                    <button 
                        @click="activeTab = 'routes'"
                        class="px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex items-center space-x-2"
                        :class="activeTab === 'routes' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-850/50'"
                    >
                        🗺️ Patrol Routes
                    </button>
                    <button 
                        @click="activeTab = 'contacts'"
                        class="px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex items-center space-x-2"
                        :class="activeTab === 'contacts' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-850/50'"
                    >
                        🔔 Alert Contacts
                    </button>
                </div>

                <!-- DYNAMIC TAB PANEL VIEWS -->
                <!-- TAB 1: LIVE DISPATCH PANEL -->
                <div v-if="activeTab === 'live'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Left: Ongoing Patrol Shifts -->
                    <div class="lg:col-span-2 space-y-4">
                        <div class="bg-slate-900 border border-slate-850 rounded-3xl p-5 shadow-lg space-y-4">
                            <h3 class="text-xs font-black tracking-widest text-indigo-400 uppercase flex items-center space-x-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                                <span>ONGOING PATROL FEED</span>
                            </h3>

                            <div v-if="activePatrols.length === 0" class="py-16 text-center border border-dashed border-slate-800 rounded-2xl">
                                <svg class="w-10 h-10 text-slate-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm text-slate-400 font-medium">No active patrol shifts currently running.</p>
                                <p class="text-[10px] text-slate-500 mt-1">Guards on shift will appear here upon route initialization.</p>
                            </div>

                            <div v-else class="space-y-4.5">
                                <div 
                                    v-for="patrol in activePatrols" 
                                    :key="patrol.id"
                                    class="bg-slate-950 border border-slate-850 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4"
                                >
                                    <div class="space-y-2 flex-1">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h4 class="text-sm font-bold text-slate-100">{{ patrol.route?.name }}</h4>
                                                <span class="text-[10px] font-mono text-slate-500">Duration: {{ Math.floor((Date.now() - new Date(patrol.started_at).getTime()) / 60000) }} mins elapsed</span>
                                            </div>
                                            <span 
                                                class="text-[9px] font-bold px-2 py-0.5 rounded border"
                                                :class="patrol.status === 'in_progress' ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' : 'bg-slate-900 text-slate-400 border-slate-800'"
                                            >
                                                {{ patrol.status.toUpperCase() }}
                                            </span>
                                        </div>

                                        <!-- Completion details -->
                                        <div class="flex items-center justify-between text-[10px] text-slate-400">
                                            <span>Guard: <strong class="text-slate-300">{{ patrol.security_guard?.full_name }}</strong></span>
                                            <span class="font-mono text-indigo-300 font-semibold">{{ patrol.completed_checkpoints }}/{{ patrol.total_checkpoints }} Checkpoints</span>
                                        </div>

                                        <!-- Progress Bar -->
                                        <div class="w-full bg-slate-900 h-1.5 rounded-full overflow-hidden">
                                            <div 
                                                class="bg-gradient-to-r from-indigo-500 to-indigo-600 h-full rounded-full transition-all"
                                                :style="{ width: `${(patrol.completed_checkpoints / patrol.total_checkpoints) * 100}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Incident Logs Feed -->
                    <div class="space-y-4">
                        <div class="bg-slate-900 border border-slate-850 rounded-3xl p-5 shadow-lg space-y-4">
                            <h3 class="text-xs font-black tracking-widest text-indigo-400 uppercase">RECENT INCIDENTS</h3>

                            <div v-if="recentIncidents.length === 0" class="py-16 text-center border border-dashed border-slate-800 rounded-2xl">
                                <p class="text-xs text-slate-500">No security incidents reported today.</p>
                            </div>

                            <div v-else class="space-y-3 max-h-[460px] overflow-y-auto pr-1">
                                <div 
                                    v-for="incident in recentIncidents" 
                                    :key="incident.id"
                                    class="bg-slate-950 border border-slate-850 rounded-xl p-3.5 space-y-3"
                                >
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h5 class="text-xs font-bold text-slate-200">{{ incident.title }}</h5>
                                            <span class="text-[9px] text-slate-500 block font-mono mt-0.5">
                                                Reporter: {{ incident.security_guard?.full_name }}
                                            </span>
                                        </div>
                                        <span 
                                            class="text-[8px] font-black uppercase px-2 py-0.5 rounded border"
                                            :class="{
                                                'low': 'bg-sky-500/10 text-sky-400 border-sky-500/20',
                                                'medium': 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                                'high': 'bg-orange-500/10 text-orange-400 border-orange-500/20',
                                                'critical': 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                                            }[incident.priority]"
                                        >
                                            {{ incident.priority }}
                                        </span>
                                    </div>
                                    
                                    <p class="text-[11px] text-slate-400 leading-relaxed">{{ incident.description || 'No description provided.' }}</p>

                                    <!-- Quick action to resolve open incidents -->
                                    <div class="flex justify-between items-center pt-2 border-t border-slate-900">
                                        <span class="text-[9px] font-mono text-slate-500">
                                            Status: <strong :class="incident.status === 'open' ? 'text-amber-500' : 'text-emerald-500'">{{ incident.status.toUpperCase() }}</strong>
                                        </span>
                                        
                                        <button 
                                            v-if="incident.status === 'open'"
                                            @click="resolveTargetId = incident.id; showResolveModal = 'incident'"
                                            class="bg-indigo-600/15 hover:bg-indigo-650/30 text-indigo-400 border border-indigo-500/20 text-[9px] font-bold uppercase tracking-wider px-2.5 py-1.5 rounded-lg active:scale-95 transition-all"
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
                <div v-else-if="activeTab === 'guards'" class="space-y-4 animate-fadeIn">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xs font-black tracking-widest text-slate-400 uppercase">Guards Registry</h3>
                        <button 
                            @click="showAddGuardModal = true"
                            class="bg-indigo-600 hover:bg-indigo-505 text-white font-bold text-xs uppercase tracking-wider px-4 py-2.5 rounded-xl transition-all shadow-md active:scale-95"
                        >
                            + Register Guard
                        </button>
                    </div>

                    <div class="bg-slate-900 border border-slate-850 rounded-3xl overflow-hidden shadow-lg">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-850 bg-slate-950 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                    <th class="p-4">Guard Details</th>
                                    <th class="p-4">Employee ID</th>
                                    <th class="p-4">Phone Number</th>
                                    <th class="p-4">Last Active</th>
                                    <th class="p-4 text-center">Duty Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-850 text-xs">
                                <tr v-for="g in guards" :key="g.id" class="hover:bg-slate-950/40">
                                    <td class="p-4 font-bold text-slate-200">{{ g.full_name }}</td>
                                    <td class="p-4 font-mono text-indigo-400">{{ g.employee_id }}</td>
                                    <td class="p-4 font-mono text-slate-400">{{ g.phone }}</td>
                                    <td class="p-4 text-slate-500 font-mono">
                                        {{ g.last_seen_at ? new Date(g.last_seen_at).toLocaleString() : 'Never' }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <span 
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                            :class="g.is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'"
                                        >
                                            {{ g.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="guards.length === 0">
                                    <td colspan="5" class="p-8 text-center text-slate-500">No guards registered under this tenant.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: CHECKPOINTS & SITES -->
                <div v-else-if="activeTab === 'locations'" class="space-y-6 animate-fadeIn">
                    <div class="flex flex-col md:flex-row justify-between gap-4">
                        <div class="flex gap-2">
                            <button @click="showAddLocationModal = true" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase tracking-wider px-4 py-2.5 rounded-xl transition-all shadow-md">
                                + Add Location
                            </button>
                            <button @click="showAddCheckpointModal = true" class="bg-violet-650 hover:bg-violet-600 text-white font-bold text-xs uppercase tracking-wider px-4 py-2.5 rounded-xl transition-all shadow-md">
                                + Add Checkpoint
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Left: Locations list -->
                        <div class="bg-slate-900 border border-slate-850 rounded-3xl p-5 shadow-lg space-y-4">
                            <h3 class="text-xs font-black tracking-widest text-indigo-400 uppercase">LOCATIONS</h3>
                            <div class="space-y-3">
                                <div v-for="l in locations" :key="l.id" class="bg-slate-950 border border-slate-850 rounded-2xl p-4">
                                    <h4 class="text-sm font-bold text-slate-100">{{ l.name }}</h4>
                                    <p class="text-xs text-slate-500 mt-1">{{ l.address }}, {{ l.city }}</p>
                                    <div class="mt-3 flex justify-between items-center text-[10px] font-mono text-indigo-400">
                                        <span>Geofence: {{ l.geofence_radius }}m</span>
                                        <span>Coords: {{ l.latitude }}, {{ l.longitude }}</span>
                                    </div>
                                </div>
                                <div v-if="locations.length === 0" class="p-6 text-center text-slate-500 text-xs">No locations registered.</div>
                            </div>
                        </div>

                        <!-- Right: Checkpoints sequence table -->
                        <div class="lg:col-span-2 bg-slate-900 border border-slate-850 rounded-3xl p-5 shadow-lg space-y-4">
                            <h3 class="text-xs font-black tracking-widest text-indigo-400 uppercase">CHECKPOINTS DEFINITIONS</h3>
                            <div class="space-y-3 overflow-y-auto max-h-[500px] pr-1">
                                <div v-for="cp in checkpoints" :key="cp.id" class="bg-slate-950 border border-slate-850 rounded-2xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-200">{{ cp.name }}</h4>
                                        <p class="text-xs text-slate-500 mt-1">{{ cp.description || 'No description' }}</p>
                                        <p class="text-[10px] text-slate-500 mt-1">Location: <strong class="text-slate-400">{{ cp.location?.name }}</strong></p>
                                    </div>
                                    <div class="text-right space-y-1">
                                        <span class="inline-block text-[9px] font-mono bg-slate-900 border border-slate-800 text-indigo-400 px-2 py-0.5 rounded">
                                            SCAN METHOD: {{ cp.scan_method.toUpperCase() }}
                                        </span>
                                        <div class="text-[9px] text-slate-500 font-mono">
                                            Photo req: {{ cp.photo_requirement }} • Sig: {{ cp.signature_required ? 'Yes' : 'No' }}
                                        </div>
                                    </div>
                                </div>
                                <div v-if="checkpoints.length === 0" class="p-8 text-center text-slate-500 text-xs">No checkpoints configured.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: PATROL ROUTES CONFIG -->
                <div v-else-if="activeTab === 'routes'" class="space-y-4 animate-fadeIn">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xs font-black tracking-widest text-slate-400 uppercase">Patrol Sequences</h3>
                        <button 
                            @click="showAddRouteModal = true"
                            class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase tracking-wider px-4 py-2.5 rounded-xl transition-all shadow-md active:scale-95"
                        >
                            + Create Patrol Route
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div 
                            v-for="route in routes" 
                            :key="route.id"
                            class="bg-slate-900 border border-slate-850 rounded-3xl p-5 shadow-lg space-y-4"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-100">{{ route.name }}</h4>
                                    <p class="text-xs text-slate-500 mt-1">{{ route.description || 'No description provided.' }}</p>
                                </div>
                                <div class="text-right text-[10px] text-slate-400 font-mono space-y-1">
                                    <span class="block bg-slate-950 px-2 py-0.5 rounded border border-slate-800">
                                        Expected: {{ route.expected_duration_mins || 30 }} mins
                                    </span>
                                    <span class="block text-indigo-400 font-bold">
                                        {{ route.enforce_order ? 'Strict Order' : 'Flexible Sequence' }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h5 class="text-[9px] font-bold text-slate-400 uppercase tracking-widest pl-1">Ordered Checkpoints Sequence</h5>
                                <div class="space-y-1.5 pl-2 border-l border-indigo-500/20">
                                    <div 
                                        v-for="rc in route.route_checkpoints" 
                                        :key="rc.id"
                                        class="text-xs text-slate-300 flex items-center space-x-2"
                                    >
                                        <span class="w-4 h-4 rounded bg-slate-950 text-[10px] font-mono text-indigo-400 flex items-center justify-center border border-slate-800">
                                            {{ rc.position }}
                                        </span>
                                        <span>{{ rc.checkpoint?.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="routes.length === 0" class="col-span-2 p-12 text-center text-slate-500 bg-slate-900/40 border border-slate-850 rounded-3xl text-sm">
                            No patrol routes defined.
                        </div>
                    </div>
                </div>

                <!-- TAB 5: ALERT CONTACTS -->
                <div v-else-if="activeTab === 'contacts'" class="space-y-4 animate-fadeIn">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xs font-black tracking-widest text-slate-400 uppercase">Emergency Notification List</h3>
                        <button 
                            @click="showAddContactModal = true"
                            class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase tracking-wider px-4 py-2.5 rounded-xl transition-all shadow-md active:scale-95"
                        >
                            + Add Contact
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div 
                            v-for="c in contacts" 
                            :key="c.id"
                            class="bg-slate-900 border border-slate-850 rounded-3xl p-5 shadow-lg space-y-4"
                        >
                            <div>
                                <h4 class="text-sm font-bold text-slate-100">{{ c.name }}</h4>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-400 block mt-0.5">{{ c.role_label }}</span>
                            </div>

                            <div class="space-y-1 text-xs text-slate-400 font-mono">
                                <p v-if="c.phone">📞 {{ c.phone }}</p>
                                <p v-if="c.email">✉️ {{ c.email }}</p>
                            </div>

                            <div class="space-y-2 pt-3 border-t border-slate-850 text-[10px] font-mono">
                                <div>
                                    <span class="text-slate-500 block uppercase font-bold text-[9px] mb-1">Alert Channels:</span>
                                    <span v-for="ch in c.notify_channels" :key="ch" class="bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-1.5 py-0.5 rounded mr-1">
                                        {{ ch.toUpperCase() }}
                                    </span>
                                </div>
                                <div class="pt-2">
                                    <span class="text-slate-500 block uppercase font-bold text-[9px] mb-1">Notify On:</span>
                                    <span v-for="evt in c.notify_on" :key="evt" class="bg-amber-500/10 text-amber-400 border border-amber-500/20 px-1.5 py-0.5 rounded mr-1 block mt-1">
                                        {{ evt.toUpperCase() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-if="contacts.length === 0" class="col-span-3 p-12 text-center text-slate-500 bg-slate-900/40 border border-slate-850 rounded-3xl text-sm">
                            No dispatch contacts configured.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- RESOLVE MODAL FOR INCIDENTS & SOS -->
        <div 
            v-if="showResolveModal" 
            class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6"
        >
            <div class="bg-slate-900 border border-slate-850 rounded-3xl p-6 w-full max-w-sm space-y-4 shadow-2xl animate-fadeIn">
                <h4 class="text-sm font-bold text-slate-100">
                    Resolve {{ showResolveModal === 'incident' ? 'Incident Report' : 'Emergency SOS Alert' }}
                </h4>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Type a supervisor resolution statement or logging note. This will mark the alarm as resolved and close the dispatch window.
                </p>
                
                <textarea 
                    v-model="resolutionNote"
                    rows="3"
                    class="w-full bg-slate-950 border border-slate-850 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-slate-100 p-2.5 rounded-xl text-xs focus:outline-none"
                    placeholder="E.g. Security dispatch team dispatched to North Pier. Intruder caught and police notified..."
                ></textarea>

                <div class="flex space-x-3">
                    <button 
                        @click="showResolveModal = null; resolveTargetId = null; resolutionNote = ''"
                        class="flex-1 py-3 bg-slate-800 hover:bg-slate-750 text-slate-300 text-xs font-bold uppercase tracking-wider rounded-xl active:scale-95"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="showResolveModal === 'incident' ? submitResolveIncident() : submitResolveSos()"
                        :disabled="!resolutionNote.trim()"
                        class="flex-1 py-3 bg-indigo-650 hover:bg-indigo-600 disabled:opacity-40 disabled:pointer-events-none text-white text-xs font-bold uppercase tracking-wider rounded-xl active:scale-95 shadow-lg shadow-indigo-600/20"
                    >
                        Confirm Close
                    </button>
                </div>
            </div>
        </div>

        <!-- REGISTER GUARD MODAL -->
        <div v-if="showAddGuardModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6">
            <div class="bg-slate-900 border border-slate-850 rounded-3xl p-6 w-full max-w-sm space-y-4 shadow-2xl animate-fadeIn">
                <h4 class="text-sm font-bold text-slate-100">Register Security Guard</h4>
                
                <div class="space-y-3">
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase tracking-widest pl-1">Guard Full Name</label>
                        <input v-model="guardForm.full_name" type="text" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-xs text-slate-100 focus:outline-none" placeholder="John Doe" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase tracking-widest pl-1">Phone (Login)</label>
                        <input v-model="guardForm.phone" type="text" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-xs text-slate-100 focus:outline-none" placeholder="+35799123456" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase tracking-widest pl-1">Employee ID</label>
                        <input v-model="guardForm.employee_id" type="text" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-xs text-slate-100 focus:outline-none" placeholder="GD-007" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase tracking-widest pl-1">Pin Hash (Offline Mode Code)</label>
                        <input v-model="guardForm.pin" type="password" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-xs text-slate-100 focus:outline-none" />
                    </div>
                </div>

                <div class="flex space-x-3 pt-2">
                    <button @click="showAddGuardModal = false" class="flex-1 py-3 bg-slate-800 text-slate-300 text-xs font-bold uppercase tracking-wider rounded-xl">Cancel</button>
                    <button @click="submitAddGuard" class="flex-1 py-3 bg-indigo-600 text-white text-xs font-bold uppercase tracking-wider rounded-xl shadow-lg shadow-indigo-600/20">Register</button>
                </div>
            </div>
        </div>

        <!-- ADD LOCATION MODAL -->
        <div v-if="showAddLocationModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6">
            <div class="bg-slate-900 border border-slate-850 rounded-3xl p-6 w-full max-w-sm space-y-4 shadow-2xl animate-fadeIn">
                <h4 class="text-sm font-bold text-slate-100">Add Site Location</h4>
                
                <div class="space-y-3">
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase pl-1">Location Name</label>
                        <input v-model="locationForm.name" type="text" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-xs text-slate-100 focus:outline-none" placeholder="Limassol Marina Port" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase pl-1">Address</label>
                        <input v-model="locationForm.address" type="text" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-xs text-slate-100 focus:outline-none" placeholder="Marina Road 1" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 uppercase pl-1">Latitude</label>
                            <input v-model="locationForm.latitude" type="number" step="0.000001" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-xs text-slate-100 focus:outline-none" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 uppercase pl-1">Longitude</label>
                            <input v-model="locationForm.longitude" type="number" step="0.000001" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-xs text-slate-100 focus:outline-none" />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase pl-1">Geofence Radius (meters)</label>
                        <input v-model="locationForm.geofence_radius" type="number" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-xs text-slate-100 focus:outline-none" />
                    </div>
                </div>

                <div class="flex space-x-3 pt-2">
                    <button @click="showAddLocationModal = false" class="flex-1 py-3 bg-slate-800 text-slate-300 text-xs font-bold uppercase rounded-xl">Cancel</button>
                    <button @click="submitAddLocation" class="flex-1 py-3 bg-indigo-600 text-white text-xs font-bold uppercase rounded-xl">Add</button>
                </div>
            </div>
        </div>

        <!-- ADD CHECKPOINT MODAL -->
        <div v-if="showAddCheckpointModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6">
            <div class="bg-slate-900 border border-slate-850 rounded-3xl p-6 w-full max-w-md space-y-4 shadow-2xl animate-fadeIn overflow-y-auto max-h-[90vh]">
                <h4 class="text-sm font-bold text-slate-100">Add Checkpoint</h4>
                
                <div class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase">Select Location Site</label>
                        <select v-model="checkpointForm.location_id" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none">
                            <option value="">Choose Site...</option>
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase">Checkpoint Name</label>
                        <input v-model="checkpointForm.name" type="text" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none" placeholder="Server Room Gate" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase">Scan Method Required</label>
                        <select v-model="checkpointForm.scan_method" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none">
                            <option value="qr">QR Code Scanner Only</option>
                            <option value="nfc">NFC Tag Only</option>
                            <option value="both">Both Methods Allowed</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 uppercase">Latitude</label>
                            <input v-model="checkpointForm.latitude" type="number" step="0.000001" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none" />
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 uppercase">Longitude</label>
                            <input v-model="checkpointForm.longitude" type="number" step="0.000001" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 uppercase">Photo Requirement</label>
                            <select v-model="checkpointForm.photo_requirement" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none">
                                <option value="off">Off</option>
                                <option value="optional">Optional</option>
                                <option value="required">Required</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 uppercase">Signature Check-in</label>
                            <select v-model="checkpointForm.signature_required" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none">
                                <option :value="false">Not Required</option>
                                <option :value="true">Required</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-3 pt-2">
                    <button @click="showAddCheckpointModal = false" class="flex-1 py-3 bg-slate-800 text-slate-300 text-xs font-bold uppercase rounded-xl">Cancel</button>
                    <button @click="submitAddCheckpoint" class="flex-1 py-3 bg-indigo-600 text-white text-xs font-bold uppercase rounded-xl">Add Checkpoint</button>
                </div>
            </div>
        </div>

        <!-- CREATE ROUTE MODAL -->
        <div v-if="showAddRouteModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6">
            <div class="bg-slate-900 border border-slate-850 rounded-3xl p-6 w-full max-w-md space-y-4 shadow-2xl animate-fadeIn overflow-y-auto max-h-[90vh]">
                <h4 class="text-sm font-bold text-slate-100">Create Patrol Route</h4>
                
                <div class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase">Route Name</label>
                        <input v-model="routeForm.name" type="text" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none" placeholder="Marina North Dock Shift" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase">Description</label>
                        <textarea v-model="routeForm.description" rows="2" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none" placeholder="Covering docks, fuel office..."></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 uppercase">Enforce Strict Order</label>
                            <select v-model="routeForm.enforce_order" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none">
                                <option :value="true">Strict Sequence (1, 2, 3)</option>
                                <option :value="false">Any Order</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] text-slate-400 uppercase">Allow Skip Checkpoints</label>
                            <select v-model="routeForm.allow_skip" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none">
                                <option :value="true">Allowed with Reason</option>
                                <option :value="false">Prohibited</option>
                            </select>
                        </div>
                    </div>

                    <!-- Checkpoint Sequence Selector -->
                    <div class="space-y-2">
                        <label class="block text-[10px] text-slate-400 uppercase pl-1">Assign Checkpoints (Check to add in order)</label>
                        <div class="bg-slate-950 border border-slate-850 rounded-xl p-3 max-h-40 overflow-y-auto space-y-2">
                            <div v-for="cp in checkpoints" :key="cp.id" class="flex items-center space-x-3">
                                <input 
                                    type="checkbox" 
                                    :value="cp.id" 
                                    v-model="routeForm.checkpoints"
                                    class="w-4 h-4 text-indigo-600 rounded bg-slate-900 border-slate-800 focus:ring-0"
                                />
                                <span class="text-xs text-slate-300">{{ cp.name }} <small class="text-slate-500">({{ cp.location?.name }})</small></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-3 pt-2">
                    <button @click="showAddRouteModal = false" class="flex-1 py-3 bg-slate-800 text-slate-300 text-xs font-bold uppercase rounded-xl">Cancel</button>
                    <button @click="submitAddRoute" class="flex-1 py-3 bg-indigo-600 text-white text-xs font-bold uppercase rounded-xl">Create Route</button>
                </div>
            </div>
        </div>

        <!-- ADD CONTACT MODAL -->
        <div v-if="showAddContactModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6">
            <div class="bg-slate-900 border border-slate-850 rounded-3xl p-6 w-full max-w-sm space-y-4 shadow-2xl animate-fadeIn">
                <h4 class="text-sm font-bold text-slate-100">Add Alert Contact</h4>
                
                <div class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase">Contact Name</label>
                        <input v-model="contactForm.name" type="text" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none" placeholder="Operations Manager" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase">Role Label</label>
                        <input v-model="contactForm.role_label" type="text" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none" placeholder="Supervisor" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase">Phone (SMS Alert)</label>
                        <input v-model="contactForm.phone" type="text" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none" placeholder="+35799887766" />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-[10px] text-slate-400 uppercase">Email</label>
                        <input v-model="contactForm.email" type="email" class="w-full bg-slate-950 border border-slate-850 p-2.5 rounded-xl text-slate-100 focus:outline-none" placeholder="supervisor@sentinel.com" />
                    </div>
                </div>

                <div class="flex space-x-3 pt-2">
                    <button @click="showAddContactModal = false" class="flex-1 py-3 bg-slate-800 text-slate-300 text-xs font-bold uppercase rounded-xl">Cancel</button>
                    <button @click="submitAddContact" class="flex-1 py-3 bg-indigo-600 text-white text-xs font-bold uppercase rounded-xl">Add Contact</button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style>
/* Dashboard Micro-animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes pulse-slow {
    0%, 100% { border-color: rgba(244, 63, 94, 0.4); shadow: 0 0 15px rgba(244, 63, 94, 0.1); }
    50% { border-color: rgba(244, 63, 94, 0.8); shadow: 0 0 30px rgba(244, 63, 94, 0.25); }
}
.animate-pulse-slow {
    animation: pulse-slow 2s infinite ease-in-out;
}
</style>
