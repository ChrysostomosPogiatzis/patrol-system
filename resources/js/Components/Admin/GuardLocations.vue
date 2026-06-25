<script setup lang="ts">
interface Tenant {
    id: number;
    name: string;
}

interface Guard {
    id: number;
    full_name: string;
    employee_id: string;
}

interface GuardLocation {
    id: number;
    latitude: string | number;
    longitude: string | number;
    accuracy_m?: string | number;
    battery_pct?: number;
    is_online: boolean;
    pinged_at: string;
    security_guard?: Guard;
    tenant?: Tenant;
}

const props = defineProps<{
    locations: GuardLocation[];
}>();

function formatTime(timeStr: string) {
    if (!timeStr) return '';
    try {
        return new Date(timeStr).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (e) {
        return timeStr;
    }
}

function getBatteryColor(pct?: number) {
    if (pct === undefined) return 'text-slate-500';
    if (pct > 50) return 'text-emerald-500';
    if (pct > 20) return 'text-amber-500';
    return 'text-rose-500 animate-pulse';
}
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
                🚨 Live Guard Location Feed
            </h3>
            <span
                class="rounded-full bg-indigo-50 px-2 py-0.5 font-mono text-[9px] font-bold text-indigo-600"
            >
                ACTIVE
            </span>
        </div>

        <div
            v-if="locations.length === 0"
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
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                    />
                </svg>
            </div>
            <p class="text-slate-450 text-xs font-medium">
                No live guard tracking data available.
            </p>
        </div>

        <div v-else class="max-h-[420px] space-y-3 overflow-y-auto pr-1">
            <div
                v-for="loc in locations"
                :key="loc.id"
                class="border-slate-150 group space-y-3 rounded-xl border bg-slate-50 p-4 transition-all duration-200 hover:border-indigo-500/30"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center space-x-2">
                            <span
                                class="h-2 w-2 rounded-full"
                                :class="
                                    loc.is_online
                                        ? 'shadow-emerald-550/50 bg-emerald-500 shadow-sm'
                                        : 'bg-slate-400'
                                "
                            ></span>
                            <h4
                                class="text-xs font-bold text-slate-800 transition-colors group-hover:text-indigo-600"
                            >
                                {{
                                    loc.security_guard?.full_name ||
                                    'Unknown Guard'
                                }}
                            </h4>
                        </div>
                        <span
                            class="text-slate-450 mt-1 block font-mono text-[9px]"
                        >
                            ID: {{ loc.security_guard?.employee_id || 'N/A' }}
                            <span
                                v-if="loc.tenant"
                                class="bg-purple-550/10 text-purple-650 ml-1 rounded border border-purple-200 px-1 text-[8px] font-black uppercase tracking-wider"
                            >
                                {{ loc.tenant.name }}
                            </span>
                        </span>
                    </div>

                    <div class="flex items-center space-x-2">
                        <!-- Battery Indicator -->
                        <span
                            v-if="loc.battery_pct !== undefined"
                            class="flex items-center space-x-1 font-mono text-[9px] font-bold"
                            :class="getBatteryColor(loc.battery_pct)"
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
                                    d="M13 10V3L4 14h7v7l9-11h-7z"
                                />
                            </svg>
                            <span>{{ loc.battery_pct }}%</span>
                        </span>

                        <span
                            class="rounded border px-1.5 py-0.5 font-mono text-[8px] font-bold"
                            :class="
                                loc.is_online
                                    ? 'border-emerald-100 bg-emerald-50 text-emerald-600'
                                    : 'border-slate-200 bg-slate-50 text-slate-500'
                            "
                        >
                            {{ loc.is_online ? 'ONLINE' : 'OFFLINE' }}
                        </span>
                    </div>
                </div>

                <div
                    class="border-slate-150 flex items-center justify-between border-t pt-1 text-[10px] text-slate-500"
                >
                    <span class="text-slate-450 font-mono">
                        GPS: {{ Number(loc.latitude).toFixed(5) }},
                        {{ Number(loc.longitude).toFixed(5) }}
                    </span>
                    <span class="font-mono text-[9px] text-slate-400">
                        Ping: {{ formatTime(loc.pinged_at) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
