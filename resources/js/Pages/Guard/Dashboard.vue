<script setup lang="ts">
import { useOfflineSync } from '@/Composables/useOfflineSync';
import axios from 'axios';
import { onMounted, ref } from 'vue';

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
            localStorage.setItem(
                'patrol_cached_routes',
                JSON.stringify(routes.value),
            );
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
        <div
            class="relative overflow-hidden rounded-3xl border border-slate-800/80 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-5 shadow-xl"
        >
            <div
                class="absolute -bottom-10 -right-10 h-32 w-32 rounded-full bg-indigo-500/10 blur-2xl"
            ></div>
            <div class="flex items-center space-x-4">
                <div
                    class="relative flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl border border-slate-700/50 bg-slate-950 shadow-lg"
                >
                    <img
                        v-if="guard.avatar_url"
                        :src="guard.avatar_url"
                        class="h-full w-full object-cover"
                        alt="Avatar"
                    />
                    <svg
                        v-else
                        class="h-8 w-8 text-indigo-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                        />
                    </svg>
                </div>
                <div>
                    <span
                        v-if="guard.tenant?.name"
                        class="mb-1.5 block text-[10px] font-black uppercase leading-none tracking-widest text-indigo-400"
                        >{{ guard.tenant.name }}</span
                    >
                    <h2 class="text-lg font-bold text-slate-100">
                        {{ guard.full_name }}
                    </h2>
                    <p
                        class="font-mono text-xs font-medium tracking-wide text-slate-400"
                    >
                        ID: {{ guard.employee_id || 'GD-007' }}
                    </p>
                </div>
            </div>

            <div
                class="mt-4 flex justify-between border-t border-slate-800/80 pt-4 text-center text-xs"
            >
                <div>
                    <span
                        class="block text-[10px] font-bold uppercase tracking-wider text-slate-500"
                        >Location Scope</span
                    >
                    <span class="mt-0.5 block font-semibold text-slate-300"
                        >Limassol Marina</span
                    >
                </div>
                <div>
                    <span
                        class="block text-[10px] font-bold uppercase tracking-wider text-slate-500"
                        >Status</span
                    >
                    <span
                        class="mt-0.5 block flex items-center justify-center space-x-1 font-bold text-emerald-400"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                        ></span>
                        <span>ON DUTY</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- ACTIVE PATROL PROGRESS BLOCK -->
        <div
            v-if="activePatrol"
            class="animate-pulse-slow rounded-3xl border border-indigo-500/30 bg-indigo-950/40 p-5 shadow-[0_0_20px_-3px_rgba(99,102,241,0.2)] backdrop-blur-md"
        >
            <div class="mb-3 flex items-center justify-between">
                <span
                    class="text-[10px] font-black uppercase tracking-widest text-indigo-400"
                    >ACTIVE PATROL IN PROGRESS</span
                >
                <span
                    class="rounded bg-indigo-500/20 px-2 py-0.5 font-mono text-xs font-semibold text-indigo-300"
                >
                    {{ activePatrol.completed_checkpoints }}/{{
                        activePatrol.total_checkpoints
                    }}
                    Checked
                </span>
            </div>
            <h3 class="mb-4 text-base font-bold text-slate-100">
                {{ activePatrol.route?.name || 'Port Security Route' }}
            </h3>

            <!-- Progress Bar -->
            <div
                class="mb-5 h-2.5 w-full overflow-hidden rounded-full bg-slate-900"
            >
                <div
                    class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-600 transition-all duration-500"
                    :style="{
                        width: `${(activePatrol.completed_checkpoints / activePatrol.total_checkpoints) * 100}%`,
                    }"
                ></div>
            </div>

            <button
                @click="emit('navigate', 'patrol')"
                class="active:scale-98 flex w-full items-center justify-center space-x-2 rounded-2xl bg-indigo-600 py-3.5 text-xs font-bold uppercase tracking-wider text-white shadow-lg shadow-indigo-600/30 transition-all hover:bg-indigo-500"
            >
                <span>Resume Active Patrol</span>
                <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5l7 7-7 7"
                    />
                </svg>
            </button>
        </div>

        <!-- ASSIGNED ROUTES -->
        <div v-else class="space-y-4">
            <h3
                class="pl-1 text-xs font-black uppercase tracking-wider text-slate-400"
            >
                Assigned Patrol Routes
            </h3>

            <div
                v-if="isLoadingRoutes"
                class="flex flex-col items-center justify-center space-y-3 py-12"
            >
                <span
                    class="border-3 h-8 w-8 animate-spin rounded-full border-indigo-500/20 border-t-indigo-500"
                ></span>
                <p class="text-xs text-slate-500">
                    Loading your patrol routes...
                </p>
            </div>

            <div
                v-else-if="routes.length === 0"
                class="rounded-3xl border border-slate-800/80 bg-slate-900/40 p-8 text-center"
            >
                <svg
                    class="mx-auto mb-3 h-10 w-10 text-slate-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"
                    />
                </svg>
                <p class="text-sm font-medium text-slate-400">
                    No assigned patrol routes found.
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                    Please verify assignments with your supervisor.
                </p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="route in routes"
                    :key="route.id"
                    class="border-slate-850 rounded-2xl border bg-slate-900 p-4 transition-all duration-200 hover:border-slate-800/80"
                >
                    <div class="mb-2 flex items-start justify-between">
                        <h4 class="text-sm font-bold text-slate-100">
                            {{ route.name }}
                        </h4>
                        <span
                            class="rounded border border-slate-800 bg-slate-950 px-2 py-0.5 font-mono text-[10px] text-slate-400"
                        >
                            {{ route.route_checkpoints?.length || 0 }}
                            Checkpoints
                        </span>
                    </div>
                    <p
                        class="mb-4 line-clamp-2 text-xs leading-relaxed text-slate-400"
                    >
                        {{ route.description || 'No description provided.' }}
                    </p>

                    <div class="flex items-center justify-between">
                        <span
                            class="flex items-center space-x-1 text-[10px] font-semibold text-slate-500"
                        >
                            <svg
                                class="mr-0.5 h-3.5 w-3.5 text-indigo-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <span
                                >~{{
                                    route.expected_duration_mins || 30
                                }}
                                mins</span
                            >
                        </span>

                        <button
                            @click="emit('start-patrol', route.id)"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
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
    0%,
    100% {
        opacity: 1;
        border-color: rgba(99, 102, 241, 0.3);
    }
    50% {
        opacity: 0.95;
        border-color: rgba(99, 102, 241, 0.6);
    }
}
.animate-pulse-slow {
    animation: pulse-slow 3s infinite ease-in-out;
}
</style>
