<script setup lang="ts">
import { useGeolocation } from '@/Composables/useGeolocation';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { onMounted, onUnmounted } from 'vue';

interface Props {
    currentTab: string;
    guardName?: string;
    avatarUrl?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'navigate', tab: string): void;
    (e: 'logout'): void;
}>();

const { isOnline, queue, isSyncing, triggerSync } = useOfflineSync();
const { batteryPct, updateLocation } = useGeolocation();

// Periodic battery update
let batteryInterval: any = null;

onMounted(async () => {
    await updateLocation();
    batteryInterval = setInterval(async () => {
        await updateLocation();
    }, 30000);
});

onUnmounted(() => {
    if (batteryInterval) clearInterval(batteryInterval);
});
</script>

<template>
    <div
        class="flex min-h-screen select-none flex-col bg-slate-950 font-sans text-slate-100 antialiased"
    >
        <!-- Status & Connectivity Banner -->
        <div
            v-if="!isOnline"
            class="flex items-center justify-center space-x-2 border-b border-amber-500/30 bg-amber-600/90 px-4 py-1.5 text-center text-xs font-semibold text-amber-50 shadow-md backdrop-blur-md transition-all duration-300"
        >
            <span
                class="h-2 w-2 animate-pulse rounded-full bg-amber-200"
            ></span>
            <span>Offline Mode Active • {{ queue.length }} actions queued</span>
        </div>

        <div
            v-else-if="isSyncing"
            class="flex items-center justify-center space-x-2 border-b border-indigo-500/30 bg-indigo-600/90 px-4 py-1.5 text-center text-xs font-semibold text-indigo-50 shadow-md backdrop-blur-md transition-all duration-300"
        >
            <span
                class="h-2 w-2 animate-ping rounded-full bg-indigo-200"
            ></span>
            <span>Synchronizing offline logs...</span>
        </div>

        <!-- Top Header Navigation -->
        <header
            class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-800/80 bg-slate-900/80 px-4 py-3 backdrop-blur-md"
        >
            <div class="flex items-center space-x-3">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-tr from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/20"
                >
                    <svg
                        class="h-5 w-5 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                        />
                    </svg>
                </div>
                <div>
                    <h1
                        class="bg-gradient-to-r from-indigo-400 to-violet-400 bg-clip-text text-sm font-black tracking-wider text-transparent"
                    >
                        WITBO
                    </h1>
                    <p
                        class="font-mono text-[10px] leading-none tracking-widest text-slate-400"
                    >
                        PATROL SYSTEM
                    </p>
                </div>
            </div>

            <!-- Device Stats & Profile -->
            <div class="flex items-center space-x-4">
                <!-- Battery Info -->
                <div
                    class="flex items-center space-x-1 text-slate-400"
                    title="Device Battery Level"
                >
                    <svg
                        class="h-4 w-4"
                        :class="{
                            'text-emerald-400': (batteryPct ?? 0) > 50,
                            'text-amber-400':
                                (batteryPct ?? 0) <= 50 &&
                                (batteryPct ?? 0) > 20,
                            'animate-pulse text-rose-500':
                                (batteryPct ?? 0) <= 20,
                        }"
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
                    <span class="font-mono text-xs">{{
                        batteryPct !== null ? `${batteryPct}%` : '---'
                    }}</span>
                </div>

                <!-- Online Status Tag -->
                <div
                    class="flex items-center space-x-1.5 rounded-full border bg-slate-950/80 px-2 py-0.5 text-[10px] font-semibold tracking-wide"
                    :class="
                        isOnline
                            ? 'border-emerald-500/20 text-emerald-400'
                            : 'border-amber-500/20 text-amber-400'
                    "
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full"
                        :class="
                            isOnline
                                ? 'animate-pulse bg-emerald-500'
                                : 'bg-amber-500'
                        "
                    ></span>
                    <span>{{ isOnline ? 'ONLINE' : 'OFFLINE' }}</span>
                </div>

                <!-- User Profile & Sign Out -->
                <button
                    @click="emit('logout')"
                    class="rounded-lg border border-slate-800 p-1.5 text-slate-400 transition-all duration-200 hover:bg-slate-800/50 hover:text-slate-200"
                    title="Log Out"
                >
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
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                        />
                    </svg>
                </button>
            </div>
        </header>

        <!-- Main View Content Area -->
        <main class="flex-1 overflow-y-auto px-4 py-4 pb-28">
            <slot />
        </main>

        <!-- Bottom Navigation Dock -->
        <nav
            class="fixed bottom-0 left-0 right-0 z-40 mx-auto flex max-w-lg items-center justify-around rounded-t-2xl border-t border-slate-900 bg-slate-950/90 px-3 py-2 shadow-2xl backdrop-blur-lg"
        >
            <!-- Dashboard Tab -->
            <button
                @click="emit('navigate', 'dashboard')"
                class="flex h-12 w-16 flex-col items-center justify-center rounded-xl transition-all duration-200 focus:outline-none"
                :class="
                    currentTab === 'dashboard'
                        ? 'bg-indigo-500/10 text-indigo-400'
                        : 'text-slate-500 hover:text-slate-300'
                "
            >
                <svg
                    class="mb-0.5 h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"
                    />
                </svg>
                <span class="text-[9px] font-bold uppercase tracking-wider"
                    >Home</span
                >
            </button>

            <!-- Patrol/Routes Tab -->
            <button
                @click="emit('navigate', 'patrol')"
                class="flex h-12 w-16 flex-col items-center justify-center rounded-xl transition-all duration-200 focus:outline-none"
                :class="
                    currentTab === 'patrol'
                        ? 'bg-indigo-500/10 text-indigo-400'
                        : 'text-slate-500 hover:text-slate-300'
                "
            >
                <svg
                    class="mb-0.5 h-5 w-5"
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
                <span class="text-[9px] font-bold uppercase tracking-wider"
                    >Patrol</span
                >
            </button>

            <!-- Quick Incident Tab -->
            <button
                @click="emit('navigate', 'incident')"
                class="flex h-12 w-16 flex-col items-center justify-center rounded-xl transition-all duration-200 focus:outline-none"
                :class="
                    currentTab === 'incident'
                        ? 'bg-indigo-500/10 text-indigo-400'
                        : 'text-slate-500 hover:text-slate-300'
                "
            >
                <svg
                    class="mb-0.5 h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
                <span class="text-[9px] font-bold uppercase tracking-wider"
                    >Incident</span
                >
            </button>

            <!-- History Tab -->
            <button
                @click="emit('navigate', 'history')"
                class="flex h-12 w-16 flex-col items-center justify-center rounded-xl transition-all duration-200 focus:outline-none"
                :class="
                    currentTab === 'history'
                        ? 'bg-indigo-500/10 text-indigo-400'
                        : 'text-slate-500 hover:text-slate-300'
                "
            >
                <svg
                    class="mb-0.5 h-5 w-5"
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
                <span class="text-[9px] font-bold uppercase tracking-wider"
                    >History</span
                >
            </button>

            <!-- Prominent Pulse SOS Button -->
            <button
                @click="emit('navigate', 'sos')"
                class="group flex h-12 w-16 flex-col items-center justify-center rounded-xl transition-all duration-200 focus:outline-none"
                :class="
                    currentTab === 'sos'
                        ? 'bg-rose-500/10 text-rose-400'
                        : 'text-slate-500 hover:text-rose-400'
                "
            >
                <div
                    class="relative mb-0.5 flex h-6 w-6 items-center justify-center"
                >
                    <span
                        class="absolute inline-flex h-full w-full rounded-full bg-rose-500 opacity-20 group-hover:animate-ping"
                        :class="currentTab === 'sos' ? 'animate-ping' : ''"
                    ></span>
                    <svg
                        class="relative z-10 h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                        />
                    </svg>
                </div>
                <span
                    class="text-[9px] font-bold uppercase tracking-wider text-rose-500"
                    >Panic SOS</span
                >
            </button>
        </nav>
    </div>
</template>

<style scoped>
/* Glassmorphism custom enhancements */
nav {
    box-shadow: 0 -10px 25px -5px rgba(0, 0, 0, 0.5);
}
</style>
