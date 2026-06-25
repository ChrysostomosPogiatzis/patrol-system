<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';

interface Route {
    id: number;
    name: string;
    description?: string;
    expected_duration_mins?: number;
    route_checkpoints?: any[];
}

interface Guard {
    id: number;
    full_name: string;
    employee_id: string;
}

interface Assignment {
    id: number;
    route_id: number;
    route: Route;
    schedule_start: string | null;
    schedule_end: string | null;
    cron_schedule: string | null;
    is_active: boolean;
}

const props = defineProps<{
    show: boolean;
    guard: Guard | null;
    routes: Route[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'assigned'): void;
}>();

const assignments = ref<Assignment[]>([]);
const loading = ref(false);
const saving = ref(false);
const errorMsg = ref('');
const successMsg = ref('');

// Form state
const selectedRouteId = ref<number | null>(null);
const scheduleStart = ref('');
const scheduleEnd = ref('');
const cronSchedule = ref('');
const showAdvanced = ref(false);

// Available routes not yet assigned
const unassignedRoutes = computed(() => {
    const assignedRouteIds = assignments.value.map(a => a.route_id);
    return props.routes.filter(r => !assignedRouteIds.includes(r.id));
});

async function fetchAssignments() {
    if (!props.guard) return;
    loading.value = true;
    try {
        const res = await axios.get(`/admin/api/guards/${props.guard.id}/assignments`);
        assignments.value = res.data.assignments;
    } catch (e) {
        console.error('Failed to fetch assignments:', e);
    } finally {
        loading.value = false;
    }
}

async function assignRoute() {
    if (!selectedRouteId.value || !props.guard) return;
    saving.value = true;
    errorMsg.value = '';
    successMsg.value = '';
    try {
        await axios.post('/admin/api/assignments', {
            guard_id: props.guard.id,
            route_id: selectedRouteId.value,
            schedule_start: scheduleStart.value || null,
            schedule_end: scheduleEnd.value || null,
            cron_schedule: cronSchedule.value || null,
        });
        successMsg.value = 'Patrol route assigned successfully!';
        selectedRouteId.value = null;
        scheduleStart.value = '';
        scheduleEnd.value = '';
        cronSchedule.value = '';
        showAdvanced.value = false;
        await fetchAssignments();
        emit('assigned');
    } catch (e: any) {
        errorMsg.value = e.response?.data?.message || 'Failed to assign route.';
    } finally {
        saving.value = false;
    }
}

async function removeAssignment(id: number) {
    if (!confirm('Remove this patrol assignment?')) return;
    try {
        await axios.delete(`/admin/api/assignments/${id}`);
        await fetchAssignments();
        emit('assigned');
    } catch (e: any) {
        alert(e.response?.data?.message || 'Failed to remove assignment.');
    }
}

function formatDate(dateStr: string | null) {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return dateStr;
    }
}

