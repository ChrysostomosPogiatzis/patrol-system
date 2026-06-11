<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';

interface PatrolSummary {
    id: number;
    route?: { name: string };
    status: string;
    started_at: string;
    completed_at?: string;
    duration_seconds?: number;
    total_checkpoints: number;
    completed_checkpoints: number;
    skipped_checkpoints: number;
    incident_count: number;
    general_note?: string;
}

interface CheckpointLog {
    id: number;
    position: number;
    status: string;
    note?: string;
    skip_reason?: string;
    scanned_at?: string;
    skipped_at?: string;
    scan_method_used?: string;
    gps_distance_metres?: number;
    gps_within_fence?: boolean;
    checkpoint?: { name: string; description: string };
    media?: { id: number; kind: string; file_url: string }[];
}

interface PatrolDetail extends PatrolSummary {
    route?: { name: string; enforce_order: boolean; allow_skip: boolean };
    checkpoint_logs: CheckpointLog[];
    incidents?: { id: number; type: string; description: string; reported_at: string }[];
    completion_signature_url?: string;
    completion_latitude?: number;
    completion_longitude?: number;
}

const props = defineProps<{ guard: any }>();

// List view state
const patrols = ref<PatrolSummary[]>([]);
const loading = ref(true);
const loadingMore = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const error = ref<string | null>(null);

// Detail view state
const selectedPatrol = ref<PatrolDetail | null>(null);
const detailLoading = ref(false);

