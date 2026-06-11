<script setup lang="ts">
interface Guard {
    id: number;
    full_name: string;
}

interface Incident {
    id: number;
    title: string;
    description?: string;
    priority: 'low' | 'medium' | 'high' | 'critical';
    status: string;
    security_guard?: Guard;
}

defineProps<{
    incidents: Incident[];
}>();

defineEmits<{
    (e: 'resolve', id: number): void;
}>();
</script>

<template>
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
        <div class="flex justify-between items-center pb-2 border-b border-slate-100">
            <h3 class="text-xs font-black uppercase tracking-widest text-slate-500">
                Recent Incidents
            </h3>
            <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold bg-amber-50 text-amber-600">
                MONITORED
            </span>
        </div>

        <div v-if="incidents.length === 0" class="py-12 text-center border-2 border-dashed border-slate-150 rounded-2xl">
            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-2.5">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <p class="text-xs text-slate-450 font-medium">No recent incidents reported.</p>
        </div>

        <div v-else class="space-y-4 max-h-[420px] overflow-y-auto pr-1">
            <div 
                v-for="incident in incidents" 
                :key="incident.id"
                class="bg-slate-50 border border-slate-150 rounded-xl p-4 space-y-3 hover:border-amber-500/30 transition-all duration-200 group"
            >
                <div class="flex justify-between items-start">
                    <div>
                        <h5 class="text-xs font-bold text-slate-800 group-hover:text-amber-600 transition-colors">
                            {{ incident.title }}
                        </h5>
                        <span class="text-[9px] text-slate-450 block font-mono mt-0.5">
                            Guard: {{ incident.security_guard?.full_name }}
                            <span v-if="(incident as any).tenant" class="ml-1 bg-purple-50 text-purple-650 border border-purple-200 px-1 rounded uppercase font-black tracking-wider text-[8px]">
                                {{ (incident as any).tenant.name }}
                            </span>
                        </span>
                    </div>
                    <span 
                        class="text-[8px] font-black uppercase px-2 py-0.5 rounded-full border tracking-wider animate-none"
                        :class="{
                            'low': 'bg-sky-50 text-sky-650 border-sky-100',
                            'medium': 'bg-amber-50 text-amber-650 border-amber-100',
                            'high': 'bg-orange-50 text-orange-650 border-orange-100',
                            'critical': 'bg-rose-50 text-rose-650 border-rose-100 animate-pulse'
                        }[incident.priority]"
                    >
                        {{ incident.priority }}
                    </span>
                </div>

                <p class="text-[10px] text-slate-500 leading-relaxed">
                    {{ incident.description || 'No descriptive logs recorded' }}
                </p>

                <div class="flex justify-between items-center pt-2 border-t border-slate-150">
                    <span class="text-[9px] font-mono text-slate-450 uppercase font-black">
                        Status: 
                        <strong :class="incident.status === 'open' ? 'text-amber-600' : 'text-emerald-600'">
                            {{ incident.status }}
                        </strong>
                    </span>
                    <button 
                        v-if="incident.status === 'open'"
                        @click="$emit('resolve', incident.id)"
                        class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-lg transition-all hover:scale-105 active:scale-95 border border-indigo-200/40"
                    >
                        Resolve
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
