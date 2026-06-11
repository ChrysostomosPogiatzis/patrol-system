<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

// Component Imports
import StatsCard from '@/Components/Admin/StatsCard.vue';
import OngoingPatrols from '@/Components/Admin/OngoingPatrols.vue';
import RecentIncidents from '@/Components/Admin/RecentIncidents.vue';
import ResolveModal from '@/Components/Admin/ResolveModal.vue';
import GuardLocations from '@/Components/Admin/GuardLocations.vue';
import LiveMap from '@/Components/Admin/LiveMap.vue';

interface Guard {
    id: number;
    full_name: string;
    phone: string;
    employee_id: string;
    is_active: boolean;
}

interface Route {
    id: number;
    name: string;
}

interface Patrol {
    id: number;
    started_at: string;
    status: string;
    total_checkpoints: number;
    completed_checkpoints: number;
    route?: Route;
    security_guard?: Guard;
    tenant?: { name: string };
}

interface Incident {
    id: number;
    title: string;
    description?: string;
    priority: 'low' | 'medium' | 'high' | 'critical';
    status: string;
    security_guard?: Guard;
    tenant?: { name: string };
}

interface SosAlert {
    id: number;
    status: string;
    triggered_latitude: number;
    triggered_longitude: number;
    triggered_at: string;
    security_guard?: Guard;
    tenant?: { name: string };
}

const stats = ref<any>({});
const activePatrols = ref<Patrol[]>([]);
const recentIncidents = ref<Incident[]>([]);
const activeSos = ref<SosAlert[]>([]);
const guardLocations = ref<any[]>([]);
const guardPings24h = ref<any[]>([]);
const locations = ref<any[]>([]);

const showResolveModal = ref<'incident' | 'sos' | null>(null);
const resolveTargetId = ref<number | null>(null);
const isRefreshing = ref(false);
let refreshInterval: any = null;

async function fetchOverview() {
    try {
        const response = await axios.get('/admin/api/overview');
        stats.value = response.data.stats;
        activePatrols.value = response.data.active_patrols;
        recentIncidents.value = response.data.recent_incidents;
        activeSos.value = response.data.active_sos;
        guardLocations.value = response.data.guard_locations || [];
        guardPings24h.value = response.data.guard_pings_24h || [];
        locations.value = response.data.locations || [];
    } catch (e) {
        console.error('Failed to load overview data:', e);
    }
}

async function handleResolveConfirm(note: string) {
    if (!resolveTargetId.value || !showResolveModal.value) return;
    try {
        const endpoint = showResolveModal.value === 'incident'
            ? `/admin/api/incidents/${resolveTargetId.value}/resolve`
            : `/admin/api/sos/${resolveTargetId.value}/resolve`;
        
        await axios.post(endpoint, { resolution_note: note });
        showResolveModal.value = null;
        resolveTargetId.value = null;
        fetchOverview();
    } catch (e) {
        alert('Failed to submit resolution.');
    }
}

function openResolveModal(type: 'incident' | 'sos', id: number) {
    resolveTargetId.value = id;
    showResolveModal.value = type;
}

onMounted(() => {
    fetchOverview();
    // 5-second polling for real-time dispatch updates
    refreshInterval = setInterval(fetchOverview, 5000);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});
</script>

<template>
    <Head title="Live Dispatch Console" />

    <AdminLayout title="Live Dispatch Console">
        <!-- CRITICAL SOS PANIC ALARMS BANNER -->
        <div 
            v-if="activeSos.length > 0"
            class="mb-6 bg-red-50 border-l-4 border-red-600 text-red-950 p-5 rounded-r-2xl shadow-sm shadow-red-100/50 animate-pulse"
        >
            <h4 class="font-black flex items-center space-x-2 text-xs uppercase tracking-wider font-mono text-red-700">
                <span class="w-2.5 h-2.5 rounded-full bg-red-650 animate-ping"></span>
                <span>Active SOS Panic Alerts ({{ activeSos.length }})</span>
            </h4>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div 
                    v-for="sos in activeSos" 
                    :key="sos.id"
                    class="bg-white border border-red-200/80 p-4 rounded-xl flex items-center justify-between hover:border-red-400 transition-colors shadow-sm"
                >
                    <div class="space-y-0.5">
                        <span class="block text-xs font-black text-slate-850 font-mono">
                            {{ sos.security_guard?.full_name }}
                            <span v-if="sos.tenant" class="ml-1.5 text-[8px] bg-red-50 text-red-600 border border-red-200 px-1.5 py-0.5 rounded uppercase tracking-wider font-black">
                                {{ sos.tenant.name }}
                            </span>
                        </span>
                        <span class="text-[9px] text-slate-450 font-mono font-bold uppercase">Time: {{ new Date(sos.triggered_at).toLocaleTimeString() }}</span>
                    </div>
                    <button 
                        @click="openResolveModal('sos', sos.id)"
                        class="bg-red-600 hover:bg-red-500 hover:scale-105 active:scale-95 text-white font-black text-[9px] uppercase tracking-widest px-3.5 py-2 rounded-xl transition-all"
                    >
                        Resolve
                    </button>
                </div>
            </div>
        </div>

        <!-- STATS METRIC TILE GROUP -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
            <StatsCard title="Active Patrols" :value="stats.active_patrols_count ?? 0" color="indigo">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </StatsCard>
            
            <StatsCard title="Active Locations" :value="stats.locations_count ?? 0" color="emerald">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                </svg>
            </StatsCard>
            
            <StatsCard title="Incidents Today" :value="stats.incidents_today_count ?? 0" color="amber">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </StatsCard>
            
            <StatsCard title="Guards On Duty" :value="stats.guards_count ?? 0" color="sky">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </StatsCard>
        </div>

        <!-- LIVE MAP CONTAINER -->
        <div class="mb-6">
            <LiveMap :locations="locations" :guard-locations="guardLocations" :guard-pings-24h="guardPings24h" />
        </div>

        <!-- LAYOUT GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Active Patrols -->
            <div>
                <OngoingPatrols :patrols="activePatrols" />
            </div>

            <!-- Guard Locations -->
            <div>
                <GuardLocations :locations="guardLocations" />
            </div>

            <!-- Incidents listing -->
            <div>
                <RecentIncidents :incidents="recentIncidents" @resolve="openResolveModal('incident', $event)" />
            </div>
        </div>

        <!-- RESOLVE MODAL -->
        <ResolveModal 
            :show="showResolveModal !== null" 
            :type="showResolveModal" 
            @close="showResolveModal = null; resolveTargetId = null;"
            @confirm="handleResolveConfirm"
        />
    </AdminLayout>
</template>