watch(() => props.show, (val) => {
    if (val && props.guard) {
        errorMsg.value = '';
        successMsg.value = '';
        selectedRouteId.value = null;
        scheduleStart.value = '';
        scheduleEnd.value = '';
        cronSchedule.value = '';
        showAdvanced.value = false;
        fetchAssignments();
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="$emit('close')"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="$emit('close')"></div>

                <!-- Modal Card -->
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-200/60 z-10">

                    <!-- Header -->
                    <div class="bg-gradient-to-r from-indigo-600 to-violet-600 px-7 py-5 flex items-center justify-between flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-white font-black text-sm tracking-wide">Patrol Assignments</h3>
                                <p class="text-indigo-200 text-xs font-medium mt-0.5">{{ guard?.full_name }} <span class="opacity-70">· #{{ guard?.employee_id }}</span></p>
                            </div>
                        </div>
                        <button @click="$emit('close')" class="w-8 h-8 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center transition-colors">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body (scrollable) -->
                    <div class="overflow-y-auto flex-1 p-7 space-y-6">

                        <!-- Assign New Route Form -->
                        <div class="space-y-4">
                            <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest font-mono">Assign New Patrol Route</h4>

                            <!-- No routes available -->
                            <div v-if="routes.length === 0" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-700 font-medium flex items-center gap-2">
                                <span>⚠️</span>
                                <span>No patrol routes found. Create patrol routes first from the <strong>Routes</strong> section.</span>
                            </div>

                            <div v-else-if="unassignedRoutes.length === 0" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-xs text-emerald-700 font-medium flex items-center gap-2">
                                <span>✅</span>
                                <span>All available patrol routes are already assigned to this guard.</span>
                            </div>

                            <div v-else class="space-y-3">
                                <!-- Route selector -->
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest font-mono mb-1.5">Select Route</label>
                                    <select
                                        v-model="selectedRouteId"
                                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all cursor-pointer"
                                    >
                                        <option :value="null">— Choose a patrol route —</option>
                                        <option v-for="r in unassignedRoutes" :key="r.id" :value="r.id">
                                            {{ r.name }} {{ r.expected_duration_mins ? `(${r.expected_duration_mins} min)` : '' }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Selected route preview -->
                                <div v-if="selectedRouteId" class="bg-indigo-50 border border-indigo-150 rounded-xl p-3">
                                    <p class="text-xs text-indigo-600 font-medium">
                                        {{ routes.find(r => r.id === selectedRouteId)?.description || 'No description.' }}
                                    </p>
                                    <p class="text-[10px] text-indigo-400 font-mono mt-1">
                                        {{ routes.find(r => r.id === selectedRouteId)?.route_checkpoints?.length || 0 }} checkpoint(s)
                                        · {{ routes.find(r => r.id === selectedRouteId)?.expected_duration_mins || 30 }} min expected
                                    </p>
                                </div>

                                <!-- Advanced scheduling toggle -->
                                <button
                                    @click="showAdvanced = !showAdvanced"
                                    class="text-[10px] font-black text-indigo-500 uppercase tracking-widest font-mono flex items-center gap-1 hover:text-indigo-700 transition-colors"
                                >
                                    <svg class="w-3 h-3 transition-transform" :class="showAdvanced ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                    </svg>
                                    Advanced Scheduling (optional)
                                </button>

                                <div v-if="showAdvanced" class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-4 bg-slate-50 border border-slate-200 rounded-xl">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest font-mono mb-1.5">Schedule Start</label>
                                        <input
                                            v-model="scheduleStart"
                                            type="datetime-local"
                                            class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 transition-all"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest font-mono mb-1.5">Schedule End</label>
                                        <input
                                            v-model="scheduleEnd"
                                            type="datetime-local"
                                            class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 transition-all"
                                        />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest font-mono mb-1.5">Cron Schedule <span class="normal-case font-normal text-slate-400">(e.g. 0 22 * * 1-5)</span></label>
                                        <input
                                            v-model="cronSchedule"
                                            type="text"
                                            placeholder="e.g. 0 22 * * 1-5  (Mon-Fri at 10pm)"
                                            class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:border-indigo-500 transition-all"
                                        />
                                    </div>
                                </div>

                                <!-- Feedback messages -->
                                <p v-if="errorMsg" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-xl px-4 py-2.5 font-medium">{{ errorMsg }}</p>
                                <p v-if="successMsg" class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2.5 font-medium">{{ successMsg }}</p>

                                <button
                                    @click="assignRoute"
                                    :disabled="!selectedRouteId || saving"
                                    class="w-full py-3 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2"
                                    :class="!selectedRouteId || saving
                                        ? 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                        : 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-md active:scale-[0.99]'"
                                >
                                    <svg v-if="!saving" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    {{ saving ? 'Assigning...' : 'Assign Patrol Route' }}
                                </button>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="border-t border-slate-150"></div>

                        <!-- Current Assignments List -->
                        <div class="space-y-3">
                            <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest font-mono">
                                Current Assignments
                                <span class="ml-2 bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full text-[9px]">{{ assignments.length }}</span>
                            </h4>

                            <!-- Loading state -->
                            <div v-if="loading" class="flex items-center justify-center py-8 text-slate-400">
                                <svg class="w-5 h-5 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span class="text-xs font-medium">Loading assignments...</span>
                            </div>

                            <!-- Empty state -->
                            <div v-else-if="assignments.length === 0" class="bg-slate-50 border border-dashed border-slate-200 rounded-2xl p-8 text-center">
                                <svg class="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                                <p class="text-xs text-slate-400 font-medium">No patrol routes assigned yet</p>
                            </div>

                            <!-- Assignment cards -->
                            <div v-else class="space-y-2.5">
                                <div
                                    v-for="a in assignments"
                                    :key="a.id"
                                    class="bg-white border border-slate-200 rounded-2xl p-4 flex items-start justify-between gap-3 hover:border-indigo-200 transition-colors group"
                                >
                                    <div class="flex items-start gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-black text-slate-800 font-mono truncate group-hover:text-indigo-600 transition-colors">{{ a.route?.name }}</p>
                                            <div class="flex flex-wrap gap-2 mt-1.5">
                                                <span v-if="a.schedule_start" class="text-[9px] font-mono text-slate-500 bg-slate-50 border border-slate-150 px-2 py-0.5 rounded-lg">
                                                    📅 {{ formatDate(a.schedule_start) }} → {{ formatDate(a.schedule_end) }}
                                                </span>
                                                <span v-if="a.cron_schedule" class="text-[9px] font-mono text-violet-600 bg-violet-50 border border-violet-100 px-2 py-0.5 rounded-lg">
                                                    🔁 {{ a.cron_schedule }}
                                                </span>
                                                <span
                                                    class="text-[9px] font-black uppercase font-mono px-2 py-0.5 rounded-full border"
                                                    :class="a.is_active ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-slate-50 text-slate-500 border-slate-200'"
                                                >
                                                    {{ a.is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        @click="removeAssignment(a.id)"
                                        class="flex-shrink-0 w-7 h-7 rounded-lg bg-red-50 hover:bg-red-100 text-red-500 flex items-center justify-center transition-all active:scale-95 border border-red-100/60"
                                        title="Remove assignment"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="border-t border-slate-100 px-7 py-4 flex justify-end flex-shrink-0 bg-slate-50/50">
                        <button
                            @click="$emit('close')"
                            class="px-6 py-2.5 bg-white border border-slate-200 hover:border-slate-350 text-slate-600 rounded-xl text-xs font-black uppercase tracking-wider font-mono transition-all active:scale-95"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
.modal-fade-enter-active .relative,
.modal-fade-leave-active .relative {
    transition: transform 0.2s ease;
}
.modal-fade-enter-from .relative {
    transform: scale(0.97) translateY(8px);
}
</style>
