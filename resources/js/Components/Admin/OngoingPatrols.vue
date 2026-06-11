<script setup lang="ts">
import { ref } from 'vue';

interface Guard {
    id: number;
    full_name: string;
    phone: string;
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
}

defineProps<{
    patrols: Patrol[];
}>();

const expandedPatrolId = ref<number | null>(null);

function toggleExpand(id: number) {
    if (expandedPatrolId.value === id) {
        expandedPatrolId.value = null;
    } else {
        expandedPatrolId.value = id;
    }
}

function formatTime(timeStr: string) {
    if (!timeStr) return '';
    try {
        return new Date(timeStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return timeStr;
    }
}
</script>

<template>
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
        <div class="flex justify-between items-center pb-2 border-b border-slate-100">
            <h3 class="text-xs font-black uppercase tracking-widest text-slate-500">
                Ongoing Patrol Shifts
            </h3>
            <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold bg-indigo-50 text-indigo-600">
                LIVE
            </span>
        </div>

        <div v-if="patrols.length === 0" class="py-12 text-center border-2 border-dashed border-slate-150 rounded-2xl">
            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-2.5">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <p class="text-xs text-slate-455 font-medium">No active patrols are running right now.</p>
        </div>

        <div v-else class="space-y-4 max-h-[500px] overflow-y-auto pr-1">
            <div 
                v-for="patrol in patrols" 
                :key="patrol.id"
                class="bg-slate-50 border border-slate-150 rounded-xl p-4 space-y-3 hover:border-indigo-500/30 transition-all duration-200 group cursor-pointer"
                @click="toggleExpand(patrol.id)"
            >
                <div class="flex justify-between items-start">
                    <div>
                        <h4 class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 transition-colors flex items-center gap-1.5">
                            <span>{{ patrol.route?.name }}</span>
                            <span v-if="(patrol as any).tenant" class="bg-purple-50 text-purple-650 border border-purple-200 px-1 py-0.5 rounded uppercase font-black tracking-wider text-[8px]">
                                {{ (patrol as any).tenant.name }}
                            </span>
                        </h4>
                        <span class="text-[9px] text-slate-400 font-mono mt-0.5 block">
                            Started: {{ formatTime(patrol.started_at) }}
                        </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="bg-indigo-50 text-indigo-650 border border-indigo-100/60 px-2 py-0.5 rounded text-[8px] font-mono font-black uppercase tracking-wider">
                            {{ patrol.status.replace('_', ' ') }}
                        </span>
                        <!-- Chevron icon -->
                        <svg 
                            class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" 
                            :class="expandedPatrolId === patrol.id ? 'rotate-180' : ''"
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>

                <div class="flex justify-between items-center text-[10px] text-slate-500">
                    <span class="font-medium">
                        Guard: <strong class="text-slate-700">{{ patrol.security_guard?.full_name }}</strong>
                    </span>
                    <span class="font-mono text-indigo-600 font-bold">
                        {{ patrol.completed_checkpoints }}/{{ patrol.total_checkpoints }} Checked
                    </span>
                </div>

                <div class="w-full bg-slate-150 h-2 rounded-full overflow-hidden">
                    <div 
                        class="bg-gradient-to-r from-indigo-500 to-purple-650 h-full rounded-full transition-all duration-500"
                        :style="{ width: `${(patrol.completed_checkpoints / patrol.total_checkpoints) * 100}%` }"
                    ></div>
                </div>

                <!-- Expanded Checkpoint Logs Progress -->
                <div 
                    v-if="expandedPatrolId === patrol.id" 
                    class="mt-4 pt-4 border-t border-slate-200/80 space-y-3 cursor-default"
                    @click.stop
                >
                    <h5 class="text-[9px] font-black text-slate-455 uppercase tracking-widest font-mono">
                        Shift Checkpoints Progress
                    </h5>
                    
                    <div class="space-y-2 max-h-[260px] overflow-y-auto pr-1">
                        <div 
                            v-for="log in (patrol as any).checkpoint_logs" 
                            :key="log.id"
                            class="bg-white border border-slate-150 rounded-xl p-3 space-y-2 text-[11px]"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="font-bold text-slate-800">{{ log.checkpoint?.name }}</span>
                                    <span class="text-[9px] text-slate-400 block font-mono">
                                        Position #{{ log.position }} • Method: {{ log.checkpoint?.scan_method || 'QR' }}
                                    </span>
                                </div>
                                <span 
                                    class="px-2 py-0.5 rounded text-[8px] font-black uppercase font-mono tracking-wider"
                                    :class="{
                                        'bg-emerald-50 text-emerald-650 border border-emerald-200': log.status === 'scanned',
                                        'bg-amber-50 text-amber-650 border border-amber-200': log.status === 'skipped',
                                        'bg-slate-50 text-slate-500 border border-slate-200': log.status === 'pending'
                                    }"
                                >
                                    {{ log.status }}
                                </span>
                            </div>

                            <!-- Scanned / Skipped Details -->
                            <div v-if="log.status === 'scanned' || log.status === 'skipped'" class="space-y-2 border-t border-slate-100 pt-2 text-[10px] text-slate-600">
                                <div class="flex justify-between font-mono text-[9px] text-slate-400">
                                    <span>Time: {{ formatTime(log.scanned_at || log.skipped_at) }}</span>
                                    <span v-if="log.recorded_offline" class="text-amber-600">Recorded Offline</span>
                                </div>
                                
                                <p v-if="log.note || log.skip_reason" class="bg-slate-50 p-2 rounded border border-slate-150/60 italic leading-relaxed text-slate-700">
                                    <span class="font-black font-mono text-[8px] uppercase text-slate-400 block not-italic mb-0.5">
                                        {{ log.status === 'scanned' ? 'Guard Note' : 'Skip Reason' }}
                                    </span>
                                    "{{ log.note || log.skip_reason }}"
                                </p>

                                <!-- Attached photos -->
                                <div v-if="log.media && log.media.length > 0" class="space-y-1.5">
                                    <span class="font-black font-mono text-[8px] uppercase text-slate-400 block">Attached Evidence</span>
                                    <div class="flex flex-wrap gap-2">
                                        <a 
                                            v-for="m in log.media" 
                                            :key="m.id"
                                            :href="m.file_url" 
                                            target="_blank"
                                            class="relative block w-14 h-14 rounded-lg overflow-hidden border border-slate-200 hover:border-indigo-500 transition-colors shadow-xs"
                                        >
                                            <img :src="m.file_url" class="w-full h-full object-cover" />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
