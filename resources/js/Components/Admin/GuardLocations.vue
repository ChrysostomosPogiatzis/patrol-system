<script setup lang="ts">
import { computed } from 'vue';

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
        return new Date(timeStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
        <div class="flex justify-between items-center pb-2 border-b border-slate-100">
            <h3 class="text-xs font-black uppercase tracking-widest text-slate-500">
                🚨 Live Guard Location Feed
            </h3>
            <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold bg-indigo-50 text-indigo-600">
                ACTIVE
            </span>
        </div>

        <div v-if="locations.length === 0" class="py-12 text-center border-2 border-dashed border-slate-150 rounded-2xl">
            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-2.5">
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                </svg>
            </div>
            <p class="text-xs text-slate-450 font-medium">No live guard tracking data available.</p>
        </div>

        <div v-else class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
            <div 
                v-for="loc in locations" 
                :key="loc.id"
                class="bg-slate-50 border border-slate-150 rounded-xl p-4 space-y-3 hover:border-indigo-500/30 transition-all duration-200 group"
            >
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center space-x-2">
                            <span 
                                class="w-2 h-2 rounded-full"
                                :class="loc.is_online ? 'bg-emerald-500 shadow-sm shadow-emerald-550/50' : 'bg-slate-400'"
                            ></span>
                            <h4 class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 transition-colors">
                                {{ loc.security_guard?.full_name || 'Unknown Guard' }}
                            </h4>
                        </div>
                        <span class="text-[9px] text-slate-450 font-mono mt-1 block">
                            ID: {{ loc.security_guard?.employee_id || 'N/A' }} 
                            <span v-if="loc.tenant" class="ml-1 bg-purple-550/10 text-purple-650 border border-purple-200 px-1 rounded uppercase font-black tracking-wider text-[8px]">
                                {{ loc.tenant.name }}
                            </span>
                        </span>
                    </div>

                    <div class="flex items-center space-x-2">
                        <!-- Battery Indicator -->
                        <span 
                            v-if="loc.battery_pct !== undefined" 
                            class="text-[9px] font-mono font-bold flex items-center space-x-1"
                            :class="getBatteryColor(loc.battery_pct)"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span>{{ loc.battery_pct }}%</span>
                        </span>
                        
                        <span 
                            class="text-[8px] font-mono font-bold px-1.5 py-0.5 rounded border"
                            :class="loc.is_online ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-slate-50 text-slate-500 border-slate-200'"
                        >
                            {{ loc.is_online ? 'ONLINE' : 'OFFLINE' }}
                        </span>
                    </div>
                </div>

                <div class="flex justify-between items-center text-[10px] text-slate-500 pt-1 border-t border-slate-150">
                    <span class="font-mono text-slate-450">
                        GPS: {{ Number(loc.latitude).toFixed(5) }}, {{ Number(loc.longitude).toFixed(5) }}
                    </span>
                    <span class="text-[9px] text-slate-400 font-mono">
                        Ping: {{ formatTime(loc.pinged_at) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
