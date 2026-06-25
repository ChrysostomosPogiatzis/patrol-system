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
    <div
        class="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"
    >
        <div
            class="flex items-center justify-between border-b border-slate-100 pb-2"
        >
            <h3
                class="text-xs font-black uppercase tracking-widest text-slate-500"
            >
                Recent Incidents
            </h3>
            <span
                class="rounded-full bg-amber-50 px-2 py-0.5 font-mono text-[9px] font-bold text-amber-600"
            >
                MONITORED
            </span>
        </div>

        <div
            v-if="incidents.length === 0"
            class="border-slate-150 rounded-2xl border-2 border-dashed py-12 text-center"
        >
            <div
                class="mx-auto mb-2.5 flex h-10 w-10 items-center justify-center rounded-full bg-slate-50"
            >
                <svg
                    class="h-5 w-5 text-slate-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                    />
                </svg>
            </div>
            <p class="text-slate-450 text-xs font-medium">
                No recent incidents reported.
            </p>
        </div>

        <div v-else class="max-h-[420px] space-y-4 overflow-y-auto pr-1">
            <div
                v-for="incident in incidents"
                :key="incident.id"
                class="border-slate-150 group space-y-3 rounded-xl border bg-slate-50 p-4 transition-all duration-200 hover:border-amber-500/30"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h5
                            class="text-xs font-bold text-slate-800 transition-colors group-hover:text-amber-600"
                        >
                            {{ incident.title }}
                        </h5>
                        <span
                            class="text-slate-450 mt-0.5 block font-mono text-[9px]"
                        >
                            Guard: {{ incident.security_guard?.full_name }}
                            <span
                                v-if="(incident as any).tenant"
                                class="text-purple-650 ml-1 rounded border border-purple-200 bg-purple-50 px-1 text-[8px] font-black uppercase tracking-wider"
                            >
                                {{ (incident as any).tenant.name }}
                            </span>
                        </span>
                    </div>
                    <span
                        class="animate-none rounded-full border px-2 py-0.5 text-[8px] font-black uppercase tracking-wider"
                        :class="
                            {
                                low: 'text-sky-650 border-sky-100 bg-sky-50',
                                medium: 'text-amber-650 border-amber-100 bg-amber-50',
                                high: 'text-orange-650 border-orange-100 bg-orange-50',
                                critical:
                                    'text-rose-650 animate-pulse border-rose-100 bg-rose-50',
                            }[incident.priority]
                        "
                    >
                        {{ incident.priority }}
                    </span>
                </div>

                <p class="text-[10px] leading-relaxed text-slate-500">
                    {{ incident.description || 'No descriptive logs recorded' }}
                </p>

                <div
                    class="border-slate-150 flex items-center justify-between border-t pt-2"
                >
                    <span
                        class="text-slate-450 font-mono text-[9px] font-black uppercase"
                    >
                        Status:
                        <strong
                            :class="
                                incident.status === 'open'
                                    ? 'text-amber-600'
                                    : 'text-emerald-600'
                            "
                        >
                            {{ incident.status }}
                        </strong>
                    </span>
                    <button
                        v-if="incident.status === 'open'"
                        @click="$emit('resolve', incident.id)"
                        class="rounded-lg border border-indigo-200/40 bg-indigo-50 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-indigo-600 transition-all hover:scale-105 hover:bg-indigo-100 active:scale-95"
                    >
                        Resolve
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
