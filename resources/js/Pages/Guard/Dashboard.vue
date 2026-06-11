<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useOfflineSync } from '@/Composables/useOfflineSync';

interface Checkpoint {
    id: number;
    name: string;
    description: string;
}

interface RouteCheckpoint {
    id: number;
    position: number;
    checkpoint: Checkpoint;
}

interface Route {
    id: number;
    name: string;
    description: string;
    expected_duration_mins: number;
    route_checkpoints: RouteCheckpoint[];
}

const props = defineProps<{
    guard: any;
    activePatrol: any;
}>();

const emit = defineEmits<{
    (e: 'start-patrol', routeId: number): void;
    (e: 'navigate', tab: string): void;
}>();

const { isOnline } = useOfflineSync();

const routes = ref<Route[]>([]);
const isLoadingRoutes = ref(false);
const errorMsg = ref<string | null>(null);

// Fetch assigned routes when online
async function fetchAssignedRoutes() {
    if (!isOnline.value) {
        // Load fallback cached routes from localStorage if offline
        try {
            const cached = localStorage.getItem('patrol_cached_routes');
            if (cached) {
                routes.value = JSON.parse(cached);
            }
        } catch (e) {
            console.error('Failed to parse cached routes:', e);
        }
        return;
    }
    
    isLoadingRoutes.value = true;
    errorMsg.value = null;
    try {
        const response = await axios.get('/api/guard/routes');
        if (response.data && response.data.routes) {
            routes.value = response.data.routes;
            // Cache them for offline use
            localStorage.setItem('patrol_cached_routes', JSON.stringify(routes.value));
        }
    } catch (e: any) {
        console.error(e);
        errorMsg.value = 'Failed to load assigned patrol routes.';
    } finally {
        isLoadingRoutes.value = false;
    }
}

onMounted(() => {
    fetchAssignedRoutes();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Guard Profile Hero Card -->
        <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 border border-slate-800/80 rounded-3xl p-5 shadow-xl relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl"></div>
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-2xl bg-slate-950 border border-slate-700/50 flex items-center justify-center shadow-lg relative overflow-hidden">
                    <img 
                        v-if="guard.avatar_url" 
                        :src="guard.avatar_url" 
                        class="w-full h-full object-cover" 
                        alt="Avatar"
                    />
                    <svg v-else class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-100">{{ guard.full_name }}</h2>
                    <p class="text-xs text-indigo-400 font-mono font-medium tracking-wide">ID: {{ guard.employee_id || 'GD-007' }}</p>
                </div>
            </div>
            
            <div class="mt-4 pt-4 border-t border-slate-800/80 flex justify-between text-center text-xs">
                <div>
                    <span class="block text-slate-500 font-bold uppercase tracking-wider text-[10px]">Location Scope</span>
                    <span class="text-slate-300 font-semibold mt-0.5 block">Limassol Marina</span>
                </div>
                <div>
                    <span class="block text-slate-500 font-bold uppercase tracking-wider text-[10px]">Status</span>
                    <span class="text-emerald-400 font-bold mt-0.5 block flex items-center justify-center space-x-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>ON DUTY</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- ACTIVE PATROL PROGRESS BLOCK -->
        <div 
            v-if="activePatrol" 
            class="bg-indigo-950/40 backdrop-blur-md border border-indigo-500/30 rounded-3xl p-5 shadow-[0_0_20px_-3px_rgba(99,102,241,0.2)] animate-pulse-slow"
        >
            <div class="flex items-center justify-between mb-3">
                <span class="text-[10px] font-black tracking-widest text-indigo-400 uppercase">ACTIVE PATROL IN PROGRESS</span>
                <span class="text-xs font-mono font-semibold bg-indigo-500/20 px-2 py-0.5 rounded text-indigo-300">
                    {{ activePatrol.completed_checkpoints }}/{{ activePatrol.total_checkpoints }} Checked
                </span>
            </div>
            <h3 class="text-base font-bold text-slate-100 mb-4">{{ activePatrol.route?.name || 'Port Security Route' }}</h3>
            
            <!-- Progress Bar -->
            <div class="w-full bg-slate-900 rounded-full h-2.5 overflow-hidden mb-5">
                <div 
                    class="bg-gradient-to-r from-indigo-500 to-violet-600 h-full rounded-full transition-all duration-500"
                    :style="{ width: `${(activePatrol.completed_checkpoints / activePatrol.total_checkpoints) * 100}%` }"
                ></div>
            </div>

            <button 
                @click="emit('navigate', 'patrol')" 
                class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-2xl shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center space-x-2 text-xs uppercase tracking-wider active:scale-98"
            >
                <span>Resume Active Patrol</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        <!-- ASSIGNED ROUTES -->
        <div v-else class="space-y-4">
            <h3 class="text-xs font-black tracking-wider text-slate-400 uppercase pl-1">Assigned Patrol Routes</h3>
            
            <div v-if="isLoadingRoutes" class="flex flex-col items-center justify-center py-12 space-y-3">
                <span class="w-8 h-8 border-3 border-indigo-500/20 border-t-indigo-500 rounded-full animate-spin"></span>
                <p class="text-xs text-slate-500">Loading your patrol routes...</p>
            </div>

            <div v-else-if="routes.length === 0" class="bg-slate-900/40 border border-slate-800/80 rounded-3xl p-8 text-center">
                <svg class="w-10 h-10 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                </svg>
                <p class="text-sm text-slate-400 font-medium">No assigned patrol routes found.</p>
                <p class="text-[11px] text-slate-500 mt-1">Please verify assignments with your supervisor.</p>
            </div>

            <div v-else class="space-y-3">
                <div 
                    v-for="route in routes" 
                    :key="route.id"
                    class="bg-slate-900 border border-slate-850 hover:border-slate-800/80 rounded-2xl p-4 transition-all duration-200"
                >
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="text-sm font-bold text-slate-100">{{ route.name }}</h4>
                        <span class="text-[10px] font-mono bg-slate-950 px-2 py-0.5 rounded text-slate-400 border border-slate-800">
                            {{ route.route_checkpoints?.length || 0 }} Checkpoints
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 line-clamp-2 mb-4 leading-relaxed">{{ route.description || 'No description provided.' }}</p>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] text-slate-500 font-semibold flex items-center space-x-1">
                            <svg class="w-3.5 h-3.5 mr-0.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>~{{ route.expected_duration_mins || 30 }} mins</span>
                        </span>
                        
                        <button 
                            @click="emit('start-patrol', route.id)"
                            class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] uppercase tracking-wider px-4 py-2 rounded-xl transition-all shadow-md active:scale-95"
                        >
                            Start Patrol
                        </button>
                    </div>
                </div>
            </div>
        </div> 
    </div>
</template>

<style scoped>
@keyframes pulse-slow {
    0%, 100% { opacity: 1; border-color: rgba(99, 102, 241, 0.3); }
    50% { opacity: 0.95; border-color: rgba(99, 102, 241, 0.6); }
}
.animate-pulse-slow {
    animation: pulse-slow 3s infinite ease-in-out;
}
</style>