async function loadHistory(page = 1) {
    if (page === 1) loading.value = true;
    else loadingMore.value = true;
    error.value = null;

    try {
        const res = await axios.get(`/api/guard/patrols/history?page=${page}`, { timeout: 5000 });
        if (page === 1) {
            patrols.value = res.data.data;
        } else {
            patrols.value.push(...res.data.data);
        }
        currentPage.value = res.data.current_page;
        lastPage.value = res.data.last_page;
    } catch (e: any) {
        error.value = 'Failed to load patrol history.';
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

async function openDetail(patrol: PatrolSummary) {
    detailLoading.value = true;
    selectedPatrol.value = null;

    try {
        const res = await axios.get(`/api/guard/patrols/${patrol.id}`, { timeout: 5000 });
        selectedPatrol.value = res.data.patrol;
    } catch {
        error.value = 'Failed to load patrol details.';
    } finally {
        detailLoading.value = false;
    }
}

function closeDetail() {
    selectedPatrol.value = null;
}

function formatDuration(seconds?: number): string {
    if (seconds === undefined || seconds === null) return '—';
    const cleanSeconds = Math.max(0, seconds);
    const h = Math.floor(cleanSeconds / 3600);
    const m = Math.floor((cleanSeconds % 3600) / 60);
    const s = cleanSeconds % 60;
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`;
}

function formatDateTime(dt?: string): string {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatTime(dt?: string): string {
    if (!dt) return '—';
    return new Date(dt).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

function scoreColor(p: PatrolSummary): string {
    const rate = p.total_checkpoints > 0 ? p.completed_checkpoints / p.total_checkpoints : 0;
    if (rate === 1) return 'text-emerald-400';
    if (rate >= 0.7) return 'text-amber-400';
    return 'text-rose-400';
}

function statusBadge(status: string) {
    const map: Record<string, { bg: string; text: string; label: string }> = {
        completed:  { bg: 'bg-emerald-500/10 border-emerald-500/30', text: 'text-emerald-400', label: 'Completed' },
        abandoned:  { bg: 'bg-rose-500/10 border-rose-500/30',       text: 'text-rose-400',    label: 'Abandoned' },
        in_progress:{ bg: 'bg-indigo-500/10 border-indigo-500/30',   text: 'text-indigo-400',  label: 'In Progress' },
    };
    return map[status] ?? { bg: 'bg-slate-800 border-slate-700', text: 'text-slate-400', label: status };
}

function logStatusStyle(status: string) {
    if (status === 'scanned')  return { dot: 'bg-emerald-500', card: 'border-emerald-500/20', badge: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' };
    if (status === 'skipped')  return { dot: 'bg-amber-500',   card: 'border-amber-500/20',   badge: 'bg-amber-500/10 text-amber-400 border-amber-500/30' };
    return { dot: 'bg-slate-600', card: 'border-slate-800', badge: 'bg-slate-800 text-slate-400 border-slate-700' };
}

onMounted(() => loadHistory(1));
</script>

<template>
    <div class="space-y-4">

        <!-- ─── DETAIL OVERLAY ─── -->
        <div
            v-if="selectedPatrol || detailLoading"
            class="fixed inset-0 z-50 bg-slate-950/95 backdrop-blur-sm overflow-y-auto"
        >
            <div class="max-w-lg mx-auto px-4 py-4 pb-28">
                <!-- Back header -->
                <div class="flex items-center gap-3 mb-4 sticky top-0 bg-slate-950/90 backdrop-blur py-2">
                    <button @click="closeDetail" class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-white active:scale-95 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </button>
                    <div>
                        <h2 class="text-sm font-black text-slate-100">Patrol Report</h2>
                        <p class="text-[10px] text-slate-500 font-mono">{{ selectedPatrol?.route?.name }}</p>
                    </div>
                </div>

                <!-- Loading spinner -->
                <div v-if="detailLoading" class="flex flex-col items-center justify-center py-20 space-y-3">
                    <div class="w-8 h-8 border-2 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin"></div>
                    <p class="text-xs text-slate-500">Loading patrol data...</p>
                </div>

                <template v-else-if="selectedPatrol">
                    <!-- Summary Card -->
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs text-slate-400">Started</p>
                                <p class="text-sm font-bold text-slate-100">{{ formatDateTime(selectedPatrol.started_at) }}</p>
                            </div>
                            <span :class="[statusBadge(selectedPatrol.status).bg, statusBadge(selectedPatrol.status).text]"
                                  class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full border">
                                {{ statusBadge(selectedPatrol.status).label }}
                            </span>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="bg-slate-950/80 rounded-xl py-3 border border-slate-800">
                                <p class="text-lg font-black text-emerald-400">{{ selectedPatrol.completed_checkpoints }}</p>
                                <p class="text-[9px] uppercase tracking-widest text-slate-500 mt-0.5">Scanned</p>
                            </div>
                            <div class="bg-slate-950/80 rounded-xl py-3 border border-slate-800">
                                <p class="text-lg font-black text-amber-400">{{ selectedPatrol.skipped_checkpoints }}</p>
                                <p class="text-[9px] uppercase tracking-widest text-slate-500 mt-0.5">Skipped</p>
                            </div>
                            <div class="bg-slate-950/80 rounded-xl py-3 border border-slate-800">
                                <p class="text-lg font-black text-violet-400">{{ formatDuration(selectedPatrol.duration_seconds) }}</p>
                                <p class="text-[9px] uppercase tracking-widest text-slate-500 mt-0.5">Duration</p>
                            </div>
                        </div>

                        <!-- General Note -->
                        <div v-if="selectedPatrol.general_note" class="bg-indigo-500/5 border border-indigo-500/15 rounded-xl p-3">
                            <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-1">📝 General Note</p>
                            <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-line">{{ selectedPatrol.general_note }}</p>
                        </div>
                    </div>

                    <!-- Checkpoint Logs -->
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-500 pl-1 mb-2">Checkpoint Log</h3>
                    <div class="space-y-2">
                        <div
                            v-for="log in selectedPatrol.checkpoint_logs"
                            :key="log.id"
                            class="bg-slate-900 border rounded-2xl overflow-hidden"
                            :class="logStatusStyle(log.status).card"
                        >
                            <!-- Log header -->
                            <div class="px-4 py-3 flex items-center gap-3">
                                <!-- Position badge -->
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-black flex-shrink-0"
                                     :class="log.status === 'scanned' ? 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30'
                                           : log.status === 'skipped' ? 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30'
                                           : 'bg-slate-800 text-slate-500'">
                                    <svg v-if="log.status === 'scanned'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <svg v-else-if="log.status === 'skipped'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    <span v-else>{{ log.position }}</span>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-slate-100 truncate">{{ log.checkpoint?.name }}</p>
                                    <p class="text-[10px] text-slate-500">
                                        <span v-if="log.status === 'scanned'">{{ formatTime(log.scanned_at) }} via {{ log.scan_method_used?.toUpperCase() }}</span>
                                        <span v-else-if="log.status === 'skipped'">Skipped at {{ formatTime(log.skipped_at) }}</span>
                                        <span v-else>Not reached</span>
                                    </p>
                                </div>

                                <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded-full border flex-shrink-0"
                                      :class="logStatusStyle(log.status).badge">
                                    {{ log.status }}
                                </span>
                            </div>

                            <!-- Expandable details -->
                            <div v-if="log.note || log.skip_reason || (log.media && log.media.length > 0)"
                                 class="border-t border-slate-800/60 px-4 py-3 space-y-2.5 bg-slate-950/40">
                                <!-- Guard note -->
                                <div v-if="log.note">
                                    <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1">Guard Note</p>
                                    <p class="text-xs text-slate-300 leading-relaxed bg-slate-900/60 rounded-xl px-3 py-2 border border-slate-800">{{ log.note }}</p>
                                </div>

                                <!-- Skip reason -->
                                <div v-if="log.skip_reason">
                                    <p class="text-[9px] font-bold text-amber-500/70 uppercase tracking-widest mb-1">Skip Reason</p>
                                    <p class="text-xs text-amber-200/80 leading-relaxed bg-amber-500/5 rounded-xl px-3 py-2 border border-amber-500/15">{{ log.skip_reason }}</p>
                                </div>

                                <!-- GPS info -->
                                <div v-if="log.gps_distance_metres != null" class="flex items-center gap-2 text-[10px] text-slate-500">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span>GPS: {{ Number(log.gps_distance_metres).toFixed(1) }}m from checkpoint</span>
                                    <span v-if="log.gps_within_fence" class="text-emerald-400 font-bold">✓ In zone</span>
                                    <span v-else class="text-amber-400 font-bold">⚠ Outside fence</span>
                                </div>

                                <!-- Photos -->
                                <div v-if="log.media && log.media.length > 0">
                                    <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-2">Photos</p>
                                    <div class="flex gap-2 flex-wrap">
                                        <img
                                            v-for="m in log.media.filter(x => x.kind === 'photo')"
                                            :key="m.id"
                                            :src="m.file_url"
                                            class="w-20 h-16 object-cover rounded-xl border border-slate-700 bg-slate-800"
                                            loading="lazy"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Incidents -->
                    <div v-if="selectedPatrol.incidents && selectedPatrol.incidents.length > 0" class="mt-4">
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-rose-500/70 pl-1 mb-2">⚠ Incidents Reported</h3>
                        <div class="space-y-2">
                            <div v-for="inc in selectedPatrol.incidents" :key="inc.id"
                                 class="bg-rose-950/20 border border-rose-500/20 rounded-2xl px-4 py-3">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-[10px] font-bold text-rose-400 uppercase tracking-wider">{{ inc.type }}</span>
                                    <span class="text-[10px] text-slate-500">{{ formatTime(inc.reported_at) }}</span>
                                </div>
                                <p class="text-xs text-slate-300 leading-relaxed">{{ inc.description }}</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ─── LIST VIEW ─── -->
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-black text-slate-100">Patrol History</h2>
                <p class="text-[10px] text-slate-500 mt-0.5">Tap a patrol to view full checkpoint log</p>
            </div>
            <div class="w-8 h-8 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center py-16 space-y-3">
            <div class="w-8 h-8 border-2 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin"></div>
            <p class="text-xs text-slate-500">Loading history...</p>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="bg-rose-500/10 border border-rose-500/20 rounded-2xl px-4 py-5 text-center">
            <p class="text-xs text-rose-400">{{ error }}</p>
            <button @click="loadHistory(1)" class="mt-2 text-[10px] text-indigo-400 underline">Retry</button>
        </div>

        <!-- Empty -->
        <div v-else-if="patrols.length === 0" class="bg-slate-900/40 border border-slate-800 rounded-3xl py-14 text-center">
            <div class="w-14 h-14 rounded-full bg-slate-900 border border-slate-800 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-sm font-bold text-slate-400">No Patrol History Yet</h3>
            <p class="text-xs text-slate-600 mt-1 max-w-xs mx-auto">Completed patrols will appear here with full checkpoint logs and notes.</p>
        </div>

        <!-- Patrol Cards -->
        <div v-else class="space-y-3">
            <button
                v-for="patrol in patrols"
                :key="patrol.id"
                @click="openDetail(patrol)"
                class="w-full text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/30 hover:bg-slate-900/80 rounded-2xl p-4 transition-all duration-200 active:scale-[0.98] space-y-3"
            >
                <!-- Top row -->
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-slate-100 truncate">{{ patrol.route?.name ?? 'Unknown Route' }}</p>
                        <p class="text-[10px] text-slate-500 mt-0.5 font-mono">{{ formatDateTime(patrol.started_at) }}</p>
                    </div>
                    <span :class="[statusBadge(patrol.status).bg, statusBadge(patrol.status).text]"
                          class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border flex-shrink-0">
                        {{ statusBadge(patrol.status).label }}
                    </span>
                </div>

                <!-- Progress bar -->
                <div>
                    <div class="flex justify-between text-[9px] font-bold mb-1">
                        <span :class="scoreColor(patrol)">
                            {{ patrol.completed_checkpoints }}/{{ patrol.total_checkpoints }} checkpoints scanned
                        </span>
                        <span class="text-slate-600">{{ formatDuration(patrol.duration_seconds) }}</span>
                    </div>
                    <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="patrol.completed_checkpoints === patrol.total_checkpoints ? 'bg-emerald-500' : 'bg-indigo-500'"
                            :style="{ width: patrol.total_checkpoints > 0 ? `${Math.round(patrol.completed_checkpoints / patrol.total_checkpoints * 100)}%` : '0%' }"
                        ></div>
                    </div>
                </div>

                <!-- Stats row -->
                <div class="flex gap-3 text-[9px] font-bold">
                    <span class="flex items-center gap-1 text-emerald-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        {{ patrol.completed_checkpoints }} scanned
                    </span>
                    <span v-if="patrol.skipped_checkpoints > 0" class="flex items-center gap-1 text-amber-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        {{ patrol.skipped_checkpoints }} skipped
                    </span>
                    <span v-if="patrol.incident_count > 0" class="flex items-center gap-1 text-rose-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                        {{ patrol.incident_count }} incident{{ patrol.incident_count !== 1 ? 's' : '' }}
                    </span>
                </div>

                <!-- Tap hint -->
                <div class="flex items-center justify-end gap-1 text-[9px] text-slate-600">
                    <span>View details</span>
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </button>

            <!-- Load more -->
            <button
                v-if="currentPage < lastPage"
                @click="loadHistory(currentPage + 1)"
                :disabled="loadingMore"
                class="w-full py-3 text-xs font-bold text-indigo-400 bg-indigo-500/5 border border-indigo-500/20 rounded-2xl hover:bg-indigo-500/10 transition-all active:scale-95 flex items-center justify-center gap-2"
            >
                <span v-if="loadingMore" class="w-4 h-4 border-2 border-indigo-400/30 border-t-indigo-400 rounded-full animate-spin"></span>
                <span>{{ loadingMore ? 'Loading...' : 'Load More Patrols' }}</span>
            </button>
        </div>
    </div>
</template>
