<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

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
}

interface CheckpointLog {
    id: number;
    status: 'pending' | 'scanned' | 'skipped' | 'out_of_order_attempt';
    scanned_at?: string;
    skipped_at?: string;
    skip_reason?: string;
    note?: string;
    checkpoint: {
        name: string;
        description: string;
    };
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
    tenant?: { name: string };
}

const patrols = ref<Patrol[]>([]);
const incidents = ref<Incident[]>([]);
const guards = ref<Guard[]>([]);

// Filter states
const selectedGuard = ref<string>('');
const selectedTimeframe = ref<string>('7_days');
const searchQuery = ref<string>('');
const activeTab = ref<'patrols' | 'incidents'>('patrols');
const isLoading = ref<boolean>(false);

// Detail Modal state
const selectedPatrolDetails = ref<Patrol | null>(null);

async function fetchHistory() {
    isLoading.value = true;
    try {
        const response = await axios.get('/admin/api/history', {
            params: {
                guard_id: selectedGuard.value || undefined,
                timeframe: selectedTimeframe.value
            }
        });
        patrols.value = response.data.patrols || [];
        incidents.value = response.data.incidents || [];
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

function formatDuration(seconds?: number): string {
    if (!seconds) return 'N/A';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    return `${m} mins`;
}

function formatDate(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function getPriorityClass(priority: string) {
    switch (priority) {
        case 'critical': return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'high': return 'bg-red-50 text-red-600 border-red-200';
        case 'medium': return 'bg-amber-50 text-amber-700 border-amber-200';
        default: return 'bg-slate-100 text-slate-650 border-slate-200';
    }
}
</script>

<template>
    <Head title="Patrol & Incident History" />

    <AdminLayout title="Patrol & Incident History">
        <!-- FILTER BAR -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 mb-6 shadow-sm">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Guard Filter -->
                <div class="flex flex-col">
                    <label class="text-[10px] font-black uppercase tracking-wider text-slate-450 mb-1.5 font-mono">Security Guard</label>
                    <select 
                        v-model="selectedGuard"
                        class="bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer min-h-[44px]"
                    >
                        <option value="">All Security Guards</option>
                        <option v-for="g in guards" :key="g.id" :value="g.id">
                            {{ g.full_name }} ({{ g.employee_id }})
                        </option>
                    </select>
                </div>

                <!-- Timeframe Filter -->
                <div class="flex flex-col">
                    <label class="text-[10px] font-black uppercase tracking-wider text-slate-450 mb-1.5 font-mono">Time Period</label>
                    <select 
                        v-model="selectedTimeframe"
                        class="bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer min-h-[44px]"
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
                    <label class="text-[10px] font-black uppercase tracking-wider text-slate-450 mb-1.5 font-mono">Search text</label>
                    <input 
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search routes, guards, notes..."
                        class="bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 min-h-[44px]"
                    />
                </div>
            </div>
        </div>

        <!-- TABS BAR -->
        <div class="flex border-b border-slate-200 mb-6">
            <button 
                @click="activeTab = 'patrols'"
                class="px-5 py-3 text-xs font-black uppercase tracking-wider border-b-2 font-mono transition-all"
                :class="activeTab === 'patrols' ? 'border-indigo-650 text-indigo-650' : 'border-transparent text-slate-400 hover:text-slate-600'"
            >
                📋 Patrol Shifts ({{ patrols.length }})
            </button>
            <button 
                @click="activeTab = 'incidents'"
                class="px-5 py-3 text-xs font-black uppercase tracking-wider border-b-2 font-mono transition-all"
                :class="activeTab === 'incidents' ? 'border-indigo-650 text-indigo-650' : 'border-transparent text-slate-400 hover:text-slate-600'"
            >
                ⚠️ All Incidents Log ({{ incidents.length }})
            </button>
        </div>

        <!-- LOADING INDICATOR -->
        <div v-if="isLoading" class="flex flex-col items-center justify-center py-20 text-slate-400 gap-3">
            <div class="w-8 h-8 border-4 border-indigo-500/20 border-t-indigo-600 rounded-full animate-spin"></div>
            <span class="text-xs font-bold font-mono uppercase tracking-wider">Fetching History logs...</span>
        </div>

        <!-- NO DATA STATE -->
        <div v-else-if="activeTab === 'patrols' && patrols.length === 0" class="bg-white border border-slate-200/80 rounded-3xl p-12 text-center py-16">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest font-mono">No patrol history logs match the filters.</p>
        </div>
        <div v-else-if="activeTab === 'incidents' && incidents.length === 0" class="bg-white border border-slate-200/80 rounded-3xl p-12 text-center py-16">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest font-mono">No incidents recorded in this timeframe.</p>
        </div>

        <!-- TABS VIEWS -->
        <div v-else class="space-y-4">
            <!-- PATROL SHIFTS VIEW -->
            <template v-if="activeTab === 'patrols'">
                <div 
                    v-for="patrol in patrols" 
                    :key="patrol.id"
                    class="bg-white border border-slate-200/85 rounded-2xl p-5 hover:border-slate-300 transition-colors shadow-sm space-y-4"
                >
                    <!-- Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <div class="space-y-0.5">
                            <span class="text-xs font-black text-slate-800 uppercase tracking-wide">
                                {{ patrol.route?.name || 'Manual Patrol' }}
                            </span>
                            <div class="flex items-center space-x-2 text-[10px] text-slate-450 font-bold font-mono">
                                <span>Guard: {{ patrol.security_guard?.full_name || 'Deleted Guard' }}</span>
                                <span>•</span>
                                <span>Started: {{ formatDate(patrol.started_at) }}</span>
                                <span v-if="patrol.tenant" class="ml-1 text-[8px] bg-slate-100 border border-slate-200 text-slate-600 px-1.5 py-0.5 rounded uppercase font-black">
                                    {{ patrol.tenant.name }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <!-- Progress Badge -->
                            <span class="text-[9px] font-mono font-bold bg-indigo-50 text-indigo-600 border border-indigo-100 px-2 py-0.5 rounded-lg">
                                {{ patrol.completed_checkpoints }}/{{ patrol.total_checkpoints }} Checkpoints
                            </span>
                            <!-- Status Badge -->
                            <span 
                                class="text-[9px] font-mono font-black uppercase tracking-wider px-2 py-0.5 rounded-lg border"
                                :class="[
                                    patrol.status === 'completed' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : '',
                                    patrol.status === 'in_progress' ? 'bg-indigo-50 text-indigo-600 border-indigo-200' : '',
                                    patrol.status === 'abandoned' ? 'bg-rose-50 text-rose-600 border-rose-200' : '',
                                ]"
                            >
                                {{ patrol.status }}
                            </span>
                        </div>
                    </div>

                    <!-- Statistics & Notes -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                        <div>
                            <span class="block text-[9px] font-black uppercase tracking-wider text-slate-400 font-mono">Completion Time</span>
                            <span class="font-mono font-bold text-slate-650">{{ patrol.completed_at ? formatDate(patrol.completed_at) : 'Active Shift' }}</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-black uppercase tracking-wider text-slate-400 font-mono">Total Duration</span>
                            <span class="font-mono font-bold text-slate-650">{{ formatDuration(patrol.duration_seconds) }}</span>
                        </div>
                        <div class="flex justify-end items-center">
                            <button 
                                @click="selectedPatrolDetails = patrol"
                                class="text-[10px] font-black font-mono uppercase tracking-widest text-indigo-600 hover:text-indigo-500 bg-indigo-50/50 hover:bg-indigo-50 px-3.5 py-2 rounded-xl transition-all"
                            >
                                View Log Details
                            </button>
                        </div>
                    </div>

                    <!-- NESTED INCIDENTS LOG FOR THIS PATROL -->
                    <div 
                        v-if="patrol.incidents && patrol.incidents.length > 0"
                        class="bg-amber-50/40 border border-amber-250/30 rounded-xl p-4 space-y-3"
                    >
                        <h5 class="text-[10px] font-black uppercase tracking-wider text-amber-700 font-mono flex items-center space-x-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Patrol Incidents Recorded ({{ patrol.incidents.length }})</span>
                        </h5>
                        <div class="space-y-2.5">
                            <div 
                                v-for="inc in patrol.incidents" 
                                :key="inc.id"
                                class="bg-white border border-amber-200/60 p-3 rounded-lg flex flex-col sm:flex-row sm:items-center justify-between gap-2 shadow-xs"
                            >
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs font-bold text-slate-800">{{ inc.title }}</span>
                                        <span 
                                            class="text-[8px] font-mono font-black uppercase tracking-wider px-1.5 py-0.5 rounded border"
                                            :class="getPriorityClass(inc.priority)"
                                        >
                                            {{ inc.priority }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-1">{{ inc.description || 'No description provided.' }}</p>
                                    <div class="flex items-center space-x-2 mt-1.5 text-[9px] text-slate-400 font-bold font-mono">
                                        <span v-if="inc.checkpoint">Checkpoint: {{ inc.checkpoint.name }}</span>
                                    </div>
                                </div>
                                <div class="text-right flex sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-1.5">
                                    <span 
                                        class="text-[8px] font-mono font-black uppercase tracking-widest px-1.5 py-0.5 rounded border"
                                        :class="inc.status === 'resolved' ? 'bg-emerald-50 text-emerald-600 border-emerald-150' : 'bg-rose-50 text-rose-600 border-rose-150'"
                                    >
                                        {{ inc.status }}
                                    </span>
                                    <span class="text-[9px] text-slate-400 font-mono font-semibold">{{ formatDate(inc.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- STANDALONE INCIDENTS LOG VIEW -->
            <template v-else>
                <div 
                    v-for="inc in incidents" 
                    :key="inc.id"
                    class="bg-white border border-slate-200/85 rounded-2xl p-5 hover:border-slate-300 transition-colors shadow-sm space-y-3"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-100 pb-3 gap-2">
                        <div class="space-y-0.5">
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                <span class="text-xs font-black text-slate-800 uppercase tracking-wide">{{ inc.title }}</span>
                                <span 
                                    class="text-[8px] font-mono font-black uppercase tracking-wider px-1.5 py-0.5 rounded border"
                                    :class="getPriorityClass(inc.priority)"
                                >
                                    {{ inc.priority }}
                                </span>
                                <span v-if="inc.tenant" class="text-[8px] bg-slate-100 border border-slate-200 text-slate-650 px-1.5 py-0.5 rounded uppercase font-black">
                                    {{ inc.tenant.name }}
                                </span>
                            </div>
                            <div class="flex items-center space-x-2 text-[10px] text-slate-450 font-bold font-mono">
                                <span>Guard: {{ inc.security_guard?.full_name || 'Deleted Guard' }}</span>
                                <span>•</span>
                                <span>Time: {{ formatDate(inc.created_at) }}</span>
                            </div>
                        </div>
                        <div>
                            <span 
                                class="text-[9px] font-mono font-black uppercase tracking-widest px-2.5 py-1 rounded-lg border"
                                :class="inc.status === 'resolved' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-rose-50 text-rose-600 border-rose-200'"
                            >
                                {{ inc.status }}
                            </span>
                        </div>
                    </div>

                    <div class="text-xs text-slate-650 space-y-2">
                        <p>{{ inc.description || 'No description provided.' }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[10px] text-slate-450 font-mono font-bold uppercase">
                            <div>Site Location: {{ inc.location?.name || 'N/A' }}</div>
                            <div>Checkpoint: {{ inc.checkpoint?.name || 'General Route Incident' }}</div>
                        </div>
                    </div>

                    <!-- Resolution logs -->
                    <div 
                        v-if="inc.status === 'resolved'"
                        class="bg-emerald-50/30 border border-emerald-150/40 rounded-xl p-3.5 text-xs text-emerald-950 space-y-1"
                    >
                        <span class="block text-[9px] font-black uppercase tracking-wider text-emerald-700 font-mono">Resolution details</span>
                        <p class="italic text-slate-600">{{ inc.resolution_note || 'Resolved by Administrator.' }}</p>
                        <span class="block text-[8px] text-slate-400 font-mono font-bold uppercase mt-1">Resolved At: {{ formatDate(inc.resolved_at!) }}</span>
                    </div>
                </div>
            </template>
        </div>

        <!-- DETAILS MODAL FOR COMPLETE SHIFT LOG -->
        <div 
            v-if="selectedPatrolDetails"
            class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-6"
        >
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg overflow-hidden flex flex-col max-h-[85vh] shadow-2xl animate-fade-in">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-150 flex items-center justify-between bg-slate-50">
                    <div class="space-y-0.5">
                        <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 font-mono">Patrol Checkpoints Log</h4>
                        <p class="text-[10px] text-slate-500 font-bold uppercase font-mono">Route: {{ selectedPatrolDetails.route?.name }}</p>
                    </div>
                    <button 
                        @click="selectedPatrolDetails = null"
                        class="text-slate-400 hover:text-slate-700 text-lg focus:outline-none"
                    >
                        ×
                    </button>
                </div>

                <!-- Scrollable Body -->
                <div class="flex-1 p-6 overflow-y-auto space-y-6">
                    <!-- Checkpoints Checklist Sequence -->
                    <div class="space-y-3">
                        <span class="block text-[9px] font-black uppercase tracking-widest text-slate-450 font-mono">Scanned Checklist Status</span>
                        <div class="space-y-2.5">
                            <div 
                                v-for="log in selectedPatrolDetails.checkpoint_logs" 
                                :key="log.id"
                                class="bg-slate-50 border rounded-xl p-3 flex flex-col gap-2"
                                :class="[
                                    log.status === 'scanned' ? 'border-emerald-250 bg-emerald-50/10' : '',
                                    log.status === 'skipped' ? 'border-amber-250 bg-amber-50/10' : '',
                                ]"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-800">{{ log.checkpoint?.name }}</span>
                                    <span 
                                        class="text-[8px] font-mono font-black uppercase tracking-wider px-1.5 py-0.5 rounded border"
                                        :class="[
                                            log.status === 'scanned' ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-amber-50 text-amber-600 border-amber-200'
                                        ]"
                                    >
                                        {{ log.status }}
                                    </span>
                                </div>
                                <p class="text-[10px] text-slate-500">{{ log.checkpoint?.description }}</p>
                                
                                <!-- Scanned At -->
                                <div v-if="log.scanned_at" class="text-[9px] text-slate-400 font-mono">
                                    Scanned At: {{ formatDate(log.scanned_at) }}
                                </div>
                                <!-- Skip Details -->
                                <div v-if="log.status === 'skipped'" class="space-y-1 border-t border-amber-200/40 pt-1.5 mt-1">
                                    <span class="block text-[8px] font-black uppercase tracking-wider text-amber-700 font-mono">Skip Reason:</span>
                                    <p class="text-[10px] text-amber-950 italic">{{ log.skip_reason || 'No reason specified.' }}</p>
                                </div>
                                <!-- Note -->
                                <div v-if="log.note" class="space-y-1 border-t border-slate-200/50 pt-1.5 mt-1">
                                    <span class="block text-[8px] font-black uppercase tracking-wider text-slate-450 font-mono">Checkpoint Note:</span>
                                    <p class="text-[10px] text-slate-650">{{ log.note }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- General Note -->
                    <div v-if="selectedPatrolDetails.general_note" class="space-y-2">
                        <span class="block text-[9px] font-black uppercase tracking-widest text-slate-450 font-mono">General Shift Note</span>
                        <p class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-650 leading-relaxed italic">
                            "{{ selectedPatrolDetails.general_note }}"
                        </p>
                    </div>

                    <!-- Completion Signature -->
                    <div v-if="selectedPatrolDetails.completion_signature_url" class="space-y-2">
                        <span class="block text-[9px] font-black uppercase tracking-widest text-slate-450 font-mono">Guard Digital Sign-Off</span>
                        <div class="w-full max-w-[240px] aspect-[2/1] border border-slate-200 rounded-xl overflow-hidden bg-slate-50 flex items-center justify-center p-2">
                            <img 
                                :src="selectedPatrolDetails.completion_signature_url" 
                                alt="Guard Signature" 
                                class="max-h-full max-w-full object-contain"
                            />
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-slate-150 flex justify-end bg-slate-50">
                    <button 
                        @click="selectedPatrolDetails = null"
                        class="px-5 py-2.5 bg-slate-200 hover:bg-slate-350 text-slate-700 text-xs font-black uppercase tracking-wider font-mono rounded-xl active:scale-95 transition-all"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
